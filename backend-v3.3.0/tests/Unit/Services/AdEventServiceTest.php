<?php

use App\Enums\Advertisement\AdCampaignStatusEnum;
use App\Enums\Advertisement\AdEventTypeEnum;
use App\Jobs\ProcessAdEventsJob;
use App\Models\AdCampaign;
use App\Models\AdCampaignStat;
use App\Services\AdEventService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->service = new AdEventService();
});

// ── Dispatch tests ─────────────────────────────────────────────────────────

describe('recordBulkClicks / recordBulkImpressions dispatch', function () {
    it('dispatches a ProcessAdEventsJob for click events', function () {
        Bus::fake();

        $events = [
            ['campaign_id' => 1, 'visitor_key' => 'abc', 'timestamp' => now()->toIso8601String()],
        ];

        $this->service->recordBulkClicks($events);

        Bus::assertDispatched(ProcessAdEventsJob::class);
    });

    it('dispatches a ProcessAdEventsJob for impression events', function () {
        Bus::fake();

        $events = [
            ['campaign_id' => 1, 'visitor_key' => 'abc', 'timestamp' => now()->toIso8601String()],
        ];

        $this->service->recordBulkImpressions($events);

        Bus::assertDispatched(ProcessAdEventsJob::class);
    });
});

// ── processEvents (click) ──────────────────────────────────────────────────

describe('processEvents — clicks', function () {
    it('records a click and deducts CPC from budget', function () {
        $campaign = AdCampaign::factory()->create([
            'budget'            => 100.00,
            'spent'             => 0.00,
            'cpc_rate_snapshot' => 2.50,
            'status'            => AdCampaignStatusEnum::RUNNING(),
        ]);

        $this->service->processEvents([
            [
                'campaign_id' => $campaign->id,
                'visitor_key' => hash('sha256', 'visitor-1'),
                'timestamp'   => now()->toIso8601String(),
            ],
        ], AdEventTypeEnum::CLICK());

        $campaign->refresh();
        expect((float) $campaign->spent)->toBe(2.50);

        $stat = AdCampaignStat::where('campaign_id', $campaign->id)->first();
        expect($stat)->not->toBeNull()
            ->and($stat->clicks)->toBe(1)
            ->and((float) $stat->spent)->toBe(2.50);
    });

    it('skips duplicate clicks via INSERT IGNORE', function () {
        $campaign = AdCampaign::factory()->create([
            'budget'            => 100.00,
            'spent'             => 0.00,
            'cpc_rate_snapshot' => 2.50,
            'status'            => AdCampaignStatusEnum::RUNNING(),
        ]);

        $event = [
            'campaign_id' => $campaign->id,
            'visitor_key' => hash('sha256', 'visitor-1'),
            'timestamp'   => now()->toIso8601String(),
        ];

        $this->service->processEvents([$event], AdEventTypeEnum::CLICK());
        $this->service->processEvents([$event], AdEventTypeEnum::CLICK());

        $campaign->refresh();
        expect((float) $campaign->spent)->toBe(2.50);

        expect(DB::table('ad_event_dedup')->count())->toBe(1);
    });

    it('auto-completes campaign when budget is exhausted', function () {
        $campaign = AdCampaign::factory()->create([
            'budget'            => 5.00,
            'spent'             => 2.50,
            'cpc_rate_snapshot' => 2.50,
            'status'            => AdCampaignStatusEnum::RUNNING(),
        ]);

        $this->service->processEvents([
            [
                'campaign_id' => $campaign->id,
                'visitor_key' => hash('sha256', 'visitor-2'),
                'timestamp'   => now()->toIso8601String(),
            ],
        ], AdEventTypeEnum::CLICK());

        $campaign->refresh();
        expect($campaign->status->value)->toBe(AdCampaignStatusEnum::COMPLETED());
    });

    it('aggregates multiple clicks for same campaign in one batch', function () {
        $campaign = AdCampaign::factory()->create([
            'budget'            => 100.00,
            'spent'             => 0.00,
            'cpc_rate_snapshot' => 1.00,
            'status'            => AdCampaignStatusEnum::RUNNING(),
        ]);

        $events = [];
        for ($i = 1; $i <= 5; $i++) {
            $events[] = [
                'campaign_id' => $campaign->id,
                'visitor_key' => hash('sha256', "visitor-{$i}"),
                'timestamp'   => now()->toIso8601String(),
            ];
        }

        $this->service->processEvents($events, AdEventTypeEnum::CLICK());

        $campaign->refresh();
        expect((float) $campaign->spent)->toBe(5.00);

        $stat = AdCampaignStat::where('campaign_id', $campaign->id)->first();
        expect($stat->clicks)->toBe(5)
            ->and((float) $stat->spent)->toBe(5.00);
    });
});

// ── processEvents (impressions) ────────────────────────────────────────────

describe('processEvents — impressions', function () {
    it('records impressions without deducting budget', function () {
        $campaign = AdCampaign::factory()->create([
            'budget'            => 100.00,
            'spent'             => 0.00,
            'cpc_rate_snapshot' => 2.50,
            'status'            => AdCampaignStatusEnum::RUNNING(),
        ]);

        $this->service->processEvents([
            [
                'campaign_id' => $campaign->id,
                'visitor_key' => hash('sha256', 'visitor-1'),
                'timestamp'   => now()->toIso8601String(),
            ],
        ], AdEventTypeEnum::IMPRESSION());

        $campaign->refresh();
        expect((float) $campaign->spent)->toBe(0.00);

        $stat = AdCampaignStat::where('campaign_id', $campaign->id)->first();
        expect($stat->impressions)->toBe(1)
            ->and($stat->clicks)->toBe(0);
    });

    it('allows same visitor to have both click and impression on same day', function () {
        $campaign = AdCampaign::factory()->create([
            'budget'            => 100.00,
            'spent'             => 0.00,
            'cpc_rate_snapshot' => 2.50,
            'status'            => AdCampaignStatusEnum::RUNNING(),
        ]);

        $visitorKey = hash('sha256', 'visitor-1');
        $timestamp = now()->toIso8601String();

        $this->service->processEvents(
            [['campaign_id' => $campaign->id, 'visitor_key' => $visitorKey, 'timestamp' => $timestamp]],
            AdEventTypeEnum::CLICK(),
        );

        $this->service->processEvents(
            [['campaign_id' => $campaign->id, 'visitor_key' => $visitorKey, 'timestamp' => $timestamp]],
            AdEventTypeEnum::IMPRESSION(),
        );

        expect(DB::table('ad_event_dedup')->count())->toBe(2);
    });

    it('skips events for non-running campaigns', function () {
        $campaign = AdCampaign::factory()->create([
            'budget' => 100.00,
            'spent'  => 0.00,
            'status' => AdCampaignStatusEnum::PAUSED(),
        ]);

        $this->service->processEvents([
            [
                'campaign_id' => $campaign->id,
                'visitor_key' => hash('sha256', 'visitor-1'),
                'timestamp'   => now()->toIso8601String(),
            ],
        ], AdEventTypeEnum::IMPRESSION());

        expect(DB::table('ad_event_dedup')->count())->toBe(0);
        expect(AdCampaignStat::count())->toBe(0);
    });
});
