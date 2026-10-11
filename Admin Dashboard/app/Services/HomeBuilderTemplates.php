<?php

namespace App\Services;

use App\Enums\HomePage\HomeSectionTypeEnum;

class HomeBuilderTemplates
{
    /** Section preset templates categorized by section type. */
    public function sectionTemplates(string $platform = 'app'): array
    {
        $templates = [
            [
                'id' => 'hero_flash_sale',
                'category' => 'hero',
                'title' => 'Flash Sale & Limited Deals',
                'description' => 'Eye-catching hero section with styled item cards, vibrant background, and category item source.',
                'icon' => 'ti-flame',
                'badge' => 'Hot Deal',
                'type' => HomeSectionTypeEnum::HERO(),
                'style' => 'image_title',
                'config' => [
                    'media_type' => 'image',
                    'layout' => 'items',
                    'item_source' => 'categories',
                    'card_bg_type' => 'color',
                    'card_bg_color' => '#FFF3E0',
                    'card_title_color' => '#E65100',
                ],
                'title_default' => '🔥 Flash Deals & Discounts',
                'items' => [
                    ['title' => 'Up to 50% Off', 'purpose' => 'display'],
                    ['title' => 'Trending Deals', 'purpose' => 'display'],
                    ['title' => 'Limited Stock', 'purpose' => 'display'],
                ],
            ],
            [
                'id' => 'hero_minimal',
                'category' => 'hero',
                'title' => 'Minimalist Brand Showcase',
                'description' => 'Full-bleed banner header with subtle dark contrast typography.',
                'icon' => 'ti-layout-grid-add',
                'badge' => 'Minimal',
                'type' => HomeSectionTypeEnum::HERO(),
                'style' => 'full',
                'config' => [
                    'media_type' => 'image',
                    'layout' => 'none',
                    'item_source' => 'categories',
                    'card_bg_type' => 'color',
                    'card_bg_color' => '#F1EFE8',
                    'card_title_color' => '#111827',
                ],
                'title_default' => 'Discover New Arrivals',
                'items' => [],
            ],
            [
                'id' => 'products_horizontal_reel',
                'category' => 'products',
                'title' => 'Top Rated Product Reel',
                'description' => 'Smooth horizontal product carousel featuring top rated items.',
                'icon' => 'ti-shopping-bag',
                'badge' => 'Popular',
                'type' => HomeSectionTypeEnum::PRODUCTS(),
                'style' => null,
                'config' => [
                    'source' => 'top_rated',
                    'orientation' => 'horizontal',
                    'background_type' => 'color',
                    'background_color' => '#F8FAFC',
                ],
                'title_default' => '⭐ Top Rated Products',
                'items' => [],
            ],
            [
                'id' => 'products_best_sellers',
                'category' => 'products',
                'title' => 'Best Sellers Grid',
                'description' => 'Vertical multi-row grid layout showcasing highest selling products.',
                'icon' => 'ti-trophy',
                'badge' => 'Best Seller',
                'type' => HomeSectionTypeEnum::PRODUCTS(),
                'style' => null,
                'config' => [
                    'source' => 'best_seller',
                    'orientation' => 'vertical',
                    'background_type' => 'none',
                ],
                'title_default' => '🏆 Best Selling Products',
                'items' => [],
            ],
            [
                'id' => 'categories_overlay_grid',
                'category' => 'categories',
                'title' => 'Category Tile Grid',
                'description' => 'Modern tile layout with text overlay and rounded category cards.',
                'icon' => 'ti-category',
                'badge' => 'Grid',
                'type' => HomeSectionTypeEnum::CATEGORIES(),
                'style' => 'overlay',
                'config' => [
                    'source' => 'all',
                ],
                'title_default' => 'Explore Categories',
                'items' => [],
            ],
            [
                'id' => 'brands_strip',
                'category' => 'brands',
                'title' => 'Brand Partner Carousel',
                'description' => 'Clean horizontal logo strip for featured brand partners.',
                'icon' => 'ti-building-store',
                'badge' => 'Brands',
                'type' => HomeSectionTypeEnum::BRANDS(),
                'style' => 'image_title',
                'config' => [
                    'source' => 'all',
                ],
                'title_default' => 'Featured Brands',
                'items' => [],
            ],
            [
                'id' => 'banners_peek_carousel',
                'category' => 'banners',
                'title' => 'Peek Carousel Banner Strip',
                'description' => 'Side-peeking promotional banner slider with multiple slides.',
                'icon' => 'ti-layout-board',
                'badge' => 'Banners',
                'type' => HomeSectionTypeEnum::BANNERS(),
                'style' => 'peek',
                'config' => [],
                'title_default' => '',
                'items' => [
                    ['title' => 'Promo Slide 1', 'purpose' => 'display', 'link_url' => 'https://example.com'],
                    ['title' => 'Promo Slide 2', 'purpose' => 'display', 'link_url' => 'https://example.com'],
                ],
            ],
        ];

        if ($platform === 'web') {
            $templates = array_values(array_filter($templates, fn ($t) => $t['type'] !== HomeSectionTypeEnum::HERO()));
        }

        return $templates;
    }

