<?php

namespace App\Services\Search\Providers;

use App\Models\Order;
use App\Models\SellerOrder;
use App\Models\User;
use App\Services\Search\Contracts\SearchableProviderInterface;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;

/**
 * Class OrderSearchProvider
 * Searches customer orders by ID, billing/shipping customer details, or item titles. Multi-tenant scoped for sellers.
 */
class OrderSearchProvider implements SearchableProviderInterface
{
    public function search(string $query, string $panel, User $user, int $limit = 5): array
    {
        $escapedQuery = addcslashes($query, '%_\\');

        if ($panel === 'seller') {
            $sellerId = $user->seller()?->id;
            if (!$sellerId) {
                return [];
            }

            // For seller, search through SellerOrder relation
            $sellerOrders = SellerOrder::query()
                ->where('seller_id', $sellerId)
                ->where(function ($q) use ($escapedQuery) {
                    $q->where('order_id', 'LIKE', "%{$escapedQuery}%")
                        ->orWhereHas('order', function ($oq) use ($escapedQuery) {
                            $oq->where('id', 'LIKE', "%{$escapedQuery}%")
                                ->orWhere('billing_name', 'LIKE', "%{$escapedQuery}%")
                                ->orWhere('shipping_name', 'LIKE', "%{$escapedQuery}%");
                        })
                        ->orWhereHas('items.orderItem', function ($iq) use ($escapedQuery) {
                            $iq->where('title', 'LIKE', "%{$escapedQuery}%")
                                ->orWhere('variant_title', 'LIKE', "%{$escapedQuery}%")
                                ->orWhere('sku', 'LIKE', "%{$escapedQuery}%");
                        });
                })
                ->with(['order', 'items.orderItem'])
                ->limit($limit)
                ->get();

            $results = [];
            $routeName = 'seller.orders.show';

            foreach ($sellerOrders as $sellerOrder) {
                $order = $sellerOrder->order;
                if (!$order) {
                    continue;
                }

                $matchedItem = $sellerOrder->items->first()?->orderItem?->title ?? 'Items included';
                $url = Route::has($routeName) ? route($routeName, $order->id) : '#';

                $results[] = [
                    'type' => 'order',
                    'category' => 'Orders',
                    'badge' => 'ORDER',
                    'badge_class' => 'bg-green-lt',
                    'icon' => 'ti ti-shopping-cart',
                    'title' => 'Order #' . $order->id,
                    'subtitle' => 'Status: ' . ucfirst(str_replace('_', ' ', $sellerOrder->status ?? 'pending')) . ' • Item: ' . $matchedItem,
                    'url' => $url,
                ];
            }

            return $results;
        }

        // Admin panel: platform wide order search
        $orders = Order::query()
            ->where(function ($q) use ($escapedQuery) {
                $q->where('id', 'LIKE', "%{$escapedQuery}%")
                    ->orWhere('billing_name', 'LIKE', "%{$escapedQuery}%")
                    ->orWhere('shipping_name', 'LIKE', "%{$escapedQuery}%")
                    ->orWhere('walkin_customer_name', 'LIKE', "%{$escapedQuery}%")
                    ->orWhere('email', 'LIKE', "%{$escapedQuery}%")
                    ->orWhereHas('items', function ($iq) use ($escapedQuery) {
                        $iq->where('title', 'LIKE', "%{$escapedQuery}%")
                            ->orWhere('variant_title', 'LIKE', "%{$escapedQuery}%")
                            ->orWhere('sku', 'LIKE', "%{$escapedQuery}%");
                    })
                    ->orWhereHas('user', function ($uq) use ($escapedQuery) {
                        $uq->where('name', 'LIKE', "%{$escapedQuery}%")
                            ->orWhere('email', 'LIKE', "%{$escapedQuery}%")
                            ->orWhere('mobile', 'LIKE', "%{$escapedQuery}%");
                    });
            })
            ->with(['user', 'items'])
            ->limit($limit)
            ->get();

        $results = [];
        $routeName = 'admin.orders.show';

        foreach ($orders as $order) {
            $customerName = $order->billing_name ?: ($order->user?->name ?: ($order->walkin_customer_name ?: 'Guest'));
            $matchedItem = $order->items->first()?->title ?? '';
            $subtitle = 'Customer: ' . $customerName;
            if ($matchedItem) {
                $subtitle .= ' • Item: ' . $matchedItem;
            }

            $url = Route::has($routeName) ? route($routeName, $order->id) : '#';

            $results[] = [
                'type' => 'order',
                'category' => 'Orders',
                'badge' => 'ORDER',
                'badge_class' => 'bg-green-lt',
                'icon' => 'ti ti-shopping-cart',
                'title' => 'Order #' . $order->id,
                'subtitle' => $subtitle,
                'url' => $url,
            ];
        }

        return $results;
    }
}
