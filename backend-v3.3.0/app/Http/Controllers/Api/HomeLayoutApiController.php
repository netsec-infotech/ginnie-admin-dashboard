<?php

namespace App\Http\Controllers\Api;

use App\Enums\GuardNameEnum;
use App\Enums\HomePageScopeEnum;
use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\DeliveryZone;
use App\Models\HomeLayout;
use App\Models\HomeSection;
use App\Models\User;
use App\Services\DeliveryZoneService;
use App\Services\HomeLayoutResolver;
use App\Types\Api\ApiResponseType;
use Dedoc\Scramble\Attributes\Group;
use Dedoc\Scramble\Attributes\QueryParameter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

#[Group('Home page')]
class HomeLayoutApiController extends Controller
{
    public function __construct(private readonly HomeLayoutResolver $resolver)
    {
    }

    /**
     * Resolved home layout for the current delivery zone.
     *
     * Global by default; pass ?category_slug=… for a category-scoped layout.
     * Returns ordered, active, zone-visible sections with their content
     * materialised (products, categories, brands, hero/banner items).
     */
    #[QueryParameter('platform', description: 'Storefront platform (app | web)', type: 'string', example: 'app')]
    #[QueryParameter('category_slug', description: 'Category slug to filter by', type: 'string', example: 'electronics')]
    #[QueryParameter('page', description: 'Sections page number', type: 'integer', example: 1)]
    #[QueryParameter('per_page', description: 'Sections per page (max 100)', type: 'integer', example: 5)]
    #[QueryParameter('latitude', description: 'User latitude for location-based zone filtering', type: 'float', example: 37.7749)]
    #[QueryParameter('longitude', description: 'User longitude for location-based zone filtering', type: 'float', example: -122.4194)]
    public function index(Request $request): JsonResponse
    {
        $request->validate([
            'platform'  => 'sometimes|in:app,web',
            'category_slug' => 'sometimes|nullable|string',
            'page'      => 'sometimes|integer|min:1',
            'per_page'  => 'sometimes|integer|min:1|max:100',
            'latitude'  => 'sometimes|required_with:longitude|numeric|between:-90,90',
            'longitude' => 'sometimes|required_with:latitude|numeric|between:-180,180',
        ]);

        $platform = (string) $request->input('platform', 'app');
        $perPage = (int) $request->input('per_page', 5);
        $page = (int) $request->input('page', 1);
        $lat = $request->filled('latitude') ? (float) $request->input('latitude') : null;
        $lng = $request->filled('longitude') ? (float) $request->input('longitude') : null;

        $zone = $this->resolveZone($lat, $lng);

        $category = null;
        $navbarItem = null;
        $layout = null;
        $isSpecificScopeRequested = false;

        if ($slug = $request->query('category_slug')) {
            $isSpecificScopeRequested = true;
            $category = Category::where('slug', $slug)->first();
            if (! $category) {
                $navbarItem = \App\Models\HomeNavbarItem::where('slug', $slug)->first();
                if ($navbarItem) {
                    $layout = HomeLayout::query()
                        ->where('platform', $platform)
                        ->whereIn('scope_type', ['navbar', 'category'])
                        ->where('scope_id', $navbarItem->id)
                        ->where('is_active', true)
                        ->first();
                }
            } else {
                $categoryId = $category->id;
                $layout = $this->resolver->resolveLayoutForCustomer($zone, $categoryId, $platform);
                if ($layout && ! in_array($layout->scope_type, ['category', 'zone_category'])) {
                    $layout = null;
                }
            }
        } elseif ($navbarSlug = $request->query('navbar_slug')) {
            $isSpecificScopeRequested = true;
            $navbarItem = \App\Models\HomeNavbarItem::where('slug', $navbarSlug)->first();
            if ($navbarItem) {
                $layout = HomeLayout::query()
                    ->where('platform', $platform)
                    ->whereIn('scope_type', ['navbar', 'category'])
                    ->where('scope_id', $navbarItem->id)
                    ->where('is_active', true)
                    ->first();
            }
        } else {
            // Main / Global Home layout request
            $layout = $this->resolver->resolveLayoutForCustomer($zone, null, $platform);
        }

        // If zone main layout is empty and it wasn't a category request, fallback to Global Layout
        if ($layout && $layout->scope_type === 'zone' && $layout->sections()->active()->count() === 0) {
            $layout = $this->resolver->resolveLayoutForCustomer(null, null, $platform);
        }

        if ($layout) {
            $layout->load([
                'sections' => fn ($q) => $q->active()->orderBy('sort_order'),
                'sections.media',
                'sections.items' => fn ($q) => $q->orderBy('sort_order'),
                'sections.items.itemable',
                'sections.items.media',
                'sections.zones:id',
            ]);
        }

        $targetScopeId = $navbarItem ? $navbarItem->id : ($category ? $category->id : null);
        $data = $layout
            ? $this->resolver->present($layout, $zone, $perPage, $page, $lat, $lng, $this->customer($request))
            : [
                'scope_type' => $isSpecificScopeRequested ? 'category' : 'global',
                'scope_id' => $targetScopeId,
                'current_page' => $page,
                'last_page' => 1,
                'per_page' => $perPage,
                'total' => 0,
                'sections' => [],
            ];

        return ApiResponseType::sendJsonResponse(
            success: true,
            message: 'labels.home_layout_fetched_successfully',
            data: $data,
        );
    }

