<?php

namespace App\Http\Controllers\Api;

use App\Enums\Category\CategorySubCategoryFilterEnum;
use App\Enums\CategoryStatusEnum;
use App\Enums\Product\ProductStatusEnum;
use App\Enums\Product\ProductVarificationStatusEnum;
use App\Http\Controllers\Controller;
use App\Http\Resources\CategoryResource;
use App\Models\Category;
use App\Models\HomeNavbarItem;
use App\Models\Product;
use App\Services\CategoryService;
use App\Services\DeliveryZoneService;
use App\Traits\ZoneAvailability;
use App\Types\Api\ApiResponseType;
use Dedoc\Scramble\Attributes\Group;
use Dedoc\Scramble\Attributes\QueryParameter;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

#[Group('Categories')]
class CategoryApiController extends Controller
{
    use ZoneAvailability;

    /**
     * Get categories base api.
     * If slug is not provided, returns root categories.
     */
    #[QueryParameter('page', description: 'Page number for pagination.', type: 'int', default: 1, example: 1)]
    #[QueryParameter('per_page', description: 'Number of categories per page', type: 'int', default: 15, example: 15)]
    #[QueryParameter('latitude', description: 'Latitude of the user location for zone-wise product counts', type: 'float', example: 23.11684540)]
    #[QueryParameter('longitude', description: 'Longitude of the user location for zone-wise product counts', type: 'float', example: 70.02805670)]
    #[QueryParameter('slug', description: 'Category slug to filter by', type: 'string', example: 'apple')]
    #[QueryParameter('search', description: 'Search term to filter categories', type: 'string', example: 'electronics')]
    #[QueryParameter('home', description: 'When true, returns only root categories marked as home categories, ordered by sort_order', type: 'boolean', example: true)]
    #[QueryParameter('include_no_product', description: 'When true, also return categories without product', type: 'boolean', example: 'false')]
    public function index(Request $request): JsonResponse
    {
        // Validate inputs
        $request->validate([
            'page' => 'sometimes|integer|min:1',
            'per_page' => 'sometimes|integer|min:1|max:100',
            'latitude' => 'nullable|numeric|between:-90,90',
            'longitude' => 'nullable|numeric|between:-180,180',
            'slug' => 'sometimes|string',
            'search' => 'sometimes|string',
            'home' => 'nullable',
        ]);

        $perPage = (int) $request->input('per_page', 15);
        $includeNoProduct = $request->input('include_no_product', false);
        $mainCategoryData = [];

        // Base query: either children of slug or root categories
        $query = Category::query()->with(['parent', 'searchLabelSetting'])->where('status', CategoryStatusEnum::ACTIVE());
        if ($request->has('search')) {
            $query->whereLocalizedTextLike((string) $request->input('search'));
        }
        $homeOnly = filter_var($request->input('home', false), FILTER_VALIDATE_BOOLEAN);

        if ($homeOnly) {
            // Force root categories and home-category filter when `home` is truthy
            $query->whereNull('parent_id')
                ->where('is_home_category', true);
        } elseif ($request->has('slug')) {
            $slug = $request->input('slug');
            $parentCategory = Category::with('searchLabelSetting')->where('slug', $slug)
                ->where('status', CategoryStatusEnum::ACTIVE())
                ->first();
            if ($parentCategory) {
                $mainCategoryData = [
                    'id' => $parentCategory->id,
                    'title' => $parentCategory->title,
                    'search_labels' => $parentCategory->resolveSearchLabels(),
                ];
                $query->where('parent_id', $parentCategory->id);
            } else {
                // Check if slug belongs to a custom navbar category
                $customNavbarItem = HomeNavbarItem::where('slug', $slug)
                    ->where('is_active', true)
                    ->first();
                if ($customNavbarItem) {
                    $mainCategoryData = [
                        'id' => $customNavbarItem->id,
                        'title' => $customNavbarItem->title,
                        'search_labels' => array_values($customNavbarItem->search_labels ?? []),
                    ];

                    return ApiResponseType::sendJsonResponse(true, 'labels.category_fetched_successfully', $this->emptyResponse($perPage, [
                        'main_category_data' => $mainCategoryData,
                    ]));
                }

                return ApiResponseType::sendJsonResponse(true, 'labels.category_fetched_successfully', $this->emptyResponse($perPage, [
                    'main_category_data' => [],
                ]));
            }
        } else {
            $query->whereNull('parent_id');
        }

        // Zone context and counts
        [$useZoneFilter, $storeIds] = $this->zoneContext(
            $request->input('latitude'),
            $request->input('longitude')
        );
        $this->applyProductsCount($query, $storeIds, $useZoneFilter);

        // Fetch and post-process
        if ($homeOnly) {
            $platform = $request->input('platform', 'app');
            // Fetch custom navbar categories (created without catalog products)
            $customCategories = HomeNavbarItem::query()
                ->with('media')
                ->where('is_active', true)
                ->forPlatform($platform)
                ->ordered($platform)
                ->get();

            // Fetch catalog home categories (if any are marked)
            $catalogHomeCategories = $query->ordered()->get();
            $catalogHomeProcessed = $this->aggregateDescendantProducts($catalogHomeCategories, fn ($c) => true, $useZoneFilter, $storeIds);
            if (! $includeNoProduct) {
                $catalogHomeProcessed = $this->filterNonZeroProducts($catalogHomeProcessed);
            }

            // Merge custom categories and catalog home categories and sort by sort_order
            $sortField = $platform === 'web' ? 'web_sort_order' : 'app_sort_order';
            $processed = $customCategories->concat($catalogHomeProcessed)->sortBy(function ($item) use ($sortField) {
                return $item->{$sortField} ?? $item->sort_order ?? 0;
            })->values();
        } else {
            $allCategories = $query->orderByTitle()->get();
            $predicate = $request->has('slug')
                ? function (Category $cat) {
                    return true;
                }
            : function (Category $cat) {
                return is_null($cat->parent_id);
            };

            $processed = $this->aggregateDescendantProducts($allCategories, $predicate, $useZoneFilter, $storeIds);

            if (! $includeNoProduct) {
                $processed = $this->filterNonZeroProducts($processed);
            }
        }

        // Paginate and respond
        $paginator = $this->paginateCollection($processed, (int) $request->input('page', 1), $perPage, $request);
        $response = ApiResponseType::responseFromPaginator($paginator);
        if ($request->has('slug')) {
            $response['main_category_data'] = $mainCategoryData;
        }

        return ApiResponseType::sendJsonResponse(true, 'labels.category_fetched_successfully', $response);
    }

