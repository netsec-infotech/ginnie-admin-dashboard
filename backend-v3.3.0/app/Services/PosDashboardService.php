<?php

namespace App\Services;

use App\Enums\Order\OrderCreatedByEnum;
use App\Models\Order;
use App\Models\PosParkedSale;
use App\Models\PosRefund;
use App\Models\Product;
use App\Models\SellerOrder;
use App\Models\Setting;
use App\Models\StoreProductVariant;
use App\Support\Currency\CurrencyGroupedTotals;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class PosDashboardService
{
    public function __construct(
        protected CurrencyService $currencyService,
    ) {}

    /**
     * Core sales summary: total revenue, order count, avg order value.
     */
    public function getSalesSummary(int $sellerId, ?int $storeId, Carbon $from, Carbon $to): array
    {
        $query = $this->basePosOrderQuery($sellerId, $storeId)
            ->whereBetween('orders.created_at', [$from, $to]);

        $result = $query->selectRaw('
            COUNT(DISTINCT orders.id) as total_orders,
            COALESCE(SUM(orders.final_total), 0) as total_revenue,
            COALESCE(AVG(orders.final_total), 0) as avg_order_value
        ')->first();

        $refundQuery = PosRefund::query()
            ->join('orders', 'orders.id', '=', 'pos_refunds.order_id')
            ->join('seller_orders', 'seller_orders.order_id', '=', 'orders.id')
            ->where('seller_orders.seller_id', $sellerId)
            ->whereBetween('pos_refunds.created_at', [$from, $to]);

        if ($storeId) {
            $refundQuery->where('pos_refunds.store_id', $storeId);
        }

        $refundResult = $refundQuery->selectRaw('COALESCE(SUM(pos_refunds.total_amount), 0) as refund_total')->first();
        $refundTotal = (float) $refundResult->refund_total;

        $totalRevenue = (float) $result->total_revenue;
        $totalEarnings = max(0.0, $totalRevenue - $refundTotal);
        $revenueCurrencyTotals = $this->groupedTotals()->fromGroupedRows(
            $this->basePosOrderQuery($sellerId, $storeId)
                ->whereBetween('orders.created_at', [$from, $to])
                ->selectRaw('orders.currency_code, COALESCE(SUM(orders.final_total), 0) as raw_total, COUNT(DISTINCT orders.id) as record_count')
                ->groupBy('orders.currency_code')
                ->get()
        );
        $refundCurrencyTotals = $this->groupedTotals()->fromGroupedRows(
            $this->baseRefundQuery($sellerId, $storeId, $from, $to)
                ->selectRaw('orders.currency_code, COALESCE(SUM(pos_refunds.total_amount), 0) as raw_total, COUNT(pos_refunds.id) as record_count')
                ->groupBy('orders.currency_code')
                ->get()
        );
        $earningsCurrencyTotals = $this->subtractGroupedTotals($revenueCurrencyTotals, $refundCurrencyTotals);

        return [
            'total_orders'    => (int) $result->total_orders,
            'total_revenue'   => round($totalRevenue, 2),
            'avg_order_value' => round((float) $result->avg_order_value, 2),
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
     * Payment method breakdown with amounts and counts.
     */
    public function getPaymentBreakdown(int $sellerId, ?int $storeId, Carbon $from, Carbon $to): array
    {
        $rows = $this->basePosOrderQuery($sellerId, $storeId)
            ->whereBetween('orders.created_at', [$from, $to])
            ->selectRaw('orders.payment_method, orders.currency_code, COUNT(DISTINCT orders.id) as count, COALESCE(SUM(orders.final_total), 0) as amount')
            ->groupBy('orders.payment_method', 'orders.currency_code')
            ->orderByDesc('amount')
            ->get();

        return $rows->map(fn ($r) => [
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
     * Sales trend — hourly when single day, daily otherwise.
     */
    public function getSalesTrend(int $sellerId, ?int $storeId, Carbon $from, Carbon $to): array
    {
        $diffDays = $from->diffInDays($to);
        $isHourly = $diffDays <= 1;

        $dateExpr = $isHourly
            ? "DATE_FORMAT(orders.created_at, '%Y-%m-%d %H:00:00')"
            : 'DATE(orders.created_at)';

        $rows = $this->basePosOrderQuery($sellerId, $storeId)
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
     * Top selling products by quantity sold.
     */
    public function getTopProducts(int $sellerId, ?int $storeId, Carbon $from, Carbon $to, int $limit = 10): array
    {
        $query = DB::table('order_items')
            ->join('orders', 'orders.id', '=', 'order_items.order_id')
            ->join('seller_orders', 'seller_orders.order_id', '=', 'orders.id')
            ->join('products', 'products.id', '=', 'order_items.product_id')
            ->where('seller_orders.seller_id', $sellerId)
            ->where('orders.created_by', OrderCreatedByEnum::SELLER())
            ->whereBetween('orders.created_at', [$from, $to]);

        if ($storeId) {
            $query->where('order_items.store_id', $storeId);
        }

        $rows = $query
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
                'id'               => $r->id,
                'title'            => (string) ($englishTitles[$r->id] ?? ''),
                'qty_sold'         => (int) $r->qty_sold,
                'revenue'          => round((float) $r->revenue, 2),
                'currency_code' => $r->currency_code,
                'formatted_revenue' => $this->groupedTotals()->display($this->groupedTotals()->fromGroupedRows([$r], 'revenue', 'currency_code', 'qty_sold'), $this->currencyService->format($r->revenue)),
            ])->toArray();
    }

    /**
     * Walk-in vs registered customer split.
     */
    public function getCustomerBreakdown(int $sellerId, ?int $storeId, Carbon $from, Carbon $to): array
    {
        $walkinUserId = Setting::posWalkinUserId();

        $query = $this->basePosOrderQuery($sellerId, $storeId)
            ->whereBetween('orders.created_at', [$from, $to]);

        $total = (clone $query)->count(DB::raw('DISTINCT orders.id'));

        $walkinCount = 0;
        if ($walkinUserId) {
            $walkinCount = (clone $query)
                ->where('orders.user_id', $walkinUserId)
                ->count(DB::raw('DISTINCT orders.id'));
        }

        $registeredCount = $total - $walkinCount;
        $uniqueCustomers = (clone $query)
            ->when($walkinUserId, fn ($q) => $q->where('orders.user_id', '!=', $walkinUserId))
            ->distinct()
            ->count('orders.user_id');

        return [
            'total'              => $total,
            'walkin_count'       => $walkinCount,
            'registered_count'   => $registeredCount,
            'unique_customers'   => $uniqueCustomers,
            'walkin_pct'         => $total > 0 ? round(($walkinCount / $total) * 100, 1) : 0,
            'registered_pct'     => $total > 0 ? round(($registeredCount / $total) * 100, 1) : 0,
        ];
    }

    /**
     * Refund summary: total amount, count, rate.
     */
    public function getRefundSummary(int $sellerId, ?int $storeId, Carbon $from, Carbon $to): array
    {
        $query = PosRefund::query()
            ->join('orders', 'orders.id', '=', 'pos_refunds.order_id')
            ->join('seller_orders', 'seller_orders.order_id', '=', 'orders.id')
            ->where('seller_orders.seller_id', $sellerId)
            ->whereBetween('pos_refunds.created_at', [$from, $to]);

        if ($storeId) {
            $query->where('pos_refunds.store_id', $storeId);
        }

        $result = $query->selectRaw('COUNT(pos_refunds.id) as refund_count, COALESCE(SUM(pos_refunds.total_amount), 0) as refund_total')
            ->first();

        $salesSummary = $this->getSalesSummary($sellerId, $storeId, $from, $to);
        $refundRate = $salesSummary['total_orders'] > 0
            ? round(((int) $result->refund_count / $salesSummary['total_orders']) * 100, 1)
            : 0;

        return [
            'refund_count'           => (int) $result->refund_count,
            'refund_total'           => round((float) $result->refund_total, 2),
            'formatted_refund_total' => $this->currencyService->format($result->refund_total),
            'refund_currency_totals' => $this->groupedTotals()->fromGroupedRows(
                $this->baseRefundQuery($sellerId, $storeId, $from, $to)
                    ->selectRaw('orders.currency_code, COALESCE(SUM(pos_refunds.total_amount), 0) as raw_total, COUNT(pos_refunds.id) as record_count')
                    ->groupBy('orders.currency_code')
                    ->get()
            ),
            'formatted_refund_total_display' => $this->groupedTotals()->display(
                $this->groupedTotals()->fromGroupedRows($this->baseRefundQuery($sellerId, $storeId, $from, $to)
                    ->selectRaw('orders.currency_code, COALESCE(SUM(pos_refunds.total_amount), 0) as raw_total, COUNT(pos_refunds.id) as record_count')
                    ->groupBy('orders.currency_code')
                    ->get()),
                $this->currencyService->format($result->refund_total)
            ),
            'refund_rate'            => $refundRate,
        ];
    }

    /**
     * Discount and promo usage.
     */
    public function getDiscountSummary(int $sellerId, ?int $storeId, Carbon $from, Carbon $to): array
    {
        $query = $this->basePosOrderQuery($sellerId, $storeId)
            ->whereBetween('orders.created_at', [$from, $to]);

        $result = (clone $query)->selectRaw("
            COALESCE(SUM(orders.promo_discount), 0) as promo_total,
            COUNT(DISTINCT CASE WHEN orders.promo_code IS NOT NULL AND orders.promo_code != '' THEN orders.id END) as promo_orders,
            COALESCE(SUM(orders.wallet_balance), 0) as wallet_total,
            COUNT(DISTINCT CASE WHEN orders.wallet_balance > 0 THEN orders.id END) as wallet_orders,
            COALESCE(SUM(orders.pos_savings), 0) as cashier_discount_total,
            COALESCE(SUM(orders.pos_split_cash), 0) as split_cash_total
        ")->first();

        return [
            'promo_total'                => round((float) $result->promo_total, 2),
            'formatted_promo_total'      => $this->currencyService->format($result->promo_total),
            'promo_orders'               => (int) $result->promo_orders,
            'wallet_total'               => round((float) $result->wallet_total, 2),
            'formatted_wallet_total'     => $this->currencyService->format($result->wallet_total),
            'wallet_orders'              => (int) $result->wallet_orders,
            'cashier_discount_total'     => round((float) $result->cashier_discount_total, 2),
            'formatted_cashier_discount' => $this->currencyService->format($result->cashier_discount_total),
            'promo_currency_totals' => $this->discountCurrencyTotals($sellerId, $storeId, $from, $to, 'orders.promo_discount'),
            'wallet_currency_totals' => $this->discountCurrencyTotals($sellerId, $storeId, $from, $to, 'orders.wallet_balance'),
            'cashier_discount_currency_totals' => $this->discountCurrencyTotals($sellerId, $storeId, $from, $to, 'orders.pos_savings'),
        ];
    }

    /**
     * Currently parked/held sales count and total.
     */
    public function getParkedSales(int $sellerId, ?int $storeId): array
    {
        $query = PosParkedSale::where('seller_id', $sellerId);

        if ($storeId) {
            $query->where('store_id', $storeId);
        }

        $result = $query->selectRaw('COUNT(*) as count, COALESCE(SUM(amount), 0) as total')
            ->first();

        return [
            'count'           => (int) $result->count,
            'total'           => round((float) $result->total, 2),
            'formatted_total' => $this->currencyService->format($result->total),
        ];
    }

    /**
     * Low stock variants for the seller's stores.
     */
    public function getLowStockProducts(int $sellerId, ?int $storeId, int $threshold = 10, int $limit = 15): array
    {
        $query = StoreProductVariant::query()
            ->join('stores', 'stores.id', '=', 'store_product_variants.store_id')
            ->join('product_variants', 'product_variants.id', '=', 'store_product_variants.product_variant_id')
            ->join('products', 'products.id', '=', 'product_variants.product_id')
            ->where('stores.seller_id', $sellerId)
            ->where('store_product_variants.stock', '<=', $threshold)
            ->where('store_product_variants.stock', '>=', 0)
            ->whereNull('store_product_variants.deleted_at');

        if ($storeId) {
            $query->where('store_product_variants.store_id', $storeId);
        }

        $rows = $query
            ->select([
                'products.id as product_id',
                'product_variants.title as variant_title',
                'stores.name as store_name',
                'store_product_variants.stock',
            ])
            ->orderBy('store_product_variants.stock')
            ->limit($limit)
            ->get();
        $englishTitles = Product::query()
            ->whereIn('id', $rows->pluck('product_id'))
            ->get()
            ->mapWithKeys(fn (Product $product): array => [
                $product->id => $product->getTranslation('title', 'en', false),
            ]);

        return $rows
            ->map(fn ($r) => [
                'product'  => (string) ($englishTitles[$r->product_id] ?? ''),
                'variant'  => $r->variant_title,
                'store'    => $r->store_name,
                'stock'    => (int) $r->stock,
            ])->toArray();
    }

    /**
     * Store-wise revenue split for the donut chart.
     */
    public function getStoreRevenueSplit(int $sellerId, ?int $storeId, Carbon $from, Carbon $to): array
    {
        $query = DB::table('order_items')
            ->join('orders', 'orders.id', '=', 'order_items.order_id')
            ->join('seller_orders', 'seller_orders.order_id', '=', 'orders.id')
            ->join('stores', 'stores.id', '=', 'order_items.store_id')
            ->where('seller_orders.seller_id', $sellerId)
            ->where('orders.created_by', OrderCreatedByEnum::SELLER())
            ->whereBetween('orders.created_at', [$from, $to]);

        if ($storeId) {
            $query->where('order_items.store_id', $storeId);
        }

        return $query
            ->selectRaw('stores.name, orders.currency_code, COALESCE(SUM(order_items.subtotal), 0) as revenue')
            ->groupBy('stores.id', 'stores.name', 'orders.currency_code')
            ->orderByDesc('revenue')
            ->limit(10)
            ->get()
            ->map(fn ($r) => [
                'name'             => $r->name,
                'revenue'          => round((float) $r->revenue, 2),
                'currency_code' => $r->currency_code,
                'formatted_revenue' => $this->groupedTotals()->display($this->groupedTotals()->fromGroupedRows([$r], 'revenue', 'currency_code'), $this->currencyService->format($r->revenue)),
            ])->toArray();
    }

    /**
     * Base query builder for POS orders scoped to a seller.
     */
    private function basePosOrderQuery(int $sellerId, ?int $storeId)
    {
        $query = DB::table('orders')
            ->join('seller_orders', 'seller_orders.order_id', '=', 'orders.id')
            ->where('seller_orders.seller_id', $sellerId)
            ->where('orders.created_by', OrderCreatedByEnum::SELLER());

        if ($storeId) {
            $query->whereExists(function ($sub) use ($storeId) {
                $sub->select(DB::raw(1))
                    ->from('order_items')
                    ->whereColumn('order_items.order_id', 'orders.id')
                    ->where('order_items.store_id', $storeId);
            });
        }

        return $query;
    }

    private function groupedTotals(): CurrencyGroupedTotals
    {
        return app(CurrencyGroupedTotals::class);
    }

    private function discountCurrencyTotals(int $sellerId, ?int $storeId, Carbon $from, Carbon $to, string $amountColumn): array
    {
        return $this->groupedTotals()->fromGroupedRows(
            $this->basePosOrderQuery($sellerId, $storeId)
                ->whereBetween('orders.created_at', [$from, $to])
                ->selectRaw("orders.currency_code, COALESCE(SUM({$amountColumn}), 0) as raw_total, COUNT(DISTINCT orders.id) as record_count")
                ->groupBy('orders.currency_code')
                ->get()
        );
    }

    private function baseRefundQuery(int $sellerId, ?int $storeId, Carbon $from, Carbon $to)
    {
        $query = PosRefund::query()
            ->join('orders', 'orders.id', '=', 'pos_refunds.order_id')
            ->join('seller_orders', 'seller_orders.order_id', '=', 'orders.id')
            ->where('seller_orders.seller_id', $sellerId)
            ->whereBetween('pos_refunds.created_at', [$from, $to]);

        if ($storeId) {
            $query->where('pos_refunds.store_id', $storeId);
        }

        return $query;
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