    /**
     * Paginated content for a single section ("view all" / load more).
     */
    #[QueryParameter('page', description: 'Page number', type: 'integer', example: 1)]
    #[QueryParameter('per_page', description: 'Items per page (max 100)', type: 'integer', example: 15)]
    #[QueryParameter('latitude', description: 'User latitude for location-based zone filtering', type: 'float', example: 37.7749)]
    #[QueryParameter('longitude', description: 'User longitude for location-based zone filtering', type: 'float', example: -122.4194)]
    public function section(Request $request, HomeSection $section): JsonResponse
    {
        $request->validate([
            'page'      => 'sometimes|integer|min:1',
            'per_page'  => 'sometimes|integer|min:1|max:100',
            'latitude'  => 'sometimes|required_with:longitude|numeric|between:-90,90',
            'longitude' => 'sometimes|required_with:latitude|numeric|between:-180,180',
        ]);

        $perPage = (int) $request->input('per_page', 15);
        $page = (int) $request->input('page', 1);
        $lat = $request->filled('latitude') ? (float) $request->input('latitude') : null;
        $lng = $request->filled('longitude') ? (float) $request->input('longitude') : null;

        if (! $section->is_active) {
            return ApiResponseType::sendJsonResponse(
                success: true,
                message: 'labels.home_layout_fetched_successfully',
                data: ['current_page' => $page, 'last_page' => 1, 'per_page' => $perPage, 'total' => 0, 'data' => []],
            );
        }

        $section->load([
            'items' => fn ($q) => $q->orderBy('sort_order'),
            'items.itemable',
            'items.media',
            'zones:id',
        ]);

        $zone = $this->resolveZone($lat, $lng);

        return ApiResponseType::sendJsonResponse(
            success: true,
            message: 'labels.home_layout_fetched_successfully',
            data: $this->resolver->paginateSection($section, $zone, $perPage, $page, $lat, $lng, $this->customer($request)),
        );
    }

    private function customer(Request $request): ?User
    {
        $user = $request->user('sanctum');

        return $user?->access_panel === GuardNameEnum::WEB ? $user : null;
    }

    private function resolveZone(?float $lat, ?float $lng): ?DeliveryZone
    {
        if (! $lat || ! $lng) {
            return null;
        }

        $zoneInfo = DeliveryZoneService::getZonesAtPoint($lat, $lng);
        if (($zoneInfo['exists'] ?? false) && ! empty($zoneInfo['zone_id'])) {
            return DeliveryZone::find($zoneInfo['zone_id']);
        }

        return null;
    }
}