    /**
     * Get sub-categories.
     */
    #[QueryParameter('page', description: 'Page number for pagination.', type: 'int', default: 1, example: 1)]
    #[QueryParameter('per_page', description: 'Number of categories per page', type: 'int', default: 15, example: 15)]
    #[QueryParameter('filter', description: 'Filter enum: random | top_category', type: 'string', example: 'random')]
    #[QueryParameter('latitude', description: 'Latitude of the user location for zone-wise product counts', type: 'float', example: 23.11684540)]
    #[QueryParameter('longitude', description: 'Longitude of the user location for zone-wise product counts', type: 'float', example: 70.02805670)]
    public function subCategories(Request $request): JsonResponse
    {
        // Validate inputs
        $request->validate([
            'page' => 'sometimes|integer|min:1',
            'per_page' => 'sometimes|integer|min:1|max:100',
            'latitude' => 'nullable|numeric|between:-90,90',
            'longitude' => 'nullable|numeric|between:-180,180',
            'filter' => 'nullable|string',
        ]);

        $perPage = (int) $request->input('per_page', 15);
        //        $filter = is_null($request->input('filter')) ? CategorySubCategoryFilterEnum::RANDOM() : $request->input('filter');
        $filter = $request->input('filter');

        $query = Category::query()
            ->with(['parent', 'searchLabelSetting'])
            ->whereNotNull('parent_id')
            ->where('status', CategoryStatusEnum::ACTIVE());

        if ($filter === CategorySubCategoryFilterEnum::TOP_CATEGORY()) {
            $topCategory = Category::query()
                ->whereNull('parent_id')
                ->where('status', CategoryStatusEnum::ACTIVE())
                ->first();
            if (! $topCategory) {
                return ApiResponseType::sendJsonResponse(true, 'labels.category_fetched_successfully', $this->emptyResponse($perPage, ['filter' => $filter]));
            }
            $query->where('parent_id', $topCategory->id);
        }

        // Zone context and counts
        [$useZoneFilter, $storeIds] = $this->zoneContext(
            $request->input('latitude'),
            $request->input('longitude')
        );
        $this->applyProductsCount($query, $storeIds, $useZoneFilter);

        // Ordering
        $filter === CategorySubCategoryFilterEnum::RANDOM()
            ? $query->inRandomOrder()
            : $query->orderByTitle();

        $allCategories = $query->get();

        $processed = $this->aggregateDescendantProducts($allCategories, function (Category $cat) {
            return ($cat->children_count ?? 0) > 0;
        }, $useZoneFilter, $storeIds);

        $filtered = $this->filterNonZeroProducts($processed);

        $paginator = $this->paginateCollection($filtered, (int) $request->input('page', 1), $perPage, $request);
        $response = array_merge(ApiResponseType::responseFromPaginator($paginator), ['filter' => $filter]);

        return ApiResponseType::sendJsonResponse(true, 'labels.category_fetched_successfully', $response);
    }

