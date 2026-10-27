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
        $singularRaw = Str::singular($query);
        $escapedSingular = addcslashes($singularRaw, '%_\\');

        $builder = Product::query()
            ->where(function ($q) use ($query, $escapedQuery, $singularRaw, $escapedSingular) {
                $q->whereEnglishTextLike($query)
                    ->orWhere(fn ($sub) => $sub->whereEnglishTextLike($singularRaw))
                    ->orWhere('slug', 'LIKE', "%{$escapedQuery}%")
                    ->orWhere('tags', 'LIKE', "%{$escapedQuery}%")
                    ->orWhereHas('category', function ($cq) use ($query, $singularRaw) {
                        $cq->where(fn ($sub) => $sub->whereEnglishTextLike($query))
                            ->orWhere(fn ($sub) => $sub->whereEnglishTextLike($singularRaw));
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
                'title' => $product->getTranslation('title', 'en', false),
                'subtitle' => 'Slug: ' . ($product->slug ?: 'N/A') . ' • Type: ' . ucfirst($product->type ?? 'Simple'),
                'url' => $url,
            ];
        }

        return $results;
    }
}
