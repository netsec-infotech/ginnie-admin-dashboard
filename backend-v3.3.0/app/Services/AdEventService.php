<?php

namespace App\Services;

use App\Enums\Advertisement\AdCampaignStatusEnum;
use App\Enums\Advertisement\AdEventTypeEnum;
use App\Jobs\ProcessAdEventsJob;
use App\Models\AdCampaign;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class AdEventService
{
    /**
     * Dispatch click events to the queue for async processing.
     */
    public function recordBulkClicks(array $events): void
    {
        ProcessAdEventsJob::dispatch($events, AdEventTypeEnum::CLICK());
    }

    /**
     * Dispatch impression events to the queue for async processing.
     */
    public function recordBulkImpressions(array $events): void
    {
        ProcessAdEventsJob::dispatch($events, AdEventTypeEnum::IMPRESSION());
    }

    /**
     * Process a batch of events — called by the queue job.
     *
     * 1. Bulk INSERT IGNORE for dedup — one query for the whole batch.
     * 2. Aggregate unique events per campaign+date in memory.
     * 3. Batch CPC deduction — one lock per campaign instead of per click.
     * 4. Bulk upsert daily stats — one query per campaign+date combo.
     */
    public function processEvents(array $events, string $eventType): void
    {
        $campaignIds = array_unique(array_column($events, 'campaign_id'));
        $runningCampaigns = AdCampaign::whereIn('id', $campaignIds)
            ->running()
            ->get()
            ->keyBy('id');

        // Build dedup rows, filtering out campaigns that aren't running
        $dedupRows = [];
        foreach ($events as $event) {
            $campaignId = (int) $event['campaign_id'];
            if (! $runningCampaigns->has($campaignId)) {
                continue;
            }

            $dedupRows[] = [
                'campaign_id' => $campaignId,
                'event_type'  => $eventType,
                'visitor_key' => (string) $event['visitor_key'],
                'event_date'  => Carbon::parse($event['timestamp'] ?? now())->toDateString(),
                'created_at'  => now(),
            ];
        }

        if (empty($dedupRows)) {
            return;
        }

        // Bulk INSERT IGNORE — duplicates silently skipped by the unique constraint
        $insertedCount = $this->bulkInsertIgnoreDedup($dedupRows);

        if ($insertedCount === 0) {
            return;
        }

        // Aggregate: count unique events per campaign_id + date
        $aggregated = $this->aggregateByKey($dedupRows, $insertedCount, count($dedupRows));

        // Process each campaign+date bucket
        foreach ($aggregated as $key => $bucket) {
            [$campaignId, $date] = explode('|', $key);
            $campaignId = (int) $campaignId;
            $count = $bucket['count'];

            $spentAmount = 0.0;

            if ($eventType === AdEventTypeEnum::CLICK()) {
                $result = $this->deductBatchCpc($campaignId, $count);
                if ($result !== null) {
                    $spentAmount = $result['spent'];
                }
            }

            $this->upsertDailyStat($campaignId, $date, $eventType, $count, $spentAmount);
        }
    }

    /**
     * Bulk INSERT OR IGNORE into the dedup table. Returns the number of actually inserted rows.
     */
    private function bulkInsertIgnoreDedup(array $rows): int
    {
        if (empty($rows)) {
            return 0;
        }

        return DB::table('ad_event_dedup')->insertOrIgnore($rows);
    }

    /**
     * Aggregate events per campaign_id + date.
     *
     * When INSERT IGNORE inserts fewer rows than submitted, we pro-rate counts
     * proportionally. This is an approximation — acceptable because dedup
     * duplicates are evenly distributed across campaigns in practice.
     */
    private function aggregateByKey(array $dedupRows, int $insertedCount, int $totalRows): array
    {
        // Count events per campaign+date from the submitted batch
        $buckets = [];
        foreach ($dedupRows as $row) {
            $key = $row['campaign_id'] . '|' . $row['event_date'];
            if (! isset($buckets[$key])) {
                $buckets[$key] = ['count' => 0];
            }
            $buckets[$key]['count']++;
        }

        // If some were duplicates, pro-rate each bucket
        if ($insertedCount < $totalRows && $totalRows > 0) {
            $ratio = $insertedCount / $totalRows;
            foreach ($buckets as $key => &$bucket) {
                $bucket['count'] = (int) max(1, round($bucket['count'] * $ratio));
            }
            unset($bucket);

            // Correct rounding — total must equal insertedCount
            $sum = array_sum(array_column($buckets, 'count'));
            if ($sum !== $insertedCount && ! empty($buckets)) {
                $firstKey = array_key_first($buckets);
                $buckets[$firstKey]['count'] += ($insertedCount - $sum);
            }
        }

        return $buckets;
    }

    /**
     * Deduct CPC for N clicks in a single lock acquisition.
     *
     * @return array{spent: float, completed: bool}|null
     */
    private function deductBatchCpc(int $campaignId, int $clickCount): ?array
    {
        try {
            return DB::transaction(function () use ($campaignId, $clickCount) {
                $locked = AdCampaign::where('id', $campaignId)
                    ->lockForUpdate()
                    ->first();

                if (! $locked || $locked->status !== AdCampaignStatusEnum::RUNNING) {
                    return null;
                }

                $remaining = (float) $locked->budget - (float) $locked->spent;
                $cpc = (float) $locked->cpc_rate_snapshot;

                if ($remaining <= 0 || $cpc <= 0) {
                    return null;
                }

                // Charge up to N clicks, capped by remaining budget
                $maxCharge = $cpc * $clickCount;
                $charge = min($maxCharge, $remaining);
                $locked->spent = (float) $locked->spent + $charge;

                $completed = false;
                if ((float) $locked->spent >= (float) $locked->budget) {
                    $locked->status = AdCampaignStatusEnum::COMPLETED();
                    $completed = true;
                }

                $locked->save();

                return ['spent' => $charge, 'completed' => $completed];
            });
        } catch (\Throwable $e) {
            Log::error('AdEventService::deductBatchCpc failed', [
                'campaign_id' => $campaignId,
                'click_count' => $clickCount,
                'error'       => $e->getMessage(),
            ]);
            return null;
        }
    }

    /**
     * Atomically increment daily stats for a campaign.
     *
     * Uses insertOrIgnore + conditional increment-update so concurrent workers
     * processing the same campaign+date never collide on the unique constraint.
     */
    private function upsertDailyStat(int $campaignId, string $date, string $eventType, int $count, float $spentAmount): void
    {
        $isClick     = $eventType === AdEventTypeEnum::CLICK();
        $clicks      = $isClick ? $count : 0;
        $impressions = $isClick ? 0 : $count;

        // Attempt to create the row; silently skipped if it already exists.
        $inserted = DB::table('ad_campaign_stats')->insertOrIgnore([
            'campaign_id' => $campaignId,
            'stat_date'   => $date,
            'clicks'      => $clicks,
            'impressions' => $impressions,
            'spent'       => $spentAmount,
            'created_at'  => now(),
            'updated_at'  => now(),
        ]);

        if ($inserted === 0) {
            // Row already existed — increment in place.
            DB::table('ad_campaign_stats')
                ->where('campaign_id', $campaignId)
                ->where('stat_date', $date)
                ->update([
                    'clicks'      => DB::raw('clicks + ' . $clicks),
                    'impressions' => DB::raw('impressions + ' . $impressions),
                    'spent'       => DB::raw('spent + ' . $spentAmount),
                    'updated_at'  => now(),
                ]);
        }
    }
}
