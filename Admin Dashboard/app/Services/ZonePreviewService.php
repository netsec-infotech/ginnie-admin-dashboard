<?php

namespace App\Services;

use App\Enums\CategoryStatusEnum;
use App\Enums\HomePageScopeEnum;
use App\Enums\Store\StoreVerificationStatusEnum;
use App\Enums\Store\StoreVisibilityStatusEnum;
use App\Enums\Product\ProductStatusEnum;
use App\Enums\Product\ProductVarificationStatusEnum;
use App\Models\Banner;
use App\Models\Brand;
use App\Models\Category;
use App\Models\FeaturedSection;
use App\Models\Product;
use App\Models\Store;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator as LengthAwarePaginatorImpl;
use Illuminate\Support\Collection;

/**
 * Read-only queries that mirror the customer experience for a given zone.
 * Each method delegates to the canonical service helper for that domain so
 * preview output stays in lockstep with the customer-facing API.
 */
class ZonePreviewService
{
    public function __construct(
        protected BrandService $brandService,
        protected CategoryService $categoryService,
        protected BannerService $bannerService,
        protected FeaturedSectionService $featuredSectionService,
    ) {}

    /**
     * IDs of stores that customers in the given zone can actually order from.
     */
    public function storeIdsInZone(int $zoneId): array
    {
        return Store::query()
            ->where('verification_status', StoreVerificationStatusEnum::APPROVED())
            ->where('visibility_status', StoreVisibilityStatusEnum::VISIBLE())
            ->whereHas('zones', fn(Builder $q) => $q->where('delivery_zones.id', $zoneId))
            ->pluck('id')
            ->all();
    }

    /**
     * Active zones the seller's approved + visible stores are attached to.
     * Used by the Visibility Inspector on the seller panel so a seller doesn't
     * see "Reachable in 1 of 9 zones" when 8 of those zones aren't theirs.
     */
    public function sellerZoneIds(int $sellerId): array
    {
        return \DB::table('store_zone')
            ->join('stores', 'stores.id', '=', 'store_zone.store_id')
            ->join('delivery_zones', 'delivery_zones.id', '=', 'store_zone.zone_id')
            ->where('stores.seller_id', $sellerId)
            ->where('stores.verification_status', StoreVerificationStatusEnum::APPROVED())
            ->where('stores.visibility_status', StoreVisibilityStatusEnum::VISIBLE())
            ->where('delivery_zones.status', \App\Enums\ActiveInactiveStatusEnum::ACTIVE())
            ->distinct()
            ->pluck('store_zone.zone_id')
            ->map(fn($id) => (int) $id)
            ->all();
    }

    /**
     * Active home-page categories used to populate the Viewing dropdown.
     * Same source the customer home page reads from (is_home_category=true root).
     */
    public function homeCategories(): Collection
    {
        return Category::query()
            ->select('id', 'title', 'slug')
            ->where('status', CategoryStatusEnum::ACTIVE())
            ->whereNull('parent_id')
            ->where('is_home_category', true)
            ->orderBy('title')
            ->get();
    }

    /**
     * Brands a customer in the zone would see for the given context.
     *
     * - $categoryId === null → home view: global-scope brands.
     * - $categoryId set      → category drill-down: brands scoped to that category.
     */
    public function availableBrands(int $zoneId, ?int $categoryId = null, int $page = 1, int $perPage = 24, ?string $search = null): LengthAwarePaginator
    {
        $storeIds = $this->storeIdsInZone($zoneId);

        $query = $this->brandService->availableInStoresQuery($storeIds);

        if ($categoryId) {
            Brand::scopeByCategory($query, $categoryId);
        } else {
            $query->where('scope_type', HomePageScopeEnum::GLOBAL());
        }

        $this->applyTitleSearch($query, $search);

        return $query->orderBy('title')->paginate($perPage, ['*'], 'page', $page);
    }

    /**
     * Categories a customer in the zone would see for the given context.
     *
     * - $parentId === null → root categories
     * - $parentId set      → direct children of that category
     *
     * Mirrors CategoryApiController::index: query → withCount (zone-restricted)
     * → roll up immediate children's product counts → drop zero-count rows
     * → in-memory paginate. The rollup ensures a parent with zero direct
     * products but stocked subcategories still appears, matching what the
     * customer sees on the home / category page.
     */
    public function availableCategories(int $zoneId, ?int $parentId = null, int $page = 1, int $perPage = 24, ?string $search = null): LengthAwarePaginator
    {
        $storeIds = $this->storeIdsInZone($zoneId);

        $query = $this->categoryService->availableInStoresQuery($storeIds);

        if (is_null($parentId)) {
            $query->whereNull('parent_id');
        } else {
            $query->where('parent_id', $parentId);
        }

        $this->applyTitleSearch($query, $search);

        $all = $query->orderBy('title')->get();
        $aggregated = $this->categoryService->aggregateImmediateChildrenProducts($all, $storeIds);
        $filtered = $aggregated->filter(fn(Category $c) => ($c->products_count ?? 0) > 0)->values();

        $items = $filtered->slice(($page - 1) * $perPage, $perPage)->values();

        return new LengthAwarePaginatorImpl(
            $items,
            $filtered->count(),
            $perPage,
            $page,
            ['path' => request()->url(), 'pageName' => 'page'],
        );
    }

