<?php

namespace App\Services\Search\Contracts;

use App\Models\User;

/**
 * Interface SearchableProviderInterface
 * Contract implemented by all modular search providers (Menu, Products, Orders, Returns, etc.)
 */
interface SearchableProviderInterface
{
    /**
     * Perform a search query for the given panel and user.
     *
     * @param string $query Sanitize search string
     * @param string $panel Current panel ('admin' or 'seller')
     * @param User $user Authenticated user instance
     * @param int $limit Maximum results to return for this provider
     * @return array Array of formatted search result objects
     */
    public function search(string $query, string $panel, User $user, int $limit = 5): array;
}
