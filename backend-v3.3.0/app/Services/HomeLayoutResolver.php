<?php

namespace App\Services;

use App\Enums\BrandStatusEnum;
use App\Enums\CategoryStatusEnum;
use App\Enums\HomePage\HomeSectionTypeEnum;
use App\Enums\Product\ProductStatusEnum;
use App\Enums\Product\ProductVarificationStatusEnum;
use App\Enums\Store\StoreVerificationStatusEnum;
use App\Enums\Store\StoreVisibilityStatusEnum;
use App\Http\Resources\BrandResource;
use App\Http\Resources\CategoryResource;
use App\Http\Resources\Product\ProductListResource;
use App\Http\Resources\WatchBuy\Customer\CustomerWatchBuyProfileStatusGroupResource;
use App\Http\Resources\WatchBuy\Customer\CustomerWatchBuyReelResource;
use App\Models\Brand;
use App\Models\Category;
use App\Models\DeliveryZone;
use App\Models\HomeLayout;
use App\Models\HomeSection;
use App\Models\Product;
use App\Models\Store;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class HomeLayoutResolver
{
    private const SORT_MAP = [
        'newly_added' => 'latest',
        'top_rated' => 'ratings',
        'best_seller' => 'best_seller',
        'featured' => 'featured',
        'recommended' => 'recommended',
    ];

    private const DEFAULT_LIMIT = 12;

    /**
     * Resolve the active layout following strict hierarchy:
     * 1. Zone + Category layout (scope_type = zone_category, zone_id = X, scope_id = Y)
     * 2. Zone Main layout (scope_type = zone, zone_id = X, scope_id = null)
     * 3. Global Category layout (scope_type = category, zone_id = null, scope_id = Y)
     * 4. Global System layout (scope_type = global, zone_id = null, scope_id = null)
     */
    public function resolveLayoutForCustomer(?DeliveryZone $zone, ?int $categoryId = null, string $platform = 'app'): ?HomeLayout
    {
        $zoneId = $zone?->id;

        // 1. Zone + Category
        if ($zoneId && $categoryId) {
            $layout = HomeLayout::query()
                ->where('platform', $platform)
                ->where('scope_type', 'zone_category')
                ->where('zone_id', $zoneId)
                ->where('scope_id', $categoryId)
                ->where('is_active', true)
                ->first();
            if ($layout) {
                return $layout;
            }
        }

        // 2. Global Category Layout (when categoryId requested)
        if ($categoryId) {
            $layout = HomeLayout::query()
                ->where('platform', $platform)
                ->where('scope_type', 'category')
                ->where('scope_id', $categoryId)
                ->where('is_active', true)
                ->first();
            if ($layout) {
                return $layout;
            }
        }

        // 3. Zone Main Layout
        if ($zoneId) {
            $layout = HomeLayout::query()
                ->where('platform', $platform)
                ->where('scope_type', 'zone')
                ->where('zone_id', $zoneId)
                ->where('is_active', true)
                ->first();
            if ($layout) {
                return $layout;
            }
        }

        // 4. Global System Layout (Fallback)
        $globalLayout = HomeLayout::query()
            ->where('platform', $platform)
            ->where('scope_type', 'global')
            ->where('is_active', true)
            ->first();

        return $globalLayout;
    }

    public function present(HomeLayout $layout, ?DeliveryZone $zone, int $perPage, int $page, ?float $lat = null, ?float $lng = null, ?User $user = null): array
    {
        $layoutSections = $layout->sections
            ->filter(fn(HomeSection $s) => $s->is_active && $this->visibleInZone($s, $zone));

        // If rendering a custom zone layout, also merge active Global Layout sections specifically assigned to this zone via pivot
        if ($zone && $layout->scope_type === 'zone') {
            $globalZoneSections = HomeSection::query()
                ->whereHas('layout', fn($q) => $q->where('platform', $layout->platform)->where('scope_type', 'global')->where('is_active', true))
                ->whereHas('zones', fn($q) => $q->where('delivery_zones.id', $zone->id))
                ->where('is_active', true)
                ->with(['media', 'items' => fn($q) => $q->orderBy('sort_order'), 'items.itemable', 'items.media', 'zones:id'])
                ->get();

            $existingIds = $layoutSections->pluck('id')->filter()->toArray();
            $additionalSections = $globalZoneSections->reject(fn(HomeSection $s) => in_array($s->id, $existingIds, true));
            $sections = $layoutSections->concat($additionalSections)->sortBy('sort_order')->values();
        } else {
            $sections = $layoutSections->sortBy('sort_order')->values();
        }

        $total = $sections->count();

        $pageSections = $sections->forPage($page, $perPage)->values()
            ->map(fn(HomeSection $s) => $this->presentSection($s, $zone, $lat, $lng, $user))
            ->all();

        return [
            'scope_type' => $layout->scope_type,
            'scope_id' => $layout->scope_id,
            'current_page' => $page,
            'last_page' => (int) max(1, ceil($total / max(1, $perPage))),
            'per_page' => $perPage,
            'total' => $total,
            'sections' => $pageSections,
        ];
    }

    private function visibleInZone(HomeSection $section, ?DeliveryZone $zone): bool
    {
        if (! $zone || $section->zones->isEmpty()) {
            return true;
        }

        return $section->zones->contains('id', $zone->id);
    }

    private function presentSection(HomeSection $section, ?DeliveryZone $zone, ?float $lat = null, ?float $lng = null, ?User $user = null): array
    {
        $config = $section->config ?? [];
        $typeVal = (is_object($section->type) && property_exists($section->type, 'value')) ? $section->type->value : (string)$section->type;
        $isHero = ($section->type === HomeSectionTypeEnum::HERO || $typeVal === 'hero');
        $isBanner = ($section->type === HomeSectionTypeEnum::BANNERS || $typeVal === 'banners');
        $isPlayImage = ($section->type === HomeSectionTypeEnum::PLAY_IMAGE || $typeVal === 'play_image');
        $isProducts = ($section->type === HomeSectionTypeEnum::PRODUCTS || $typeVal === 'products');
        $isCategories = ($section->type === HomeSectionTypeEnum::CATEGORIES || $typeVal === 'categories');
        $isBrands = ($section->type === HomeSectionTypeEnum::BRANDS || $typeVal === 'brands');
        $isStories = ($section->type === HomeSectionTypeEnum::STORIES || $typeVal === 'stories');
        $isReels = ($section->type === HomeSectionTypeEnum::REELS || $typeVal === 'reels');

        if ($isReels) {
            unset($config['show_views']);
        }
        $isCardSection = $isProducts || $isCategories || $isBrands || $isStories || $isReels;

        $sp = $config['spacing'] ?? [];
        $outer = [
            'top' => (int) ($sp['outer']['top'] ?? 0),
            'bottom' => (int) ($sp['outer']['bottom'] ?? 0),
            'left' => (int) ($sp['outer']['left'] ?? 0),
            'right' => (int) ($sp['outer']['right'] ?? 0),
        ];
        $inner = [
            'top' => (int) ($sp['inner']['top'] ?? 0),
            'bottom' => (int) ($sp['inner']['bottom'] ?? 0),
            'sides' => (int) ($sp['inner']['sides'] ?? 0),
        ];
        $itemGap = isset($sp['item_gap']) ? (int)$sp['item_gap'] : 12;
        $borderRadius = isset($sp['border_radius']) ? (int)$sp['border_radius'] : 12;

        if ($isHero || $isBanner || $isPlayImage || $isBrands) {
            $inner = ['top' => 0, 'bottom' => 0, 'sides' => 0];
            $r = $sp['border_radius'] ?? 12;
            if (is_array($r)) {
                $borderRadius = [
                    'top_left' => (int) ($r['top_left'] ?? 12),
                    'top_right' => (int) ($r['top_right'] ?? 12),
                    'bottom_left' => (int) ($r['bottom_left'] ?? 12),
                    'bottom_right' => (int) ($r['bottom_right'] ?? 12),
                ];
            } else {
                $rInt = (int) $r;
                $borderRadius = [
                    'top_left' => $rInt,
                    'top_right' => $rInt,
                    'bottom_left' => $rInt,
                    'bottom_right' => $rInt,
                ];
            }

            if ($isBrands) {
                $isVert = (in_array($config['rows_layout'] ?? 'row', ['grid', '2'], true));
                $vGap = (int)(is_array($sp['item_gap'] ?? null) ? ($sp['item_gap']['vertical'] ?? 12) : ($sp['item_gap'] ?? 12));
                $hGap = (int)(is_array($sp['item_gap'] ?? null) ? ($sp['item_gap']['horizontal'] ?? 12) : ($sp['item_gap'] ?? 12));
                if ($isVert) {
                    $itemGap = [
                        'vertical' => $vGap,
                        'horizontal' => $hGap,
                    ];
                } else {
                    $itemGap = [
                        'horizontal' => $hGap,
                    ];
                }
            } else {
                $itemGap = 0;
            }
        } elseif ($isCardSection) {
            $isVert = $isProducts
                ? (($config['orientation'] ?? 'horizontal') === 'vertical')
                : (in_array($config['rows_layout'] ?? 'row', ['grid', '2'], true));

            $vGap = (int)(is_array($sp['item_gap'] ?? null) ? ($sp['item_gap']['vertical'] ?? 12) : ($sp['item_gap'] ?? 12));
            $hGap = (int)(is_array($sp['item_gap'] ?? null) ? ($sp['item_gap']['horizontal'] ?? 12) : ($sp['item_gap'] ?? 12));

            $inner = ['top' => 0, 'bottom' => 0, 'sides' => 0];
            $borderRadius = 0;
            if ($isVert) {
                $itemGap = [
                    'vertical' => $vGap,
                    'horizontal' => $hGap,
                ];
            } else {
                $itemGap = [
                    'horizontal' => $hGap,
                ];
            }
        }

        $config['spacing'] = [
            'outer' => $outer,
            'inner' => $inner,
            'item_gap' => $itemGap,
            'border_radius' => $borderRadius,
        ];

        if (($config['background_type'] ?? 'none') === 'color') {
            $bgColor = $config['background_color'] ?? null;
            if (is_string($bgColor) && ! empty($bgColor)) {
                $config['background_color'] = [
                    'light' => $bgColor,
                    'dark' => '#1E293B',
                ];
            } elseif (is_array($bgColor)) {
                $config['background_color'] = [
                    'light' => $bgColor['light'] ?? '#FFFFFF',
                    'dark' => $bgColor['dark'] ?? '#1E293B',
                ];
            }
        }

        if (isset($config['text_color'])) {
            $textColor = $config['text_color'];
            if (is_string($textColor) && ! empty($textColor)) {
                $config['text_color'] = [
                    'light' => $textColor,
                    'dark' => '#F8FAFC',
                ];
            } elseif (is_array($textColor)) {
                $config['text_color'] = [
                    'light' => $textColor['light'] ?? '#1E293B',
                    'dark' => $textColor['dark'] ?? '#F8FAFC',
                ];
            }
        }

        return [
            'id' => $section->id,
            'type' => $section->type,
            'title' => $section->title,
            'style' => $section->style,
            'config' => $config,
            'background_image' => $section->background_image,
            'hero_media' => $section->hero_media,
            'hero_media_web_mobile' => $section->hero_media_web_mobile,
            'card_background_image' => $section->card_background_image,
            'content' => $this->resolveContent($section, $zone, $lat, $lng, $user),
        ];
    }

    private function resolveContent(HomeSection $section, ?DeliveryZone $zone, ?float $lat = null, ?float $lng = null, ?User $user = null): array
    {
        return match ($section->type) {
            HomeSectionTypeEnum::PRODUCTS() => [
                'products' => ProductListResource::collection($this->resolveProducts($section, $zone, $lat, $lng)),
            ],
            HomeSectionTypeEnum::CATEGORIES() => [
                'categories' => CategoryResource::collection($this->resolveCategories($section)),
            ],
            HomeSectionTypeEnum::BRANDS() => [
                'brands' => BrandResource::collection($this->resolveBrands($section)),
            ],
            HomeSectionTypeEnum::HERO() => [
                'items' => $this->heroItems($section),
            ],
            HomeSectionTypeEnum::BANNERS() => [
                'items' => $this->bannerItems($section),
            ],
            HomeSectionTypeEnum::PLAY_IMAGE() => [
                'rows' => $this->playImageRows($section),
            ],
            HomeSectionTypeEnum::STORIES() => [
                'stories' => CustomerWatchBuyProfileStatusGroupResource::collection($this->resolveStories($section, $zone, $lat, $lng, $user)),
            ],
            HomeSectionTypeEnum::REELS() => [
                'reels' => CustomerWatchBuyReelResource::collection($this->resolveReels($section, $zone, $lat, $lng, $user)),
            ],
            default => [],
        };
    }

    public function resolveStories(HomeSection $section, ?DeliveryZone $zone, ?float $lat = null, ?float $lng = null, ?User $user = null): Collection
    {
        $config = $section->config ?? [];
        $limit = min(max((int) ($config['limit'] ?? 10), 1), 50);
        $tray = app(WatchBuyStatusService::class)->getStatusTray(['per_page' => $limit], $user);

        return $tray['data'];
    }

    public function resolveReels(HomeSection $section, ?DeliveryZone $zone, ?float $lat = null, ?float $lng = null, ?User $user = null): Collection
    {
        $config = $section->config ?? [];
        $source = $config['source'] ?? 'trending';
        $limit = min(max((int) ($config['limit'] ?? 8), 1), 50);

        return app(WatchBuyService::class)->getHomeReels(
            $source,
            $limit,
            $zone,
            $lat !== null && $lng !== null,
            $user,
        );
    }

    public function resolveProducts(HomeSection $section, ?DeliveryZone $zone, ?float $lat = null, ?float $lng = null): Collection
    {
        $config = $section->config ?? [];
        $source = $config['source'] ?? 'newly_added';
        $limit = (int) ($config['limit'] ?? self::DEFAULT_LIMIT);

        if ($source === 'custom') {
            $ids = $this->displayIds($section, Product::class);
            if (empty($ids)) {
                return collect();
            }

            $query = Product::query()->whereIn('id', $ids);
            $this->applyZoneFilterToProductsQuery($query, $zone, $lat, $lng);
            $products = $query->get();

            if ($lat && $lng) {
                $zoneInfo = DeliveryZoneService::getZonesAtPoint((float) $lat, (float) $lng);
                foreach ($products as $product) {
                    $product->user_latitude = (float) $lat;
                    $product->user_longitude = (float) $lng;
                    $product->zone_info = $zoneInfo;
                    $product->preferNearestStoreVariants((float) $lat, (float) $lng);
                }
            }

            return $this->orderByIds($products, $ids);
        }

        $query = Product::query()
            ->where('status', ProductStatusEnum::ACTIVE())
            ->where('verification_status', ProductVarificationStatusEnum::APPROVED());

        $categoryIds = $this->filterCategoryIds($section);
        if (! empty($categoryIds)) {
            $query->whereIn('category_id', $categoryIds);
        }

        $this->applyZoneFilterToProductsQuery($query, $zone, $lat, $lng);

        if ($source === 'top_rated') {
            $query->withCount(['reviews as avg_rating' => function ($q) {
                $q->select(DB::raw('coalesce(avg(rating), 0)'));
            }])->orderByDesc('avg_rating');
        } elseif ($source === 'best_seller') {
            $query->withCount('orderItems')->orderByDesc('order_items_count');
        } elseif ($source === 'featured') {
            $query->where('featured', true)->latest();
        } elseif ($source === 'recommended') {
            $query->whereNotNull('badge_id')->latest();
        } else {
            $query->latest();
        }

        $products = $query->take($limit)->get();

        if ($lat && $lng) {
            $zoneInfo = DeliveryZoneService::getZonesAtPoint((float) $lat, (float) $lng);
            foreach ($products as $product) {
                $product->user_latitude = (float) $lat;
                $product->user_longitude = (float) $lng;
                $product->zone_info = $zoneInfo;
                $product->preferNearestStoreVariants((float) $lat, (float) $lng);
            }
        }

        return Product::markSponsored($products);
    }

    private function applyZoneFilterToProductsQuery(Builder $productsQuery, ?DeliveryZone $zone, ?float $lat = null, ?float $lng = null): void
    {
        if (! $zone) {
            $productsQuery->with(['variants.storeProductVariants.store']);

            return;
        }

        $storeIds = Store::whereHas('zones', function ($q) use ($zone) {
            $q->where('delivery_zones.id', $zone->id);
        })
            ->where('verification_status', StoreVerificationStatusEnum::APPROVED())
            ->where('visibility_status', StoreVisibilityStatusEnum::VISIBLE())
            ->pluck('id')
            ->toArray();

        $productsQuery->with([
            'variants' => function ($q) use ($storeIds) {
                $q->whereHas('storeProductVariants', function ($sq) use ($storeIds) {
                    $sq->whereIn('store_id', $storeIds);
                });
            },
            'variants.storeProductVariants' => function ($q) use ($storeIds) {
                $q->whereIn('store_id', $storeIds);
            },
            'variants.storeProductVariants.store',
            'variants.storeVariantAddons' => function ($q) use ($storeIds) {
                $q->whereIn('store_id', $storeIds);
            },
            'variants.storeVariantAddons.addonGroup',
            'variants.storeVariantAddons.addonItem',
        ])
            ->whereHas('variants.storeProductVariants', function ($q) use ($storeIds) {
                $q->whereIn('store_id', $storeIds);
            });
    }

    private function filterCategoryIds(HomeSection $section): array
    {
        $parentIds = $section->items
            ->where('purpose', 'filter')
            ->filter(fn($it) => $it->itemable_type === Category::class || $it->itemable_type === 'category')
            ->pluck('itemable_id')->filter()->values()->all();

        if (empty($parentIds)) {
            return [];
        }

        $allCategoryIds = Category::whereIn('parent_id', $parentIds)->pluck('id')->all();

        return array_values(array_unique(array_merge($parentIds, $allCategoryIds)));
    }

    public function resolveCategories(HomeSection $section): Collection
    {
        if (($section->config['source'] ?? 'all') === 'custom') {
            $ids = $this->displayIds($section, Category::class);
            if (empty($ids)) {
                return collect();
            }

            $categories = $this->orderByIds(
                Category::whereIn('id', $ids)->where('status', CategoryStatusEnum::ACTIVE())->get(),
                $ids,
            );

            if ($section->style === 'custom_image') {
                $itemsByCatId = $section->items
                    ->filter(fn($it) => $it->purpose === 'display')
                    ->keyBy('itemable_id');

                foreach ($categories as $cat) {
                    $item = $itemsByCatId->get($cat->id);
                    if ($item && $item->image) {
                        $cat->custom_image_url = $item->image;
                        $cat->setAttribute('image', $item->image);
                    }
                }
            }

            return $categories;
        }

        return Category::query()
            ->where('status', CategoryStatusEnum::ACTIVE())
            ->whereNull('parent_id')
            ->orderBy('id')
            ->limit(8)
            ->get();
    }

    public function resolveBrands(HomeSection $section): Collection
    {
        if (($section->config['source'] ?? 'all') === 'custom') {
            $ids = $this->displayIds($section, Brand::class);
            if (empty($ids)) {
                return collect();
            }

            $brands = $this->orderByIds(
                Brand::whereIn('id', $ids)->where('status', BrandStatusEnum::ACTIVE())->whereHas('products', function (Builder $query) {
                    $query->where('status', ProductStatusEnum::ACTIVE())
                        ->where('verification_status', ProductVarificationStatusEnum::APPROVED());
                })->withCount('products')->get(),
                $ids,
            );

            if ($section->style === 'custom_image') {
                $itemsByBrandId = $section->items
                    ->filter(fn($it) => $it->purpose === 'display')
                    ->keyBy('itemable_id');

                foreach ($brands as $b) {
                    $item = $itemsByBrandId->get($b->id);
                    if ($item && $item->image) {
                        $b->custom_image_url = $item->image;
                        $b->setAttribute('logo', $item->image);
                    }
                }
            }

            return $brands;
        }

        return Brand::query()
            ->where('status', BrandStatusEnum::ACTIVE())
            ->withCount('products')
            ->having('products_count', '>', 0)
            ->limit(6)
            ->orderByTitle()
            ->get();
    }

    private function heroItems(HomeSection $section): array
    {
        return $section->items
            ->where('purpose', 'display')
            ->sortBy('sort_order')
            ->map(fn($it) => [
                'id' => $it->id,
                'title' => $it->title ?: ($it->itemable?->title ?? $it->itemable?->name),
                'image' => $it->image ?: $this->itemableImage($it->itemable, $it->itemable_type),
                'default_image' => $this->itemableImage($it->itemable, $it->itemable_type),
                'type' => $this->itemableKey($it->itemable_type),
                'target_id' => $it->itemable_id,
                'slug' => $it->itemable?->slug,
            ])->values()->all();
    }

    private function bannerItems(HomeSection $section): array
    {
        return $section->items
            ->where('purpose', 'display')
            ->sortBy('sort_order')
            ->map(fn($it) => [
                'id' => $it->id,
                'image' => $it->image ?: $this->itemableImage($it->itemable, $it->itemable_type),
                'image_web_mobile' => $it->image_web_mobile ?: null,
                'link_type' => $it->link_url ? 'custom' : (($it->config ?? [])['link_type'] ?? $this->itemableKey($it->itemable_type)),
                'link_url' => $it->link_url ?: null,
                'target_id' => $it->itemable_id,
                'slug' => $it->itemable?->slug,
            ])->values()->all();
    }

    private function playImageItems(HomeSection $section): array
    {
        return $section->items
            ->where('purpose', 'display')
            ->sortBy('sort_order')
            ->map(fn($it) => [
                'id' => $it->id,
                'image' => $it->image ?: $this->itemableImage($it->itemable, $it->itemable_type),
                'image_web_mobile' => $it->image_web_mobile ?: null,
                'link_type' => $it->link_url ? 'custom' : $this->itemableKey($it->itemable_type),
                'link_url' => $it->link_url ?: null,
                'target_id' => $it->itemable_id,
                'slug' => $it->itemable?->slug,
                // Per-item layout options are consumed directly by web and app clients.
                'config' => $it->config ?? [],
            ])->values()->all();
    }

    private function playImageRows(HomeSection $section): array
    {
        $grouped = [];

        foreach ($this->playImageItems($section) as $item) {
            $rowIndex = (int) ($item['config']['row_index'] ?? 0);
            $grouped[$rowIndex][] = $item;
        }

        $rowConfigs = array_values((array) (($section->config ?? [])['rows'] ?? []));
        $rowCount = max(count($rowConfigs), empty($grouped) ? 0 : max(array_keys($grouped)) + 1);
        $rows = [];

        for ($rowIndex = 0; $rowIndex < $rowCount; $rowIndex++) {
            $rawConfig = (array) ($rowConfigs[$rowIndex] ?? []);
            // [Play Image Slider Controls] Merge default row configuration so slider flags (show_scroll_buttons, show_bullets, auto_scroll, auto_scroll_time) are reliably delivered in API responses
            $config = array_merge($this->defaultPlayImageRowConfig(), $rawConfig);
            $rows[] = [
                'row_index' => $rowIndex,
                'style' => $config['style'] ?? 'grid',
                'config' => $config,
                'items' => $grouped[$rowIndex] ?? [],
            ];
        }

        return $rows;
    }

    private function defaultPlayImageRowConfig(): array
    {
        return [
            'style' => 'grid',
            'container_width' => 'container',
            'outer_padding' => 0,
            'outer_padding_custom' => '',
            'grid_gap' => 0,
            'border_radius' => 0,
            'hover_effect' => 'none',
            'background_type' => 'none',
            'background_color' => '#ffffff',
            'background_image' => null,
            'items_to_show' => 1,
            'items_to_show_mobile' => 1,
            'show_scroll_buttons' => 1,
            'show_bullets' => 0,
            'auto_scroll' => 0,
            'auto_scroll_time' => 3,
        ];
    }

    private function displayIds(HomeSection $section, string $class): array
    {
        $key = match ($class) {
            Product::class => 'product',
            Category::class => 'category',
            Brand::class => 'brand',
            default => null,
        };

        return $section->items
            ->where('purpose', 'display')
            ->filter(fn($it) => $it->itemable_type === $class || ($key && $it->itemable_type === $key))
            ->pluck('itemable_id')->filter()->values()->all();
    }

    private function orderByIds(Collection $models, array $ids): Collection
    {
        $order = array_flip($ids);

        return $models->sortBy(fn($m) => $order[$m->id] ?? PHP_INT_MAX)->values();
    }

    private function itemableKey(?string $class): ?string
    {
        return $class ? (array_flip(HomeBuilderService::ITEMABLE_MAP)[$class] ?? null) : null;
    }

    private function itemableImage($itemable, ?string $class): ?string
    {
        if (! $itemable) {
            return null;
        }

        return match ($class) {
            Product::class => $itemable->main_image,
            Category::class => $itemable->image,
            Brand::class => $itemable->logo,
            default => null,
        };
    }

    public function paginateSection(HomeSection $section, ?DeliveryZone $zone, int $perPage, int $page, ?float $lat = null, ?float $lng = null, ?User $user = null): array
    {
        switch ($section->type) {
            case HomeSectionTypeEnum::PRODUCTS():
                $paginator = $this->productsPaginator($section, $zone, $perPage, $page, $lat, $lng);

                return $this->paginatedResponse($paginator, ProductListResource::collection($paginator->getCollection()));

            case HomeSectionTypeEnum::CATEGORIES():
                $paginator = $this->categoriesPaginator($section, $perPage, $page);

                return $this->paginatedResponse($paginator, CategoryResource::collection($paginator->getCollection()));

            case HomeSectionTypeEnum::BRANDS():
                $paginator = $this->brandsPaginator($section, $perPage, $page);

                return $this->paginatedResponse($paginator, BrandResource::collection($paginator->getCollection()));

            case HomeSectionTypeEnum::HERO():
                $paginator = $this->collectionPaginator(collect($this->heroItems($section)), $perPage, $page);

                return $this->paginatedResponse($paginator, $paginator->getCollection());

            case HomeSectionTypeEnum::BANNERS():
                $paginator = $this->collectionPaginator(collect($this->bannerItems($section)), $perPage, $page);

                return $this->paginatedResponse($paginator, $paginator->getCollection());

            case HomeSectionTypeEnum::PLAY_IMAGE():
                $paginator = $this->collectionPaginator(collect($this->playImageRows($section)), $perPage, $page);

                return $this->paginatedResponse($paginator, $paginator->getCollection());

            case HomeSectionTypeEnum::STORIES():
                $stories = $this->resolveStories($section, $zone, $lat, $lng, $user);
                $paginator = $this->collectionPaginator($stories, $perPage, $page);

                return $this->paginatedResponse($paginator, CustomerWatchBuyProfileStatusGroupResource::collection($paginator->getCollection()));

            case HomeSectionTypeEnum::REELS():
                $reels = $this->resolveReels($section, $zone, $lat, $lng, $user);
                $paginator = $this->collectionPaginator($reels, $perPage, $page);

                return $this->paginatedResponse($paginator, CustomerWatchBuyReelResource::collection($paginator->getCollection()));

            default:
                $paginator = $this->emptyPaginator($perPage, $page);

                return $this->paginatedResponse($paginator, collect());
        }
    }

    private function productsPaginator(HomeSection $section, ?DeliveryZone $zone, int $perPage, int $page, ?float $lat = null, ?float $lng = null): LengthAwarePaginator
    {
        $config = $section->config ?? [];
        $source = $config['source'] ?? 'newly_added';

        if ($source === 'custom') {
            $ids = $this->displayIds($section, Product::class);
            if (empty($ids)) {
                return $this->emptyPaginator($perPage, $page);
            }

            $query = Product::query()->whereIn('id', $ids);
            $this->applyZoneFilterToProductsQuery($query, $zone, $lat, $lng);
            $products = $this->orderByIds($query->get(), $ids);

            if ($lat && $lng) {
                $zoneInfo = DeliveryZoneService::getZonesAtPoint((float) $lat, (float) $lng);
                foreach ($products as $product) {
                    $product->user_latitude = (float) $lat;
                    $product->user_longitude = (float) $lng;
                    $product->zone_info = $zoneInfo;
                    $product->preferNearestStoreVariants((float) $lat, (float) $lng);
                }
            }

            return $this->collectionPaginator($products, $perPage, $page);
        }

        $query = Product::query()
            ->where('status', ProductStatusEnum::ACTIVE())
            ->where('verification_status', ProductVarificationStatusEnum::APPROVED());

        $categoryIds = $this->filterCategoryIds($section);
        if (! empty($categoryIds)) {
            $query->whereIn('category_id', $categoryIds);
        }

        $this->applyZoneFilterToProductsQuery($query, $zone, $lat, $lng);

        if ($source === 'top_rated') {
            $query->withCount(['reviews as avg_rating' => function ($q) {
                $q->select(DB::raw('coalesce(avg(rating), 0)'));
            }])->orderByDesc('avg_rating');
        } elseif ($source === 'best_seller') {
            $query->withCount('orderItems')->orderByDesc('order_items_count');
        } elseif ($source === 'featured') {
            $query->where('featured', true)->latest();
        } elseif ($source === 'recommended') {
            $query->whereNotNull('badge_id')->latest();
        } else {
            $query->latest();
        }

        $paginator = $query->paginate($perPage, ['*'], 'page', $page);

        if ($lat && $lng) {
            $zoneInfo = DeliveryZoneService::getZonesAtPoint((float) $lat, (float) $lng);
            foreach ($paginator->getCollection() as $product) {
                $product->user_latitude = (float) $lat;
                $product->user_longitude = (float) $lng;
                $product->zone_info = $zoneInfo;
                $product->preferNearestStoreVariants((float) $lat, (float) $lng);
            }
        }

        return $paginator;
    }

    private function categoriesPaginator(HomeSection $section, int $perPage, int $page): LengthAwarePaginator
    {
        if (($section->config['source'] ?? 'all') === 'custom') {
            $ids = $this->displayIds($section, Category::class);
            if (empty($ids)) {
                return $this->emptyPaginator($perPage, $page);
            }

            $categories = $this->orderByIds(
                Category::whereIn('id', $ids)->where('status', CategoryStatusEnum::ACTIVE())->get(),
                $ids,
            );

            if ($section->style === 'custom_image') {
                $itemsByCatId = $section->items
                    ->filter(fn($it) => $it->purpose === 'display')
                    ->keyBy('itemable_id');

                foreach ($categories as $cat) {
                    $item = $itemsByCatId->get($cat->id);
                    if ($item && $item->image) {
                        $cat->custom_image_url = $item->image;
                        $cat->setAttribute('image', $item->image);
                    }
                }
            }

            return $this->collectionPaginator($categories, $perPage, $page);
        }

        return Category::query()
            ->where('status', CategoryStatusEnum::ACTIVE())
            ->whereNull('parent_id')
            ->orderBy('id')
            ->paginate($perPage, ['*'], 'page', $page);
    }

    private function brandsPaginator(HomeSection $section, int $perPage, int $page): LengthAwarePaginator
    {
        if (($section->config['source'] ?? 'all') === 'custom') {
            $ids = $this->displayIds($section, Brand::class);
            if (empty($ids)) {
                return $this->emptyPaginator($perPage, $page);
            }

            $brands = $this->orderByIds(
                Brand::whereIn('id', $ids)->where('status', BrandStatusEnum::ACTIVE())->whereHas('products', function (Builder $query) {
                    $query->where('status', ProductStatusEnum::ACTIVE())
                        ->where('verification_status', ProductVarificationStatusEnum::APPROVED());
                })->withCount('products')->get(),
                $ids,
            );

            if ($section->style === 'custom_image') {
                $itemsByBrandId = $section->items
                    ->filter(fn($it) => $it->purpose === 'display')
                    ->keyBy('itemable_id');

                foreach ($brands as $b) {
                    $item = $itemsByBrandId->get($b->id);
                    if ($item && $item->image) {
                        $b->custom_image_url = $item->image;
                        $b->setAttribute('logo', $item->image);
                    }
                }
            }

            return $this->collectionPaginator($brands, $perPage, $page);
        }

        return Brand::query()
            ->where('status', BrandStatusEnum::ACTIVE())
            ->withCount('products')
            ->having('products_count', '>', 0)
            ->orderByTitle()
            ->paginate($perPage, ['*'], 'page', $page);
    }

    private function collectionPaginator(Collection $items, int $perPage, int $page): LengthAwarePaginator
    {
        return new LengthAwarePaginator(
            $items->forPage($page, $perPage)->values(),
            $items->count(),
            $perPage,
            $page,
            ['path' => Paginator::resolveCurrentPath()],
        );
    }

    private function emptyPaginator(int $perPage, int $page): LengthAwarePaginator
    {
        return new LengthAwarePaginator(collect(), 0, $perPage, $page);
    }

    private function paginatedResponse(LengthAwarePaginator $paginator, $items): array
    {
        return [
            'current_page' => $paginator->currentPage(),
            'last_page' => $paginator->lastPage(),
            'per_page' => $paginator->perPage(),
            'total' => $paginator->total(),
            'data' => $items,
        ];
    }
}
