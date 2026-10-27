<?php

namespace App\Services\Search\Providers;

use App\Models\User;
use App\Services\Search\Contracts\SearchableProviderInterface;
use Illuminate\Support\Facades\Route;

/**
 * Class CustomerSearchProvider
 * Admin-only search provider for platform customers/users.
 */
class CustomerSearchProvider implements SearchableProviderInterface
{
    public function search(string $query, string $panel, User $user, int $limit = 5): array
    {
        // Admin panel only
        if ($panel !== 'admin') {
            return [];
        }

        $escapedQuery = addcslashes($query, '%_\\');

        $customers = User::query()
            ->where(function ($q) use ($escapedQuery) {
                $q->where('name', 'LIKE', "%{$escapedQuery}%")
                    ->orWhere('email', 'LIKE', "%{$escapedQuery}%")
                    ->orWhere('mobile', 'LIKE', "%{$escapedQuery}%");
            })
            ->limit($limit)
            ->get();

        $results = [];
        $routeName = 'admin.customers.show';

        foreach ($customers as $customer) {
            $url = Route::has($routeName) ? route($routeName, $customer->id) : '#';

            $results[] = [
                'type' => 'customer',
                'category' => 'Customers',
                'badge' => 'CUSTOMER',
                'badge_class' => 'bg-purple-lt',
                'icon' => 'ti ti-user',
                'title' => $customer->name ?: 'Customer #' . $customer->id,
                'subtitle' => 'Email: ' . ($customer->email ?: 'N/A') . ' • Phone: ' . ($customer->mobile ?: 'N/A'),
                'url' => $url,
            ];
        }

        return $results;
    }
}
