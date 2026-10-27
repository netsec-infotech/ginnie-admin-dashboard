<?php

namespace App\Services;

use App\Enums\HomePage\HomeSectionTypeEnum;
use App\Enums\SpatieMediaCollectionName;
use App\Models\Banner;
use App\Models\Brand;
use App\Models\Category;
use App\Models\FeaturedSection;
use App\Models\HomeLayout;
use App\Models\HomeSection;
use App\Models\HomeSectionItem;
use App\Models\Product;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

class HomeBuilderLegacyMigrationService
{
    private const LEGACY_FEATURED_CONFIG_KEY = 'legacy_featured_section_id';

    private const LEGACY_BANNER_CONFIG_KEY = 'legacy_banner_id';

    private const LEGACY_BANNER_GROUP_CONFIG_KEY = 'legacy_banner_group';

    public function status(): array
    {
        $stats = $this->migrate(true);

        return [
            'has_pending' => $stats['featured_created'] > 0 || $stats['banner_sections_created'] > 0 || $stats['banner_sections_converted'] > 0,
            'stats' => $stats,
        ];
    }

    public function homeBuilderBannerStatus(): array
    {
        $stats = $this->emptyStats();

        if (! Schema::hasTable('home_layouts')) {
            return [
                'has_pending' => false,
                'stats' => $stats,
            ];
        }

        $this->convertExistingHomeBuilderBannerSections(true, $stats);

        return [
            'has_pending' => $stats['banner_sections_converted'] > 0,
            'stats' => $stats,
        ];
    }

    public function migrateHomeBuilderBannersOnly(bool $dryRun = false): array
    {
        $stats = $this->emptyStats();

        if (! Schema::hasTable('home_layouts')) {
            return $stats;
        }

        DB::transaction(function () use ($dryRun, &$stats) {
            // This path only converts banner sections that already live in Home Builder.
            $this->convertExistingHomeBuilderBannerSections($dryRun, $stats);
        });

        return $stats;
    }

    public function migrate(bool $dryRun = false): array
    {
        $stats = $this->emptyStats();

        if (! Schema::hasTable('home_layouts')) {
            return $stats;
        }

        DB::transaction(function () use ($dryRun, &$stats) {
            if (Schema::hasTable('featured_sections')) {
                FeaturedSection::with(['categories', 'products', 'zones', 'media'])
                    ->orderBy('sort_order')
                    ->get()
                    ->each(function (FeaturedSection $section) use ($dryRun, &$stats) {
                        foreach (['app', 'web'] as $platform) {
                            $this->migrateFeaturedSection($section, $platform, $dryRun, $stats);
                        }
                    });
            }

            $this->convertExistingHomeBuilderBannerSections($dryRun, $stats);

            if (Schema::hasTable('banners')) {
                $banners = Banner::with(['zones', 'media'])
                    ->orderBy('display_order')
                    ->get();

                foreach (['app', 'web'] as $platform) {
                    $this->migrateBanners($banners, $platform, $dryRun, $stats);
                }
            }
        });

        return $stats;
    }

    private function emptyStats(): array
    {
        return [
            'featured_created' => 0,
            'featured_skipped' => 0,
            'banner_sections_created' => 0,
            'banner_sections_skipped' => 0,
            'banner_sections_converted' => 0,
            'banner_items_created' => 0,
        ];
    }

    private function convertExistingHomeBuilderBannerSections(bool $dryRun, array &$stats): void
    {
        $sections = HomeSection::query()
            ->where('type', 'banners')
            ->with('items')
            ->get();

        if ($dryRun) {
            $stats['banner_sections_converted'] += $sections->count();

            return;
        }

        foreach ($sections as $section) {
            $rowConfig = $this->playImageRowConfigFromSection($section);

            // Existing builder banner sections are converted in-place so their media and ordering stay intact.
            $section->forceFill([
                'type' => HomeSectionTypeEnum::PLAY_IMAGE(),
                'style' => $rowConfig['style'],
                'config' => array_merge((array) $section->config, [
                    'rows' => [$rowConfig],
                    'legacy_type' => 'banners',
                ]),
            ])->save();

            $section->items
                ->sortBy('sort_order')
                ->values()
                ->each(function (HomeSectionItem $item, int $index) {
                    $item->forceFill([
                        'config' => $this->playImageItemConfigFromItem($item, $index),
                    ])->save();
                });

            $stats['banner_sections_converted']++;
        }
    }

