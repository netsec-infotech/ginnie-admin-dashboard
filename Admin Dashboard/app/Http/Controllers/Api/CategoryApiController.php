<?php

namespace App\Http\Controllers\Api;

use App\Enums\CategoryStatusEnum;
use App\Enums\Product\ProductStatusEnum;
use App\Enums\Product\ProductVarificationStatusEnum;
use App\Http\Controllers\Controller;
use App\Http\Resources\CategoryResource;
use App\Models\Category;
use App\Models\Product;
use App\Services\CategoryService;
use App\Services\DeliveryZoneService;
use App\Enums\Category\CategorySubCategoryFilterEnum;
use App\Traits\ZoneAvailability;
use Dedoc\Scramble\Attributes\Group;
use Dedoc\Scramble\Attributes\QueryParameter;
use Illuminate\Http\Request;
use App\Types\Api\ApiResponseType;
use Illuminate\Http\JsonResponse;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
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

        $perPage = (int)$request->input('per_page', 15);
        $includeNoProduct = $request->input('include_no_product', false);
        $mainCategoryData = [];

        // Base query: either children of slug or root categories
        $query = Category::query()->with(['parent', 'searchLabelSetting'])->where('status', CategoryStatusEnum::ACTIVE());
        if ($request->has('search')) {
            $query->where('title', 'like', '%' . $request->input('search') . '%');
        }
        $homeOnly = filter_var($request->input('home', false), FILTER_VALIDATE_BOOLEAN);

        if ($homeOnly) {
            // Force root categories and home-category filter when `home` is truthy
            $query->whereNull('parent_id')
                ->where('is_home_category', true);
        } elseif ($request->has('slug')) {
            $parentCategory = Category::with('searchLabelSetting')->where('slug', $request->input('slug'))
                ->where('status', CategoryStatusEnum::ACTIVE())
                ->first();
            if (!$parentCategory) {
                return ApiResponseType::sendJsonResponse(true, 'labels.category_fetched_successfully', $this->emptyResponse($perPage, [
                    'main_category_data' => [],
                ]));
            }
            $mainCategoryData = [
                'id' => $parentCategory->id,
                'title' => $parentCategory->title,
                'search_labels' => $parentCategory->resolveSearchLabels(),
            ];
            $query->where('parent_id', $parentCategory->id);
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
            // Home categories should be ordered by configured sort_order
            $allCategories = $query->ordered()->get();
        } else {
            $allCategories = $query->orderBy('title')->get();
        }
        // When listing root categories, aggregate children's product counts only for root items.
        // When a slug is provided (listing children of a specific category), aggregate for all
        // returned categories so their immediate children's products are included as well.
        $predicate = $request->has('slug')
            ? function (Category $cat) {
                return true;
            }
            : function (Category $cat) {
                return is_null($cat->parent_id);
            };

        $processed = $this->aggregateDescendantProducts($allCategories, $predicate, $useZoneFilter, $storeIds);

        if (!$includeNoProduct) {
            $processed = $this->filterNonZeroProducts($processed);
        }

        // Paginate and respond
        $paginator = $this->paginateCollection($processed, (int)$request->input('page', 1), $perPage, $request);
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

        $perPage = (int)$request->input('per_page', 15);
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
            if (!$topCategory) {
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
            : $query->orderBy('title');

        $allCategories = $query->get();

        $processed = $this->aggregateDescendantProducts($allCategories, function (Category $cat) {
            return ($cat->children_count ?? 0) > 0;
        }, $useZoneFilter, $storeIds);

        $filtered = $this->filterNonZeroProducts($processed);

        $paginator = $this->paginateCollection($filtered, (int)$request->input('page', 1), $perPage, $request);
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
            $ids = array_filter(array_map('trim', explode(',', $idsInput)), fn($v) => $v !== '');
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

        $perPage = (int)$request->input('per_page', 15);

        $query = Category::query()
            ->with(['parent', 'searchLabelSetting'])
            ->where('status', CategoryStatusEnum::ACTIVE());
        // If IDs were provided, limit to those
        if (!empty($ids)) {
            $query->whereIn('id', $ids);
        }

        // Zone context and counts
        [$useZoneFilter, $storeIds] = $this->zoneContext(
            $request->input('latitude'),
            $request->input('longitude')
        );
        $this->applyProductsCount($query, $storeIds, $useZoneFilter);

        // Fetch all. If IDs provided, keep their order; otherwise order by title
        if (!empty($ids)) {
            $allCategories = $query->get()->sortBy(function (Category $cat) use ($ids) {
                return array_search($cat->id, $ids);
            })->values();
        } else {
            $allCategories = $query->orderBy('title')->get();
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
        $paginator = $this->paginateCollection($filtered, (int)$request->input('page', 1), $perPage, $request);
        $response = ApiResponseType::responseFromPaginator($paginator);
        return ApiResponseType::sendJsonResponse(true, 'labels.category_fetched_successfully', $response);
    }

    /**
     * Build an empty paginated-like response.
     *
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
     * @return array{0: bool, 1: array}
     */
    private function zoneContext($latitude, $longitude): array
    {
        $useZoneFilter = !is_null($latitude) && !is_null($longitude);
        if (!$useZoneFilter) {
            return [false, []];
        }
        $zoneInfo = DeliveryZoneService::getZonesAtPoint((float)$latitude, (float)$longitude);
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
                }
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
     * @param  int[] $parentIds  IDs whose children to fetch next
     * @param  int[] $visited    IDs already seen — skip any child found here
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
     * @param Collection<int,Category> $categories
     * @param callable $predicate receives Category and returns bool to decide aggregation
     */
    private function aggregateDescendantProducts(Collection $categories, callable $predicate, bool $useZoneFilter, array $storeIds): Collection
    {
        return $categories->map(function (Category $cat) use ($predicate, $useZoneFilter, $storeIds) {
            if (!$predicate($cat)) {
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
     * @param Collection<int,Category> $categories
     */
    private function filterNonZeroProducts(Collection $categories): Collection
    {
        return $categories->filter(function ($cat) {
            return ($cat->products_count ?? 0) > 0;
        })->values();
    }

    /**
     * Paginate an in-memory collection and wrap items in CategoryResource.
     */
    private function paginateCollection(Collection $categories, int $page, int $perPage, Request $request): LengthAwarePaginator
    {
        $total = $categories->count();
        $items = $categories->slice(($page - 1) * $perPage, $perPage)->values();
        $resourceItems = $items->map(fn($cat) => new CategoryResource($cat));

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
     * Search categories with zone.
     */
    #[QueryParameter('search', description: 'Search term to filter categories by name or description', type: 'string', example: 'electronics')]
    #[QueryParameter('per_page', description: 'Categories Per Page', type: 'int', default: 15, example: 15)]
    #[QueryParameter('zone_ids', description: 'Comma-separated list of zone IDs to filter categories', type: 'string', example: '1,2,3')]
    public function search(Request $request): JsonResponse
    {
        return $this->zoneSearch(
            request:        $request,
            fetchPaginator: fn($zoneIds, $search, $perPage) =>
            app(CategoryService::class)->getAvailableByZoneIds($zoneIds, $search, $perPage),
            mapItem:        fn($item) => [
                'id'    => $item->id,
                'value' => $item->id,
                'text'  => $item->title,
                'image' => $item->image,
            ],
            successMessage: 'labels.categories_fetched_successfully',
            errorMessage:   'labels.error_fetching_categories',
        );
    }
}