    /** Full home page theme quick-start presets. */
    public function layoutThemes(string $platform = 'app'): array
    {
        $themes = [
            [
                'id' => 'theme_superstore',
                'title' => '🏬 E-Commerce Superstore',
                'description' => 'Complete high-converting layout with Hero Deals, Category Grid, Best Seller Reel, and Brand Carousel.',
                'badge' => 'Recommended',
                'sections' => [
                    [
                        'type' => HomeSectionTypeEnum::HERO(),
                        'style' => 'image_title',
                        'title' => '🔥 Big Savings Today',
                        'config' => ['media_type' => 'image', 'layout' => 'items', 'item_source' => 'categories', 'card_bg_type' => 'color', 'card_bg_color' => '#FFF3E0', 'card_title_color' => '#E65100'],
                        'is_active' => true,
                        'items' => [
                            ['title' => 'Up to 50% Off', 'purpose' => 'display'],
                            ['title' => 'Daily Deals', 'purpose' => 'display'],
                        ],
                    ],
                    [
                        'type' => HomeSectionTypeEnum::CATEGORIES(),
                        'style' => 'overlay',
                        'title' => 'Explore Categories',
                        'config' => ['source' => 'all'],
                        'is_active' => true,
                        'items' => [],
                    ],
                    [
                        'type' => HomeSectionTypeEnum::PRODUCTS(),
                        'style' => null,
                        'title' => '⭐ Top Rated Items',
                        'config' => ['source' => 'top_rated', 'orientation' => 'horizontal', 'background_type' => 'none'],
                        'is_active' => true,
                        'items' => [],
                    ],
                    [
                        'type' => HomeSectionTypeEnum::BANNERS(),
                        'style' => 'peek',
                        'title' => '',
                        'config' => [],
                        'is_active' => true,
                        'items' => [
                            ['title' => 'Special Promo', 'purpose' => 'display', 'link_url' => 'https://example.com'],
                        ],
                    ],
                    [
                        'type' => HomeSectionTypeEnum::BRANDS(),
                        'style' => 'image_title',
                        'title' => 'Top Brands',
                        'config' => ['source' => 'all'],
                        'is_active' => true,
                        'items' => [],
                    ],
                ],
            ],
            [
                'id' => 'theme_flash_deals',
                'title' => '⚡ Flash Deal Express',
                'description' => 'Promotional layout built for flash sales, newly added products, and banner announcements.',
                'badge' => 'Flash Sale',
                'sections' => [
                    [
                        'type' => HomeSectionTypeEnum::BANNERS(),
                        'style' => 'full',
                        'title' => '',
                        'config' => [],
                        'is_active' => true,
                        'items' => [
                            ['title' => 'Flash Sale Event', 'purpose' => 'display', 'link_url' => 'https://example.com'],
                        ],
                    ],
                    [
                        'type' => HomeSectionTypeEnum::PRODUCTS(),
                        'style' => null,
                        'title' => '🔥 Newly Added Deals',
                        'config' => ['source' => 'newly_added', 'orientation' => 'horizontal', 'background_type' => 'color', 'background_color' => '#FFF8E1'],
                        'is_active' => true,
                        'items' => [],
                    ],
                    [
                        'type' => HomeSectionTypeEnum::CATEGORIES(),
                        'style' => 'default',
                        'title' => 'Shop by Category',
                        'config' => ['source' => 'all'],
                        'is_active' => true,
                        'items' => [],
                    ],
                ],
            ],
        ];

        if ($platform === 'web') {
            foreach ($themes as &$t) {
                $t['sections'] = array_values(array_filter($t['sections'], fn ($s) => $s['type'] !== HomeSectionTypeEnum::HERO()));
            }
        }

        return $themes;
    }
}
