<?php

namespace App\Http\Controllers\Api;

use App\Enums\Banner\BannerPositionEnum;
use App\Enums\HomePageScopeEnum;
use App\Http\Controllers\Controller;
use App\Http\Resources\BannerResource;
use App\Models\Banner;
use App\Models\Category;
use App\Models\Product;
use App\Services\BannerService;
use App\Services\DeliveryZoneService;
use App\Types\Api\ApiResponseType;
use Dedoc\Scramble\Attributes\Group;
use Dedoc\Scramble\Attributes\QueryParameter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\ResourceCollection;

#[Group('Banners')]
class BannerApiController extends Controller
{
    public function __construct(protected BannerService $bannerService) {}

    /**
     * Display a listing of the banners.
     */
    #[QueryParameter('position', description: 'position filter', type: 'string', example: "top, carousel")]
    #[QueryParameter('scope_category_slug', description: 'if you pass slug then banners will be filtered by category', type: 'string', example: "apple, amul")]
    #[QueryParameter('latitude', description: 'Latitude of the user location for zone-wise availability', type: 'float', example: 23.11684540)]
    #[QueryParameter('longitude', description: 'Longitude of the user location for zone-wise availability', type: 'float', example: 70.02805670)]
    public function index(Request $request): JsonResponse
    {
        // Validate request parameters
        $validationResult = $this->validateRequest($request);
        if ($validationResult) {
            return $validationResult;
        }

        // Build query
        $query = $this->buildQuery($request);

        // Get paginated results
        $perPage = $request->input('per_page', 15);
        $banners = $query->orderBy('position')->orderBy('display_order')->paginate($perPage);

        // Transform and format response
        $response = $this->formatResponse($banners);

        return ApiResponseType::sendJsonResponse(success: true, message: 'labels.banner_fetched_successfully', data: $response);
    }

    /**
     * Validate request parameters
     */
    private function validateRequest(Request $request): ?JsonResponse
    {
        // Validate position parameter
        if ($request->has('position')) {
            $position = $request->input('position');
            if (!in_array($position, BannerPositionEnum::values())) {
                return ApiResponseType::sendJsonResponse(success: false, message: __('labels.invalid_position_entered'), data: []);
            }
        }

        // Validate scope parameter
        if ($request->has('scope_category_slug')) {
            $categorySlug = $request->input('scope_category_slug');
            if (empty($categorySlug)) {
                return ApiResponseType::sendJsonResponse(success: false, message: __('labels.category_slug_is_required'), data: []);
            }

            $category = Category::where('slug', $categorySlug)->first();
            if (!$category) {
                return ApiResponseType::sendJsonResponse(success: false, message: __('labels.category_not_found'), data: []);
            }
        }

        return null;
    }

    /**
     * Build the query based on request parameters.
     *
     * Visibility + zone gating + per-type indirect filter live in BannerService
     * so the admin Zone Preview shares the same logic. The controller keeps
     * request-shaped concerns: position, scope, and ordering.
     */
    private function buildQuery(Request $request)
    {
        $latitude = $request->input('latitude');
        $longitude = $request->input('longitude');
        $useZoneFilter = ! is_null($latitude) && ! is_null($longitude);
        $storeIds = [];
        $zoneId = null;
        if ($useZoneFilter) {
            $zoneInfo = DeliveryZoneService::getZonesAtPoint((float)$latitude, (float)$longitude);
            $storeIds = Product::getStoreIdsInZone($zoneInfo);
            $zoneId = $zoneInfo['zone_id'] ?? null;
        }

        $query = $this->bannerService->availableInZoneStoresQuery($zoneId, $storeIds);

        // Apply position filter
        if ($request->has('position')) {
            $query->where('position', $request->input('position'));
        }

        // Apply scope filter
        if ($request->has('scope_category_slug')) {
            $categorySlug = $request->input('scope_category_slug');
            $category = Category::where('slug', $categorySlug)->first();
            $query = Banner::scopeByCategory($query, $category->id);
        } else {
            $query->where('scope_type', HomePageScopeEnum::GLOBAL());
        }

        return $query;
    }

    /**
     * Format the response data
     */
    private function formatResponse($banners): array
    {
        // Transform banners to resources
        $banners->getCollection()->transform(function ($banner) {
            return new BannerResource($banner);
        });

        // Group banners by position
        $grouped = $this->groupBannersByPosition($banners->getCollection());

        return [
            'current_page' => $banners->currentPage(),
            'last_page' => $banners->lastPage(),
            'per_page' => $banners->perPage(),
            'total' => $banners->total(),
            'data' => $grouped,
        ];
    }

    /**
     * Group banners by position with all positions initialized
     */
    private function groupBannersByPosition($banners)
    {
        // Group banners by position
        $grouped = $banners->groupBy('position')->map(function ($items) {
            return $items->values();
        });

        // Initialize all positions from enum with empty arrays
        $allPositions = collect();
        foreach (BannerPositionEnum::cases() as $position) {
            $allPositions[$position->value] = collect();
        }

        // Merge with actual grouped data
        return $allPositions->merge($grouped);
    }
}
