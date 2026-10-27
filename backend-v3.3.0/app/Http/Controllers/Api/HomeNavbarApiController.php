<?php

namespace App\Http\Controllers\Api;

use App\Enums\SettingTypeEnum;
use App\Http\Controllers\Controller;
use App\Http\Resources\HomeNavbarItemResource;
use App\Models\HomeNavbarItem;
use App\Services\SettingService;
use App\Types\Api\ApiResponseType;
use Dedoc\Scramble\Attributes\Group;
use Dedoc\Scramble\Attributes\QueryParameter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

#[Group('Home page')]
class HomeNavbarApiController extends Controller
{
    /**
     * Fetch all active navbar categories for the specified platform, including global home appearance.
     */
    #[QueryParameter('platform', description: 'Storefront platform (app | web)', type: 'string', example: 'app')]
    public function index(Request $request, SettingService $settingService): JsonResponse
    {
        $validated = $request->validate([
            'platform' => ['sometimes', 'in:app,web'],
        ]);
        $platform = $validated['platform'] ?? 'app';
        $items = HomeNavbarItem::query()
            ->with('media')
            ->where('is_active', true)
            ->forPlatform($platform)
            ->ordered($platform)
            ->get();

        $general = data_get(
            $settingService->getSettingByVariable(SettingTypeEnum::HOME_GENERAL_SETTINGS())?->resolve(),
            'value',
            [],
        );
        $appearance = $this->globalAppearance($general, $platform);
        $global = [
            'id' => null,
            'title' => $general['title'] ?? 'All',
            'slug' => null,
            'is_default' => true,
            'platforms' => ['app', 'web'],
            'layout_query' => [],
            'appearance' => $appearance,
            'search_labels' => array_values($general['searchLabels'] ?? []),
        ];

        return ApiResponseType::sendJsonResponse(
            success: true,
            message: 'labels.home_layout_fetched_successfully',
            data: array_merge(
                [$global],
                HomeNavbarItemResource::collection($items)->resolve($request),
            ),
        );
    }

    /**
     * Resolve default global appearance for header themes based on platform.
     */
    private function globalAppearance(array $general, string $platform): array
    {
        $stored = (array) ($general['homeAppearance'] ?? []);
        $appMobileBackground = $general['backgroundImage'] ?? '';
        $appTabletBackground = ($general['appTabletBackgroundImage'] ?? '') ?: $appMobileBackground;
        $app = array_merge([
            'background_type' => ($general['backgroundType'] ?? '') === 'image'
                ? 'image'
                : (($general['backgroundType'] ?? '') === 'color' ? 'gradient' : 'none'),
            'gradient_start' => $general['backgroundColor'] ?? '#ffffff',
            'gradient_end' => $general['backgroundColor'] ?? '#ffffff',
            'gradient_angle' => 90,
            'font_color' => $general['fontColor'] ?? '#475569',
            'active_font_color' => '#111827',
        ], (array) ($stored['app'] ?? []), [
            'icon' => $general['icon'] ?? '',
            'active_icon' => $general['activeIcon'] ?? ($general['icon'] ?? ''),
            'background_image' => $appMobileBackground,
            'mobile_background_image' => $appMobileBackground,
            'tablet_background_image' => $appTabletBackground,
        ]);
        $desktopBackground = ($general['desktopBackgroundImage'] ?? '') ?: $appMobileBackground;
        $desktopTabletBackground = ($general['desktopTabletBackgroundImage'] ?? '') ?: $desktopBackground;
        $desktopMobileBackground = ($general['desktopMobileBackgroundImage'] ?? '') ?: $desktopTabletBackground ?: $desktopBackground;
        $desktop = array_merge($app, (array) ($stored['desktop'] ?? []), [
            'icon' => $general['desktopIcon'] ?? ($general['icon'] ?? ''),
            'active_icon' => $general['desktopActiveIcon'] ?? ($general['activeIcon'] ?? ''),
            'background_image' => $desktopBackground,
            'desktop_background_image' => $desktopBackground,
            'tablet_background_image' => $desktopTabletBackground,
            'mobile_background_image' => $desktopMobileBackground,
            'app_background_image' => $desktopMobileBackground,
        ]);

        return $platform === 'web' ? $desktop : $app;
    }
}
