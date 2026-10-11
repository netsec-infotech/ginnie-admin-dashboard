<?php

namespace Database\Seeders;

use App\Enums\HomePage\BackgroundTypeEnum;
use App\Enums\HomePage\BannerStyleEnum;
use App\Enums\HomePage\BrandStyleEnum;
use App\Enums\HomePage\CategoryStyleEnum;
use App\Enums\HomePage\HomePagePlatformEnum;
use App\Enums\HomePageScopeEnum;
use App\Enums\HomePage\HomeSectionTypeEnum;
use App\Enums\HomePage\ItemPurposeEnum;
use App\Enums\HomePage\OrientationEnum;
use App\Enums\HomePage\ProductSourceEnum;
use App\Enums\HomePage\SourceModeEnum;
use App\Enums\SpatieMediaCollectionName;
use App\Models\Category;
use App\Models\FeaturedSection;
use App\Models\HomeLayout;
use App\Models\HomeSection;
use App\Models\Product;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

class WebHomeLayoutSeeder extends Seeder
{
    /**
     * Run the database seeds for the web home layout.
     * Synchronizes existing app layout / featured section data from the server,
     * and seeds standard fallback sections if no existing sections are found.
     */
    public function run(): void
    {
        DB::transaction(function () {
            $appLayouts = HomeLayout::forPlatform(HomePagePlatformEnum::APP())
                ->with(['sections.items', 'sections.zones', 'sections.media', 'sections.items.media'])
                ->get();

            if ($appLayouts->isNotEmpty()) {
                $this->syncFromAppLayouts($appLayouts);
            } elseif (Schema::hasTable('featured_sections')) {
                $this->syncFromFeaturedSections();
            }

            // Ensure global web layout exists and has default sections if still empty
            $globalWebLayout = HomeLayout::firstOrCreate([
                'platform' => HomePagePlatformEnum::WEB(),
                'scope_type' => HomePageScopeEnum::GLOBAL(),
                'scope_id' => null,
            ], [
                'is_active' => true,
            ]);

            if ($globalWebLayout->sections()->count() === 0) {
                $this->seedDefaultWebSections($globalWebLayout);
            }
        });
    }

    /**
     * Replicate compatible sections from the app layout to the web layout.
     * Skips hero sections since the web platform layout does not support them.
     */
    private function syncFromAppLayouts($appLayouts): void
    {
        foreach ($appLayouts as $appLayout) {
            $webLayout = HomeLayout::firstOrCreate([
                'platform' => HomePagePlatformEnum::WEB(),
                'scope_type' => $appLayout->scope_type,
                'scope_id' => $appLayout->scope_id,
            ], [
                'is_active' => $appLayout->is_active,
            ]);

            foreach ($appLayout->sections as $section) {
                // Web layout does not support hero sections
                if ($section->type === HomeSectionTypeEnum::HERO() || $section->type === 'hero') {
                    continue;
                }

                $existingSection = $webLayout->sections()
                    ->where('type', $section->type)
                    ->where('title', $section->title)
                    ->first();

                if ($existingSection) {
                    continue;
                }

                $webSection = $webLayout->sections()->create([
                    'type' => $section->type,
                    'title' => $section->title,
                    'style' => $section->style,
                    'config' => $section->config ?? [],
                    'sort_order' => $section->sort_order,
                    'is_active' => $section->is_active,
                ]);

                if ($section->zones && $section->zones->isNotEmpty()) {
                    $webSection->zones()->sync($section->zones->pluck('id')->toArray());
                }

                foreach ($section->items as $item) {
                    $webItem = $webSection->items()->create([
                        'purpose' => $item->purpose,
                        'itemable_type' => $item->itemable_type,
                        'itemable_id' => $item->itemable_id,
                        'title' => $item->title,
                        'link_url' => $item->link_url,
                        'sort_order' => $item->sort_order,
                    ]);

                    $itemMedia = $item->getFirstMedia(SpatieMediaCollectionName::HOME_SECTION_ITEM_IMAGE());
                    if ($itemMedia && file_exists($itemMedia->getPath())) {
                        try {
                            $webItem->addMedia($itemMedia->getPath())
                                ->preservingOriginal()
                                ->toMediaCollection(SpatieMediaCollectionName::HOME_SECTION_ITEM_IMAGE());
                        } catch (\Throwable $e) {
                            Log::warning("Could not copy item media for item ID {$item->id}: {$e->getMessage()}");
                        }
                    }
                }

                $bgMedia = $section->getFirstMedia(SpatieMediaCollectionName::HOME_SECTION_BACKGROUND());
                if ($bgMedia && file_exists($bgMedia->getPath())) {
                    try {
                        $webSection->addMedia($bgMedia->getPath())
                            ->preservingOriginal()
                            ->toMediaCollection(SpatieMediaCollectionName::HOME_SECTION_BACKGROUND());
                    } catch (\Throwable $e) {
                        Log::warning("Could not copy background media for section ID {$section->id}: {$e->getMessage()}");
                    }
                }

                $cardMedia = $section->getFirstMedia(SpatieMediaCollectionName::HOME_SECTION_CARD_BACKGROUND());
                if ($cardMedia && file_exists($cardMedia->getPath())) {
                    try {
                        $webSection->addMedia($cardMedia->getPath())
                            ->preservingOriginal()
                            ->toMediaCollection(SpatieMediaCollectionName::HOME_SECTION_CARD_BACKGROUND());
                    } catch (\Throwable $e) {
                        Log::warning("Could not copy card background media for section ID {$section->id}: {$e->getMessage()}");
                    }
                }
            }
        }
    }

