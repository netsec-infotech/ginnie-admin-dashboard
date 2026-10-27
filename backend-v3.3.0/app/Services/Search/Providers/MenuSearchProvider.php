<?php

namespace App\Services\Search\Providers;

use App\Models\User;
use App\Services\Search\Contracts\SearchableProviderInterface;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;

/**
 * Class MenuSearchProvider
 * Searches navigation menu routes configured in config/menu.php with flexible title, key, and route_param matching.
 */
class MenuSearchProvider implements SearchableProviderInterface
{
    /**
     * Search navigation menu items.
     */
    public function search(string $query, string $panel, User $user, int $limit = 25): array
    {
        // Fetch navigation structure for current panel
        $menuItems = config("menu.{$panel}", []);
        $results = [];
        $queryLower = mb_strtolower(trim($query));
        $querySingular = mb_strtolower(Str::singular(trim($query)));

        foreach ($menuItems as $key => $item) {
            if (($item['type'] ?? '') === 'section') {
                continue;
            }

            // Handle sub-menu routes (dropdown menus)
            if (is_array($item['route'] ?? null)) {
                foreach ($item['route'] as $subKey => $subItem) {
                    $translatedTitle = __($subItem['sub_title'] ?? '');
                    $parentTitle = __($item['title'] ?? '');
                    $rawSubTitle = $subItem['sub_title'] ?? '';
                    $humanKey = str_replace(['labels.', '_', '-'], ' ', $subKey);
                    $humanParentKey = str_replace(['labels.', '_', '-'], ' ', $key);

                    if (
                        $this->matches($translatedTitle, $queryLower, $querySingular) ||
                        $this->matches($parentTitle, $queryLower, $querySingular) ||
                        $this->matches($rawSubTitle, $queryLower, $querySingular) ||
                        $this->matches($humanKey, $queryLower, $querySingular) ||
                        $this->matches($humanParentKey, $queryLower, $querySingular)
                    ) {
                        $routeName = $subItem['sub_route'] ?? null;
                        if ($routeName && Route::has($routeName)) {
                            $displayTitle = !empty($translatedTitle) && !str_starts_with($translatedTitle, 'labels.') ? $translatedTitle : ucwords($humanKey);
                            $displayParent = !empty($parentTitle) && !str_starts_with($parentTitle, 'labels.') ? $parentTitle : ucfirst($humanParentKey);

                            $routeParams = $subItem['route_param'] ?? $subItem['params'] ?? [];
                            try {
                                $url = route($routeName, $routeParams);
                            } catch (\Throwable $e) {
                                $url = url($panel);
                            }

                            $results[] = [
                                'type' => 'navigation',
                                'category' => 'Navigation',
                                'badge' => 'MENU',
                                'badge_class' => 'bg-indigo-lt',
                                'icon' => 'ti ti-compass',
                                'title' => $displayTitle,
                                'subtitle' => $displayParent . ' > ' . $displayTitle,
                                'url' => $url,
                            ];
                        }
                    }

                    if (count($results) >= $limit) {
                        break 2;
                    }
                }
            } else {
                // Handle single menu route
                $translatedTitle = __($item['title'] ?? '');
                $rawTitle = $item['title'] ?? '';
                $humanKey = str_replace(['labels.', '_', '-'], ' ', $key);

                if (
                    $this->matches($translatedTitle, $queryLower, $querySingular) ||
                    $this->matches($rawTitle, $queryLower, $querySingular) ||
                    $this->matches($humanKey, $queryLower, $querySingular)
                ) {
                    $routeName = $item['route'] ?? null;
                    if ($routeName && Route::has($routeName)) {
                        $rawIcon = $item['icon'] ?? 'ti-compass';
                        $iconClass = str_starts_with($rawIcon, 'ti-') ? 'ti ' . $rawIcon : $rawIcon;
                        $displayTitle = !empty($translatedTitle) && !str_starts_with($translatedTitle, 'labels.') ? $translatedTitle : ucwords($humanKey);

                        $routeParams = $item['route_param'] ?? $item['params'] ?? [];
                        try {
                            $url = route($routeName, $routeParams);
                        } catch (\Throwable $e) {
                            $url = url($panel);
                        }

                        $results[] = [
                            'type' => 'navigation',
                            'category' => 'Navigation',
                            'badge' => 'MENU',
                            'badge_class' => 'bg-indigo-lt',
                            'icon' => $iconClass,
                            'title' => $displayTitle,
                            'subtitle' => ucfirst($panel) . ' Panel > ' . $displayTitle,
                            'url' => $url,
                        ];
                    }
                }
            }

            if (count($results) >= $limit) {
                break;
            }
        }

        return $results;
    }

    /**
     * Case-insensitive substring match helper supporting singular and plural forms.
     */
    private function matches(string $text, string $query, string $querySingular): bool
    {
        if (empty($text)) {
            return false;
        }
        $lowerText = mb_strtolower($text);
        return str_contains($lowerText, $query) || str_contains($lowerText, $querySingular);
    }
}