    private function migrateFeaturedSection(FeaturedSection $featuredSection, string $platform, bool $dryRun, array &$stats): void
    {
        $layoutAttributes = [
            'platform' => $platform,
            'scope_type' => $featuredSection->scope_type === 'category' && $featuredSection->scope_id ? 'category' : 'global',
            'scope_id' => $featuredSection->scope_type === 'category' && $featuredSection->scope_id ? $featuredSection->scope_id : null,
        ];

        if ($dryRun) {
            $layout = HomeLayout::where($layoutAttributes)->first();
            if ($layout && $this->featuredSectionAlreadyMigrated($layout, $featuredSection)) {
                $stats['featured_skipped']++;
            } else {
                $stats['featured_created']++;
            }

            return;
        }

        $layout = HomeLayout::firstOrCreate($layoutAttributes, [
            'is_active' => true,
        ]);

        if ($this->featuredSectionAlreadyMigrated($layout, $featuredSection)) {
            $stats['featured_skipped']++;

            return;
        }

        $source = $this->sourceFromFeaturedSection($featuredSection->section_type);

        $section = $layout->sections()->create([
            'type' => 'products',
            'title' => $featuredSection->title,
            'style' => $featuredSection->style ?: 'horizontal_carousel',
            'sort_order' => $featuredSection->sort_order ?: 0,
            'is_active' => $featuredSection->status === 'active' || $featuredSection->status === '1' || $featuredSection->status === true,
            'config' => [
                self::LEGACY_FEATURED_CONFIG_KEY => $featuredSection->id,
                'source' => $source,
                'limit' => 12,
                'orientation' => $this->orientationFromStyle($featuredSection->style),
                'background_type' => $featuredSection->background_type ?: 'color',
                'background_color' => $this->defaultBackgroundColors(),
                'text_color' => $featuredSection->text_color ?: '#000000',
            ],
        ]);

        if ($featuredSection->zones && $featuredSection->zones->isNotEmpty()) {
            $section->zones()->sync($featuredSection->zones->pluck('id')->toArray());
        }

        if ($source === 'custom' && $featuredSection->products) {
            foreach ($featuredSection->products as $index => $product) {
                $section->items()->create([
                    'purpose' => 'display',
                    'itemable_type' => Product::class,
                    'itemable_id' => $product->id,
                    'sort_order' => $index,
                ]);
            }
        } elseif ($featuredSection->categories && $featuredSection->categories->isNotEmpty()) {
            foreach ($featuredSection->categories as $index => $category) {
                $section->items()->create([
                    'purpose' => 'filter',
                    'itemable_type' => Category::class,
                    'itemable_id' => $category->id,
                    'sort_order' => $index,
                ]);
            }
        }

        $this->copyBackgroundMedia($featuredSection, $section, $platform);
        $stats['featured_created']++;
    }

    private function migrateBanners(Collection $legacyBanners, string $platform, bool $dryRun, array &$stats): void
    {
        $legacyBanners
            ->groupBy(fn (Banner $banner) => $this->bannerGroupKey($banner))
            ->each(function (Collection $banners) use ($platform, $dryRun, &$stats) {
                $this->migrateBannerGroup($banners->values(), $platform, $dryRun, $stats);
            });
    }

