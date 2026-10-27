<?php

namespace App\Services;

use App\Enums\DefaultSystemRolesEnum;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Support\Facades\Route;

class SellerTourCatalog
{
    /** @return array<string, bool> */
    public function features(User $user): array
    {
        $seller = $user->seller();

        return [
            'pos' => $seller !== null && app(SubscriptionUsageService::class)->hasPosAccess($seller->id),
            'scheduled' => Setting::isScheduledDeliveryEnabled(),
            'subscription' => Setting::isSystemVendorTypeMultiple() && Setting::isSubscriptionEnabled(),
            'ads' => app(AdWalletService::class)->isFeatureEnabled(),
            'watch' => app(WatchBuyService::class)->enabled(),
            'stories' => app(WatchBuyService::class)->statusesEnabled(),
        ];
    }

    public function permitted(User $user, ?string $permission): bool
    {
        if ($permission === null || $user->hasRole(DefaultSystemRolesEnum::SELLER()) || $user->hasRole(DefaultSystemRolesEnum::SUPER_ADMIN())) {
            return true;
        }

        try {
            return $user->hasPermissionTo($permission);
        } catch (\Spatie\Permission\Exceptions\PermissionDoesNotExist) {
            return false;
        }
    }

    /**
     * @param array<string, bool>|null $features
     * @return array<string, array<string, mixed>>
     */
    public function forUser(?User $user, ?array $features = null): array
    {
        if ($user === null) {
            return [];
        }

        $features ??= $this->features($user);
        $catalog = [];

        foreach (config('tours.seller', []) as $id => $tour) {
            if (! $this->eligible($tour, $user, $features)) {
                continue;
            }

            $stages = [];
            foreach ($tour['stages'] as $stage) {
                if (! $this->eligible($stage, $user, $features)) {
                    continue;
                }

                $stage['steps'] = array_values(array_filter($stage['steps'], fn (array $step): bool => $this->eligible($step, $user, $features)));
                foreach ($stage['steps'] as &$step) {
                    $step['title'] = __($step['title']);
                    $step['description'] = __($step['description']);
                }
                unset($step);

                $stage['url'] = $this->routeTemplate($stage['route']);
                if (isset($stage['listing'])) {
                    $stage['listingUrl'] = $this->routeTemplate($stage['listing']);
                }
                if (isset($stage['redirect'])) {
                    $stage['redirectUrl'] = $this->routeTemplate($stage['redirect']);
                }
                $stages[] = $stage;
            }

            if ($stages === []) {
                continue;
            }

            $tour['stages'] = $stages;
            $tour['title'] = __($tour['title']);
            $tour['description'] = __($tour['description']);
            $tour['group'] = __($tour['group']);
            $tour['action'] = $id;
            $catalog[$id] = $tour;
        }

        return $catalog;
    }

    /** @param array<string, mixed> $definition
     * @param array<string, bool> $features
     */
    private function eligible(array $definition, User $user, array $features): bool
    {
        if (isset($definition['feature']) && ! ($features[$definition['feature']] ?? false)) {
            return false;
        }

        foreach ($definition['permissions'] ?? (isset($definition['permission']) ? [$definition['permission']] : []) as $permission) {
            if (! $this->permitted($user, $permission)) {
                return false;
            }
        }

        return true;
    }

    private function routeTemplate(string $name): string
    {
        $route = Route::getRoutes()->getByName($name);
        $parameters = array_fill_keys($route->parameterNames(), '__tour_record__');

        return str_replace('__tour_record__', ':record', route($name, $parameters));
    }
}