    /**
     * Banners a customer in the zone would see for the given context.
     *
     * - $categoryId === null → home view: global-scope banners.
     * - $categoryId set      → category drill-down: banners scoped to that category.
     *
     * Reuses BannerService::availableInZoneStoresQuery() — same gate set as the
     * customer-facing BannerApiController. Layers on the scope filter +
     * ordering + pagination.
     */
    public function availableBanners(int $zoneId, ?int $categoryId = null, int $page = 1, int $perPage = 24, ?string $search = null): LengthAwarePaginator
    {
        $storeIds = $this->storeIdsInZone($zoneId);

        $query = $this->bannerService
            ->availableInZoneStoresQuery($zoneId, $storeIds)
            ->with('zones:id,name');

        if ($categoryId) {
            Banner::scopeByCategory($query, $categoryId);
        } else {
            $query->where('scope_type', HomePageScopeEnum::GLOBAL());
        }

        $this->applyTitleSearch($query, $search);

        return $query
            ->orderBy('position')
            ->orderBy('display_order')
            ->paginate($perPage, ['*'], 'page', $page);
    }

    /**
     * Featured sections a customer in the zone would see for the given context.
     *
     * - $categoryId === null → home view: global-scope featured sections.
     * - $categoryId set      → category drill-down: featured sections scoped to that category.
     *
     * Reuses FeaturedSectionService::availableInZoneQuery() so behavior tracks
     * the customer-facing paginateSections() flow.
     */
    public function availableFeaturedSections(int $zoneId, ?int $categoryId = null, int $page = 1, int $perPage = 24, ?string $search = null): LengthAwarePaginator
    {
        $query = $this->featuredSectionService
            ->availableInZoneQuery($zoneId)
            ->with('zones:id,name');

        if ($categoryId) {
            FeaturedSection::scopeByCategory($query, $categoryId);
        } else {
            $query->where('scope_type', HomePageScopeEnum::GLOBAL());
        }

        $this->applyTitleSearch($query, $search);

        return $query->paginate($perPage, ['*'], 'page', $page);
    }

    /**
     * Active+approved products with at least one variant stocked in a store
     * reachable in the zone. When $categoryId is set, restricts to that
     * category and its descendants — the same tree the customer browses.
     */
    public function availableProducts(int $zoneId, ?int $categoryId = null, int $page = 1, int $perPage = 24, ?string $search = null): LengthAwarePaginator
    {
        $storeIds = $this->storeIdsInZone($zoneId);

        $query = Product::query()
            ->where('status', ProductStatusEnum::ACTIVE())
            ->where('verification_status', ProductVarificationStatusEnum::APPROVED());

        if (empty($storeIds)) {
            return $query->whereRaw('1 = 0')->paginate($perPage, ['*'], 'page', $page);
        }

        $query->whereHas(
            'variants.storeProductVariants',
            fn(Builder $sq) => $sq->whereIn('store_id', $storeIds),
        );

        if ($categoryId) {
            $categoryIds = $this->categoryAndDescendantIds($categoryId);
            $query->whereIn('category_id', $categoryIds);
        }

        $this->applyTitleSearch($query, $search);

        return $query->orderBy('title')->paginate($perPage, ['*'], 'page', $page);
    }

    /**
     * Apply a case-insensitive title LIKE filter when a non-empty search term is given.
     * Shared by all available* methods.
     */
    protected function applyTitleSearch(Builder $query, ?string $search): void
    {
        $term = is_string($search) ? trim($search) : '';
        if ($term === '') {
            return;
        }
        $query->where('title', 'LIKE', '%' . $term . '%');
    }

    /**
     * Recursively collect category id + all descendant ids.
     */
    protected function categoryAndDescendantIds(int $categoryId): array
    {
        $ids = [$categoryId];
        $children = Category::query()
            ->where('parent_id', $categoryId)
            ->pluck('id')
            ->all();

        foreach ($children as $childId) {
            $ids = array_merge($ids, $this->categoryAndDescendantIds($childId));
        }

        return $ids;
    }
}