    private function migrateBannerGroup(Collection $banners, string $platform, bool $dryRun, array &$stats): void
    {
        $firstBanner = $banners->first();

        if (! $firstBanner) {
            return;
        }

        $layoutAttributes = [
            'platform' => $platform,
            'scope_type' => $firstBanner->scope_type === 'category' && $firstBanner->scope_id ? 'category' : 'global',
            'scope_id' => $firstBanner->scope_type === 'category' && $firstBanner->scope_id ? $firstBanner->scope_id : null,
        ];
        $groupKey = $this->bannerGroupKey($firstBanner);

        if ($dryRun) {
            $layout = HomeLayout::where($layoutAttributes)->first();
            if ($layout && $this->legacyBannerSectionForGroup($layout, $firstBanner, $groupKey)) {
                $stats['banner_sections_converted']++;
            } elseif ($layout && $this->bannerGroupAlreadyMigrated($layout, $firstBanner, $groupKey)) {
                $stats['banner_sections_skipped']++;
            } else {
                $stats['banner_sections_created']++;
                $stats['banner_items_created'] += $banners->count();
            }

            return;
        }

        $layout = HomeLayout::firstOrCreate($layoutAttributes, [
            'is_active' => true,
        ]);

        if ($legacySection = $this->legacyBannerSectionForGroup($layout, $firstBanner, $groupKey)) {
            // Existing Home Builder banner sections are converted in-place to avoid duplicated campaign rows.
            $legacySection->forceFill([
                'type' => HomeSectionTypeEnum::PLAY_IMAGE(),
                'style' => $firstBanner->position === 'carousel' ? 'scroll' : 'grid',
                'config' => array_merge((array) $legacySection->config, [
                    'rows' => [$this->playImageRowConfig($firstBanner)],
                ]),
            ])->save();

            $legacySection->items()
                ->orderBy('sort_order')
                ->get()
                ->each(function (HomeSectionItem $item, int $index) use ($firstBanner) {
                    $item->forceFill([
                        'config' => $this->playImageItemConfig($firstBanner, $item, $index),
                    ])->save();
                });

            $stats['banner_sections_converted']++;

            return;
        }

        if ($this->bannerGroupAlreadyMigrated($layout, $firstBanner, $groupKey)) {
            $stats['banner_sections_skipped']++;

            return;
        }

        $section = $layout->sections()->create([
            'type' => HomeSectionTypeEnum::PLAY_IMAGE(),
            'title' => $firstBanner->position === 'carousel' ? 'Carousel Banners' : 'Top Banners',
            'style' => $firstBanner->position === 'carousel' ? 'scroll' : 'grid',
            'sort_order' => $banners->min('display_order') ?: 0,
            'is_active' => $banners->contains(fn (Banner $banner) => $banner->visibility_status === 'published'),
            'config' => [
                self::LEGACY_BANNER_GROUP_CONFIG_KEY => $groupKey,
                'legacy_banner_ids' => $banners->pluck('id')->values()->all(),
                'position' => $firstBanner->position,
                'rows' => [$this->playImageRowConfig($firstBanner)],
            ],
        ]);

        if ($firstBanner->zones && $firstBanner->zones->isNotEmpty()) {
            $section->zones()->sync($firstBanner->zones->pluck('id')->toArray());
        }

        foreach ($banners as $index => $banner) {
            $item = $section->items()->create([
                'purpose' => 'display',
                'itemable_type' => $this->bannerItemableType($banner),
                'itemable_id' => $this->bannerItemableId($banner),
                'title' => $banner->title,
                'link_url' => $banner->type === 'custom' ? $banner->custom_url : null,
                'sort_order' => $index,
                'config' => $this->playImageItemConfig($banner, null, $index),
            ]);

            $this->copyBannerMedia($banner, $item, $platform);
        }

        $stats['banner_sections_created']++;
        $stats['banner_items_created'] += $banners->count();
    }

