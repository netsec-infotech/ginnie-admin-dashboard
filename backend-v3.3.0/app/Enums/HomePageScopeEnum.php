<?php

namespace App\Enums;

use ArchTech\Enums\InvokableCases;
use ArchTech\Enums\Names;
use ArchTech\Enums\Values;

/**
 * Enum values for home page scopes.
 * @method static GLOBAL()
 * @method static CATEGORY()
 * @method static NAVBAR()
 * @method static ZONE()
 * @method static ZONE_CATEGORY()
 */
enum HomePageScopeEnum: string
{
    use InvokableCases, Values, Names;

    case GLOBAL = 'global';
    case CATEGORY = 'category';
    case NAVBAR = 'navbar';
    case ZONE = 'zone';
    case ZONE_CATEGORY = 'zone_category';

    /**
     * Scope types applicable to Brands.
     *
     * @return array<string>
     */
    public static function forBrands(): array
    {
        return [
            self::GLOBAL->value,
            self::CATEGORY->value,
        ];
    }
}

