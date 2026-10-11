<?php

namespace App\Services\Search\Providers;

use App\Models\Category;
use App\Models\User;
use App\Services\Search\Contracts\SearchableProviderInterface;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;

/**
 * Class CategorySearchProvider
 * Searches product categories by title, slug, and description with singular/plural support.
 */
class CategorySearchProvider implements SearchableProviderInterface
{
    public function search(string $query, string $panel, User $user, int $limit = 5): array
    {
        $escapedQuery = addcslashes($query, '%_\\');
        $singularQuery = addcslashes(Str::singular($query), '%_\\');

        $categories = Category::query()
            ->where(function ($q) use ($escapedQuery, $singularQuery) {
                $q->where('title', 'LIKE', "%{$escapedQuery}%")
                    ->orWhere('title', 'LIKE', "%{$singularQuery}%")
                    ->orWhere('slug', 'LIKE', "%{$escapedQuery}%")
                    ->orWhere('slug', 'LIKE', "%{$singularQuery}%")
                    ->orWhere('description', 'LIKE', "%{$escapedQuery}%");
            })
            ->limit($limit)
            ->get();

        $results = [];
        $routeName = $panel === 'seller' ? 'seller.categories.index' : 'admin.categories.edit';

        foreach ($categories as $category) {
            $url = Route::has($routeName)
                ? ($panel === 'seller' ? route($routeName) : route($routeName, $category->id))
                : (Route::has("{$panel}.categories.index") ? route("{$panel}.categories.index") : '#');

            $results[] = [
                'type' => 'category',
                'category' => 'Categories',
                'badge' => 'CATEGORY',
                'badge_class' => 'bg-cyan-lt',
                'icon' => 'ti ti-category-2',
                'title' => $category->title,
                'subtitle' => 'Slug: ' . $category->slug . ($category->is_home_category ? ' • Home Category' : ''),
                'url' => $url,
            ];
        }

        return $results;
    }
}