    /**
     * Get categories for sidebar filter.
     */
    #[QueryParameter('ids', description: 'Comma separated list of category IDs or array of IDs', type: 'string', example: '1,2,3')]
    #[QueryParameter('page', description: 'Page number for pagination.', type: 'int', default: 1, example: 1)]
    #[QueryParameter('per_page', description: 'Number of categories per page', type: 'int', default: 15, example: 15)]
    #[QueryParameter('latitude', description: 'Latitude of the user location for zone-wise product counts', type: 'float', example: 23.11684540)]
    #[QueryParameter('longitude', description: 'Longitude of the user location for zone-wise product counts', type: 'float', example: 70.02805670)]
    public function getCategories(Request $request): JsonResponse
    {
        // Normalize ids: accept CSV string or array
        $idsInput = $request->input('ids');
        if (is_string($idsInput)) {
            $ids = array_filter(array_map('trim', explode(',', $idsInput)), fn ($v) => $v !== '');
            $request->merge(['ids' => $ids]);
        }

        // Validate inputs
        $validated = $request->validate([
            'ids' => 'sometimes|array',
            'ids.*' => 'integer|min:1',
            'page' => 'sometimes|integer|min:1',
            'per_page' => 'sometimes|integer|min:1|max:100',
            'latitude' => 'nullable|numeric|between:-90,90',
            'longitude' => 'nullable|numeric|between:-180,180',
        ]);

        // Prepare IDs (optional)
        $ids = array_values(array_unique(array_map('intval', $validated['ids'] ?? [])));

        $perPage = (int) $request->input('per_page', 15);

        $query = Category::query()
            ->with(['parent', 'searchLabelSetting'])
            ->where('status', CategoryStatusEnum::ACTIVE());
        // If IDs were provided, limit to those
        if (! empty($ids)) {
            $query->whereIn('id', $ids);
        }

        // Zone context and counts
        [$useZoneFilter, $storeIds] = $this->zoneContext(
            $request->input('latitude'),
            $request->input('longitude')
        );
        $this->applyProductsCount($query, $storeIds, $useZoneFilter);

        // Fetch all. If IDs provided, keep their order; otherwise order by title
        if (! empty($ids)) {
            $allCategories = $query->get()->sortBy(function (Category $cat) use ($ids) {
                return array_search($cat->id, $ids);
            })->values();
        } else {
            $allCategories = $query->orderByTitle()->get();
        }

        // Aggregate full descendant product counts for root categories
        $processed = $this->aggregateDescendantProducts(
            $allCategories,
            function (Category $cat) {
                return is_null($cat->parent_id);
            },
            $useZoneFilter,
            $storeIds
        );

        $filtered = $this->filterNonZeroProducts($processed);

        // Paginate and respond
        $paginator = $this->paginateCollection($filtered, (int) $request->input('page', 1), $perPage, $request);
        $response = ApiResponseType::responseFromPaginator($paginator);

        return ApiResponseType::sendJsonResponse(true, 'labels.category_fetched_successfully', $response);
    }

    /**
     * Build an empty paginated-like response.
     */
    private function emptyResponse(int $perPage, array $extra = []): array
    {
        return array_merge([
            'current_page' => 1,
            'last_page' => 1,
            'per_page' => $perPage,
            'total' => 0,
            'data' => [],
        ], $extra);
    }

