<?php

namespace App\Http\Controllers\Admin;

use App\Enums\AdminPermissionEnum;
use App\Http\Controllers\Controller;
use App\Models\DeliveryZone;
use App\Services\ZonePreviewService;
use App\Traits\ChecksPermissions;
use App\Types\Api\ApiResponseType;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class ZonePreviewController extends Controller
{
    use ChecksPermissions;

    protected bool $viewPermission = false;

    public function __construct(protected ZonePreviewService $zonePreviewService)
    {
        $this->viewPermission = $this->hasPermission(AdminPermissionEnum::ZONE_PREVIEW_VIEW());
    }

    /**
     * Render the Zone Preview page (zone picker + brands list).
     */
    public function index(): View
    {
        if (! $this->viewPermission) {
            abort(403, __('labels.permission_denied'));
        }

        $deliveryZones = DeliveryZone::query()
            ->select('id', 'name')
            ->orderBy('name')
            ->get();

        return view('admin.zones.preview.index', compact('deliveryZones'));
    }

    /**
     * Active home-page categories used to populate the Viewing dropdown.
     */
    public function homeCategories(): JsonResponse
    {
        try {
            if (! $this->viewPermission) {
                throw new AuthorizationException();
            }

            $categories = $this->zonePreviewService->homeCategories()
                ->map(fn($c) => ['id' => $c->id, 'title' => $c->title, 'slug' => $c->slug])
                ->values();

            return ApiResponseType::sendJsonResponse(
                success: true,
                message: __('labels.fetched_successfully'),
                data: $categories,
            );
        } catch (AuthorizationException) {
            return ApiResponseType::sendJsonResponse(
                success: false,
                message: __('labels.permission_denied'),
                data: [],
            );
        } catch (\Throwable $e) {
            Log::error('ZonePreviewController@homeCategories error: ' . $e->getMessage(), ['exception' => $e]);
            return ApiResponseType::sendJsonResponse(
                success: false,
                message: __('labels.something_went_wrong'),
                data: [],
            );
        }
    }

    /**
     * Categories a customer in the selected zone would see for the chosen viewing context.
     * parent_id null/absent → root categories. parent_id set → direct children of that category.
     */
    public function categories(Request $request): JsonResponse
    {
        try {
            if (! $this->viewPermission) {
                throw new AuthorizationException();
            }

            $request->validate([
                'zone_id' => 'required|integer|exists:delivery_zones,id',
                'parent_id' => 'sometimes|nullable|integer|exists:categories,id',
                'page' => 'sometimes|integer|min:1',
            ]);

            $zoneId = (int) $request->input('zone_id');
            $parentId = $request->filled('parent_id') ? (int) $request->input('parent_id') : null;
            $page = max(1, (int) $request->input('page', 1));
            $search = $request->filled('search') ? (string) $request->input('search') : null;

            $paginator = $this->zonePreviewService->availableCategories($zoneId, $parentId, $page, search: $search);

            $items = $paginator->getCollection()->map(fn($category) => [
                'id' => $category->id,
                'title' => $category->title,
                'slug' => $category->slug,
                'products_count' => $category->products_count ?? 0,
            ])->values();

            return ApiResponseType::sendJsonResponse(
                success: true,
                message: __('labels.fetched_successfully'),
                data: [
                    'current_page' => $paginator->currentPage(),
                    'last_page' => $paginator->lastPage(),
                    'per_page' => $paginator->perPage(),
                    'total' => $paginator->total(),
                    'data' => $items,
                ],
            );
        } catch (AuthorizationException) {
            return ApiResponseType::sendJsonResponse(
                success: false,
                message: __('labels.permission_denied'),
                data: [],
            );
        } catch (\Throwable $e) {
            Log::error('ZonePreviewController@categories error: ' . $e->getMessage(), ['exception' => $e]);
            return ApiResponseType::sendJsonResponse(
                success: false,
                message: __('labels.something_went_wrong'),
                data: [],
            );
        }
    }

    /**
     * Banners a customer in the selected zone would see for the chosen viewing context.
     */
    public function banners(Request $request): JsonResponse
    {
        try {
            if (! $this->viewPermission) {
                throw new AuthorizationException();
            }

            $request->validate([
                'zone_id' => 'required|integer|exists:delivery_zones,id',
                'category_id' => 'sometimes|nullable|integer|exists:categories,id',
                'page' => 'sometimes|integer|min:1',
                'search' => 'sometimes|nullable|string|max:120',
            ]);

            $zoneId = (int) $request->input('zone_id');
            $categoryId = $request->filled('category_id') ? (int) $request->input('category_id') : null;
            $page = max(1, (int) $request->input('page', 1));
            $search = $request->filled('search') ? (string) $request->input('search') : null;

            $paginator = $this->zonePreviewService->availableBanners($zoneId, $categoryId, $page, search: $search);

            $items = $paginator->getCollection()->map(fn($banner) => [
                'id' => $banner->id,
                'title' => $banner->title,
                'image' => $banner->banner_image ?: null,
                'type' => $banner->type,
                'position' => $banner->position,
                'visibility_status' => $banner->visibility_status,
                'zones' => $banner->zones->pluck('name')->all(),
            ])->values();

            return ApiResponseType::sendJsonResponse(
                success: true,
                message: __('labels.fetched_successfully'),
                data: [
                    'current_page' => $paginator->currentPage(),
                    'last_page' => $paginator->lastPage(),
                    'per_page' => $paginator->perPage(),
                    'total' => $paginator->total(),
                    'data' => $items,
                ],
            );
        } catch (AuthorizationException) {
            return ApiResponseType::sendJsonResponse(
                success: false,
                message: __('labels.permission_denied'),
                data: [],
            );
        } catch (\Throwable $e) {
            Log::error('ZonePreviewController@banners error: ' . $e->getMessage(), ['exception' => $e]);
            return ApiResponseType::sendJsonResponse(
                success: false,
                message: __('labels.something_went_wrong'),
                data: [],
            );
        }
    }

    /**
     * Featured sections a customer in the selected zone would see for the chosen viewing context.
     */
    public function featuredSections(Request $request): JsonResponse
    {
        try {
            if (! $this->viewPermission) {
                throw new AuthorizationException();
            }

            $request->validate([
                'zone_id' => 'required|integer|exists:delivery_zones,id',
                'category_id' => 'sometimes|nullable|integer|exists:categories,id',
                'page' => 'sometimes|integer|min:1',
                'search' => 'sometimes|nullable|string|max:120',
            ]);

            $zoneId = (int) $request->input('zone_id');
            $categoryId = $request->filled('category_id') ? (int) $request->input('category_id') : null;
            $page = max(1, (int) $request->input('page', 1));
            $search = $request->filled('search') ? (string) $request->input('search') : null;

            $paginator = $this->zonePreviewService->availableFeaturedSections($zoneId, $categoryId, $page, search: $search);

            $items = $paginator->getCollection()->map(fn($section) => [
                'id' => $section->id,
                'title' => $section->title,
                'slug' => $section->slug,
                'section_type' => $section->section_type,
                'status' => $section->status,
                'zones' => $section->zones->pluck('name')->all(),
            ])->values();

            return ApiResponseType::sendJsonResponse(
                success: true,
                message: __('labels.fetched_successfully'),
                data: [
                    'current_page' => $paginator->currentPage(),
                    'last_page' => $paginator->lastPage(),
                    'per_page' => $paginator->perPage(),
                    'total' => $paginator->total(),
                    'data' => $items,
                ],
            );
        } catch (AuthorizationException) {
            return ApiResponseType::sendJsonResponse(
                success: false,
                message: __('labels.permission_denied'),
                data: [],
            );
        } catch (\Throwable $e) {
            Log::error('ZonePreviewController@featuredSections error: ' . $e->getMessage(), ['exception' => $e]);
            return ApiResponseType::sendJsonResponse(
                success: false,
                message: __('labels.something_went_wrong'),
                data: [],
            );
        }
    }

    /**
     * Products a customer in the selected zone would see for the chosen viewing context.
     * category_id null/absent → all products reachable in the zone.
     * category_id set → products under that category (and its descendants).
     */
    public function products(Request $request): JsonResponse
    {
        try {
            if (! $this->viewPermission) {
                throw new AuthorizationException();
            }

            $request->validate([
                'zone_id' => 'required|integer|exists:delivery_zones,id',
                'category_id' => 'sometimes|nullable|integer|exists:categories,id',
                'page' => 'sometimes|integer|min:1',
                'search' => 'sometimes|nullable|string|max:120',
            ]);

            $zoneId = (int) $request->input('zone_id');
            $categoryId = $request->filled('category_id') ? (int) $request->input('category_id') : null;
            $page = max(1, (int) $request->input('page', 1));
            $search = $request->filled('search') ? (string) $request->input('search') : null;

            $paginator = $this->zonePreviewService->availableProducts($zoneId, $categoryId, $page, search: $search);

            $items = $paginator->getCollection()->map(fn($product) => [
                'id' => $product->id,
                'title' => $product->title,
                'slug' => $product->slug,
                'image' => $product->main_image ?? null,
            ])->values();

            return ApiResponseType::sendJsonResponse(
                success: true,
                message: __('labels.fetched_successfully'),
                data: [
                    'current_page' => $paginator->currentPage(),
                    'last_page' => $paginator->lastPage(),
                    'per_page' => $paginator->perPage(),
                    'total' => $paginator->total(),
                    'data' => $items,
                ],
            );
        } catch (AuthorizationException) {
            return ApiResponseType::sendJsonResponse(
                success: false,
                message: __('labels.permission_denied'),
                data: [],
            );
        } catch (\Throwable $e) {
            Log::error('ZonePreviewController@products error: ' . $e->getMessage(), ['exception' => $e]);
            return ApiResponseType::sendJsonResponse(
                success: false,
                message: __('labels.something_went_wrong'),
                data: [],
            );
        }
    }

    /**
     * Brands a customer in the selected zone would see for the chosen viewing context.
     * category_id null/absent → global (home) brands. category_id set → category-scoped brands.
     */
    public function brands(Request $request): JsonResponse
    {
        try {
            if (! $this->viewPermission) {
                throw new AuthorizationException();
            }

            $request->validate([
                'zone_id' => 'required|integer|exists:delivery_zones,id',
                'category_id' => 'sometimes|nullable|integer|exists:categories,id',
                'page' => 'sometimes|integer|min:1',
                'search' => 'sometimes|nullable|string|max:120',
            ]);

            $zoneId = (int) $request->input('zone_id');
            $categoryId = $request->filled('category_id') ? (int) $request->input('category_id') : null;
            $page = max(1, (int) $request->input('page', 1));
            $search = $request->filled('search') ? (string) $request->input('search') : null;

            $paginator = $this->zonePreviewService->availableBrands($zoneId, $categoryId, $page, search: $search);

            $items = $paginator->getCollection()->map(fn($brand) => [
                'id' => $brand->id,
                'title' => $brand->title,
                'slug' => $brand->slug,
                'logo' => $brand->logo ?? null,
                'products_count' => $brand->products_count ?? 0,
            ])->values();

            return ApiResponseType::sendJsonResponse(
                success: true,
                message: __('labels.fetched_successfully'),
                data: [
                    'current_page' => $paginator->currentPage(),
                    'last_page' => $paginator->lastPage(),
                    'per_page' => $paginator->perPage(),
                    'total' => $paginator->total(),
                    'data' => $items,
                ],
            );
        } catch (AuthorizationException) {
            return ApiResponseType::sendJsonResponse(
                success: false,
                message: __('labels.permission_denied'),
                data: [],
            );
        } catch (\Throwable $e) {
            Log::error('ZonePreviewController@brands error: ' . $e->getMessage(), ['exception' => $e]);
            return ApiResponseType::sendJsonResponse(
                success: false,
                message: __('labels.something_went_wrong'),
                data: [],
            );
        }
    }
}
