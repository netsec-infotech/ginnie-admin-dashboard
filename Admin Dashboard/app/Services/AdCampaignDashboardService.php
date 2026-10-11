<?php

namespace App\Services;

use App\Enums\Advertisement\AdCampaignStatusEnum;
use App\Models\AdCampaign;
use App\Models\AdCampaignStat;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class AdCampaignDashboardService
{
    public function __construct(
        protected CurrencyService $currencyService,
        protected AdWalletService $adWalletService,
    ) {}

    /**
     * Stat cards: total spent, clicks, impressions, CTR, active/pending counts.
     */
    public function getStatCards(?int $sellerId, string $from, string $to): array
    {
        $statsQuery = AdCampaignStat::query()
            ->whereBetween('stat_date', [$from, $to]);

        if ($sellerId) {
            $statsQuery->whereHas('campaign', fn ($q) => $q->where('seller_id', $sellerId));
        }

        $totals = $statsQuery->selectRaw('
            COALESCE(SUM(clicks), 0) as total_clicks,
            COALESCE(SUM(impressions), 0) as total_impressions,
            COALESCE(SUM(spent), 0) as total_spent
        ')->first();

        $totalClicks = (int) $totals->total_clicks;
        $totalImpressions = (int) $totals->total_impressions;
        $totalSpent = (float) $totals->total_spent;
        $ctr = $totalImpressions > 0
            ? round(($totalClicks / $totalImpressions) * 100, 2)
            : 0;

        $campaignQuery = AdCampaign::query();
        if ($sellerId) {
            $campaignQuery->where('seller_id', $sellerId);
        }

        $statusRows = (clone $campaignQuery)
            ->selectRaw('status, COUNT(*) as count')
            ->groupBy('status')
            ->get();

        $statusCounts = [];
        foreach ($statusRows as $row) {
            $key = $row->status instanceof AdCampaignStatusEnum
                ? $row->status->value
                : (string) $row->status;
            $statusCounts[$key] = (int) $row->count;
        }

        $activeCampaigns = ($statusCounts[AdCampaignStatusEnum::RUNNING()] ?? 0)
            + ($statusCounts[AdCampaignStatusEnum::PAUSED()] ?? 0);
        $pendingCampaigns = $statusCounts[AdCampaignStatusEnum::PENDING_APPROVAL()] ?? 0;

        return [
            'total_spent'        => $totalSpent,
            'formatted_spent'    => $this->currencyService->format($totalSpent),
            'total_clicks'       => $totalClicks,
            'total_impressions'  => $totalImpressions,
            'ctr'                => $ctr,
            'active_campaigns'   => $activeCampaigns,
            'pending_campaigns'  => $pendingCampaigns,
            'total_campaigns'    => (clone $campaignQuery)->count(),
        ];
    }

    /**
     * Daily time-series for area/line charts.
     */
    public function getDailyTimeSeries(?int $sellerId, string $from, string $to): array
    {
        $query = AdCampaignStat::query()
            ->whereBetween('stat_date', [$from, $to]);

        if ($sellerId) {
            $query->whereHas('campaign', fn ($q) => $q->where('seller_id', $sellerId));
        }

        $rows = $query
            ->selectRaw('stat_date, SUM(clicks) as clicks, SUM(impressions) as impressions, SUM(spent) as spent')
            ->groupBy('stat_date')
            ->orderBy('stat_date')
            ->get()
            ->keyBy(fn ($r) => Carbon::parse($r->stat_date)->toDateString());

        $daily = [];
        $period = Carbon::parse($from);
        $end = Carbon::parse($to);

        while ($period->lte($end)) {
            $dateStr = $period->toDateString();
            $row = $rows->get($dateStr);

            $daily[] = [
                'date'        => $dateStr,
                'clicks'      => $row ? (int) $row->clicks : 0,
                'impressions' => $row ? (int) $row->impressions : 0,
                'spent'       => $row ? (float) $row->spent : 0,
            ];

            $period->addDay();
        }

        return $daily;
    }

    /**
     * Campaign count per status for donut chart (admin).
     */
    public function getCampaignStatusBreakdown(?int $sellerId = null): array
    {
        $query = AdCampaign::query();
        if ($sellerId) {
            $query->where('seller_id', $sellerId);
        }

        $rows = $query
            ->selectRaw('status, COUNT(*) as count')
            ->groupBy('status')
            ->get();

        $labels = [];
        $series = [];
        $colors = [];

        foreach ($rows as $row) {
            $status = $row->status instanceof AdCampaignStatusEnum
                ? $row->status
                : AdCampaignStatusEnum::from($row->status);
            $labels[] = $status->label();
            $series[] = (int) $row->count;
            $colors[] = $this->badgeColorToHex($status->badgeClass());
        }

        return [
            'labels' => $labels,
            'series' => $series,
            'colors' => $colors,
        ];
    }

    /**
     * Top campaigns ranked by spend within a period.
     */
    public function getTopCampaigns(?int $sellerId, string $from, string $to, int $limit = 5): array
    {
        $query = AdCampaign::query()
            ->with(['product:id,title', 'seller:id', 'seller.user:name,seller_name'])
            ->withSum(['stats as period_clicks' => fn ($q) => $q->whereBetween('stat_date', [$from, $to])], 'clicks')
            ->withSum(['stats as period_impressions' => fn ($q) => $q->whereBetween('stat_date', [$from, $to])], 'impressions')
            ->withSum(['stats as period_spent' => fn ($q) => $q->whereBetween('stat_date', [$from, $to])], 'spent');

        if ($sellerId) {
            $query->where('seller_id', $sellerId);
        }

        $campaigns = $query
            ->having('period_spent', '>', 0)
            ->orderByDesc('period_spent')
            ->limit($limit)
            ->get();

        return $campaigns->map(function ($c) {
            $clicks = (int) ($c->period_clicks ?? 0);
            $impressions = (int) ($c->period_impressions ?? 0);
            $spent = (float) ($c->period_spent ?? 0);
            $ctr = $impressions > 0 ? round(($clicks / $impressions) * 100, 2) : 0;

            return [
                'id'              => $c->id,
                'product_title'   => $c->product?->title ?? '—',
                'seller_name'     => $c->seller?->seller_name ?? '—',
                'status'          => $c->status->label(),
                'status_class'    => $c->status->badgeClass(),
                'budget'          => (float) $c->budget,
                'formatted_budget' => $this->currencyService->format((float) $c->budget),
                'spent'           => $spent,
                'formatted_spent' => $this->currencyService->format($spent),
                'clicks'          => $clicks,
                'impressions'     => $impressions,
                'ctr'             => $ctr,
                'progress'        => $c->spend_progress,
            ];
        })->toArray();
    }

    /**
     * Per-campaign budget utilisation for radial bar chart (seller).
     */
    public function getBudgetUtilization(int $sellerId, int $limit = 5): array
    {
        $campaigns = AdCampaign::query()
            ->where('seller_id', $sellerId)
            ->whereIn('status', AdCampaignStatusEnum::activeLocked())
            ->with('product:id,title')
            ->orderByDesc('spent')
            ->limit($limit)
            ->get();

        return $campaigns->map(fn ($c) => [
            'id'       => $c->id,
            'label'    => $c->product?->title ?? "Campaign #{$c->id}",
            'progress' => $c->spend_progress,
            'spent'    => (float) $c->spent,
            'budget'   => (float) $c->budget,
        ])->toArray();
    }

    /**
     * Seller ad wallet balance.
     */
    public function getSellerWalletBalance(int $userId): array
    {
        $result = $this->adWalletService->getAdWallet($userId);
        $balance = $result['success'] ? (float) ($result['data']['balance'] ?? 0) : 0;

        return [
            'balance'           => $balance,
            'formatted_balance' => $this->currencyService->format($balance),
        ];
    }

    /**
     * Aggregate all dashboard data for a given panel/period.
     */
    public function getDashboardData(?int $sellerId, int $days = 7, ?int $userId = null): array
    {
        $to = Carbon::today()->toDateString();
        $from = Carbon::today()->subDays($days - 1)->toDateString();

        $data = [
            'stat_cards'       => $this->getStatCards($sellerId, $from, $to),
            'daily_series'     => $this->getDailyTimeSeries($sellerId, $from, $to),
            'status_breakdown' => $this->getCampaignStatusBreakdown($sellerId),
            'top_campaigns'    => $this->getTopCampaigns($sellerId, $from, $to),
        ];

        if ($sellerId) {
            $data['budget_utilization'] = $this->getBudgetUtilization($sellerId);
            if ($userId) {
                $data['wallet'] = $this->getSellerWalletBalance($userId);
            }
        }

        return $data;
    }

    /**
     * Map badge class to hex colour for ApexCharts.
     */
    private function badgeColorToHex(string $badgeClass): string
    {
        return match ($badgeClass) {
            'success'   => '#2fb344',
            'warning'   => '#f76707',
            'info'      => '#4299e1',
            'danger'    => '#d63939',
            'secondary' => '#667382',
            'primary'   => '#206bc4',
            default     => '#667382',
        };
    }
}