    /**
     * Determine zone context and store ids.
     *
     * @return array{0: bool, 1: array}
     */
    private function zoneContext($latitude, $longitude): array
    {
        $useZoneFilter = ! is_null($latitude) && ! is_null($longitude);
        if (! $useZoneFilter) {
            return [false, []];
        }
        $zoneInfo = DeliveryZoneService::getZonesAtPoint((float) $latitude, (float) $longitude);
        $storeIds = Product::getStoreIdsInZone($zoneInfo);

        return [true, $storeIds ?? []];
    }

    /**
     * Apply children and products_count withCount to a query.
     *
     * When $useZoneFilter is true, the count is always zone-scoped — so an
     * empty $storeIds (no stores in the zone) yields products_count = 0
     * instead of silently falling back to a global product count.
     */
    private function applyProductsCount(Builder $query, array $storeIds, bool $useZoneFilter = false): void
    {
        if ($useZoneFilter) {
            $query->withCount([
                'children',
                'products as products_count' => function ($q) use ($storeIds) {
                    if (empty($storeIds)) {
                        // Zone requested but no stores available — guarantee 0
                        $q->whereRaw('1 = 0');

                        return;
                    }
                    $q->where('verification_status', ProductVarificationStatusEnum::APPROVED())
                        ->where('status', ProductStatusEnum::ACTIVE())
                        ->whereHas('variants.storeProductVariants', function ($sq) use ($storeIds) {
                            $sq->whereIn('store_id', $storeIds);
                        });
                },
            ]);

            return;
        }
        $query->withCount(['children', 'products']);
    }

    /**
     * Recursively collect all active descendant category IDs below the given parent IDs.
     * The $visited set (seeded with the root category's own ID) prevents infinite loops
     * caused by accidental self-referencing or cyclic parent_id values in the data.
     *
     * @param  int[]  $parentIds  IDs whose children to fetch next
     * @param  int[]  $visited  IDs already seen — skip any child found here
     * @return int[]
     */
    private function collectAllDescendantIds(array $parentIds, array $visited = []): array
    {
        if (empty($parentIds)) {
            return [];
        }

        $childIds = Category::whereIn('parent_id', $parentIds)
            ->where('status', CategoryStatusEnum::ACTIVE())
            ->pluck('id')
            ->toArray();

        // Strip IDs already visited to guard against cycles
        $newIds = array_values(array_diff($childIds, $visited));

        if (empty($newIds)) {
            return [];
        }

        $updatedVisited = array_merge($visited, $newIds);

        return array_merge($newIds, $this->collectAllDescendantIds($newIds, $updatedVisited));
    }

    /**
     * For each category in the collection, add the full subtree product count when predicate matches.
     * Counts products from the category itself (already in products_count) plus ALL descendants.
     *
     * @param  Collection<int,Category>  $categories
     * @param  callable  $predicate  receives Category and returns bool to decide aggregation
     */
    private function aggregateDescendantProducts(Collection $categories, callable $predicate, bool $useZoneFilter, array $storeIds): Collection
    {
        return $categories->map(function (Category $cat) use ($predicate, $useZoneFilter, $storeIds) {
            if (! $predicate($cat)) {
                return $cat;
            }

            // Seed visited with the root ID itself to prevent self-reference cycles
            $allDescendantIds = $this->collectAllDescendantIds([$cat->id], [$cat->id]);

            if (empty($allDescendantIds)) {
                return $cat;
            }

            if ($useZoneFilter) {
                $additionalCount = Product::whereIn('category_id', $allDescendantIds)
                    ->where('verification_status', ProductVarificationStatusEnum::APPROVED())
                    ->where('status', ProductStatusEnum::ACTIVE())
                    ->whereHas('variants.storeProductVariants', function ($sq) use ($storeIds) {
                        $sq->whereIn('store_id', $storeIds);
                    })
                    ->count();
            } else {
                $additionalCount = Product::whereIn('category_id', $allDescendantIds)->count();
            }

            $cat->products_count = ($cat->products_count ?? 0) + $additionalCount;

            return $cat;
        });
    }

    /**
     * Keep categories with products_count > 0.
     *
     * @param  Collection<int,Category>  $categories
     */
    private function filterNonZeroProducts(Collection $categories): Collection
    {
        return $categories->filter(function ($cat) {
            return ($cat->products_count ?? 0) > 0;
        })->values();
    }

