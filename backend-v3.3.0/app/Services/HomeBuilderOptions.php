<?php

namespace App\Services;

use App\Enums\HomePage\BackgroundTypeEnum;
use App\Enums\HomePage\BrandStyleEnum;
use App\Enums\HomePage\CategoryStyleEnum;
use App\Enums\HomePage\HeroItemStyleEnum;
use App\Enums\HomePage\HeroLayoutEnum;
use App\Enums\HomePage\HeroMediaTypeEnum;
use App\Enums\HomePage\HomePagePlatformEnum;
use App\Enums\HomePage\HomeSectionTypeEnum;
use App\Enums\HomePage\ItemSourceEnum;
use App\Enums\HomePage\OrientationEnum;
use App\Enums\HomePage\ProductSourceEnum;
use App\Enums\HomePage\ReelSourceEnum;
use App\Enums\HomePage\ReelStyleEnum;
use App\Enums\HomePage\SourceModeEnum;
use App\Enums\HomePage\StoryStyleEnum;

class HomeBuilderOptions
{
    public function specsFor(string $platform): array
    {
        $specs = [
            HomeSectionTypeEnum::HERO() => [
                'styles' => HeroItemStyleEnum::values(),
                'config' => [
                    'media_type'  => HeroMediaTypeEnum::values(),
                    'layout'      => HeroLayoutEnum::values(),
                    'item_source' => ItemSourceEnum::values(),
                ],
            ],
            HomeSectionTypeEnum::PRODUCTS() => [
                'styles' => null,
                'config' => [
                    'source'                 => ProductSourceEnum::values(),
                    'orientation'            => OrientationEnum::values(),
                    'background_type'        => BackgroundTypeEnum::values(),
                    'background_image_style' => ['full', 'half'],
                ],
            ],
            HomeSectionTypeEnum::CATEGORIES() => [
                'styles' => CategoryStyleEnum::values(),
                'config' => [
                    'source' => SourceModeEnum::values(),
                    'rows_layout' => ['row', 'grid'],
                    'grid_columns' => ['1', '2', '3', '4'],
                    'background_type' => BackgroundTypeEnum::values(),
                ],
            ],
            HomeSectionTypeEnum::BRANDS() => [
                'styles' => BrandStyleEnum::values(),
                'config' => [
                    'source' => SourceModeEnum::values(),
                    'rows_layout' => ['row', 'grid'],
                    'grid_columns' => ['1', '2', '3', '4'],
                    'background_type' => BackgroundTypeEnum::values(),
                ],
            ],
            HomeSectionTypeEnum::PLAY_IMAGE() => [
                'styles' => ['grid', 'scroll'],
                'config' => [
                    'hover_effect' => ['none', 'zoom', 'lift', 'opacity'],
                ],
            ],
            HomeSectionTypeEnum::STORIES() => [
                'styles' => StoryStyleEnum::values(),
                'config' => [
                    'background_type' => BackgroundTypeEnum::values(),
                ],
            ],
            HomeSectionTypeEnum::REELS() => [
                'styles' => ReelStyleEnum::values(),
                'config' => [
                    'source' => ReelSourceEnum::values(),
                    'background_type' => BackgroundTypeEnum::values(),
                ],
            ],
        ];

        return $specs;
    }

    public function sectionTypes(string $platform): array
    {
        return array_keys($this->specsFor($platform));
    }

    public function stylesFor(string $platform, string $type): ?array
    {
        return $this->specsFor($platform)[$type]['styles'] ?? null;
    }

    public function configValues(string $platform, string $type, string $field): ?array
    {
        return $this->specsFor($platform)[$type]['config'][$field] ?? null;
    }

    public function uiOptions(string $platform): array
    {
        return [
            'platform'        => $platform,
            'section_types'   => $this->sectionTypes($platform),
            'hero_media'      => HeroMediaTypeEnum::values(),
            'hero_layout'     => HeroLayoutEnum::values(),
            'item_source'     => ItemSourceEnum::values(),
            'hero_item_style' => HeroItemStyleEnum::values(),
            'product_source'  => ProductSourceEnum::values(),
            'orientation'     => OrientationEnum::values(),
            'background_type' => BackgroundTypeEnum::values(),
            'source_mode'     => SourceModeEnum::values(),
            'category_style'  => CategoryStyleEnum::values(),
            'brand_style'     => BrandStyleEnum::values(),
            'hover_effects'   => ['none', 'zoom', 'lift', 'opacity'],
            'story_styles'    => StoryStyleEnum::values(),
            'reel_styles'     => ReelStyleEnum::values(),
            'reel_sources'    => ReelSourceEnum::values(),
        ];
    }
}
