<?php

namespace App\Services;

use App\Enums\ActiveInactiveStatusEnum;
use App\Enums\Advertisement\AdCampaignStatusEnum;
use App\Enums\DeliveryBoy\DeliveryBoyVerificationStatusEnum;
use App\Enums\DeliveryBoy\DeliveryBoyWithdrawalStatusEnum;
use App\Enums\DeliveryBoy\EarningPaymentStatusEnum;
use App\Enums\Order\OrderItemStatusEnum;
use App\Enums\Order\OrderStatusEnum;
use App\Enums\Product\ProductStatusEnum;
use App\Enums\Seller\SellerSettlementStatusEnum;
use App\Enums\Seller\SellerVerificationStatusEnum;
use App\Enums\Seller\SellerVisibilityStatusEnum;
use App\Enums\Seller\SellerWithdrawalStatusEnum;
use App\Enums\SpatieMediaCollectionName;
use App\Enums\Store\StoreVerificationStatusEnum;
use App\Enums\Store\StoreVisibilityStatusEnum;
use App\Enums\Subscription\SellerSubscriptionStatusEnum;
use App\Models\AdCampaign;
use App\Models\AdCampaignStat;
use App\Models\Category;
use App\Models\DeliveryBoy;
use App\Models\DeliveryBoyAssignment;
use App\Models\DeliveryBoyWithdrawalRequest;
use App\Models\DeliveryZone;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\Seller;
use App\Models\SellerOrderItem;
use App\Models\SellerStatement;
use App\Models\SellerSubscription;
use App\Models\SellerWithdrawalRequest;
use App\Models\Store;
use App\Models\SellerFeedback;
use App\Models\StoreProductVariant;
use App\Models\SubscriptionPlan;
use App\Models\SubscriptionTransaction;
use App\Models\User;
use App\Support\Currency\CurrencyGroupedTotals;
use Carbon\Carbon;
use Carbon\CarbonPeriod;
use Illuminate\Support\Facades\DB;

class DashboardService
{
    protected CurrencyService $currencyService;

    public function __construct(CurrencyService $currencyService)
    {
        $this->currencyService = $currencyService;
    }

    /**
     * Get revenue data for the specified number of days.
     */
    public function getRevenueData(int $days, ?int $sellerId = null, ?int $zoneId = null): array
    {
        $startDate = Carbon::now()->subDays($days)->startOfDay();
        $endDate = Carbon::now()->endOfDay();

        if ($sellerId) {
            $query = OrderItem::with(['order', 'store'])
                ->whereBetween('created_at', [$startDate, $endDate])
                ->where('status', OrderItemStatusEnum::DELIVERED());

            $query->whereHas('store', function ($q) use ($sellerId) {
                $q->where('seller_id', $sellerId);
            });

            if ($zoneId) {
                $query->whereHas('order', fn($q) => $q->where('delivery_zone_id', $zoneId));
            }
        } else {
            $query = Order::whereBetween('created_at', [$startDate, $endDate])
                ->where('status', OrderStatusEnum::DELIVERED());

            if ($zoneId) {
                $query->where('delivery_zone_id', $zoneId);
            }
        }

        $revenueItems = $query->get();
        $groupedTotals = $this->groupedTotals();
        $currencyTotals = $groupedTotals->fromItems(
            $revenueItems,
            fn ($item) => $sellerId ? ((float) $item->subtotal - (float) $item->admin_commission_amount) : (float) $item->total_payable,
            fn ($item) => $sellerId ? $item->order?->currency_code : $item->currency_code,
        );

        $revenueByDay = $revenueItems
            ->groupBy(function ($item) {
                return $item->created_at->format('Y-m-d');
            })
            ->map(function ($items) use ($sellerId, $groupedTotals) {
                $totalRevenue = $items->sum(function ($item) use ($sellerId) {
                    if ($sellerId) {
                        return $item->subtotal - $item->admin_commission_amount;
                    }
                    return $item->total_payable;
                });
                $dayCurrencyTotals = $groupedTotals->fromItems(
                    $items,
                    fn ($item) => $sellerId ? ((float) $item->subtotal - (float) $item->admin_commission_amount) : (float) $item->total_payable,
                    fn ($item) => $sellerId ? $item->order?->currency_code : $item->currency_code,
                );

                return [
                    'date' => $items->first()->created_at->format('Y-m-d'),
                    'revenue' => $totalRevenue,
                    'formatted_revenue' => $this->currencyService->format($totalRevenue),
                    ...$groupedTotals->summary($dayCurrencyTotals),
                ];
            })
            ->values()
            ->toArray();

        // Fill in missing days with zero revenue
        $result = [];
        $currentDate = clone $startDate;

        while ($currentDate <= $endDate) {
            $dateStr = $currentDate->format('Y-m-d');
            $found = false;

            foreach ($revenueByDay as $day) {
                if ($day['date'] === $dateStr) {
                    $result[] = $day;
                    $found = true;
                    break;
                }
            }

            if (!$found) {
                $result[] = [
                    'date' => $dateStr,
                    'revenue' => 0,
                    'formatted_revenue' => $this->currencyService->format(0),
                    ...$groupedTotals->summary([]),
                ];
            }

            $currentDate->addDay();
        }

        // Calculate total revenue
        $totalRevenue = array_sum(array_column($result, 'revenue'));

        return [
            'daily' => $result,
            'total' => $totalRevenue,
            'formatted_total' => $this->currencyService->format($totalRevenue),
            ...$groupedTotals->summary($currencyTotals),
        ];
    }

    /**
     * Get store-wise order totals for the seller.
     */
    public function getStoreOrderTotals(int $sellerId): array
    {
        $stores = Store::where('seller_id', $sellerId)->get();

        $result = [];
        $totalOrders = 0;

        foreach ($stores as $store) {
            $orderCount = SellerOrderItem::whereHas('orderItem', function ($q) use ($store) {
                $q->where('store_id', $store->id);
            })->count();

            $result[] = [
                'id' => $store->id,
                'name' => $store->name,
                'order_count' => $orderCount
            ];

            $totalOrders += $orderCount;
        }

        // Calculate percentages
        if ($totalOrders > 0) {
            foreach ($result as &$store) {
                $store['percentage'] = round(($store['order_count'] / $totalOrders) * 100);
            }
        } else {
            foreach ($result as &$store) {
                $store['percentage'] = 0;
            }
        }

        return [
            'stores' => $result,
            'total' => $totalOrders
        ];
    }

    /**
     * Get store-wise revenue data with date filtering for the seller.
     */
    public function getStoreRevenueData(int $sellerId, int $days = 30): array
    {
        $startDate = Carbon::now()->subDays($days)->startOfDay();
        $endDate = Carbon::now()->endOfDay();

        $stores = Store::where('seller_id', $sellerId)->get();

        $result = [];
        $totalRevenue = 0;
        $allCurrencyTotals = [];
        $groupedTotals = $this->groupedTotals();

        foreach ($stores as $store) {
            $revenue = OrderItem::where('store_id', $store->id)
                ->whereBetween('created_at', [$startDate, $endDate])
                ->where('status', OrderItemStatusEnum::DELIVERED())
                ->sum(DB::raw('subtotal - admin_commission_amount'));
            $storeCurrencyTotals = $groupedTotals->fromItems(
                [(object) ['amount' => $revenue, 'currency_code' => $store->currency_code]],
                fn ($item) => $item->amount,
                fn ($item) => $item->currency_code,
            );
            $allCurrencyTotals = array_merge($allCurrencyTotals, $storeCurrencyTotals);

            $result[] = [
                'id' => $store->id,
                'name' => $store->name,
                'revenue' => $revenue,
                'formatted_revenue' => $this->currencyService->format($revenue),
                ...$groupedTotals->summary($storeCurrencyTotals),
            ];

            $totalRevenue += $revenue;
        }

        // Sort by revenue descending
        usort($result, function ($a, $b) {
            return $b['revenue'] <=> $a['revenue'];
        });

        return [
            'stores' => $result,
            'total' => $totalRevenue,
            'formatted_total' => $this->currencyService->format($totalRevenue),
            ...$groupedTotals->summary($groupedTotals->fromGroupedRows($allCurrencyTotals)),
            'days' => $days
        ];
    }