    /**
     * Paginate an in-memory collection and wrap items in CategoryResource / Custom category array.
     */
    private function paginateCollection(Collection $categories, int $page, int $perPage, Request $request): LengthAwarePaginator
    {
        $total = $categories->count();
        $items = $categories->slice(($page - 1) * $perPage, $perPage)->values();
        $resourceItems = $items->map(function ($cat) {
            if ($cat instanceof Category) {
                return new CategoryResource($cat);
            }
            if ($cat instanceof HomeNavbarItem) {
                return $this->transformNavbarItemToCategoryArray($cat);
            }

            return $cat;
        });

        $paginator = new LengthAwarePaginator(
            $resourceItems,
            $total,
            $perPage,
            $page,
            [
                'path' => $request->url(),
                'pageName' => 'page',
            ]
        );
        $paginator->appends($request->query());

        return $paginator;
    }

    /**
     * Transform a custom HomeNavbarItem into CategoryResource-compatible array structure.
     */
    private function transformNavbarItemToCategoryArray(HomeNavbarItem $item): array
    {
        $appearance = $item->resolved_home_appearance;
        $app = $appearance['app'] ?? [];
        $desktop = $appearance['desktop'] ?? [];
        $isColorBg = ($app['background_type'] ?? '') === 'gradient' || ($app['background_type'] ?? '') === 'color';
        $isImageBg = ($app['background_type'] ?? '') === 'image';
        $isAnimationBg = ($app['background_type'] ?? '') === 'lottie';
        $backgroundType = match ($app['background_type'] ?? 'none') {
            'gradient', 'color' => 'color',
            'lottie', 'animation' => 'animation',
            'image' => 'image',
            default => 'none',
        };

        return [
            'id' => $item->id,
            'title' => $item->title,
            'slug' => $item->slug,
            'image' => $item->app_icon ?: asset('assets/images/category-placeholder.png'),
            'icon' => $item->app_icon,
            'active_icon' => $item->app_active_icon,
            'background_type' => $backgroundType,
            'background_color' => $isColorBg ? ($app['gradient_start'] ?? '') : '',
            'background_color_web' => $isColorBg ? ($desktop['gradient_start'] ?? '') : '',
            'background_color_app' => $isColorBg ? ($app['gradient_start'] ?? '') : '',
            'background_image' => $isImageBg ? ($item->app_background_image ?: $item->desktop_background_image) : '',
            'background_image_web' => $isImageBg ? $item->desktop_background_image : '',
            'background_image_web_mobile' => $isImageBg ? $item->desktop_mobile_background_image : '',
            'background_image_app' => $isImageBg ? $item->app_background_image : '',
            'background_animation' => $isAnimationBg ? ($item->app_background_image ?: '') : '',
            'background_animation_web' => $isAnimationBg ? ($item->desktop_background_image ?: '') : '',
            'background_animation_app' => $isAnimationBg ? ($item->app_background_image ?: '') : '',
            'font_color' => $app['font_color'] ?? '',
            'search_labels' => array_values($item->search_labels ?? []),
            'parent_id' => null,
            'commission' => 0,
            'parent_slug' => null,
            'description' => '',
            'status' => $item->is_active ? 'active' : 'inactive',
            'requires_approval' => false,
            'metadata' => [
                'is_custom' => true,
                'home_appearance' => $appearance,
            ],
            'subcategory_count' => 0,
            'product_count' => 0,
            'enabled' => true,
        ];
    }

    /**
     * Search categories with zone.
     */
    #[QueryParameter('search', description: 'Search term to filter categories by name or description', type: 'string', example: 'electronics')]
    #[QueryParameter('per_page', description: 'Categories Per Page', type: 'int', default: 15, example: 15)]
    #[QueryParameter('zone_ids', description: 'Comma-separated list of zone IDs to filter categories', type: 'string', example: '1,2,3')]
    public function search(Request $request): JsonResponse
    {
        return $this->zoneSearch(
            request: $request,
            fetchPaginator: fn ($zoneIds, $search, $perPage) => app(CategoryService::class)->getAvailableByZoneIds($zoneIds, $search, $perPage),
            mapItem: fn ($item) => [
                'id' => $item->id,
                'value' => $item->id,
                'text' => $item->title,
                'image' => $item->image,
            ],
            successMessage: 'labels.categories_fetched_successfully',
            errorMessage: 'labels.error_fetching_categories',
        );
    }
}