    /**
     * Sync legacy featured sections into the web home layout if no app layouts exist.
     */
    private function syncFromFeaturedSections(): void
    {
        $legacySections = FeaturedSection::with(['categories', 'products', 'zones', 'media'])
            ->orderBy('sort_order')
            ->get();

        foreach ($legacySections as $fs) {
            $webLayout = HomeLayout::firstOrCreate([
                'platform' => HomePagePlatformEnum::WEB(),
                'scope_type' => $fs->scope_type ?: HomePageScopeEnum::GLOBAL(),
                'scope_id' => ($fs->scope_type === 'category' && $fs->scope_id) ? $fs->scope_id : null,
            ], [
                'is_active' => true,
            ]);

            if ($webLayout->sections()->where('title', $fs->title)->exists()) {
                continue;
            }

            $source = match ($fs->section_type) {
                'custom_products' => 'custom',
                default => $fs->section_type ?: 'newly_added',
            };

            $section = $webLayout->sections()->create([
                'type' => HomeSectionTypeEnum::PRODUCTS(),
                'title' => $fs->title,
                'style' => $fs->style ?: 'horizontal_carousel',
                'sort_order' => $fs->sort_order ?: 0,
                'is_active' => $fs->status === 'active' || $fs->status === '1' || $fs->status === true,
                'config' => [
                    'source' => $source,
                    'limit' => 12,
                    'background_type' => $fs->background_type ?: BackgroundTypeEnum::COLOR(),
                    'background_color' => $fs->background_color ?: '#ffffff',
                    'text_color' => $fs->text_color ?: '#000000',
                ],
            ]);

            if ($fs->zones && $fs->zones->isNotEmpty()) {
                $section->zones()->sync($fs->zones->pluck('id')->toArray());
            }

            if ($source === 'custom' && $fs->products) {
                foreach ($fs->products as $index => $product) {
                    $section->items()->create([
                        'purpose' => ItemPurposeEnum::DISPLAY(),
                        'itemable_type' => Product::class,
                        'itemable_id' => $product->id,
                        'sort_order' => $index,
                    ]);
                }
            } elseif ($fs->categories && $fs->categories->isNotEmpty()) {
                foreach ($fs->categories as $index => $category) {
                    $section->items()->create([
                        'purpose' => ItemPurposeEnum::FILTER(),
                        'itemable_type' => Category::class,
                        'itemable_id' => $category->id,
                        'sort_order' => $index,
                    ]);
                }
            }

            $bgMedia = $fs->getFirstMedia(SpatieMediaCollectionName::FEATURED_SECTION_BACKGROUND_IMAGE());
            if ($bgMedia && file_exists($bgMedia->getPath())) {
                try {
                    $section->addMedia($bgMedia->getPath())
                        ->preservingOriginal()
                        ->toMediaCollection(SpatieMediaCollectionName::HOME_SECTION_BACKGROUND());
                } catch (\Throwable $e) {
                    Log::warning("Could not copy media for featured section ID {$fs->id}: {$e->getMessage()}");
                }
            }
        }
    }

    /**
     * Seed standard fallback web sections if the layout has no sections.
     */
    private function seedDefaultWebSections(HomeLayout $layout): void
    {
        // 1. Categories section
        $layout->sections()->create([
            'type' => HomeSectionTypeEnum::CATEGORIES(),
            'title' => 'Shop by Category',
            'style' => CategoryStyleEnum::CARD(),
            'sort_order' => 1,
            'is_active' => true,
            'config' => [
                'source' => SourceModeEnum::ALL(),
            ],
        ]);

        // 2. Promotional Banners section
        $layout->sections()->create([
            'type' => HomeSectionTypeEnum::BANNERS(),
            'title' => 'Promotions & Offers',
            'style' => BannerStyleEnum::FULL_WIDTH(),
            'sort_order' => 2,
            'is_active' => true,
            'config' => [],
        ]);

        // 3. Featured Products section
        $layout->sections()->create([
            'type' => HomeSectionTypeEnum::PRODUCTS(),
            'title' => 'Featured Products',
            'style' => 'grid',
            'sort_order' => 3,
            'is_active' => true,
            'config' => [
                'source' => ProductSourceEnum::FEATURED(),
                'limit' => 12,
                'background_type' => BackgroundTypeEnum::NONE(),
                'orientation' => OrientationEnum::VERTICAL(),
            ],
        ]);

        // 4. Popular Brands section
        $layout->sections()->create([
            'type' => HomeSectionTypeEnum::BRANDS(),
            'title' => 'Popular Brands',
            'style' => BrandStyleEnum::IMAGE_TITLE(),
            'sort_order' => 4,
            'is_active' => true,
            'config' => [
                'source' => SourceModeEnum::ALL(),
            ],
        ]);

        // 5. Best Sellers section
        $layout->sections()->create([
            'type' => HomeSectionTypeEnum::PRODUCTS(),
            'title' => 'Best Sellers',
            'style' => 'horizontal_carousel',
            'sort_order' => 5,
            'is_active' => true,
            'config' => [
                'source' => ProductSourceEnum::BEST_SELLER(),
                'limit' => 12,
                'background_type' => BackgroundTypeEnum::NONE(),
                'orientation' => OrientationEnum::HORIZONTAL(),
            ],
        ]);

        // 6. New Arrivals section
        $layout->sections()->create([
            'type' => HomeSectionTypeEnum::PRODUCTS(),
            'title' => 'New Arrivals',
            'style' => 'grid',
            'sort_order' => 6,
            'is_active' => true,
            'config' => [
                'source' => ProductSourceEnum::NEWLY_ADDED(),
                'limit' => 12,
                'background_type' => BackgroundTypeEnum::NONE(),
                'orientation' => OrientationEnum::VERTICAL(),
            ],
        ]);
    }
}
