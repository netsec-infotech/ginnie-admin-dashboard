<?php

namespace App\Services\Search\Providers;

use App\Models\Product;
use App\Models\User;
use App\Services\Search\Contracts\SearchableProviderInterface;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;

/**
 * Class ProductSearchProvider
 * Searches products by title, slug, short_description, tags, and category relations. Multi-tenant scoped for sellers.
 */
class ProductSearchProvider implements SearchableProviderInterface
{
    public function search(string $query, string $panel, User $user, int $limit = 5): array
    {
        $escapedQuery = addcslashes($query, '%_\\');
        $singularQuery = addcslashes(Str::singular($query), '%_\\');

        $builder = Product::query()
            ->where(function ($q) use ($escapedQuery, $singularQuery) {
                $q->where('title', 'LIKE', "%{$escapedQuery}%")
                    ->orWhere('title', 'LIKE', "%{$singularQuery}%")
                    ->orWhere('slug', 'LIKE', "%{$escapedQuery}%")
                    ->orWhere('short_description', 'LIKE', "%{$escapedQuery}%")
                    ->orWhere('tags', 'LIKE', "%{$escapedQuery}%")
                    ->orWhereHas('categories', function ($cq) use ($escapedQuery, $singularQuery) {
                        $cq->where('title', 'LIKE', "%{$escapedQuery}%")
                            ->orWhere('title', 'LIKE', "%{$singularQuery}%");
                    });
            });

        // Enforce multi-tenancy for seller panel
        if ($panel === 'seller') {
            $sellerId = $user->seller()?->id;
            if (!$sellerId) {
                return [];
            }
            $builder->where('seller_id', $sellerId);
        }

        $products = $builder->limit($limit)->get();
        $results = [];

        $routeName = $panel === 'seller' ? 'seller.products.edit' : 'admin.products.edit';

        foreach ($products as $product) {
            $url = Route::has($routeName)
                ? route($routeName, $product->id)
                : (Route::has("{$panel}.products.index") ? route("{$panel}.products.index") : '#');

            $results[] = [
                'type' => 'product',
                'category' => 'Products',
                'badge' => 'PRODUCT',
                'badge_class' => 'bg-blue-lt',
                'icon' => 'ti ti-package',
                'title' => $product->title,
                'subtitle' => 'Slug: ' . ($product->slug ?: 'N/A') . ' • Type: ' . ucfirst($product->type ?? 'Simple'),
                'url' => $url,
            ];
        }

        return $results;
    }
}