    private function featuredSectionAlreadyMigrated(HomeLayout $layout, FeaturedSection $featuredSection): bool
    {
        $source = $this->sourceFromFeaturedSection($featuredSection->section_type);

        return $layout->sections()
            ->where('type', 'products')
            ->where(function ($query) use ($featuredSection, $source) {
                $query->where('config->'.self::LEGACY_FEATURED_CONFIG_KEY, $featuredSection->id)
                    ->orWhere(function ($query) use ($featuredSection, $source) {
                        $query->where('title', $featuredSection->title)
                            ->where('config->source', $source);
                    });
            })
            ->exists();
    }

    private function bannerGroupAlreadyMigrated(HomeLayout $layout, Banner $banner, string $groupKey): bool
    {
        return $layout->sections()
            ->where('type', HomeSectionTypeEnum::PLAY_IMAGE())
            ->where(function ($query) use ($banner, $groupKey) {
                $query->where('config->'.self::LEGACY_BANNER_CONFIG_KEY, $banner->id)
                    ->orWhere('config->'.self::LEGACY_BANNER_GROUP_CONFIG_KEY, $groupKey)
                    ->orWhere(function ($query) use ($banner) {
                        $query->where('style', $banner->position === 'carousel' ? 'scroll' : 'grid')
                            ->where('config->position', $banner->position);
                    });
            })
            ->exists();
    }

    private function legacyBannerSectionForGroup(HomeLayout $layout, Banner $banner, string $groupKey): ?HomeSection
    {
        return $layout->sections()
            ->where('type', 'banners')
            ->where(function ($query) use ($banner, $groupKey) {
                $query->where('config->'.self::LEGACY_BANNER_CONFIG_KEY, $banner->id)
                    ->orWhere('config->'.self::LEGACY_BANNER_GROUP_CONFIG_KEY, $groupKey)
                    ->orWhere(function ($query) use ($banner) {
                        $query->where('style', $banner->position === 'carousel' ? 'peek' : 'full')
                            ->where('config->position', $banner->position);
                    });
            })
            ->first();
    }

    private function playImageRowConfig(Banner $banner): array
    {
        $isCarousel = $banner->position === 'carousel';

        return [
            'style' => $isCarousel ? 'scroll' : 'grid',
            'container_width' => 'container',
            'outer_padding' => 0,
            'outer_padding_custom' => '',
            'grid_gap' => 12,
            'border_radius' => 12,
            'hover_effect' => 'zoom',
            'background_type' => 'none',
            'background_color' => '#ffffff',
            'background_image' => null,
            'items_to_show' => $isCarousel ? 1 : 'auto',
            'items_to_show_mobile' => 1,
            'show_scroll_buttons' => $isCarousel,
            'show_bullets' => false,
            'auto_scroll' => false,
            'auto_scroll_time' => 4,
        ];
    }

    private function playImageItemConfig(Banner $banner, ?HomeSectionItem $item, int $index): array
    {
        $linkType = $banner->type === 'custom'
            ? 'custom'
            : ($this->bannerItemableType($banner) ? $banner->type : 'none');

        // [Legacy Migration Fix] Default cols and aspect_ratio to auto so items fill grid spans naturally
        return array_merge((array) ($item?->config ?? []), [
            'row_index' => 0,
            'media_type' => 'image',
            'link_type' => $linkType,
            'cols' => 'auto',
            'cols_mobile' => 'auto',
            'aspect_ratio' => 'auto',
            'object_fit' => 'cover',
            'object_position' => 'center',
            'alt' => $banner->title ?: 'Banner '.($index + 1),
        ]);
    }

    private function playImageRowConfigFromSection(HomeSection $section): array
    {
        $position = (array) $section->config;
        $isCarousel = ($position['position'] ?? null) === 'carousel' || $section->style === 'peek';

        return [
            'style' => $isCarousel ? 'scroll' : 'grid',
            'container_width' => 'container',
            'outer_padding' => 0,
            'outer_padding_custom' => '',
            'grid_gap' => 12,
            'border_radius' => 12,
            'hover_effect' => 'zoom',
            'background_type' => 'none',
            'background_color' => '#ffffff',
            'background_image' => null,
            'items_to_show' => $isCarousel ? 1 : 'auto',
            'items_to_show_mobile' => 1,
            'show_scroll_buttons' => $isCarousel,
            'show_bullets' => false,
            'auto_scroll' => false,
            'auto_scroll_time' => 4,
        ];
    }

