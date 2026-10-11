<?php

namespace App\Services;

use App\Enums\ActiveInactiveStatusEnum;
use App\Enums\FeaturedSection\FeaturedSectionTypeEnum;
use App\Enums\SettingTypeEnum;
use App\Enums\Store\StoreVerificationStatusEnum;
use App\Enums\Store\StoreVisibilityStatusEnum;
use App\Models\Category;
use App\Models\FeaturedSection;
use App\Models\HomeSection;
use App\Models\Product;
use App\Models\Store;
use Illuminate\Contracts\Pagination\LengthAwarePaginator as LengthAwarePaginatorContract;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class FeaturedSectionService
{
    /**
     * Paginate active featured sections with optional filtering by category slug and type.
     * If latitude/longitude provided, attaches zone info validation and returns it alongside.
     *
     * @return array{sections: LengthAwarePaginatorContract, zone_info: array|null}
     */
    public function paginateSections(
        int $perPage = 15,
        ?string $sectionType = null,
        ?string $scopeCategorySlug = null,
        ?float $latitude = null,
        ?float $longitude = null
    ): array {
        $category = null;
        if (! empty($scopeCategorySlug)) {
            $category = Category::where('slug', $scopeCategorySlug)->first();
        }

        $zoneInfo = null;
        $zoneId = null;
        if (! is_null($latitude) && ! is_null($longitude)) {
            $zoneInfo = DeliveryZoneService::getZonesAtPoint($latitude, $longitude);
            if (! ($zoneInfo['exists'] ?? false)) {
                // Indicate non-existence with exists=false; caller can decide response
                return [
                    'sections' => new LengthAwarePaginator([], 0, $perPage),
                    'zone_info' => ['exists' => false],
                ];
            }
            $zoneId = $zoneInfo['zone_id'] ?? null;
        }

        if ($this->shouldUseHomeBuilderSections()) {
            return $this->paginateSectionsFromHomeBuilder($perPage, $sectionType, $scopeCategorySlug, $latitude, $longitude, $zoneInfo, $zoneId);
        }

        $query = $this->availableInZoneQuery($zoneId);

        if ($category) {
            $query = FeaturedSection::scopeByCategory($query, $category->id);
        } else {
            $query->where('scope_type', 'global');
        }

        if (! empty($sectionType)) {
            $query->byType($sectionType);
        }

        $sections = $query->paginate($perPage);

        return [
            'sections' => $sections,
            'zone_info' => $zoneInfo,
        ];
    }

    private function paginateSectionsFromHomeBuilder(
        int $perPage,
        ?string $sectionType,
        ?string $scopeCategorySlug,
        ?float $latitude,
        ?float $longitude,
        ?array $zoneInfo,
        ?int $zoneId
    ): array {
        $category = null;
        if (! empty($scopeCategorySlug)) {
            $category = Category::where('slug', $scopeCategorySlug)->first();
        }

        $query = HomeSection::query()
            ->where('type', 'products')
            ->where('is_active', true)
            ->whereHas('layout', function (Builder $q) use ($category) {
                $q->where('is_active', true);
                if ($category) {
                    $q->where('scope_type', 'category')->where('scope_id', $category->id);
                } else {
                    $q->where('scope_type', 'global');
                }
            })
            ->where(function (Builder $q) use ($zoneId) {
                $q->whereDoesntHave('zones')
                    ->orWhereHas('zones', function (Builder $zq) use ($zoneId) {
                        if ($zoneId) {
                            $zq->where('delivery_zones.id', $zoneId);
                        }
                    });
            });

        if (! empty($sectionType)) {
            $query->where(function (Builder $q) use ($sectionType) {
                if ($sectionType === 'custom_products') {
                    $q->where('config->source', 'custom');
                } else {
                    $q->where('config->source', $sectionType);
                }
            });
        }

        $homeSections = $query->with(['layout', 'zones', 'items'])->orderBy('sort_order')->paginate($perPage);

        $adaptedCollection = $homeSections->getCollection()->map(function ($hs) {
            return $this->adaptHomeSectionToFeaturedSection($hs);
        });

        $homeSections->setCollection($adaptedCollection);

        return [
            'sections' => $homeSections,
            'zone_info' => $zoneInfo,
        ];
    }

    public function adaptHomeSectionToFeaturedSection(HomeSection $s): FeaturedSection
    {
        $source = $s->config['source'] ?? 'newly_added';
        $sectionType = $source === 'custom' ? 'custom_products' : $source;

        $fs = new FeaturedSection;
        $fs->forceFill([
            'id' => $s->id,
            'title' => $s->title,
            'slug' => Str::slug($s->title ?: 'section-'.$s->id),
            'short_description' => '',
            'style' => $s->style ?: 'horizontal_carousel',
            'section_type' => $sectionType,
            'sort_order' => $s->sort_order,
            'status' => $s->is_active ? 'active' : 'inactive',
            'scope_type' => $s->layout->scope_type ?? 'global',
            'scope_id' => $s->layout->scope_id ?? null,
            'background_type' => $s->config['background_type'] ?? 'color',
            'background_color' => $s->config['background_color'] ?? '#ffffff',
            'text_color' => $s->config['text_color'] ?? '#000000',
            'created_at' => $s->created_at,
            'updated_at' => $s->updated_at,
        ]);

        if ($s->relationLoaded('layout') && $s->layout) {
            if ($s->layout->scope_type === 'category' && $s->layout->scope_id) {
                $scopeCategory = Category::find($s->layout->scope_id);
                $fs->setRelation('scopeCategory', $scopeCategory);
            }
        }

        if ($s->relationLoaded('zones')) {
            $fs->setRelation('zones', $s->zones);
        } else {
            $fs->setRelation('zones', $s->zones()->get());
        }

        if ($source === 'custom') {
            $productIds = $s->items()->where('purpose', 'display')->where('itemable_type', Product::class)->orderBy('sort_order')->pluck('itemable_id');
            $products = Product::whereIn('id', $productIds)->get();
            $fs->setRelation('products', $products);
        }

        $categoryIds = $s->items()->where('purpose', 'filter')->where('itemable_type', Category::class)->orderBy('sort_order')->pluck('itemable_id');
        if ($categoryIds->isNotEmpty()) {
            $categories = Category::whereIn('id', $categoryIds)->get();
            $fs->setRelation('categories', $categories);
        } else {
            $fs->setRelation('categories', collect());
        }

        return $fs;
    }

    /**
     * Canonical zone-aware base query for featured sections.
     *
     * Returns active featured sections, ordered, gated by the direct zone pivot
     * (empty pivot = visible everywhere). Caller adds the scope filter
     * (global vs category-scoped), section type, and pagination.
     *
     * Shared by paginateSections() (customer API) + the admin Zone Preview so
     * any tweak applies in one place.
     */
    public function availableInZoneQuery(?int $zoneId): Builder
    {
        return FeaturedSection::active()
            ->ordered()
            ->with(['categories', 'products', 'scopeCategory'])
            ->availableInZone($zoneId);
    }

    /**
     * Find an active featured section by slug.
     */
    public function findActiveBySlug(string $slug, ?int $zoneId = null): ?FeaturedSection
    {
        if ($this->shouldUseHomeBuilderSections()) {
            $homeSections = HomeSection::with(['layout', 'zones', 'items'])->where('type', 'products')->where('is_active', true)->get();
            foreach ($homeSections as $hs) {
                if (! $hs->layout || ! $hs->layout->is_active) {
                    continue;
                }
                if ($zoneId && $hs->zones->isNotEmpty() && ! $hs->zones->contains('id', $zoneId)) {
                    continue;
                }
                $adapted = $this->adaptHomeSectionToFeaturedSection($hs);
                if ($adapted->slug === $slug) {
                    return $adapted;
                }
            }
        }

        return FeaturedSection::active()
            ->with(['categories', 'products', 'scopeCategory'])
            ->availableInZone($zoneId)
            ->where('slug', $slug)
            ->first();
    }

    private function shouldUseHomeBuilderSections(): bool
    {
        $settings = app(SettingService::class)->getSettingValues(SettingTypeEnum::SYSTEM());

        return ($settings['layoutType'] ?? 'featured_section') === 'home_builder'
            && Schema::hasTable('home_sections')
            && HomeSection::where('type', 'products')->where('is_active', true)->exists();
    }

    /**
     * Get available featured sections for the given zone IDs with optional search and pagination.
     *
     * A featured section is considered available when at least one active product from the section
     * is available in an approved and visible store assigned to any of the provided zones.
     */
    public function getAvailableFeaturedByZoneIds(array $zoneIds, ?string $search = null, int $perPage = 15): LengthAwarePaginatorContract
    {
        $zoneIds = array_values(array_unique(array_filter(array_map('intval', $zoneIds))));

        $storeQuery = Store::query()
            ->where('verification_status', StoreVerificationStatusEnum::APPROVED())
            ->where('visibility_status', StoreVisibilityStatusEnum::VISIBLE());

        if (! empty($zoneIds)) {
            $storeQuery->whereHas('zones', function (Builder $query) use ($zoneIds) {
                $query->whereIn('delivery_zones.id', $zoneIds);
            });
        }

        $storeIds = $storeQuery->pluck('id')->toArray();

        if (empty($storeIds)) {
            return $this->emptyPaginator($perPage);
        }

        $query = FeaturedSection::query()
            ->active()
            ->ordered()
            ->with(['categories', 'products', 'scopeCategory'])
            ->where(function (Builder $featuredSectionQuery) use ($storeIds) {
                $featuredSectionQuery
                    ->where(function (Builder $manualProductsQuery) use ($storeIds) {
                        $manualProductsQuery
                            ->where('section_type', FeaturedSectionTypeEnum::CUSTOM_PRODUCTS())
                            ->whereHas('products', function (Builder $productQuery) use ($storeIds) {
                                $this->applyAvailableProductsConstraint($productQuery, $storeIds);
                            });
                    })
                    ->orWhere(function (Builder $categoryBasedQuery) use ($storeIds) {
                        $categoryBasedQuery
                            ->where('section_type', '!=', FeaturedSectionTypeEnum::CUSTOM_PRODUCTS())
                            ->where(function (Builder $categoryQuery) use ($storeIds) {
                                $categoryQuery
                                    ->whereHas('categories.products', function (Builder $productQuery) use ($storeIds) {
                                        $this->applyAvailableProductsConstraint($productQuery, $storeIds);
                                    })
                                    ->orWhere(function (Builder $scopedCategoryQuery) use ($storeIds) {
                                        $scopedCategoryQuery
                                            ->where('scope_type', 'category')
                                            ->whereHas('scopeCategory.products', function (Builder $productQuery) use ($storeIds) {
                                                $this->applyAvailableProductsConstraint($productQuery, $storeIds);
                                            });
                                    });
                            });
                    });
            });

        if (! empty($search)) {
            $query->where(function (Builder $searchQuery) use ($search) {
                $searchQuery
                    ->where('title', 'LIKE', "%{$search}%")
                    ->orWhere('short_description', 'LIKE', "%{$search}%")
                    ->orWhere('slug', 'LIKE', "%{$search}%")
                    ->orWhereHas('categories', function (Builder $categoryQuery) use ($search) {
                        $categoryQuery->where('title', 'LIKE', "%{$search}%");
                    })
                    ->orWhereHas('products', function (Builder $productQuery) use ($search) {
                        $productQuery->where('title', 'LIKE', "%{$search}%");
                    })
                    ->orWhereHas('scopeCategory', function (Builder $categoryQuery) use ($search) {
                        $categoryQuery->where('title', 'LIKE', "%{$search}%");
                    });
            });
        }

        return $query->paginate($perPage);
    }

    /**
     * Get products list for a section with optional geo filtering, sorting and attribute/category/brand filters.
     * Returns paginator and meta arrays for category_ids and brand_ids.
     *
     * @param  array{sort?: string|null, per_page?: int, latitude?: float|null, longitude?: float|null, categories?: array<int,string>|null, brands?: array<int,string>|null, attribute_values?: array<int,int>|null}  $options
     * @return array{products: LengthAwarePaginatorContract, category_ids: array<int,int>, brand_ids: array<int,int>, zone_info: array|null}
     */
    public function getProductsForSection(FeaturedSection $section, array $options): array
    {
        $perPage = (int) ($options['per_page'] ?? 15);
        $sort = $options['sort'] ?? null;
        $latitude = $options['latitude'] ?? null;
        $longitude = $options['longitude'] ?? null;
        $categories = $options['categories'] ?? null; // slugs
        $brands = $options['brands'] ?? null; // slugs
        $attributeValues = $options['attribute_values'] ?? null; // global attribute value IDs

        if (! is_null($latitude) && ! is_null($longitude)) {
            $zoneInfo = DeliveryZoneService::getZonesAtPoint($latitude, $longitude);
            if (! ($zoneInfo['exists'] ?? false)) {
                return [
                    'products' => $this->emptyPaginator($perPage),
                    'category_ids' => [],
                    'brand_ids' => [],
                    'zone_info' => ['exists' => false],
                ];
            }

            $storeIds = Store::whereHas('zones', function ($q) use ($zoneInfo) {
                $q->where('delivery_zones.id', $zoneInfo['zone_id']);
            })
                ->where('verification_status', StoreVerificationStatusEnum::APPROVED())
                ->where('visibility_status', StoreVisibilityStatusEnum::VISIBLE())
                ->pluck('id')
                ->toArray();

            $productsQuery = $section->getProductsQuery($sort, $storeIds);
            $productsQuery
                ->with([
                    'variants' => function ($q) use ($storeIds) {
                        $q->whereHas('storeProductVariants', function ($sq) use ($storeIds) {
                            $sq->whereIn('store_id', $storeIds);
                        });
                    },
                    'variants.storeProductVariants' => function ($q) use ($storeIds) {
                        $q->whereIn('store_id', $storeIds);
                    },
                    'variants.storeProductVariants.store',
                    // Store-scoped add-on attachments so ProductVariantResource can expose `addon_groups`.
                    // Availability/price/cost/stock live on `store_addon_items` — the resource pulls
                    // them from there and filters out unavailable rows at render time.
                    'variants.storeVariantAddons' => function ($q) use ($storeIds) {
                        $q->whereIn('store_id', $storeIds);
                    },
                    'variants.storeVariantAddons.addonGroup',
                    'variants.storeVariantAddons.addonItem',
                ])
                ->whereHas('variants.storeProductVariants', function ($q) use ($storeIds) {
                    $q->whereIn('store_id', $storeIds);
                });

            $productsQuery->where('status', ActiveInactiveStatusEnum::ACTIVE());

            if (! empty($attributeValues)) {
                $productsQuery->whereHas('variantAttributes', function ($q) use ($attributeValues) {
                    $q->whereIn('global_attribute_value_id', $attributeValues);
                });
            }

            // Collect unique category IDs BEFORE applying category/brand slug filters (to mirror controller logic)
            $categoryIds = (clone $productsQuery)->distinct()->pluck('category_id')->filter()->unique()->map(fn ($id) => (int) $id)->values()->toArray();

            if (! empty($categories)) {
                $productsQuery->whereHas('category', function ($q) use ($categories) {
                    $q->whereIn('slug', $categories);
                });
            }

            if (! empty($brands)) {
                $productsQuery->whereHas('brand', function ($q) use ($brands) {
                    $q->whereIn('slug', $brands);
                });
            }

            // After slug filters, compute brand ids
            $brandIds = (clone $productsQuery)->distinct()->pluck('brand_id')->filter()->unique()->map(fn ($id) => (int) $id)->values()->toArray();

            $products = $productsQuery->paginate($perPage);

            foreach ($products as $product) {
                $product->user_latitude = $latitude;
                $product->user_longitude = $longitude;
                $product->zone_info = $zoneInfo;
                $product->preferNearestStoreVariants($latitude, $longitude);
            }

            return [
                'products' => $products,
                'category_ids' => $categoryIds,
                'brand_ids' => $brandIds,
                'zone_info' => $zoneInfo,
            ];
        }

        // Non-geo flow
        $productsQuery = $section->getProductsQuery($sort);
        $productsQuery->where('status', ActiveInactiveStatusEnum::ACTIVE());

        if (! empty($attributeValues)) {
            $productsQuery->whereHas('variantAttributes', function ($q) use ($attributeValues) {
                $q->whereIn('global_attribute_value_id', $attributeValues);
            });
        }

        $categoryIds = (clone $productsQuery)->distinct()->pluck('category_id')->filter()->unique()->map(fn ($id) => (int) $id)->values()->toArray();
        $brandIds = (clone $productsQuery)->distinct()->pluck('brand_id')->filter()->unique()->map(fn ($id) => (int) $id)->values()->toArray();

        if (! empty($categories)) {
            $productsQuery->whereHas('category', function ($q) use ($categories) {
                $q->whereIn('slug', $categories);
            });
        }

        if (! empty($brands)) {
            $productsQuery->whereHas('brand', function ($q) use ($brands) {
                $q->whereIn('slug', $brands);
            });
        }

        $products = $productsQuery->paginate($perPage);

        return [
            'products' => $products,
            'category_ids' => $categoryIds,
            'brand_ids' => $brandIds,
            'zone_info' => null,
        ];
    }

    /**
     * Create an empty paginator-like object using existing ProductService util if present,
     * else a simple LengthAwarePaginator with empty items.
     */
    protected function emptyPaginator(int $perPage = 15): LengthAwarePaginatorContract
    {
        // Standalone empty paginator
        return new LengthAwarePaginator(
            collect([]),
            0,
            $perPage,
            1,
            ['path' => request()->url(), 'query' => request()->query()]
        );
    }

    /**
     * Apply availability constraints for products that can be sold by the given stores.
     */
    protected function applyAvailableProductsConstraint(Builder $query, array $storeIds): void
    {
        $query
            ->where('status', ActiveInactiveStatusEnum::ACTIVE())
            ->whereHas('variants.storeProductVariants', function (Builder $storeProductVariantQuery) use ($storeIds) {
                $storeProductVariantQuery->whereIn('store_id', $storeIds);
            });
    }
}
