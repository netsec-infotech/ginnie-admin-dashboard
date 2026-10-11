<?php

namespace App\Services\Search;

use App\Models\User;
use App\Services\Search\Providers\CategorySearchProvider;
use App\Services\Search\Providers\CustomerSearchProvider;
use App\Services\Search\Providers\MenuSearchProvider;
use App\Services\Search\Providers\OrderSearchProvider;
use App\Services\Search\Providers\ProductSearchProvider;
use App\Services\Search\Providers\ReturnOrderSearchProvider;
use App\Services\Search\Providers\SellerSearchProvider;

/**
 * Class GlobalSearchService
 * Orchestrates modular search providers and groups results by category.
 */
class GlobalSearchService
{
    /**
     * List of registered search providers.
     *
     * @var array
     */
    protected array $providers = [
        MenuSearchProvider::class,
        CategorySearchProvider::class,
        ProductSearchProvider::class,
        OrderSearchProvider::class,
        ReturnOrderSearchProvider::class,
        CustomerSearchProvider::class,
        SellerSearchProvider::class,
    ];

    /**
     * Execute search across all registered providers.
     *
     * @param string $query
     * @param string $panel
     * @param User $user
     * @param int $limitPerProvider
     * @return array Grouped results payload
     */
    public function search(string $query, string $panel, User $user, int $limitPerProvider = 20): array
    {
        $groupedResults = [];
        $totalMatches = 0;

        foreach ($this->providers as $providerClass) {
            /** @var \App\Services\Search\Contracts\SearchableProviderInterface $provider */
            $provider = app($providerClass);
            $results = $provider->search($query, $panel, $user, $limitPerProvider);

            if (!empty($results)) {
                $category = $results[0]['category'] ?? 'General';
                $groupedResults[$category] = $results;
                $totalMatches += count($results);
            }
        }

        return [
            'query' => $query,
            'total' => $totalMatches,
            'results' => $groupedResults,
        ];
    }
}
