<?php

namespace App\Enums;

use ArchTech\Enums\InvokableCases;
use ArchTech\Enums\Names;
use ArchTech\Enums\Values;

enum LanguageSurfaceEnum: string
{
    use InvokableCases, Names, Values;

    case PANEL = 'panel';
    case WEB = 'web';
    case CUSTOMER_APP = 'customer_app';
    case SELLER_APP = 'seller_app';
    case RIDER_APP = 'rider_app';

    public static function clientValues(): array
    {
        return [self::WEB->value, self::CUSTOMER_APP->value, self::SELLER_APP->value, self::RIDER_APP->value];
    }

    public function label(): string
    {
        return match ($this) {
            self::PANEL => __('labels.panel_languages'),
            self::WEB => __('labels.website_languages'),
            self::CUSTOMER_APP => __('labels.customer_app_languages'),
            self::SELLER_APP => __('labels.seller_app_languages'),
            self::RIDER_APP => __('labels.rider_app_languages'),
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::PANEL => __('labels.panel_languages_description'),
            self::WEB => __('labels.website_languages_description'),
            self::CUSTOMER_APP => __('labels.customer_app_languages_description'),
            self::SELLER_APP => __('labels.seller_app_languages_description'),
            self::RIDER_APP => __('labels.rider_app_languages_description'),
        };
    }
}

