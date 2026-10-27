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
            $this->playImageTemplate('campaign_full_width', 'Full-width Campaign', 'One responsive promotional image spanning the complete row.', 'Campaign', [
                $this->playImageRow('grid', 0, 0, 18),
            ], [$this->playImageItem(0, 12, 12, '16:9')]),
            $this->playImageTemplate('campaign_split', 'Promotional Split', 'Two promotional images that stack naturally on small screens.', 'Split', [
                $this->playImageRow('grid', 12, 12, 18, 'lift'),
            ], [$this->playImageItem(0, 6, 12, '4:3'), $this->playImageItem(0, 6, 12, '4:3')]),
            $this->playImageTemplate('campaign_offer_grid', 'Offer Image Grid', 'Four promotional image tiles with responsive wrapping.', 'Offers', [
                $this->playImageRow('grid', 12, 12, 16, 'lift'),
            ], array_fill(0, 4, $this->playImageItem(0, 3, 6, '1:1'))),
            $this->playImageTemplate('campaign_story_carousel', 'Story Image Carousel', 'Scrollable campaign images with bullets and autoplay.', 'Story', [
                $this->playImageRow('scroll', 12, 12, 18, 'zoom', 3, 1, true),
            ], array_fill(0, 4, $this->playImageItem(0, 4, 12, '16:9'))),
            [
                'id' => 'stories_avatar_circles',
                'category' => 'stories',
                'title' => 'Seller Stories Tray',
                'description' => 'Instagram-style circular status bubbles with vibrant ring borders and seller avatar previews.',
                'icon' => 'ti-history',
                'badge' => 'Popular',
                'type' => HomeSectionTypeEnum::STORIES(),
                'style' => 'avatar_circle',
                'config' => [
                    'limit' => 10,
                    'ring_color' => '#E1306C',
                    'show_profile_name' => true,
                    'background_type' => 'none',
                ],
                'title_default' => 'Recent Stories',
                'items' => [],
            ],
            [
                'id' => 'reels_trending_slider',
                'category' => 'reels',
                'title' => 'Trending Video Reels',
                'description' => '9:16 vertical video carousel featuring trending products & seller videos.',
                'icon' => 'ti-video',
                'badge' => 'Trending',
                'type' => HomeSectionTypeEnum::REELS(),
                'style' => 'card_slider',
                'config' => [
                    'source' => 'trending',
                    'show_likes' => true,
                    'show_title' => true,
                    'limit' => 8,
                    'background_type' => 'none',
                ],
                'title_default' => '🎬 Watch & Buy Reels',
                'items' => [],
            ],
            [
                'id' => 'reels_video_grid',
                'category' => 'reels',
                'title' => 'Video Shopping Grid',
                'description' => 'Multi-column grid showcase of latest seller reels.',
                'icon' => 'ti-video',
                'badge' => 'Video',
                'type' => HomeSectionTypeEnum::REELS(),
                'style' => 'grid',
                'config' => [
                    'source' => 'latest',
                    'show_likes' => true,
                    'show_title' => true,
                    'limit' => 6,
                    'background_type' => 'none',
                ],
                'title_default' => 'Latest Reels',
                'items' => [],
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
                        'type' => HomeSectionTypeEnum::PLAY_IMAGE(),
                        'style' => 'scroll',
                        'title' => '',
                        'config' => ['rows' => [$this->playImageRow('scroll', 12, 12, 18, 'zoom', 3, 1, true)]],
                        'is_active' => true,
                        'items' => [
                            $this->playImageItem(0, 4, 12, '16:9', 'Special Promo', 'https://example.com'),
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
                        'type' => HomeSectionTypeEnum::PLAY_IMAGE(),
                        'style' => 'grid',
                        'title' => '',
                        'config' => ['rows' => [$this->playImageRow('grid', 0, 0, 18)]],
                        'is_active' => true,
                        'items' => [
                            $this->playImageItem(0, 12, 12, '16:9', 'Flash Sale Event', 'https://example.com'),
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

    private function playImageTemplate(string $id, string $title, string $description, string $badge, array $rows, array $items): array
    {
        return [
            'id' => $id,
            'category' => 'play_image',
            'title' => $title,
            'description' => $description,
            'icon' => 'ti-layout-grid-add',
            'badge' => $badge,
            'type' => HomeSectionTypeEnum::PLAY_IMAGE(),
            'style' => $rows[0]['style'] ?? 'grid',
            'config' => ['rows' => $rows],
            'title_default' => '',
            'items' => $items,
        ];
    }

    private function playImageRow(
        string $style,
        int $outerPadding,
        int $gap,
        int $radius,
        string $hover = 'none',
        float $desktopItems = 1,
        float $mobileItems = 1,
        bool $autoplay = false,
    ): array {
        return [
            'style' => $style,
            'container_width' => 'container',
            'outer_padding' => $outerPadding,
            'outer_padding_custom' => '',
            'grid_gap' => $gap,
            'border_radius' => $radius,
            'hover_effect' => $hover,
            'background_type' => 'none',
            'background_color' => '#ffffff',
            'background_image' => null,
            'items_to_show' => $desktopItems,
            'items_to_show_mobile' => $mobileItems,
            'show_scroll_buttons' => true,
            'show_bullets' => $autoplay,
            'auto_scroll' => $autoplay,
            'auto_scroll_time' => 4,
        ];
    }

    private function playImageItem(
        int $row,
        int $desktopColumns,
        int $mobileColumns,
        string $aspectRatio,
        string $title = 'Upload image',
        ?string $linkUrl = null,
    ): array {
        return [
            'title' => $title,
            'purpose' => 'display',
            'link_url' => $linkUrl,
            'config' => [
                'row_index' => $row,
                'media_type' => 'image',
                'link_type' => $linkUrl ? 'custom' : 'none',
                'cols' => $desktopColumns,
                'cols_mobile' => $mobileColumns,
                'aspect_ratio' => $aspectRatio,
                'object_fit' => 'cover',
                'object_position' => 'center',
                'alt' => '',
            ],
        ];
    }
}
