<?php

namespace App\Services\Search\Providers;

use App\Models\OrderItemReturn;
use App\Models\User;
use App\Services\Search\Contracts\SearchableProviderInterface;
use Illuminate\Support\Facades\Route;

/**
 * Class ReturnOrderSearchProvider
 * Searches return & refund requests by return ID, reason, item title, or order ID.
 */
class ReturnOrderSearchProvider implements SearchableProviderInterface
{
    public function search(string $query, string $panel, User $user, int $limit = 5): array
    {
        $escapedQuery = addcslashes($query, '%_\\');

        $builder = OrderItemReturn::query()
            ->where(function ($q) use ($escapedQuery) {
                $q->where('id', 'LIKE', "%{$escapedQuery}%")
                    ->orWhere('reason', 'LIKE', "%{$escapedQuery}%")
                    ->orWhere('return_reason_name', 'LIKE', "%{$escapedQuery}%")
                    ->orWhereHas('orderItem', function ($iq) use ($escapedQuery) {
                        $iq->where('title', 'LIKE', "%{$escapedQuery}%");
                    })
                    ->orWhereHas('order', function ($oq) use ($escapedQuery) {
                        $oq->where('id', 'LIKE', "%{$escapedQuery}%");
                    });
            });

        if ($panel === 'seller') {
            $sellerId = $user->seller()?->id;
            if (!$sellerId) {
                return [];
            }
            $builder->where('seller_id', $sellerId);
        }

        $returns = $builder->with(['order', 'orderItem'])->limit($limit)->get();
        $results = [];

        $routeName = $panel === 'seller' ? 'seller.returns.show' : 'admin.returns.show';

        foreach ($returns as $returnItem) {
            $orderNum = $returnItem->order?->id ?? $returnItem->order_id;
            $itemName = $returnItem->orderItem?->title ?? 'Returned Item';
            $status = ucfirst(str_replace('_', ' ', $returnItem->return_status->value ?? $returnItem->return_status ?? 'pending'));

            $url = Route::has($routeName)
                ? route($routeName, $returnItem->id)
                : (Route::has("{$panel}.returns.index") ? route("{$panel}.returns.index") : '#');

            $results[] = [
                'type' => 'return',
                'category' => 'Return Requests',
                'badge' => 'RETURN',
                'badge_class' => 'bg-orange-lt',
                'icon' => 'ti ti-truck-return',
                'title' => 'Return #' . $returnItem->id . ' (Order #' . $orderNum . ')',
                'subtitle' => 'Status: ' . $status . ' • Item: ' . $itemName . ' • Reason: ' . ($returnItem->reason ?: 'Defective'),
                'url' => $url,
            ];
        }

        return $results;
    }
}