    private function playImageItemConfigFromItem(HomeSectionItem $item, int $index): array
    {
        $linkType = $item->link_url
            ? 'custom'
            : ($this->itemableKey($item->itemable_type) ?: 'none');

        // [Legacy Migration Fix] Default cols and aspect_ratio to auto so items fill grid spans naturally
        return array_merge((array) $item->config, [
            'row_index' => 0,
            'media_type' => 'image',
            'link_type' => $linkType,
            'cols' => 'auto',
            'cols_mobile' => 'auto',
            'aspect_ratio' => 'auto',
            'object_fit' => 'cover',
            'object_position' => 'center',
            'alt' => $item->title ?: 'Banner '.($index + 1),
        ]);
    }

    private function sourceFromFeaturedSection(?string $sectionType): string
    {
        return match ($sectionType) {
            'custom_products' => 'custom',
            'top_rated', 'best_seller', 'featured', 'recommended', 'newly_added' => $sectionType,
            default => 'newly_added',
        };
    }

    private function orientationFromStyle(?string $style): string
    {
        return in_array($style, ['grid', 'vertical'], true) ? 'vertical' : 'horizontal';
    }

    private function defaultBackgroundColors(): array
    {
        return [
            'light' => '#ffffff',
            'dark' => '#000000',
        ];
    }

    private function copyBackgroundMedia(FeaturedSection $featuredSection, HomeSection $section, string $platform): void
    {
        $media = $featuredSection->getFirstMedia(SpatieMediaCollectionName::FEATURED_SECTION_BACKGROUND_IMAGE());
        if (! $media || ! file_exists($media->getPath())) {
            return;
        }

        try {
            $section->addMedia($media->getPath())
                ->preservingOriginal()
                ->toMediaCollection(SpatieMediaCollectionName::HOME_SECTION_BACKGROUND());
        } catch (\Throwable $e) {
            Log::warning("Could not copy {$platform} home builder media for featured section ID {$featuredSection->id}: ".$e->getMessage());
        }
    }

    private function bannerItemableType(Banner $banner): ?string
    {
        return match ($banner->type) {
            'product' => Product::class,
            'category' => Category::class,
            'brand' => Brand::class,
            default => null,
        };
    }

    private function bannerItemableId(Banner $banner): ?int
    {
        return match ($banner->type) {
            'product' => $banner->product_id,
            'category' => $banner->category_id,
            'brand' => $banner->brand_id,
            default => null,
        };
    }

    private function itemableKey(?string $class): ?string
    {
        return match ($class) {
            Product::class => 'product',
            Category::class => 'category',
            Brand::class => 'brand',
            default => null,
        };
    }

    private function copyBannerMedia(Banner $banner, HomeSectionItem $item, string $platform): void
    {
        $media = $banner->getFirstMedia(SpatieMediaCollectionName::BANNER_IMAGE());
        if (! $media || ! file_exists($media->getPath())) {
            return;
        }

        try {
            $item->addMedia($media->getPath())
                ->preservingOriginal()
                ->toMediaCollection(SpatieMediaCollectionName::HOME_SECTION_ITEM_IMAGE());
        } catch (\Throwable $e) {
            Log::warning("Could not copy {$platform} home builder media for banner ID {$banner->id}: ".$e->getMessage());
        }
    }

    private function bannerGroupKey(Banner $banner): string
    {
        $scopeType = $banner->scope_type === 'category' && $banner->scope_id ? 'category' : 'global';
        $scopeId = $scopeType === 'category' ? $banner->scope_id : 'all';
        $zoneIds = $banner->zones->pluck('id')->sort()->values()->implode('-') ?: 'all';

        return implode(':', [$scopeType, $scopeId, $banner->position, $zoneIds]);
    }
}
