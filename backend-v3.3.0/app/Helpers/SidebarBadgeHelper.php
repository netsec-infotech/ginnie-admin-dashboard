<?php

namespace App\Helpers;

use App\Enums\ActiveInactiveStatusEnum;
use App\Models\AdCampaign;
use App\Models\DeliveryBoy;
use App\Models\DeliveryZone;
use App\Models\Order;
use App\Models\OrderItemReturn;
use App\Models\Product;
use App\Models\Seller;
use App\Models\SellerOrder;
use App\Models\SellerStatement;
use App\Models\SellerWithdrawalRequest;
use App\Models\Store;
use Illuminate\Support\Facades\Auth;

class SidebarBadgeHelper
{
    /**
     * Cache badge counts per request execution.
     *
     * @var array
     */
    protected static array $cache = [];

    /**
     * Get badge counts array for the specified panel (admin or seller).
     *
     * @param string $panel
     * @return array<string, int>
     */
    public static function getBadgeCounts(string $panel): array
    {
        if (isset(self::$cache[$panel])) {
            return self::$cache[$panel];
        }

        $badges = [];

        if ($panel === 'admin') {
            $badges = self::getAdminBadgeCounts();
        } elseif ($panel === 'seller') {
            $badges = self::getSellerBadgeCounts();
        }

        self::$cache[$panel] = $badges;

        return $badges;
    }

    /**
     * Safely execute a count query without breaking other badge evaluations.
     *
     * @param callable $callback
     * @return int
     */
    protected static function safeCount(callable $callback): int
    {
        try {
            return (int) $callback();
        } catch (\Throwable $e) {
            return 0;
        }
    }

    /**
     * Fetch badge counts for Admin Panel.
     *
     * @return array<string, int>
     */
    protected static function getAdminBadgeCounts(): array
    {
        $pendingStatuses = ['pending', 'awaiting_store_response'];

        return [
            // Orders
            'orders' => self::safeCount(fn() => Order::whereHas('items', fn($q) => $q->whereIn('status', $pendingStatuses))->count()),
            'scheduled_orders' => self::safeCount(fn() => Order::where('is_scheduled', 1)->whereHas('items', fn($q) => $q->whereIn('status', $pendingStatuses))->count()),

            // Returns
            'return_requests' => self::safeCount(fn() => OrderItemReturn::where('return_status', 'requested')->count()),
            'return_disputes' => self::safeCount(fn() => OrderItemReturn::where('return_status', 'disputed')->count()),

            // Stores
            'stores' => self::safeCount(fn() => Store::where('verification_status', 'not_approved')
                ->orWhere('status', 'draft')
                ->orWhere('visibility_status', 'draft')
                ->count()),

            // Products
            'products' => self::safeCount(fn() => Product::where('verification_status', 'pending_verification')
                ->orWhere('status', 'draft')
                ->count()),

            // Ad Campaigns
            'ad_campaigns' => self::safeCount(fn() => AdCampaign::where('status', 'pending_approval')->count()),

            // Sellers
            'sellers' => self::safeCount(fn() => Seller::where(function ($query) {
                $query->whereIn('verification_status', ['not_approved', ''])
                    ->orWhereIn('visibility_status', ['draft', '']);
            })->count()),

            // Delivery Boys
            'delivery_boys' => self::safeCount(fn() => DeliveryBoy::where('verification_status', 'pending')
                ->count()),

            // Delivery Zones
            'delivery_zones' => self::safeCount(fn() => DeliveryZone::where('status', ActiveInactiveStatusEnum::ACTIVE())
                ->where(function ($query) {
                    $query->whereNull('country_id')
                        ->orWhereNull('currency_code');
                })
                ->count()),

            // Settlements & Withdrawals
            'settlements' => self::safeCount(fn() => SellerStatement::where('entry_type', 'credit')
                ->where('settlement_status', 'pending')
                ->count()),
            'withdrawals' => self::safeCount(fn() => SellerWithdrawalRequest::where('status', 'pending')->count()),
        ];
    }

    /**
     * Fetch badge counts for Seller Panel.
     *
     * @return array<string, int>
     */
    protected static function getSellerBadgeCounts(): array
    {
        $user = Auth::user();
        $sellerId = null;

        if ($user) {
            $sellerId = $user->seller_id ?? Seller::where('user_id', $user->id)->value('id');
        }
        if (!$sellerId && session()->has('impersonating_as_seller')) {
            $sellerId = session('impersonating_as_seller');
        }

        if (!$sellerId) {
            return [];
        }

        $pendingStatuses = ['pending', 'awaiting_store_response'];

        return [
            // Orders
            'orders' => self::safeCount(fn() => SellerOrder::where('seller_id', $sellerId)
                ->whereHas('items.orderItem', fn($q) => $q->whereIn('status', $pendingStatuses))
                ->count()),
            'scheduled_orders' => self::safeCount(fn() => SellerOrder::where('seller_id', $sellerId)
                ->whereHas('order', fn($q) => $q->where('is_scheduled', 1))
                ->whereHas('items.orderItem', fn($q) => $q->whereIn('status', $pendingStatuses))
                ->count()),

            // Returns
            'return_requests' => self::safeCount(fn() => OrderItemReturn::where('seller_id', $sellerId)
                ->where('return_status', 'requested')
                ->count()),
            'return_disputes' => self::safeCount(fn() => OrderItemReturn::where('seller_id', $sellerId)
                ->where('return_status', 'disputed')
                ->count()),

            // Stores
            'stores' => self::safeCount(fn() => Store::where('seller_id', $sellerId)
                ->where(function ($q) {
                    $q->where('verification_status', 'not_approved')
                        ->orWhere('status', 'draft')
                        ->orWhere('visibility_status', 'draft');
                })->count()),

            // Products
            'products' => self::safeCount(fn() => Product::where('seller_id', $sellerId)
                ->where(function ($q) {
                    $q->where('verification_status', 'pending_verification')
                        ->orWhere('status', 'draft');
                })->count()),

            // Ad Campaigns
            'ad_campaigns' => self::safeCount(fn() => AdCampaign::where('seller_id', $sellerId)
                ->where('status', 'pending_approval')
                ->count()),

            // Withdrawals
            'withdrawals' => self::safeCount(fn() => SellerWithdrawalRequest::where('seller_id', $sellerId)
                ->where('status', 'pending')
                ->count()),
        ];
    }
}