    /**
     * Get today's earning with comparison to yesterday.
     */
    public function getTodaysEarning(?int $sellerId = null, ?int $zoneId = null): array
    {
        $today = Carbon::now()->format('Y-m-d');
        $yesterday = Carbon::now()->subDay()->format('Y-m-d');

        $earningCalculation = $sellerId
            ? 'SUM(subtotal - admin_commission_amount)'
            : 'SUM(admin_commission_amount)';

        $query = OrderItem::selectRaw("
            DATE(created_at) as date,
            {$earningCalculation} as earning
        ")
            ->whereIn(DB::raw('DATE(created_at)'), [$today, $yesterday])
            ->where('status', OrderItemStatusEnum::DELIVERED());

        if ($sellerId) {
            $query->whereHas('store', function ($q) use ($sellerId) {
                $q->where('seller_id', $sellerId);
            });
        }

        if ($zoneId) {
            $query->whereHas('order', fn($q) => $q->where('delivery_zone_id', $zoneId));
        }

        $earnings = $query->groupByRaw('DATE(created_at)')
            ->get()
            ->keyBy('date');
        $currencyItems = OrderItem::with('order')
            ->whereIn(DB::raw('DATE(created_at)'), [$today, $yesterday])
            ->where('status', OrderItemStatusEnum::DELIVERED());

        if ($sellerId) {
            $currencyItems->whereHas('store', function ($q) use ($sellerId) {
                $q->where('seller_id', $sellerId);
            });
        }

        if ($zoneId) {
            $currencyItems->whereHas('order', fn($q) => $q->where('delivery_zone_id', $zoneId));
        }

        $currencyItemsByDate = $currencyItems->get()->groupBy(fn ($item) => $item->created_at->format('Y-m-d'));
        $groupedTotals = $this->groupedTotals();
        $amountResolver = fn ($item) => $sellerId
            ? ((float) $item->subtotal - (float) $item->admin_commission_amount)
            : (float) $item->admin_commission_amount;
        $todayCurrencyTotals = $groupedTotals->fromItems($currencyItemsByDate->get($today, collect()), $amountResolver, fn ($item) => $item->order?->currency_code);
        $yesterdayCurrencyTotals = $groupedTotals->fromItems($currencyItemsByDate->get($yesterday, collect()), $amountResolver, fn ($item) => $item->order?->currency_code);

        // Get today's and yesterday's earnings
        $todaysEarning = $earnings->get($today)?->earning ?? 0;
        $yesterdaysEarning = $earnings->get($yesterday)?->earning ?? 0;

        // Calculate percentage change
        $percentageChange = 0;
        if ($yesterdaysEarning > 0) {
            $percentageChange = (($todaysEarning - $yesterdaysEarning) / $yesterdaysEarning) * 100;
        } elseif ($todaysEarning > 0) {
            $percentageChange = 100; // If yesterday was 0 and today is positive, that's a 100% increase
        }

        return [
            'today' => $todaysEarning,
            'yesterday' => $yesterdaysEarning,
            'formatted_today' => $this->currencyService->format($todaysEarning),
            'formatted_yesterday' => $this->currencyService->format($yesterdaysEarning),
            'today_currency_totals' => $todayCurrencyTotals,
            'formatted_today_currency_totals' => $groupedTotals->summary($todayCurrencyTotals)['formatted_currency_totals'],
            'formatted_today_display' => $groupedTotals->display($todayCurrencyTotals, $this->currencyService->format($todaysEarning)),
            'yesterday_currency_totals' => $yesterdayCurrencyTotals,
            'formatted_yesterday_currency_totals' => $groupedTotals->summary($yesterdayCurrencyTotals)['formatted_currency_totals'],
            'formatted_yesterday_display' => $groupedTotals->display($yesterdayCurrencyTotals, $this->currencyService->format($yesterdaysEarning)),
            'percentage_change' => round($percentageChange, 2),
            'is_increase' => $percentageChange >= 0
        ];
    }

    /**
     * Get daily purchase history for the last month.
     */
    public function getDailyPurchaseHistory(int $days = 30, ?int $sellerId = null, ?int $zoneId = null): array
    {
        $startDate = Carbon::now()->subDays($days)->startOfDay();
        $endDate = Carbon::now()->endOfDay();

        $query = OrderItem::selectRaw('DATE(created_at) as date, COUNT(*) as order_count')
            ->whereBetween('created_at', [$startDate, $endDate]);

        if ($sellerId) {
            $query->whereHas('store', function ($q) use ($sellerId) {
                $q->where('seller_id', $sellerId);
            });
        }

        if ($zoneId) {
            $query->whereHas('order', fn($q) => $q->where('delivery_zone_id', $zoneId));
        }

        $ordersByDay = $query->groupByRaw('DATE(created_at)')
            ->orderBy('date')
            ->get()
            ->keyBy('date')
            ->toArray();

        // Fill in missing days with zero orders
        $result = [];
        $currentDate = clone $startDate;

        while ($currentDate <= $endDate) {
            $dateStr = $currentDate->format('Y-m-d');

            if (isset($ordersByDay[$dateStr])) {
                $result[] = [
                    'date' => $dateStr,
                    'order_count' => (int)$ordersByDay[$dateStr]['order_count'],
                ];
            } else {
                $result[] = [
                    'date' => $dateStr,
                    'order_count' => 0,
                ];
            }

            $currentDate->addDay();
        }

        // Calculate total orders
        $totalOrders = array_sum(array_column($result, 'order_count'));

        return [
            'daily' => $result,
            'total' => $totalOrders,
            'days' => count($result)
        ];
    }

    /**
     * Get recent seller feedback.
     */
    public function getRecentSellerFeedback(int $sellerId): array
    {
        $feedback = SellerFeedback::where('seller_id', $sellerId)
            ->with('user')
            ->orderBy('created_at', 'desc')
            ->limit(5)
            ->get()
            ->map(function ($item) {
                return [
                    'id' => $item->id,
                    'user_name' => $item->user->name ?? 'Anonymous',
                    'rating' => $item->rating,
                    'title' => $item->title,
                    'description' => $item->description,
                    'date' => $item->created_at->format('d M Y')
                ];
            })
            ->toArray();

        // Get overall statistics
        $stats = SellerFeedback::getSellerFeedbackStatistics($sellerId);

        return [
            'items' => $feedback,
            'total_reviews' => $stats->total_reviews ?? 0,
            'average_rating' => round($stats->average_rating ?? 0, 1)
        ];
    }

    /**
     * Get total sales and unsettled payments for the seller.
     */
    public function getSalesData(int $sellerId, int $storeId = null): array
    {
        $salesQuery = OrderItem::whereHas('store', function ($query) use ($sellerId) {
            $query->where('seller_id', $sellerId);
        })
            ->when($storeId, function ($query) use ($storeId) {
                $query->where('store_id', $storeId);
            });

        // Get total sales (delivered order items)
        $totalSales = (clone $salesQuery)
            ->where('status', OrderItemStatusEnum::DELIVERED())
            ->count();

        // Get unsettled payments (order items with commission_settled = '0')
        $unsettledPayments = (clone $salesQuery)
            ->whereHas('order', function ($q) {
                $q->where('payment_status', 'completed');
            })
            ->where('commission_settled', '0')
            ->where('admin_commission_amount', '>', 0)
            ->count();

        return [
            'total_sales' => $totalSales,
            'unsettled_payments' => $unsettledPayments
        ];
    }

    /**
     * Get product statistics for the seller.
     */
    public function getProductStats(int $sellerId, int $storeId = null): array
    {
        $productQuery = Product::where('seller_id', $sellerId)
            ->when($storeId, function ($query) use ($storeId) {
                $query->whereHas('variants.storeProductVariants', function ($storeProductVariantQuery) use ($storeId) {
                    $storeProductVariantQuery->where('store_id', $storeId);
                });
            });

        // Get total number of products
        $totalProducts = (clone $productQuery)->count();

        // Get number of products added in the last 7 days
        $recentProducts = (clone $productQuery)
            ->where('created_at', '>=', Carbon::now()->subDays(7))
            ->count();

        return [
            'total_products' => $totalProducts,
            'recent_products' => $recentProducts
        ];
    }

    /**
     * Get active customers data for the seller.
     */
    public function getActiveCustomersData(int $sellerId): array
    {
        // Current period (last 7 days)
        $currentPeriodStart = Carbon::now()->subDays(7)->startOfDay();
        $currentPeriodEnd = Carbon::now()->endOfDay();

        // Previous period (7 days before the current period)
        $previousPeriodStart = Carbon::now()->subDays(14)->startOfDay();
        $previousPeriodEnd = Carbon::now()->subDays(7)->endOfDay();

        // Get unique customer count for current period
        $currentPeriodCustomers = OrderItem::with(['order'])
            ->whereHas('store', function ($q) use ($sellerId) {
                $q->where('seller_id', $sellerId);
            })
            ->whereBetween('created_at', [$currentPeriodStart, $currentPeriodEnd])
            ->get()
            ->pluck('order.user_id')
            ->unique()
            ->count();

        // Get unique customer count for previous period
        $previousPeriodCustomers = OrderItem::with(['order'])
            ->whereHas('store', function ($q) use ($sellerId) {
                $q->where('seller_id', $sellerId);
            })
            ->whereBetween('created_at', [$previousPeriodStart, $previousPeriodEnd])
            ->get()
            ->pluck('order.user_id')
            ->unique()
            ->count();

        // Calculate percentage change
        $percentageChange = 0;
        if ($previousPeriodCustomers > 0) {
            $percentageChange = (($currentPeriodCustomers - $previousPeriodCustomers) / $previousPeriodCustomers) * 100;
        } elseif ($currentPeriodCustomers > 0) {
            $percentageChange = 100; // If previous period was 0 and current is positive, that's a 100% increase
        }

        return [
            'count' => $currentPeriodCustomers,
            'previous_count' => $previousPeriodCustomers,
            'percentage_change' => round($percentageChange, 2),
            'is_increase' => $percentageChange >= 0
        ];
    }

    /**
     * Get conversion rate data for the seller.
     * Conversion rate is the percentage of delivered orders out of total orders.
     */
    public function getConversionRateData(int $sellerId, int $days = 7): array
    {
        // Current period (last 7 days)
        $currentPeriodStart = Carbon::now()->subDays($days)->startOfDay();
        $currentPeriodEnd = Carbon::now()->endOfDay();

        // Previous period (7 days before the current period)
        $previousPeriodStart = Carbon::now()->subDays($days * 2)->startOfDay();
        $previousPeriodEnd = Carbon::now()->subDays($days)->endOfDay();

        // Get total orders for the current period
        $currentPeriodTotalOrders = SellerOrderItem::whereHas('sellerOrder', function ($q) use ($sellerId) {
            $q->where('seller_id', $sellerId);
        })
            ->whereBetween('created_at', [$currentPeriodStart, $currentPeriodEnd])
            ->count();

        // Get delivered orders for the current period
        $currentPeriodDeliveredOrders = SellerOrderItem::whereHas('sellerOrder', function ($q) use ($sellerId) {
            $q->where('seller_id', $sellerId);
        })
            ->whereHas('orderItem', function ($q) {
                $q->where('status', OrderItemStatusEnum::DELIVERED());
            })
            ->whereBetween('created_at', [$currentPeriodStart, $currentPeriodEnd])
            ->count();

        // Get total orders for a previous period
        $previousPeriodTotalOrders = SellerOrderItem::whereHas('sellerOrder', function ($q) use ($sellerId) {
            $q->where('seller_id', $sellerId);
        })
            ->whereBetween('created_at', [$previousPeriodStart, $previousPeriodEnd])
            ->count();

        // Get delivered orders for a previous period
        $previousPeriodDeliveredOrders = SellerOrderItem::whereHas('sellerOrder', function ($q) use ($sellerId) {
            $q->where('seller_id', $sellerId);
        })
            ->whereHas('orderItem', function ($q) {
                $q->where('status', OrderItemStatusEnum::DELIVERED());
            })
            ->whereBetween('created_at', [$previousPeriodStart, $previousPeriodEnd])
            ->count();

        // Calculate conversion rates
        $currentPeriodRate = $currentPeriodTotalOrders > 0
            ? round(($currentPeriodDeliveredOrders / $currentPeriodTotalOrders) * 100, 2)
            : 0;

        $previousPeriodRate = $previousPeriodTotalOrders > 0
            ? round(($previousPeriodDeliveredOrders / $previousPeriodTotalOrders) * 100, 2)
            : 0;

        // Calculate percentage change
        $percentageChange = 0;
        if ($previousPeriodRate > 0) {
            $percentageChange = (($currentPeriodRate - $previousPeriodRate) / $previousPeriodRate) * 100;
        } elseif ($currentPeriodRate > 0) {
            $percentageChange = 100; // If previous period was 0 and current is positive, that's a 100% increase
        }

        return [
            'rate' => $currentPeriodRate,
            'previous_rate' => $previousPeriodRate,
            'delivered_orders' => $currentPeriodDeliveredOrders,
            'total_orders' => $currentPeriodTotalOrders,
            'percentage_change' => round($percentageChange, 2),
            'is_increase' => $percentageChange >= 0
        ];
    }

    /**
     * Get admin commission charts data.
     */
    public function getAdminCommissionChartsData(int $days, ?int $zoneId = null): array
    {
        $startDate = Carbon::now()->subDays($days)->startOfDay();
        $endDate = Carbon::now()->endOfDay();

        $commissionItems = OrderItem::with('order')
            ->whereBetween('created_at', [$startDate, $endDate])
            ->where('status', OrderItemStatusEnum::DELIVERED())
            ->when($zoneId, fn($q) => $q->whereHas('order', fn($oq) => $oq->where('delivery_zone_id', $zoneId)))
            ->get();
        $groupedTotals = $this->groupedTotals();
        $currencyTotals = $groupedTotals->fromItems($commissionItems, fn ($item) => $item->admin_commission_amount, fn ($item) => $item->order?->currency_code);

        $commissionByDay = $commissionItems
            ->groupBy(function ($item) {
                return $item->created_at->format('Y-m-d');
            })
            ->map(function ($items) use ($groupedTotals) {
                $totalCommission = $items->sum(function ($item) {
                    return $item->admin_commission_amount;
                });
                $dayCurrencyTotals = $groupedTotals->fromItems($items, fn ($item) => $item->admin_commission_amount, fn ($item) => $item->order?->currency_code);

                return [
                    'date' => $items->first()->created_at->format('Y-m-d'),
                    'revenue' => $totalCommission,
                    'formatted_revenue' => $this->currencyService->format($totalCommission),
                    ...$groupedTotals->summary($dayCurrencyTotals),
                ];
            })
            ->values()
            ->toArray();

        // Fill in missing days with zero revenue
        $result = [];
        $currentDate = clone $startDate;

        while ($currentDate <= $endDate) {
            $dateStr = $currentDate->format('Y-m-d');
            $found = false;

            foreach ($commissionByDay as $day) {
                if ($day['date'] === $dateStr) {
                    $result[] = $day;
                    $found = true;
                    break;
                }
            }

            if (!$found) {
                $result[] = [
                    'date' => $dateStr,
                    'revenue' => 0,
                    'formatted_revenue' => $this->currencyService->format(0),
                    ...$groupedTotals->summary([]),
                ];
            }

            $currentDate->addDay();
        }

        // Calculate total revenue
        $totalCommission = array_sum(array_column($result, 'revenue'));

        return [
            'daily' => $result,
            'total' => $totalCommission,
            'formatted_total' => $this->currencyService->format($totalCommission),
            ...$groupedTotals->summary($currencyTotals),
        ];
    }

    public function getAdminInsightsData(?int $zoneId = null): array
    {
        $today = Carbon::now()->format('Y-m-d');

        if ($zoneId) {
            // Zone-scoped: sellers/stores via store_zone pivot, delivery boys via delivery_zone_id, orders via delivery_zone_id
            $storeIds = DB::table('store_zone')->where('zone_id', $zoneId)->pluck('store_id');
            $sellerIds = Store::whereIn('id', $storeIds)
                ->where('verification_status', StoreVerificationStatusEnum::APPROVED())
                ->where('visibility_status', StoreVisibilityStatusEnum::VISIBLE())
                ->pluck('seller_id')
                ->unique();

            $totalSellers = Seller::whereIn('id', $sellerIds)
                ->where('verification_status', SellerVerificationStatusEnum::Approved())
                ->where('visibility_status', SellerVisibilityStatusEnum::Visible())
                ->count();

            $totalStores = Store::whereIn('id', $storeIds)
                ->where('verification_status', StoreVerificationStatusEnum::APPROVED())
                ->where('visibility_status', StoreVisibilityStatusEnum::VISIBLE())
                ->count();

            $deliveryBoysData = DeliveryBoy::where('verification_status', DeliveryBoyVerificationStatusEnum::VERIFIED())
                ->where('delivery_zone_id', $zoneId)
                ->selectRaw('
                    COUNT(*) as total_delivery_boys,
                    SUM(CASE WHEN status = ? THEN 1 ELSE 0 END) as total_active_delivery_boys
                ', [ActiveInactiveStatusEnum::ACTIVE()])
                ->first();

            $ordersData = Order::where('delivery_zone_id', $zoneId)
                ->selectRaw('
                    COUNT(*) as total_orders,
                    SUM(CASE WHEN status = ? THEN 1 ELSE 0 END) as total_delivered_orders
                ', [OrderStatusEnum::DELIVERED()])
                ->first();

            $orderItemsData = OrderItem::whereHas('order', fn($q) => $q->where('delivery_zone_id', $zoneId))
                ->selectRaw('
                    COUNT(CASE WHEN status = ? THEN 1 END) as total_product_sales,
                    SUM(CASE WHEN DATE(created_at) = ? AND status = ? THEN admin_commission_amount ELSE 0 END) as todays_commission
                ', [
                    OrderItemStatusEnum::DELIVERED(),
                    $today,
                    OrderItemStatusEnum::DELIVERED()
                ])->first();

            $totalUsers = DB::table('user_zone')->where('zone_id', $zoneId)->distinct('user_id')->count('user_id');
            $totalProducts = Product::whereIn('seller_id', $sellerIds)->count();
        } else {
            $totalSellers = Seller::where('verification_status', SellerVerificationStatusEnum::Approved())
                ->where('visibility_status', SellerVisibilityStatusEnum::Visible())->count();

            $totalStores = Store::where([
                ['verification_status', StoreVerificationStatusEnum::APPROVED()],
                ['visibility_status', StoreVisibilityStatusEnum::VISIBLE()]
            ])->count();

            $deliveryBoysData = DeliveryBoy::where('verification_status', DeliveryBoyVerificationStatusEnum::VERIFIED())
                ->selectRaw('
                    COUNT(*) as total_delivery_boys,
                    SUM(CASE WHEN status = ? THEN 1 ELSE 0 END) as total_active_delivery_boys
                ', [ActiveInactiveStatusEnum::ACTIVE()])
                ->first();

            $ordersData = Order::selectRaw('
                COUNT(*) as total_orders,
                SUM(CASE WHEN status = ? THEN 1 ELSE 0 END) as total_delivered_orders
            ', [OrderStatusEnum::DELIVERED()])
                ->first();

            $orderItemsData = OrderItem::selectRaw('
                COUNT(CASE WHEN status = ? THEN 1 END) as total_product_sales,
                SUM(CASE WHEN DATE(created_at) = ? AND status = ? THEN admin_commission_amount ELSE 0 END) as todays_commission
            ', [
                OrderItemStatusEnum::DELIVERED(),
                $today,
                OrderItemStatusEnum::DELIVERED()
            ])->first();

            $totalUsers = User::count();
            $totalProducts = Product::count();
        }

        return [
            'total_sellers' => $totalSellers,
            'total_stores' => $totalStores,
            'total_orders' => $ordersData->total_orders ?? 0,
            'total_delivered_orders' => $ordersData->total_delivered_orders ?? 0,
            'total_users' => $totalUsers,
            'total_products' => $totalProducts,
            'total_product_sales' => $orderItemsData->total_product_sales ?? 0,
            'todays_commission' => $orderItemsData->todays_commission ?? 0,
            'formatted_todays_commission' => $this->currencyService->format($orderItemsData->todays_commission ?? 0),
            'total_delivery_boys' => $deliveryBoysData->total_delivery_boys ?? 0,
            'total_active_delivery_boys' => $deliveryBoysData->total_active_delivery_boys ?? 0,
        ];
    }

    public function getAdminConversionRateData($days = 7, ?int $zoneId = null): array
    {
        $currentPeriodStart = Carbon::now()->subDays($days)->startOfDay();
        $currentPeriodEnd = Carbon::now()->endOfDay();

        $previousPeriodStart = Carbon::now()->subDays($days * 2)->startOfDay();
        $previousPeriodEnd = Carbon::now()->subDays($days)->endOfDay();

        $zoneScope = fn($q) => $zoneId ? $q->whereHas('order', fn($oq) => $oq->where('delivery_zone_id', $zoneId)) : $q;

        $currentPeriodTotalOrders = $zoneScope(OrderItem::whereBetween('created_at', [$currentPeriodStart, $currentPeriodEnd]))
            ->count();

        $currentPeriodDeliveredOrders = $zoneScope(OrderItem::where('status', OrderItemStatusEnum::DELIVERED())
            ->whereBetween('created_at', [$currentPeriodStart, $currentPeriodEnd]))
            ->count();

        $previousPeriodTotalOrders = $zoneScope(OrderItem::whereBetween('created_at', [$previousPeriodStart, $previousPeriodEnd]))
            ->count();

        $previousPeriodDeliveredOrders = $zoneScope(OrderItem::where('status', OrderItemStatusEnum::DELIVERED())
            ->whereBetween('created_at', [$previousPeriodStart, $previousPeriodEnd]))
            ->count();

        // Calculate conversion rates
        $currentPeriodRate = $currentPeriodTotalOrders > 0
            ? round(($currentPeriodDeliveredOrders / $currentPeriodTotalOrders) * 100, 2)
            : 0;

        $previousPeriodRate = $previousPeriodTotalOrders > 0
            ? round(($previousPeriodDeliveredOrders / $previousPeriodTotalOrders) * 100, 2)
            : 0;

        // Calculate percentage change
        $percentageChange = 0;
        if ($previousPeriodRate > 0) {
            $percentageChange = (($currentPeriodRate - $previousPeriodRate) / $previousPeriodRate) * 100;
        } elseif ($currentPeriodRate > 0) {
            $percentageChange = 100; // If the previous period was 0 and the current is positive, that's a 100% increase
        }

        return [
            'rate' => $currentPeriodRate,
            'previous_rate' => $previousPeriodRate,
            'delivered_orders' => $currentPeriodDeliveredOrders,
            'total_orders' => $currentPeriodTotalOrders,
            'percentage_change' => round($percentageChange, 2),
            'is_increase' => $percentageChange >= 0
        ];
    }

    /**
     * Get category product weightage data for pie chart.
     * Only includes categories that have products.
     */
    public function getCategoryProductWeightage(): array
    {
        // Get all categories with their product counts
        $categories = Category::withCount('products')
            ->having('products_count', '>', 0)
            ->get();

        // Calculate total products
        $totalProducts = $categories->sum('products_count');

        // Prepare data for pie chart
        $series = [];
        $labels = [];

        foreach ($categories as $category) {
            $series[] = $category->products_count;
            $labels[] = $category->getTranslation('title', 'en', false);
        }

        return [
            'series' => $series,
            'labels' => $labels,
            'total' => $totalProducts
        ];
    }

    /**
     * Get new user registrations data for the specified number of days.
     */
    public function getNewUserRegistrationsData(int $days = 7, ?int $zoneId = null): array
    {
        $currentPeriodStart = Carbon::now()->subDays($days)->startOfDay();
        $currentPeriodEnd = Carbon::now()->endOfDay();

        $previousPeriodStart = Carbon::now()->subDays($days * 2)->startOfDay();
        $previousPeriodEnd = Carbon::now()->subDays($days)->endOfDay();

        $userQuery = $zoneId
            ? User::whereHas('deliveryZones', fn($q) => $q->where('delivery_zones.id', $zoneId))
            : new User;

        $currentPeriodRegistrations = (clone $userQuery)->whereBetween('created_at', [$currentPeriodStart, $currentPeriodEnd])
            ->count();

        $previousPeriodRegistrations = (clone $userQuery)->whereBetween('created_at', [$previousPeriodStart, $previousPeriodEnd])
            ->count();

        // Calculate percentage change
        $percentageChange = 0;
        if ($previousPeriodRegistrations > 0) {
            $percentageChange = (($currentPeriodRegistrations - $previousPeriodRegistrations) / $previousPeriodRegistrations) * 100;
        } elseif ($currentPeriodRegistrations > 0) {
            $percentageChange = 100; // If previous period was 0 and current is positive, that's a 100% increase
        }

        // Get daily registration data for chart
        $registrationsByDay = (clone $userQuery)->whereBetween('created_at', [$currentPeriodStart, $currentPeriodEnd])
            ->get()
            ->groupBy(function ($user) {
                return $user->created_at->format('Y-m-d');
            })
            ->map(function ($users) {
                return [
                    'date' => $users->first()->created_at->format('Y-m-d'),
                    'count' => $users->count()
                ];
            })
            ->values()
            ->toArray();

        // Fill in missing days with zero registrations
        $result = [];
        $currentDate = clone $currentPeriodStart;

        while ($currentDate <= $currentPeriodEnd) {
            $dateStr = $currentDate->format('Y-m-d');
            $found = false;

            foreach ($registrationsByDay as $day) {
                if ($day['date'] === $dateStr) {
                    $result[] = $day;
                    $found = true;
                    break;
                }
            }

            if (!$found) {
                $result[] = [
                    'date' => $dateStr,
                    'count' => 0
                ];
            }

            $currentDate->addDay();
        }

        return [
            'count' => $currentPeriodRegistrations,
            'previous_count' => $previousPeriodRegistrations,
            'percentage_change' => round($percentageChange, 2),
            'is_increase' => $percentageChange >= 0,
            'daily' => $result
        ];
    }

    /**
     * Get top sellers based on revenue for the specified number of days.
     */
    public function getTopSellers(int $days = 7, int $limit = 10, string $sortBy = 'revenue', ?int $zoneId = null): array
    {
        $startDate = Carbon::now()->subDays($days)->startOfDay();
        $endDate = Carbon::now()->endOfDay();

        $query = Seller::select('sellers.*')->with('user')
            ->selectRaw('SUM(seller_order_items.price) as total_revenue')
            ->selectRaw('COUNT(seller_order_items.id) as total_orders')
            ->join('seller_orders', 'sellers.id', '=', 'seller_orders.seller_id')
            ->join('seller_order_items', 'seller_orders.id', '=', 'seller_order_items.seller_order_id')
            ->join('order_items', 'seller_order_items.order_item_id', '=', 'order_items.id')
            ->where('order_items.status', OrderItemStatusEnum::DELIVERED())
            ->whereBetween('order_items.created_at', [$startDate, $endDate])
            ->where('sellers.verification_status', SellerVerificationStatusEnum::Approved())
            ->where('sellers.visibility_status', SellerVisibilityStatusEnum::Visible());

        if ($zoneId) {
            $query->join('orders', 'order_items.order_id', '=', 'orders.id')
                ->where('orders.delivery_zone_id', $zoneId);
        }

        $orderColumn = match ($sortBy) {
            'orders' => 'total_orders',
            default => 'total_revenue',
        };

        $topSellers = $query->groupBy('sellers.id')
            ->orderBy($orderColumn, 'desc')
            ->limit($limit)
            ->get();

        return $topSellers->map(function ($seller) use ($startDate, $endDate, $zoneId) {
            $revenueItems = OrderItem::with('order')
                ->whereHas('sellerOrderItem.sellerOrder', fn ($q) => $q->where('seller_id', $seller->id))
                ->where('status', OrderItemStatusEnum::DELIVERED())
                ->whereBetween('created_at', [$startDate, $endDate])
                ->when($zoneId, fn ($q) => $q->whereHas('order', fn ($oq) => $oq->where('delivery_zone_id', $zoneId)))
                ->get();
            $currencyTotals = $this->groupedTotals()->fromItems($revenueItems, fn ($item) => $item->subtotal, fn ($item) => $item->order?->currency_code);

            return [
                'id' => $seller->id,
                'name' => $seller->user->name ?? 'N/A',
                'email' => $seller->user->email ?? '',
                'total_revenue' => $this->groupedTotals()->display($currencyTotals, $this->currencyService->format($seller->total_revenue)),
                'total_revenue_raw' => $seller->total_revenue,
                ...$this->groupedTotals()->summary($currencyTotals),
                'total_orders' => $seller->total_orders,
                'avatar' => $seller->user?->getFirstMediaUrl(SpatieMediaCollectionName::PROFILE_IMAGE()) ?? null,
            ];
        })->toArray();
    }

    /**
     * Get top selling products for the specified number of days.
     */
    public function getTopSellingProducts(int $days = 7, int $limit = 10, string $sortBy = 'quantity', ?int $zoneId = null): array
    {
        $startDate = Carbon::now()->subDays($days)->startOfDay();
        $endDate = Carbon::now()->endOfDay();

        $query = Product::select('products.*')->with('category')
            ->selectRaw('SUM(order_items.quantity) as total_quantity')
            ->selectRaw('SUM(order_items.subtotal) as total_revenue')
            ->selectRaw('COUNT(order_items.id) as total_orders')
            ->join('order_items', 'products.id', '=', 'order_items.product_id')
            ->where('order_items.status', OrderItemStatusEnum::DELIVERED())
            ->whereBetween('order_items.created_at', [$startDate, $endDate])
            ->where('products.status', ProductStatusEnum::ACTIVE());

        if ($zoneId) {
            $query->join('orders', 'order_items.order_id', '=', 'orders.id')
                ->where('orders.delivery_zone_id', $zoneId);
        }

        $orderColumn = match ($sortBy) {
            'revenue' => 'total_revenue',
            'orders' => 'total_orders',
            default => 'total_quantity',
        };

        $topProducts = $query->groupBy('products.id')
            ->orderBy($orderColumn, 'desc')
            ->limit($limit)
            ->get();

        return $topProducts->map(function ($product) use ($startDate, $endDate, $zoneId) {
            $revenueItems = OrderItem::with('order')
                ->where('product_id', $product->id)
                ->where('status', OrderItemStatusEnum::DELIVERED())
                ->whereBetween('created_at', [$startDate, $endDate])
                ->when($zoneId, fn ($q) => $q->whereHas('order', fn ($oq) => $oq->where('delivery_zone_id', $zoneId)))
                ->get();
            $currencyTotals = $this->groupedTotals()->fromItems($revenueItems, fn ($item) => $item->subtotal, fn ($item) => $item->order?->currency_code);

            return [
                'id' => $product->id,
                'name' => $product->getTranslation('title', 'en', false),
                'category' => $product->category->getTranslation('title', 'en', false),
                'slug' => $product->slug,
                'sku' => $product->sku,
                'total_quantity' => $product->total_quantity,
                'total_revenue' => $this->groupedTotals()->display($currencyTotals, $this->currencyService->format($product->total_revenue)),
                'total_revenue_raw' => $product->total_revenue,
                ...$this->groupedTotals()->summary($currencyTotals),
                'total_orders' => $product->total_orders,
                'image' => $product?->getFirstMediaUrl(SpatieMediaCollectionName::PRODUCT_MAIN_IMAGE()) ?? null,
            ];
        })->toArray();
    }

    /**
     * Get top delivery boys based on delivered parcels for the specified number of days.
     */
    public function getTopDeliveryBoys(int $days = 7, int $limit = 10, string $sortBy = 'deliveries', ?int $zoneId = null): array
    {
        $startDate = Carbon::now()->subDays($days)->startOfDay();
        $endDate = Carbon::now()->endOfDay();

        $query = DeliveryBoy::select('delivery_boys.*')->with('user')
            ->selectRaw('COUNT(order_items.id) as total_deliveries')
            ->selectRaw('SUM(order_items.subtotal) as total_revenue')
            ->join('delivery_boy_assignments', 'delivery_boys.id', '=', 'delivery_boy_assignments.delivery_boy_id')
            ->join('orders', 'delivery_boy_assignments.order_id', '=', 'orders.id')
            ->join('order_items', 'orders.id', '=', 'order_items.order_id')
            ->where('order_items.status', OrderItemStatusEnum::DELIVERED())
            ->whereBetween('order_items.created_at', [$startDate, $endDate])
            ->where('delivery_boys.verification_status', DeliveryBoyVerificationStatusEnum::VERIFIED())
            ->where('delivery_boys.status', ActiveInactiveStatusEnum::ACTIVE());

        if ($zoneId) {
            $query->where('orders.delivery_zone_id', $zoneId);
        }

        $orderColumn = match ($sortBy) {
            'revenue' => 'total_revenue',
            default => 'total_deliveries',
        };

        $topDeliveryBoys = $query->groupBy('delivery_boys.id')
            ->orderBy($orderColumn, 'desc')
            ->limit($limit)
            ->get();

        return $topDeliveryBoys->map(function ($deliveryBoy) use ($startDate, $endDate, $zoneId) {
            if ($deliveryBoy->user) {
                $revenueItems = OrderItem::with('order')
                    ->whereHas('order.deliveryBoyAssignments', fn ($q) => $q->where('delivery_boy_id', $deliveryBoy->id))
                    ->where('status', OrderItemStatusEnum::DELIVERED())
                    ->whereBetween('created_at', [$startDate, $endDate])
                    ->when($zoneId, fn ($q) => $q->whereHas('order', fn ($oq) => $oq->where('delivery_zone_id', $zoneId)))
                    ->get();
                $currencyTotals = $this->groupedTotals()->fromItems($revenueItems, fn ($item) => $item->subtotal, fn ($item) => $item->order?->currency_code);

                return [
                    'id' => $deliveryBoy->id,
                    'name' => $deliveryBoy->user->name,
                    'email' => $deliveryBoy->user->email,
                    'phone' => $deliveryBoy->user->phone,
                    'total_deliveries' => $deliveryBoy->total_deliveries,
                    'total_revenue' => $this->groupedTotals()->display($currencyTotals, $this->currencyService->format($deliveryBoy->total_revenue)),
                    'total_revenue_raw' => $deliveryBoy->total_revenue,
                    ...$this->groupedTotals()->summary($currencyTotals),
                    'avatar' => $deliveryBoy?->getFirstMediaUrl(SpatieMediaCollectionName::PROFILE_IMAGE()) ?? null,
                ];
            }
        })->toArray();
    }

    /**
     * Get categories with filters and sorting options.
     */
    public function getCategoriesWithFilters(string $sortBy = 'name', string $filterBy = 'all'): array
    {
        $query = Category::withCount(['products' => function ($query) {
            $query->where('status', ProductStatusEnum::ACTIVE());
        }]);

        // Apply filters
        switch ($filterBy) {
            case 'top_selling':
                $query->whereHas('products.orderItems', function ($query) {
                    $query->where('status', OrderItemStatusEnum::DELIVERED());
                })
                    ->withCount(['products as total_sold' => function ($query) {
                        $query->join('order_items', 'products.id', '=', 'order_items.product_id')
                            ->where('order_items.status', OrderItemStatusEnum::DELIVERED())
                            ->selectRaw('SUM(order_items.quantity)');
                    }]);
                break;
            case 'no_products':
                $query->having('products_count', '=', 0);
                break;
            default:
                // Show all categories
                break;
        }

        // Apply sorting
        switch ($sortBy) {
            case 'products_count':
                $query->orderBy('products_count', 'desc');
                break;
            case 'total_sold':
                if ($filterBy === 'top_selling') {
                    $query->orderBy('total_sold', 'desc');
                }
                break;
            case 'name':
            default:
                $query->orderByEnglishTitle();
                break;
        }

        $categories = $query->limit(12)->get();

        return $categories->map(function ($category) use ($filterBy) {
            $data = [
                'id' => $category->id,
                'title' => $category->getTranslation('title', 'en', false),
                'products_count' => $category->products_count,
                'image' => $category?->getFirstMediaUrl('image') ?? null,
            ];

            if ($filterBy === 'top_selling') {
                $data['total_sold'] = $category->total_sold ?? 0;
            }

            return $data;
        })->toArray();
    }

    /**
     * Get enhanced commission data with currency and filters.
     */
    public function getEnhancedCommissionsData(int $days = 30, string $type = 'all', ?int $zoneId = null): array
    {
        $startDate = Carbon::now()->subDays($days)->startOfDay();
        $endDate = Carbon::now()->endOfDay();

        $query = SellerOrderItem::join('order_items', 'seller_order_items.order_item_id', '=', 'order_items.id')
            ->where('order_items.status', OrderItemStatusEnum::DELIVERED())
            ->whereBetween('order_items.created_at', [$startDate, $endDate]);

        if ($zoneId) {
            $query->join('orders', 'order_items.order_id', '=', 'orders.id')
                ->where('orders.delivery_zone_id', $zoneId);
        }

        // Apply type filter
        if ($type !== 'all') {
            if ($type === 'high_commission') {
                $query->where('order_items.admin_commission_amount', '>', 100);
            } elseif ($type === 'low_commission') {
                $query->where('order_items.admin_commission_amount', '<=', 100);
            }
        }

        $commissions = $query->select(
            DB::raw('SUM(order_items.admin_commission_amount) as total_commission'),
            DB::raw('COUNT(seller_order_items.id) as total_orders'),
            DB::raw('AVG(order_items.admin_commission_amount) as avg_commission')
        )->first();

        // Get daily commission data for chart
        $dailyQuery = SellerOrderItem::join('order_items', 'seller_order_items.order_item_id', '=', 'order_items.id')
            ->where('order_items.status', OrderItemStatusEnum::DELIVERED())
            ->whereBetween('order_items.created_at', [$startDate, $endDate]);

        if ($zoneId) {
            $dailyQuery->join('orders as orders_daily', 'order_items.order_id', '=', 'orders_daily.id')
                ->where('orders_daily.delivery_zone_id', $zoneId);
        }

        $dailyCommissionsRaw = $dailyQuery->select(
                DB::raw('DATE(order_items.created_at) as date'),
                DB::raw('SUM(order_items.admin_commission_amount) as daily_commission')
            )
            ->groupBy('date')
            ->orderBy('date')
            ->pluck('daily_commission', 'date');

// Generate all dates between start and end
        $period = CarbonPeriod::create($startDate, $endDate);

// Map over the entire range
        $dailyCommissions = collect($period)->map(function ($date) use ($dailyCommissionsRaw) {
            $dateStr = $date->format('Y-m-d');
            $commission = $dailyCommissionsRaw[$dateStr] ?? 0;

            return [
                'date' => $dateStr,
                'commission' => $commission,
                'formatted_commission' => app(\App\Services\CurrencyService::class)->format($commission),
            ];
        });

        return [
            'total_commission' => $this->currencyService->format($commissions->total_commission ?? 0),
            'total_commission_raw' => $commissions->total_commission ?? 0,
            'total_orders' => $commissions->total_orders ?? 0,
            'avg_commission' => $this->currencyService->format($commissions->avg_commission ?? 0),
            'avg_commission_raw' => $commissions->avg_commission ?? 0,
            'daily_data' => $dailyCommissions->toArray(),
            'period' => $days,
            'currency_symbol' => $this->currencyService->getSymbol() ?? '$'
        ];
    }

    /**
     * Get combined revenue and order count data for dual-axis chart.
     */
    public function getRevenueVsOrdersData(int $days = 30, ?int $zoneId = null): array
    {
        $startDate = Carbon::now()->subDays($days)->startOfDay();
        $endDate = Carbon::now()->endOfDay();

        $query = Order::where('status', OrderStatusEnum::DELIVERED())
            ->whereBetween('created_at', [$startDate, $endDate]);

        if ($zoneId) {
            $query->where('delivery_zone_id', $zoneId);
        }

        $dailyData = (clone $query)
            ->selectRaw('DATE(created_at) as date, COUNT(*) as order_count, SUM(total_payable) as revenue')
            ->groupByRaw('DATE(created_at)')
            ->orderBy('date')
            ->get()
            ->keyBy('date');
        $groupedTotals = $this->groupedTotals();
        $currencyTotals = $groupedTotals->fromGroupedRows(
            (clone $query)
                ->selectRaw('currency_code, SUM(total_payable) as raw_total, COUNT(*) as record_count')
                ->groupBy('currency_code')
                ->get()
        );
        $dailyCurrencyTotals = (clone $query)
            ->selectRaw('DATE(created_at) as date, currency_code, SUM(total_payable) as raw_total, COUNT(*) as record_count')
            ->groupByRaw('DATE(created_at), currency_code')
            ->get()
            ->groupBy('date');

        $result = [];
        $period = CarbonPeriod::create($startDate, $endDate);

        foreach ($period as $date) {
            $dateStr = $date->format('Y-m-d');
            $dayData = $dailyData[$dateStr] ?? null;
            $dayTotals = $groupedTotals->fromGroupedRows($dailyCurrencyTotals->get($dateStr, collect()));

            $result[] = [
                'date' => $dateStr,
                'orders' => (int) ($dayData->order_count ?? 0),
                'revenue' => (float) ($dayData->revenue ?? 0),
                'formatted_revenue' => $this->currencyService->format($dayData->revenue ?? 0),
                ...$groupedTotals->summary($dayTotals),
            ];
        }

        $totalOrders = array_sum(array_column($result, 'orders'));
        $totalRevenue = array_sum(array_column($result, 'revenue'));
        $aov = $totalOrders > 0 ? $totalRevenue / $totalOrders : 0;

        return [
            'daily' => $result,
            'total_orders' => $totalOrders,
            'total_revenue' => $totalRevenue,
            'formatted_total_revenue' => $this->currencyService->format($totalRevenue),
            ...$groupedTotals->summary($currencyTotals),
            'aov' => round($aov, 2),
            'formatted_aov' => $this->currencyService->format($aov),
            'currency_symbol' => $this->currencyService->getSymbol() ?? '$',
        ];
    }

    /**
     * Get top stores ranked by revenue/orders.
     */
    public function getTopStores(int $days = 7, int $limit = 10, string $sortBy = 'revenue', ?int $zoneId = null): array
    {
        $startDate = Carbon::now()->subDays($days)->startOfDay();
        $endDate = Carbon::now()->endOfDay();

        $query = Store::select('stores.*')
            ->selectRaw('SUM(order_items.subtotal) as total_revenue')
            ->selectRaw('COUNT(order_items.id) as total_orders')
            ->selectRaw('SUM(order_items.quantity) as total_items_sold')
            ->join('order_items', 'stores.id', '=', 'order_items.store_id')
            ->where('order_items.status', OrderItemStatusEnum::DELIVERED())
            ->whereBetween('order_items.created_at', [$startDate, $endDate])
            ->where('stores.verification_status', StoreVerificationStatusEnum::APPROVED())
            ->where('stores.visibility_status', StoreVisibilityStatusEnum::VISIBLE());

        if ($zoneId) {
            $query->join('orders', 'order_items.order_id', '=', 'orders.id')
                ->where('orders.delivery_zone_id', $zoneId);
        }

        $orderColumn = match ($sortBy) {
            'orders' => 'total_orders',
            default => 'total_revenue',
        };

        $topStores = $query->groupBy('stores.id')
            ->orderBy($orderColumn, 'desc')
            ->limit($limit)
            ->get();

        return $topStores->map(function ($store) use ($startDate, $endDate, $zoneId) {
            $revenueItems = OrderItem::with('order')
                ->where('store_id', $store->id)
                ->where('status', OrderItemStatusEnum::DELIVERED())
                ->whereBetween('created_at', [$startDate, $endDate])
                ->when($zoneId, fn ($q) => $q->whereHas('order', fn ($oq) => $oq->where('delivery_zone_id', $zoneId)))
                ->get();
            $currencyTotals = $this->groupedTotals()->fromItems($revenueItems, fn ($item) => $item->subtotal, fn ($item) => $item->order?->currency_code);

            return [
                'id' => $store->id,
                'name' => $store->name,
                'total_revenue' => $this->groupedTotals()->display($currencyTotals, $this->currencyService->format($store->total_revenue)),
                'total_revenue_raw' => $store->total_revenue,
                ...$this->groupedTotals()->summary($currencyTotals),
                'total_orders' => $store->total_orders,
                'total_items_sold' => $store->total_items_sold,
                'image' => $store?->getFirstMediaUrl(SpatieMediaCollectionName::STORE_LOGO()) ?? null,
            ];
        })->toArray();
    }

    /**
     * Get top zones ranked by revenue/orders/delivery performance.
     */
    public function getTopZones(int $days = 7, int $limit = 10, string $sortBy = 'revenue'): array
    {
        $startDate = Carbon::now()->subDays($days)->startOfDay();
        $endDate = Carbon::now()->endOfDay();

        $query = DeliveryZone::select('delivery_zones.*')
            ->selectRaw('SUM(orders.total_payable) as total_revenue')
            ->selectRaw('COUNT(orders.id) as total_orders')
            ->selectRaw('SUM(CASE WHEN orders.status = ? THEN 1 ELSE 0 END) as delivered_orders', [OrderStatusEnum::DELIVERED()])
            ->join('orders', 'delivery_zones.id', '=', 'orders.delivery_zone_id')
            ->whereBetween('orders.created_at', [$startDate, $endDate])
            ->where('delivery_zones.status', ActiveInactiveStatusEnum::ACTIVE());

        $orderColumn = match ($sortBy) {
            'orders' => 'total_orders',
            'growth' => 'total_revenue',
            default => 'total_revenue',
        };

        $topZones = $query->groupBy('delivery_zones.id')
            ->orderBy($orderColumn, 'desc')
            ->limit($limit)
            ->get();

        return $topZones->map(function ($zone) use ($startDate, $endDate) {
            $onTimeRate = $zone->total_orders > 0
                ? round(($zone->delivered_orders / $zone->total_orders) * 100, 1)
                : 0;
            $currencyTotals = $this->groupedTotals()->fromGroupedRows(
                Order::query()
                    ->where('delivery_zone_id', $zone->id)
                    ->whereBetween('created_at', [$startDate, $endDate])
                    ->selectRaw('currency_code, SUM(total_payable) as raw_total, COUNT(*) as record_count')
                    ->groupBy('currency_code')
                    ->get()
            );

            return [
                'id' => $zone->id,
                'name' => $zone->name,
                'total_revenue' => $this->groupedTotals()->display($currencyTotals, $this->currencyService->format($zone->total_revenue)),
                'total_revenue_raw' => $zone->total_revenue,
                ...$this->groupedTotals()->summary($currencyTotals),
                'total_orders' => $zone->total_orders,
                'delivered_orders' => $zone->delivered_orders,
                'delivery_rate' => $onTimeRate,
                'active_delivery_boys' => DeliveryBoy::where('delivery_zone_id', $zone->id)
                    ->where('status', ActiveInactiveStatusEnum::ACTIVE())
                    ->count(),
            ];
        })->toArray();
    }

    /**
     * Get order status funnel data.
     */
    public function getOrderFunnelData(int $days = 7, ?int $zoneId = null): array
    {
        $startDate = Carbon::now()->subDays($days)->startOfDay();
        $endDate = Carbon::now()->endOfDay();

        $query = Order::whereBetween('created_at', [$startDate, $endDate]);

        if ($zoneId) {
            $query->where('delivery_zone_id', $zoneId);
        }

        $totalOrders = (clone $query)->count();

        $statusCounts = (clone $query)
            ->select('status', DB::raw('COUNT(*) as count'))
            ->groupBy('status')
            ->pluck('count', 'status')
            ->toArray();

        // Build funnel stages
        $placed = $totalOrders;
        $confirmed = ($statusCounts[OrderStatusEnum::ACCEPTED_BY_SELLER()] ?? 0)
            + ($statusCounts[OrderStatusEnum::PARTIALLY_ACCEPTED()] ?? 0)
            + ($statusCounts[OrderStatusEnum::READY_FOR_PICKUP()] ?? 0)
            + ($statusCounts[OrderStatusEnum::ASSIGNED()] ?? 0)
            + ($statusCounts[OrderStatusEnum::PREPARING()] ?? 0)
            + ($statusCounts[OrderStatusEnum::COLLECTED()] ?? 0)
            + ($statusCounts[OrderStatusEnum::OUT_FOR_DELIVERY()] ?? 0)
            + ($statusCounts[OrderStatusEnum::DELIVERED()] ?? 0);
        $outForDelivery = ($statusCounts[OrderStatusEnum::OUT_FOR_DELIVERY()] ?? 0)
            + ($statusCounts[OrderStatusEnum::DELIVERED()] ?? 0);
        $delivered = $statusCounts[OrderStatusEnum::DELIVERED()] ?? 0;
        $cancelled = ($statusCounts[OrderStatusEnum::CANCELLED()] ?? 0)
            + ($statusCounts[OrderStatusEnum::FAILED()] ?? 0)
            + ($statusCounts[OrderStatusEnum::REJECTED_BY_SELLER()] ?? 0);

        return [
            'funnel' => [
                ['stage' => 'Placed', 'count' => $placed],
                ['stage' => 'Confirmed', 'count' => $confirmed],
                ['stage' => 'Out for Delivery', 'count' => $outForDelivery],
                ['stage' => 'Delivered', 'count' => $delivered],
                ['stage' => 'Cancelled/Failed', 'count' => $cancelled],
            ],
            'status_breakdown' => $statusCounts,
            'conversion_rate' => $placed > 0 ? round(($delivered / $placed) * 100, 1) : 0,
            'cancellation_rate' => $placed > 0 ? round(($cancelled / $placed) * 100, 1) : 0,
        ];
    }

    /**
     * Get alerts and action items for the admin dashboard.
     */
    public function getAlertsData(?int $zoneId = null): array
    {
        $alerts = [];

        // Unassigned orders older than 15 minutes
        $unassignedQuery = Order::where('status', OrderStatusEnum::PENDING())
            ->where('created_at', '<', Carbon::now()->subMinutes(15));
        if ($zoneId) {
            $unassignedQuery->where('delivery_zone_id', $zoneId);
        }
        $unassignedCount = $unassignedQuery->count();
        if ($unassignedCount > 0) {
            $alerts[] = [
                'severity' => 'critical',
                'message' => __('labels.unassigned_orders_alert', ['count' => $unassignedCount]),
                'count' => $unassignedCount,
                'type' => 'unassigned_orders',
                'action_route' => 'admin.orders.index',
            ];
        }

        // Delivery boys offline in zone (only active zones with 0 active DBs)
        $zonesQuery = DeliveryZone::where('status', ActiveInactiveStatusEnum::ACTIVE());
        if ($zoneId) {
            $zonesQuery->where('id', $zoneId);
        }
        $zonesWithNoDBs = $zonesQuery->whereDoesntHave('deliveryBoys', function ($q) {
            $q->where('status', ActiveInactiveStatusEnum::ACTIVE());
        })->count();
        if ($zonesWithNoDBs > 0) {
            $alerts[] = [
                'severity' => 'critical',
                'message' => __('labels.zones_no_delivery_boys_alert', ['count' => $zonesWithNoDBs]),
                'count' => $zonesWithNoDBs,
                'type' => 'zones_no_delivery_boys',
                'action_route' => 'admin.delivery-zones.index',
            ];
        }

        // Low stock products (store product variants with stock < 5)
        $lowStockQuery = StoreProductVariant::where('stock', '<', 5)->where('stock', '>', 0);
        if ($zoneId) {
            $lowStockQuery->whereHas('store.zones', fn($q) => $q->where('delivery_zones.id', $zoneId));
        }
        $lowStockCount = $lowStockQuery->count();
        if ($lowStockCount > 0) {
            $alerts[] = [
                'severity' => 'warning',
                'message' => __('labels.low_stock_alert', ['count' => $lowStockCount]),
                'count' => $lowStockCount,
                'type' => 'low_stock',
                'action_route' => 'admin.products.index',
            ];
        }

        // Pending seller payouts (unsettled commissions older than 7 days)
        $pendingPayoutsQuery = OrderItem::where('commission_settled', '0')
            ->where('admin_commission_amount', '>', 0)
            ->where('status', OrderItemStatusEnum::DELIVERED())
            ->where('created_at', '<', Carbon::now()->subDays(7));
        if ($zoneId) {
            $pendingPayoutsQuery->whereHas('order', fn($q) => $q->where('delivery_zone_id', $zoneId));
        }
        $pendingPayoutsCount = $pendingPayoutsQuery->distinct('store_id')->count('store_id');
        if ($pendingPayoutsCount > 0) {
            $alerts[] = [
                'severity' => 'warning',
                'message' => __('labels.pending_payouts_alert', ['count' => $pendingPayoutsCount]),
                'count' => $pendingPayoutsCount,
                'type' => 'pending_payouts',
                'action_route' => 'admin.sellers.index',
            ];
        }

        // New seller applications pending
        $pendingSellers = Seller::where('verification_status', SellerVerificationStatusEnum::NotApproved())->count();
        if ($pendingSellers > 0) {
            $alerts[] = [
                'severity' => 'info',
                'message' => __('labels.pending_sellers_alert', ['count' => $pendingSellers]),
                'count' => $pendingSellers,
                'type' => 'pending_sellers',
                'action_route' => 'admin.sellers.index',
            ];
        }

        // Sort by severity
        $severityOrder = ['critical' => 0, 'warning' => 1, 'info' => 2];
        usort($alerts, fn($a, $b) => $severityOrder[$a['severity']] <=> $severityOrder[$b['severity']]);

        return ['alerts' => $alerts, 'total' => count($alerts)];
    }

    /**
     * Get customer insights data.
     */
    public function getCustomerInsightsData(int $days = 30, ?int $zoneId = null): array
    {
        $startDate = Carbon::now()->subDays($days)->startOfDay();
        $endDate = Carbon::now()->endOfDay();

        $ordersQuery = Order::whereBetween('created_at', [$startDate, $endDate])
            ->where('status', OrderStatusEnum::DELIVERED());

        if ($zoneId) {
            $ordersQuery->where('delivery_zone_id', $zoneId);
        }

        // Total unique customers who ordered
        $totalCustomers = (clone $ordersQuery)->distinct('user_id')->count('user_id');

        // Repeat customers (more than 1 order)
        $repeatCustomers = (clone $ordersQuery)
            ->select('user_id')
            ->groupBy('user_id')
            ->havingRaw('COUNT(*) > 1')
            ->get()
            ->count();

        $repeatRate = $totalCustomers > 0 ? round(($repeatCustomers / $totalCustomers) * 100, 1) : 0;

        // Average basket size (AOV)
        $avgBasketSize = (clone $ordersQuery)->avg('total_payable') ?? 0;

        // New vs returning
        $allTimeOrdersQuery = Order::where('status', OrderStatusEnum::DELIVERED());
        if ($zoneId) {
            $allTimeOrdersQuery->where('delivery_zone_id', $zoneId);
        }

        $previousCustomerIds = (clone $allTimeOrdersQuery)
            ->where('created_at', '<', $startDate)
            ->pluck('user_id')
            ->unique();

        $currentCustomerIds = (clone $ordersQuery)->pluck('user_id')->unique();
        $newCustomers = $currentCustomerIds->diff($previousCustomerIds)->count();
        $returningCustomers = $currentCustomerIds->intersect($previousCustomerIds)->count();

        // Churn risk: customers with 3+ orders historically who haven't ordered in 30 days
        $churnRiskQuery = Order::where('status', OrderStatusEnum::DELIVERED())
            ->where('created_at', '<', Carbon::now()->subDays(30));
        if ($zoneId) {
            $churnRiskQuery->where('delivery_zone_id', $zoneId);
        }

        $frequentCustomers = $churnRiskQuery
            ->select('user_id')
            ->groupBy('user_id')
            ->havingRaw('COUNT(*) >= 3')
            ->pluck('user_id');

        $recentOrderCustomers = Order::where('status', OrderStatusEnum::DELIVERED())
            ->where('created_at', '>=', Carbon::now()->subDays(30))
            ->when($zoneId, fn($q) => $q->where('delivery_zone_id', $zoneId))
            ->pluck('user_id')
            ->unique();

        $churnRisk = $frequentCustomers->diff($recentOrderCustomers)->count();

        return [
            'total_customers' => $totalCustomers,
            'repeat_customers' => $repeatCustomers,
            'repeat_rate' => $repeatRate,
            'avg_basket_size' => $this->currencyService->format($avgBasketSize),
            'avg_basket_size_raw' => round($avgBasketSize, 2),
            'new_customers' => $newCustomers,
            'returning_customers' => $returningCustomers,
            'churn_risk' => $churnRisk,
        ];
    }

    /**
     * Get zone health/comparison data.
     */
    public function getZoneHealthData(int $days = 7, ?int $zoneId = null): array
    {
        $startDate = Carbon::now()->subDays($days)->startOfDay();
        $endDate = Carbon::now()->endOfDay();

        $previousStart = Carbon::now()->subDays($days * 2)->startOfDay();
        $previousEnd = Carbon::now()->subDays($days)->endOfDay();

        $zonesQuery = DeliveryZone::where('status', ActiveInactiveStatusEnum::ACTIVE());
        if ($zoneId) {
            $zonesQuery->where('id', $zoneId);
        }

        $zones = $zonesQuery->get();

        $zoneData = $zones->map(function ($zone) use ($startDate, $endDate, $previousStart, $previousEnd) {
            $currentOrders = Order::where('delivery_zone_id', $zone->id)
                ->whereBetween('created_at', [$startDate, $endDate]);
            $currentTotal = (clone $currentOrders)->count();
            $currentDelivered = (clone $currentOrders)->where('status', OrderStatusEnum::DELIVERED())->count();
            $currentRevenue = (clone $currentOrders)->where('status', OrderStatusEnum::DELIVERED())->sum('total_payable');

            $previousRevenue = Order::where('delivery_zone_id', $zone->id)
                ->whereBetween('created_at', [$previousStart, $previousEnd])
                ->where('status', OrderStatusEnum::DELIVERED())
                ->sum('total_payable');

            $revenueGrowth = $previousRevenue > 0
                ? round((($currentRevenue - $previousRevenue) / $previousRevenue) * 100, 1)
                : ($currentRevenue > 0 ? 100 : 0);

            $activeDBs = DeliveryBoy::where('delivery_zone_id', $zone->id)
                ->where('status', ActiveInactiveStatusEnum::ACTIVE())
                ->count();

            $totalDBs = DeliveryBoy::where('delivery_zone_id', $zone->id)
                ->where('verification_status', DeliveryBoyVerificationStatusEnum::VERIFIED())
                ->count();

            $storeCount = DB::table('store_zone')->where('zone_id', $zone->id)->count();

            $deliveryRate = $currentTotal > 0
                ? round(($currentDelivered / $currentTotal) * 100, 1)
                : 0;

            return [
                'id' => $zone->id,
                'name' => $zone->name,
                'total_orders' => $currentTotal,
                'delivered_orders' => $currentDelivered,
                'delivery_rate' => $deliveryRate,
                'revenue' => $this->currencyService->format($currentRevenue),
                'revenue_raw' => $currentRevenue,
                'revenue_growth' => $revenueGrowth,
                'active_delivery_boys' => $activeDBs,
                'total_delivery_boys' => $totalDBs,
                'store_count' => $storeCount,
            ];
        })->toArray();

        return [
            'zones' => $zoneData,
            'total_zones' => count($zoneData),
        ];
    }

    /**
     * Get all active delivery zones for the zone selector.
     */
    public function getActiveZones(): array
    {
        return DeliveryZone::where('status', ActiveInactiveStatusEnum::ACTIVE())
            ->select('id', 'name', 'slug')
            ->orderBy('name')
            ->get()
            ->toArray();
    }

    /**
     * Get seller settlements summary data.
     */
    public function getSellerSettlementsData(int $days = 1, ?int $zoneId = null): array
    {
        $startDate = Carbon::now()->subDays($days)->startOfDay();
        $endDate = Carbon::now()->endOfDay();

        $baseQuery = SellerStatement::whereBetween('posted_at', [$startDate, $endDate]);

        if ($zoneId) {
            $baseQuery->whereHas('seller', function ($q) use ($zoneId) {
                $q->whereHas('stores', function ($sq) use ($zoneId) {
                    $sq->whereHas('zones', fn($zq) => $zq->where('delivery_zones.id', $zoneId));
                });
            });
        }

        $pending = (clone $baseQuery)->where('settlement_status', SellerSettlementStatusEnum::PENDING());
        $settled = (clone $baseQuery)->where('settlement_status', SellerSettlementStatusEnum::SETTLED());

        $pendingCount = $pending->count();
        $pendingAmount = (float) (clone $pending)->where('entry_type', 'credit')->sum('amount');
        $pendingCurrencyTotals = $this->groupedTotals()->fromGroupedRows(
            (clone $pending)
                ->where('entry_type', 'credit')
                ->selectRaw('currency_code, SUM(amount) as raw_total, COUNT(*) as record_count')
                ->groupBy('currency_code')
                ->get()
        );
        $settledCount = $settled->count();
        $settledAmount = (float) (clone $settled)->where('entry_type', 'credit')->sum('amount');
        $settledCurrencyTotals = $this->groupedTotals()->fromGroupedRows(
            (clone $settled)
                ->where('entry_type', 'credit')
                ->selectRaw('currency_code, SUM(amount) as raw_total, COUNT(*) as record_count')
                ->groupBy('currency_code')
                ->get()
        );

        // Total outstanding (all time pending)
        $outstandingQuery = SellerStatement::where('settlement_status', SellerSettlementStatusEnum::PENDING())
            ->where('entry_type', 'credit');
        if ($zoneId) {
            $outstandingQuery->whereHas('seller', function ($q) use ($zoneId) {
                $q->whereHas('stores', function ($sq) use ($zoneId) {
                    $sq->whereHas('zones', fn($zq) => $zq->where('delivery_zones.id', $zoneId));
                });
            });
        }
        $totalOutstanding = (float) $outstandingQuery->sum('amount');
        $outstandingCurrencyTotals = $this->groupedTotals()->fromGroupedRows(
            (clone $outstandingQuery)
                ->selectRaw('currency_code, SUM(amount) as raw_total, COUNT(*) as record_count')
                ->groupBy('currency_code')
                ->get()
        );

        return [
            'pending_count' => $pendingCount,
            'pending_amount' => $this->currencyService->format($pendingAmount),
            'pending_currency_totals' => $pendingCurrencyTotals,
            'pending_amount_display' => $this->groupedTotals()->display($pendingCurrencyTotals, $this->currencyService->format($pendingAmount)),
            'settled_count' => $settledCount,
            'settled_amount' => $this->currencyService->format($settledAmount),
            'settled_currency_totals' => $settledCurrencyTotals,
            'settled_amount_display' => $this->groupedTotals()->display($settledCurrencyTotals, $this->currencyService->format($settledAmount)),
            'total_outstanding' => $this->currencyService->format($totalOutstanding),
            'outstanding_currency_totals' => $outstandingCurrencyTotals,
            'total_outstanding_display' => $this->groupedTotals()->display($outstandingCurrencyTotals, $this->currencyService->format($totalOutstanding)),
            'raw_pending' => $pendingAmount,
            'raw_settled' => $settledAmount,
            'raw_outstanding' => $totalOutstanding,
        ];
    }

    /**
     * Get delivery boy settlements/earnings summary data.
     */
    public function getDeliveryBoySettlementsData(int $days = 1, ?int $zoneId = null): array
    {
        $startDate = Carbon::now()->subDays($days)->startOfDay();
        $endDate = Carbon::now()->endOfDay();

        $baseQuery = DeliveryBoyAssignment::whereBetween('assigned_at', [$startDate, $endDate]);

        if ($zoneId) {
            $baseQuery->whereHas('deliveryBoy', fn($q) => $q->where('delivery_zone_id', $zoneId));
        }

        $pendingEarnings = (clone $baseQuery)->where('payment_status', EarningPaymentStatusEnum::PENDING());
        $paidEarnings = (clone $baseQuery)->where('payment_status', EarningPaymentStatusEnum::PAID());

        $pendingCount = $pendingEarnings->count();
        $pendingAmount = (float) $pendingEarnings->sum('total_earnings');
        $pendingCurrencyTotals = $this->groupedTotals()->fromItems(
            (clone $pendingEarnings)->with('order')->get(),
            fn ($assignment) => $assignment->total_earnings,
            fn ($assignment) => $assignment->order?->currency_code,
        );
        $paidCount = $paidEarnings->count();
        $paidAmount = (float) $paidEarnings->sum('total_earnings');
        $paidCurrencyTotals = $this->groupedTotals()->fromItems(
            (clone $paidEarnings)->with('order')->get(),
            fn ($assignment) => $assignment->total_earnings,
            fn ($assignment) => $assignment->order?->currency_code,
        );

        // All-time unpaid balance
        $unpaidQuery = DeliveryBoyAssignment::where('payment_status', EarningPaymentStatusEnum::PENDING());
        if ($zoneId) {
            $unpaidQuery->whereHas('deliveryBoy', fn($q) => $q->where('delivery_zone_id', $zoneId));
        }
        $totalUnpaid = (float) $unpaidQuery->sum('total_earnings');
        $unpaidCurrencyTotals = $this->groupedTotals()->fromItems(
            (clone $unpaidQuery)->with('order')->get(),
            fn ($assignment) => $assignment->total_earnings,
            fn ($assignment) => $assignment->order?->currency_code,
        );

        return [
            'pending_count' => $pendingCount,
            'pending_amount' => $this->currencyService->format($pendingAmount),
            'pending_currency_totals' => $pendingCurrencyTotals,
            'pending_amount_display' => $this->groupedTotals()->display($pendingCurrencyTotals, $this->currencyService->format($pendingAmount)),
            'paid_count' => $paidCount,
            'paid_amount' => $this->currencyService->format($paidAmount),
            'paid_currency_totals' => $paidCurrencyTotals,
            'paid_amount_display' => $this->groupedTotals()->display($paidCurrencyTotals, $this->currencyService->format($paidAmount)),
            'total_unpaid' => $this->currencyService->format($totalUnpaid),
            'unpaid_currency_totals' => $unpaidCurrencyTotals,
            'total_unpaid_display' => $this->groupedTotals()->display($unpaidCurrencyTotals, $this->currencyService->format($totalUnpaid)),
            'raw_pending' => $pendingAmount,
            'raw_paid' => $paidAmount,
            'raw_unpaid' => $totalUnpaid,
        ];
    }

    /**
     * Get withdrawals summary data (seller + delivery boy combined).
     */
    public function getWithdrawalsData(int $days = 1, ?int $zoneId = null): array
    {
        $startDate = Carbon::now()->subDays($days)->startOfDay();
        $endDate = Carbon::now()->endOfDay();

        // Seller withdrawals
        $sellerQuery = SellerWithdrawalRequest::whereBetween('created_at', [$startDate, $endDate]);
        if ($zoneId) {
            $sellerQuery->whereHas('seller', function ($q) use ($zoneId) {
                $q->whereHas('stores', function ($sq) use ($zoneId) {
                    $sq->whereHas('zones', fn($zq) => $zq->where('delivery_zones.id', $zoneId));
                });
            });
        }

        $sellerPending = (clone $sellerQuery)->where('status', SellerWithdrawalStatusEnum::PENDING())->count();
        $sellerApproved = (clone $sellerQuery)->where('status', SellerWithdrawalStatusEnum::APPROVED());
        $sellerApprovedAmount = (float) $sellerApproved->sum('amount');
        $sellerApprovedCurrencyTotals = $this->groupedTotals()->fromItems(
            (clone $sellerApproved)->with('transaction')->get(),
            fn ($withdrawal) => $withdrawal->amount,
            fn ($withdrawal) => $withdrawal->transaction?->currency_code,
        );
        $sellerRejected = (clone $sellerQuery)->where('status', SellerWithdrawalStatusEnum::REJECTED())->count();

        // Delivery boy withdrawals
        $dbQuery = DeliveryBoyWithdrawalRequest::whereBetween('created_at', [$startDate, $endDate]);
        if ($zoneId) {
            $dbQuery->whereHas('deliveryBoy', fn($q) => $q->where('delivery_zone_id', $zoneId));
        }

        $dbPending = (clone $dbQuery)->where('status', DeliveryBoyWithdrawalStatusEnum::PENDING())->count();
        $dbApproved = (clone $dbQuery)->where('status', DeliveryBoyWithdrawalStatusEnum::APPROVED());
        $dbApprovedAmount = (float) $dbApproved->sum('amount');
        $dbApprovedCurrencyTotals = $this->groupedTotals()->fromItems(
            (clone $dbApproved)->with('transaction')->get(),
            fn ($withdrawal) => $withdrawal->amount,
            fn ($withdrawal) => $withdrawal->transaction?->currency_code,
        );
        $dbRejected = (clone $dbQuery)->where('status', DeliveryBoyWithdrawalStatusEnum::REJECTED())->count();

        // Oldest pending request (all time)
        $oldestPendingQuery = SellerWithdrawalRequest::where('status', SellerWithdrawalStatusEnum::PENDING());
        $oldestDbPendingQuery = DeliveryBoyWithdrawalRequest::where('status', DeliveryBoyWithdrawalStatusEnum::PENDING());

        $oldestSeller = $oldestPendingQuery->orderBy('created_at', 'asc')->value('created_at');
        $oldestDb = $oldestDbPendingQuery->orderBy('created_at', 'asc')->value('created_at');

        $oldestDays = 0;
        if ($oldestSeller || $oldestDb) {
            $oldest = $oldestSeller && $oldestDb
                ? min(Carbon::parse($oldestSeller), Carbon::parse($oldestDb))
                : Carbon::parse($oldestSeller ?? $oldestDb);
            $oldestDays = $oldest->diffInDays(Carbon::now());
        }

        $totalApprovedAmount = $sellerApprovedAmount + $dbApprovedAmount;
        $approvedCurrencyTotals = $this->groupedTotals()->fromGroupedRows(array_merge($sellerApprovedCurrencyTotals, $dbApprovedCurrencyTotals));

        return [
            'seller_pending' => $sellerPending,
            'db_pending' => $dbPending,
            'total_pending' => $sellerPending + $dbPending,
            'approved_amount' => $this->currencyService->format($totalApprovedAmount),
            'seller_approved_amount' => $this->currencyService->format($sellerApprovedAmount),
            'db_approved_amount' => $this->currencyService->format($dbApprovedAmount),
            'approved_currency_totals' => $approvedCurrencyTotals,
            'approved_amount_display' => $this->groupedTotals()->display($approvedCurrencyTotals, $this->currencyService->format($totalApprovedAmount)),
            'seller_approved_currency_totals' => $sellerApprovedCurrencyTotals,
            'seller_approved_amount_display' => $this->groupedTotals()->display($sellerApprovedCurrencyTotals, $this->currencyService->format($sellerApprovedAmount)),
            'db_approved_currency_totals' => $dbApprovedCurrencyTotals,
            'db_approved_amount_display' => $this->groupedTotals()->display($dbApprovedCurrencyTotals, $this->currencyService->format($dbApprovedAmount)),
            'total_rejected' => $sellerRejected + $dbRejected,
            'oldest_pending_days' => $oldestDays,
            'raw_approved' => $totalApprovedAmount,
        ];
    }

    /**
     * Get delivery boy cash collection summary data.
     */
    public function getCashCollectionData(int $days = 1, ?int $zoneId = null): array
    {
        $startDate = Carbon::now()->subDays($days)->startOfDay();
        $endDate = Carbon::now()->endOfDay();

        $baseQuery = DeliveryBoyAssignment::whereBetween('assigned_at', [$startDate, $endDate])
            ->where('cod_cash_collected', '>', 0);

        if ($zoneId) {
            $baseQuery->whereHas('deliveryBoy', fn($q) => $q->where('delivery_zone_id', $zoneId));
        }

        $totalCollected = (float) (clone $baseQuery)->sum('cod_cash_collected');
        $totalSubmitted = (float) (clone $baseQuery)->where('cod_submission_status', 'submitted')->sum('cod_cash_collected');
        $unsubmitted = (float) (clone $baseQuery)->where('cod_submission_status', '!=', 'submitted')->sum('cod_cash_collected');
        $collectedCurrencyTotals = $this->groupedTotals()->fromItems(
            (clone $baseQuery)->with('order')->get(),
            fn ($assignment) => $assignment->cod_cash_collected,
            fn ($assignment) => $assignment->order?->currency_code,
        );
        $submittedCurrencyTotals = $this->groupedTotals()->fromItems(
            (clone $baseQuery)->with('order')->where('cod_submission_status', 'submitted')->get(),
            fn ($assignment) => $assignment->cod_cash_collected,
            fn ($assignment) => $assignment->order?->currency_code,
        );
        $unsubmittedCurrencyTotals = $this->groupedTotals()->fromItems(
            (clone $baseQuery)->with('order')->where('cod_submission_status', '!=', 'submitted')->get(),
            fn ($assignment) => $assignment->cod_cash_collected,
            fn ($assignment) => $assignment->order?->currency_code,
        );

        // Top collector for the period
        $topCollector = (clone $baseQuery)
            ->select('delivery_boy_id', DB::raw('SUM(cod_cash_collected) as total_collected'))
            ->groupBy('delivery_boy_id')
            ->orderByDesc('total_collected')
            ->first();

        $topCollectorName = null;
        $topCollectorAmount = 0;
        if ($topCollector) {
            $db = DeliveryBoy::with('user')->find($topCollector->delivery_boy_id);
            $topCollectorName = $db?->user?->name ?? 'Unknown';
            $topCollectorAmount = (float) $topCollector->total_collected;
        }

        return [
            'total_collected' => $this->currencyService->format($totalCollected),
            'total_collected_display' => $this->groupedTotals()->display($collectedCurrencyTotals, $this->currencyService->format($totalCollected)),
            'collected_currency_totals' => $collectedCurrencyTotals,
            'total_submitted' => $this->currencyService->format($totalSubmitted),
            'total_submitted_display' => $this->groupedTotals()->display($submittedCurrencyTotals, $this->currencyService->format($totalSubmitted)),
            'submitted_currency_totals' => $submittedCurrencyTotals,
            'unsubmitted' => $this->currencyService->format($unsubmitted),
            'unsubmitted_display' => $this->groupedTotals()->display($unsubmittedCurrencyTotals, $this->currencyService->format($unsubmitted)),
            'unsubmitted_currency_totals' => $unsubmittedCurrencyTotals,
            'top_collector_name' => $topCollectorName,
            'top_collector_amount' => $this->currencyService->format($topCollectorAmount),
            'raw_collected' => $totalCollected,
            'raw_unsubmitted' => $unsubmitted,
        ];
    }

    /**
     * Get ad campaigns summary data (basic overview).
     */
    public function getAdCampaignsData(int $days = 1, ?int $zoneId = null): array
    {
        $startDate = Carbon::now()->subDays($days)->startOfDay();
        $endDate = Carbon::now()->endOfDay();

        // Active campaigns (running status)
        $activeCampaigns = AdCampaign::where('status', AdCampaignStatusEnum::RUNNING());
        if ($zoneId) {
            $activeCampaigns->whereHas('seller', function ($q) use ($zoneId) {
                $q->whereHas('stores', function ($sq) use ($zoneId) {
                    $sq->whereHas('zones', fn($zq) => $zq->where('delivery_zones.id', $zoneId));
                });
            });
        }
        $activeCount = $activeCampaigns->count();

        // Pending approval
        $pendingCount = AdCampaign::where('status', AdCampaignStatusEnum::PENDING_APPROVAL())->count();

        // Stats for the period
        $statsQuery = AdCampaignStat::whereBetween('stat_date', [$startDate, $endDate]);
        if ($zoneId) {
            $statsQuery->whereHas('campaign', function ($q) use ($zoneId) {
                $q->whereHas('seller', function ($sq) use ($zoneId) {
                    $sq->whereHas('stores', function ($stq) use ($zoneId) {
                        $stq->whereHas('zones', fn($zq) => $zq->where('delivery_zones.id', $zoneId));
                    });
                });
            });
        }

        $totalSpend = (float) (clone $statsQuery)->sum('spent');
        $totalClicks = (int) (clone $statsQuery)->sum('clicks');
        $totalImpressions = (int) (clone $statsQuery)->sum('impressions');

        // Top 3 campaigns by clicks in the period
        $topCampaigns = AdCampaignStat::select('campaign_id', DB::raw('SUM(clicks) as total_clicks'), DB::raw('SUM(spent) as total_spent'))
            ->whereBetween('stat_date', [$startDate, $endDate])
            ->when($zoneId, function ($q) use ($zoneId) {
                $q->whereHas('campaign', function ($cq) use ($zoneId) {
                    $cq->whereHas('seller', function ($sq) use ($zoneId) {
                        $sq->whereHas('stores', function ($stq) use ($zoneId) {
                            $stq->whereHas('zones', fn($zq) => $zq->where('delivery_zones.id', $zoneId));
                        });
                    });
                });
            })
            ->groupBy('campaign_id')
            ->orderByDesc('total_clicks')
            ->limit(3)
            ->get();

        $topCampaignsList = $topCampaigns->map(function ($stat) {
            $campaign = AdCampaign::with('product')->find($stat->campaign_id);
            return [
                'name' => $campaign?->product?->getTranslation('title', 'en', false) ?? 'Campaign #'.$stat->campaign_id,
                'clicks' => (int) $stat->total_clicks,
                'spent' => $this->currencyService->format((float) $stat->total_spent),
            ];
        })->toArray();

        return [
            'active_campaigns' => $activeCount,
            'pending_approval' => $pendingCount,
            'total_spend' => $this->currencyService->format($totalSpend),
            'total_clicks' => $totalClicks,
            'total_impressions' => $totalImpressions,
            'top_campaigns' => $topCampaignsList,
            'raw_spend' => $totalSpend,
        ];
    }

    /**
     * Get seller subscription summary data.
     */
    public function getSubscriptionsData(int $days = 1, ?int $zoneId = null): array
    {
        $now = Carbon::now();
        $startDate = Carbon::now()->subDays($days)->startOfDay();
        $endDate = Carbon::now()->endOfDay();

        // Active subscriptions
        $activeQuery = SellerSubscription::where('status', SellerSubscriptionStatusEnum::ACTIVE());
        if ($zoneId) {
            $activeQuery->whereHas('seller', function ($q) use ($zoneId) {
                $q->whereHas('stores', function ($sq) use ($zoneId) {
                    $sq->whereHas('zones', fn($zq) => $zq->where('delivery_zones.id', $zoneId));
                });
            });
        }
        $activeCount = $activeQuery->count();

        // Expiring in next 7 days
        $expiringQuery = SellerSubscription::where('status', SellerSubscriptionStatusEnum::ACTIVE())
            ->whereBetween('end_date', [$now, $now->copy()->addDays(7)]);
        if ($zoneId) {
            $expiringQuery->whereHas('seller', function ($q) use ($zoneId) {
                $q->whereHas('stores', function ($sq) use ($zoneId) {
                    $sq->whereHas('zones', fn($zq) => $zq->where('delivery_zones.id', $zoneId));
                });
            });
        }
        $expiringSoon = $expiringQuery->count();

        // Subscription revenue in the period
        $revenueQuery = SubscriptionTransaction::where('status', 'completed')
            ->whereBetween('created_at', [$startDate, $endDate]);
        if ($zoneId) {
            $revenueQuery->whereHas('seller', function ($q) use ($zoneId) {
                $q->whereHas('stores', function ($sq) use ($zoneId) {
                    $sq->whereHas('zones', fn($zq) => $zq->where('delivery_zones.id', $zoneId));
                });
            });
        }
        $subscriptionRevenue = (float) $revenueQuery->sum('amount');

        // Most popular plan (by active subscription count)
        $popularPlan = SellerSubscription::where('status', SellerSubscriptionStatusEnum::ACTIVE())
            ->select('plan_id', DB::raw('COUNT(*) as sub_count'))
            ->groupBy('plan_id')
            ->orderByDesc('sub_count')
            ->first();

        $popularPlanName = null;
        if ($popularPlan) {
            $popularPlanName = SubscriptionPlan::where('id', $popularPlan->plan_id)->value('name');
        }

        return [
            'active_count' => $activeCount,
            'expiring_soon' => $expiringSoon,
            'revenue' => $this->currencyService->format($subscriptionRevenue),
            'popular_plan' => $popularPlanName ?? 'N/A',
            'raw_revenue' => $subscriptionRevenue,
        ];
    }

    private function groupedTotals(): CurrencyGroupedTotals
    {
        return app(CurrencyGroupedTotals::class);
    }
}
