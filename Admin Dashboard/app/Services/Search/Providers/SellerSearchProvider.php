<?php

namespace App\Services\Search\Providers;

use App\Models\Store;
use App\Models\User;
use App\Services\Search\Contracts\SearchableProviderInterface;
use Illuminate\Support\Facades\Route;

/**
 * Class SellerSearchProvider
 * Admin-only search provider for registered stores and sellers.
 */
class SellerSearchProvider implements SearchableProviderInterface
{
    public function search(string $query, string $panel, User $user, int $limit = 5): array
    {
        // Admin panel only
        if ($panel !== 'admin') {
            return [];
        }

        $escapedQuery = addcslashes($query, '%_\\');

        $stores = Store::query()
            ->where(function ($q) use ($escapedQuery) {
                $q->where('name', 'LIKE', "%{$escapedQuery}%")
                    ->orWhere('description', 'LIKE', "%{$escapedQuery}%");
            })
            ->with('seller')
            ->limit($limit)
            ->get();

        $results = [];
        $routeName = 'admin.sellers.edit';

        foreach ($stores as $store) {
            $sellerId = $store->seller_id;
            $url = ($sellerId && Route::has($routeName)) ? route($routeName, $sellerId) : '#';

            $results[] = [
                'type' => 'seller',
                'category' => 'Sellers / Stores',
                'badge' => 'STORE',
                'badge_class' => 'bg-teal-lt',
                'icon' => 'ti ti-building-store',
                'title' => $store->name,
                'subtitle' => 'Seller ID: SEL-' . ($sellerId ?: 'N/A') . ' • City: ' . ($store->city ?: 'N/A'),
                'url' => $url,
            ];
        }

        return $results;
    }
}
