<?php

namespace App\Services;

use App\Enums\Order\OrderCreatedByEnum;
use App\Models\Order;
use App\Models\PosRefund;
use App\Models\Product;
use App\Models\Seller;
use App\Models\Setting;
use App\Support\Currency\CurrencyGroupedTotals;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class AdminPosDashboardService
{
    public function __construct(
        protected CurrencyService $currencyService,
    ) {}

    /**
     * Platform-wide POS sales summary.
     */
    public function getSalesSummary(Carbon $from, Carbon $to): array
    {
        $result = $this->basePosQuery()
            ->whereBetween('orders.created_at', [$from, $to])
            ->selectRaw('
                COUNT(DISTINCT orders.id) as total_orders,
                COALESCE(SUM(orders.final_total), 0) as total_revenue,
                COALESCE(AVG(orders.final_total), 0) as avg_order_value,
                COUNT(DISTINCT seller_orders.seller_id) as active_sellers
            ')
            ->first();

        $refundResult = PosRefund::query()
            ->whereBetween('pos_refunds.created_at', [$from, $to])
            ->selectRaw('COALESCE(SUM(pos_refunds.total_amount), 0) as refund_total')
            ->first();
        $refundTotal = (float) $refundResult->refund_total;

        $totalRevenue = (float) $result->total_revenue;
        $totalEarnings = max(0.0, $totalRevenue - $refundTotal);
        $revenueCurrencyTotals = $this->groupedTotals()->fromGroupedRows(
            $this->basePosQuery()
                ->whereBetween('orders.created_at', [$from, $to])
                ->selectRaw('orders.currency_code, COALESCE(SUM(orders.final_total), 0) as raw_total, COUNT(DISTINCT orders.id) as record_count')
                ->groupBy('orders.currency_code')
                ->get()
        );
        $refundCurrencyTotals = $this->groupedTotals()->fromGroupedRows(
            $this->baseRefundQuery($from, $to)
                ->selectRaw('orders.currency_code, COALESCE(SUM(pos_refunds.total_amount), 0) as raw_total, COUNT(pos_refunds.id) as record_count')
                ->groupBy('orders.currency_code')
                ->get()
        );
        $earningsCurrencyTotals = $this->subtractGroupedTotals($revenueCurrencyTotals, $refundCurrencyTotals);

        return [
            'total_orders'    => (int) $result->total_orders,
            'total_revenue'   => round($totalRevenue, 2),
            'avg_order_value' => round((float) $result->avg_order_value, 2),
            'active_sellers'  => (int) $result->active_sellers,
            'total_earnings'  => round($totalEarnings, 2),
            'formatted_revenue'   => $this->currencyService->format($totalRevenue),
            'formatted_avg_value' => $this->currencyService->format($result->avg_order_value),
            'formatted_earnings'  => $this->currencyService->format($totalEarnings),
            'revenue_currency_totals' => $revenueCurrencyTotals,
            'formatted_revenue_display' => $this->groupedTotals()->display($revenueCurrencyTotals, $this->currencyService->format($totalRevenue)),
            'earnings_currency_totals' => $earningsCurrencyTotals,
            'formatted_earnings_display' => $this->groupedTotals()->display($earningsCurrencyTotals, $this->currencyService->format($totalEarnings)),
        ];
    }

    /**
     * Payment method breakdown across all POS orders.
     */
    public function getPaymentBreakdown(Carbon $from, Carbon $to): array
    {
        return $this->basePosQuery()
            ->whereBetween('orders.created_at', [$from, $to])
            ->selectRaw('orders.payment_method, orders.currency_code, COUNT(DISTINCT orders.id) as count, COALESCE(SUM(orders.final_total), 0) as amount')
            ->groupBy('orders.payment_method', 'orders.currency_code')
            ->orderByDesc('amount')
            ->get()
            ->map(fn ($r) => [
                'method'           => $r->payment_method ?? 'unknown',
                'count'            => (int) $r->count,
                'amount'           => round((float) $r->amount, 2),
                'formatted_amount' => $this->currencyService->format($r->amount),
                'currency_code' => $r->currency_code,
                'currency_totals' => $this->groupedTotals()->fromGroupedRows([$r], 'amount', 'currency_code', 'count'),
                'formatted_currency_totals' => $this->groupedTotals()->summary($this->groupedTotals()->fromGroupedRows([$r], 'amount', 'currency_code', 'count'))['formatted_currency_totals'],
            ])->values()->toArray();
    }

    /**
     * Sales trend — hourly for single day, daily otherwise.
     */
    public function getSalesTrend(Carbon $from, Carbon $to): array
    {
        $diffDays = $from->diffInDays($to);
        $isHourly = $diffDays <= 1;

        $dateExpr = $isHourly
            ? "DATE_FORMAT(orders.created_at, '%Y-%m-%d %H:00:00')"
            : 'DATE(orders.created_at)';

        $rows = $this->basePosQuery()
            ->whereBetween('orders.created_at', [$from, $to])
            ->selectRaw("$dateExpr as period, orders.currency_code, COUNT(DISTINCT orders.id) as count, COALESCE(SUM(orders.final_total), 0) as revenue")
            ->groupBy('period', 'orders.currency_code')
            ->orderBy('period')
            ->get();

        return [
            'is_hourly' => $isHourly,
            'data'      => $rows->map(fn ($r) => [
                'period'  => $r->period,
                'count'   => (int) $r->count,
                'revenue' => round((float) $r->revenue, 2),
                'currency_code' => $r->currency_code,
                'formatted_revenue' => $this->groupedTotals()->display($this->groupedTotals()->fromGroupedRows([$r], 'revenue', 'currency_code', 'count')),
            ])->values()->toArray(),
        ];
    }

    /**
     * Top sellers by POS revenue.
     */
    public function getTopSellers(Carbon $from, Carbon $to, int $limit = 10): array
    {
        return $this->basePosQuery()
            ->join('sellers', 'sellers.id', '=', 'seller_orders.seller_id')
            ->whereBetween('orders.created_at', [$from, $to])
            ->selectRaw('sellers.id, orders.currency_code, COUNT(DISTINCT orders.id) as order_count, COALESCE(SUM(orders.final_total), 0) as revenue')
            ->groupBy('sellers.id', 'orders.currency_code')
            ->orderByDesc('revenue')
            ->limit($limit)
            ->get()
            ->map(fn ($r) => [
                'id'                => $r->id,
                'name'              => $r->user->name ?? "",
                'order_count'       => (int) $r->order_count,
                'revenue'           => round((float) $r->revenue, 2),
                'currency_code' => $r->currency_code,
                'formatted_revenue' => $this->groupedTotals()->display($this->groupedTotals()->fromGroupedRows([$r], 'revenue', 'currency_code', 'order_count'), $this->currencyService->format($r->revenue)),
            ])->toArray();
    }

    /**
     * Top products sold across all POS orders.
     */
    public function getTopProducts(Carbon $from, Carbon $to, int $limit = 10): array
    {
        $rows = DB::table('order_items')
            ->join('orders', 'orders.id', '=', 'order_items.order_id')
            ->join('products', 'products.id', '=', 'order_items.product_id')
            ->where('orders.created_by', OrderCreatedByEnum::SELLER())
            ->whereBetween('orders.created_at', [$from, $to])
            ->selectRaw('products.id, orders.currency_code, SUM(order_items.quantity) as qty_sold, SUM(order_items.subtotal) as revenue')
            ->groupBy('products.id', 'orders.currency_code')
            ->orderByDesc('qty_sold')
            ->limit($limit)
            ->get();
        $englishTitles = Product::query()
            ->whereIn('id', $rows->pluck('id'))
            ->get()
            ->mapWithKeys(fn (Product $product): array => [
                $product->id => $product->getTranslation('title', 'en', false),
            ]);

        return $rows
            ->map(fn ($r) => [
                'id'                => $r->id,
                'title'             => (string) ($englishTitles[$r->id] ?? ''),
                'qty_sold'          => (int) $r->qty_sold,
                'revenue'           => round((float) $r->revenue, 2),
                'currency_code' => $r->currency_code,
                'formatted_revenue' => $this->groupedTotals()->display($this->groupedTotals()->fromGroupedRows([$r], 'revenue', 'currency_code', 'qty_sold'), $this->currencyService->format($r->revenue)),
            ])->toArray();
    }

    /**
     * Platform-wide POS refund summary.
     */
    public function getRefundSummary(Carbon $from, Carbon $to): array
    {
        $result = PosRefund::query()
            ->whereBetween('pos_refunds.created_at', [$from, $to])
            ->selectRaw('COUNT(pos_refunds.id) as refund_count, COALESCE(SUM(pos_refunds.total_amount), 0) as refund_total')
            ->first();

        $salesSummary = $this->getSalesSummary($from, $to);
        $refundRate = $salesSummary['total_orders'] > 0
            ? round(((int) $result->refund_count / $salesSummary['total_orders']) * 100, 1)
            : 0;

        return [
            'refund_count'           => (int) $result->refund_count,
            'refund_total'           => round((float) $result->refund_total, 2),
            'formatted_refund_total' => $this->currencyService->format($result->refund_total),
            'refund_currency_totals' => $this->groupedTotals()->fromGroupedRows(
                $this->baseRefundQuery($from, $to)
                    ->selectRaw('orders.currency_code, COALESCE(SUM(pos_refunds.total_amount), 0) as raw_total, COUNT(pos_refunds.id) as record_count')
                    ->groupBy('orders.currency_code')
                    ->get()
            ),
            'formatted_refund_total_display' => $this->groupedTotals()->display(
                $this->groupedTotals()->fromGroupedRows($this->baseRefundQuery($from, $to)
                    ->selectRaw('orders.currency_code, COALESCE(SUM(pos_refunds.total_amount), 0) as raw_total, COUNT(pos_refunds.id) as record_count')
                    ->groupBy('orders.currency_code')
                    ->get()),
                $this->currencyService->format($result->refund_total)
            ),
            'refund_rate'            => $refundRate,
        ];
    }

    /**
     * Walk-in vs registered customer split across all POS.
     */
    public function getCustomerBreakdown(Carbon $from, Carbon $to): array
    {
        $walkinUserId = Setting::posWalkinUserId();

        $query = $this->basePosQuery()
            ->whereBetween('orders.created_at', [$from, $to]);

        $total = (clone $query)->count(DB::raw('DISTINCT orders.id'));

        $walkinCount = 0;
        if ($walkinUserId) {
            $walkinCount = (clone $query)
                ->where('orders.user_id', $walkinUserId)
                ->count(DB::raw('DISTINCT orders.id'));
        }

        $registeredCount = $total - $walkinCount;

        return [
            'total'            => $total,
            'walkin_count'     => $walkinCount,
            'registered_count' => $registeredCount,
            'walkin_pct'       => $total > 0 ? round(($walkinCount / $total) * 100, 1) : 0,
            'registered_pct'   => $total > 0 ? round(($registeredCount / $total) * 100, 1) : 0,
        ];
    }

    /**
     * Seller POS adoption stats.
     */
    public function getSellerAdoption(): array
    {
        $totalSellers = Seller::count();

        $sellersWithPosOrders = DB::table('seller_orders')
            ->join('orders', 'orders.id', '=', 'seller_orders.order_id')
            ->where('orders.created_by', OrderCreatedByEnum::SELLER())
            ->distinct('seller_orders.seller_id')
            ->count('seller_orders.seller_id');

        return [
            'total_sellers'           => $totalSellers,
            'sellers_with_pos_orders' => $sellersWithPosOrders,
            'adoption_pct'            => $totalSellers > 0
                ? round(($sellersWithPosOrders / $totalSellers) * 100, 1)
                : 0,
        ];
    }

    /**
     * Base query for all POS orders (no seller filter).
     */
    private function basePosQuery()
    {
        return DB::table('orders')
            ->join('seller_orders', 'seller_orders.order_id', '=', 'orders.id')
            ->where('orders.created_by', OrderCreatedByEnum::SELLER());
    }

    private function baseRefundQuery(Carbon $from, Carbon $to)
    {
        return PosRefund::query()
            ->join('orders', 'orders.id', '=', 'pos_refunds.order_id')
            ->whereBetween('pos_refunds.created_at', [$from, $to]);
    }

    private function groupedTotals(): CurrencyGroupedTotals
    {
        return app(CurrencyGroupedTotals::class);
    }

    private function subtractGroupedTotals(array $baseTotals, array $subtractTotals): array
    {
        $rows = collect($baseTotals)
            ->mapWithKeys(fn (array $row) => [$row['currency_code'] => $row])
            ->all();

        foreach ($subtractTotals as $subtractTotal) {
            $currencyCode = $subtractTotal['currency_code'];
            $rows[$currencyCode] ??= [
                'currency_code' => $currencyCode,
                'raw_total' => 0,
                'record_count' => 0,
            ];
            $rows[$currencyCode]['raw_total'] -= (float) $subtractTotal['raw_total'];
        }

        return $this->groupedTotals()->fromGroupedRows(array_values($rows));
    }
}
