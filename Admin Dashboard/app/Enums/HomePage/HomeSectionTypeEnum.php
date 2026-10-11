<?php

namespace App\Enums\HomePage;

use ArchTech\Enums\InvokableCases;
use ArchTech\Enums\Names;
use ArchTech\Enums\Values;

/**
 * The kind of block a home section renders.
 * @method static HERO()
 * @method static PRODUCTS()
 * @method static CATEGORIES()
 * @method static BRANDS()
 * @method static BANNERS()
 */
enum HomeSectionTypeEnum: string
{
    use InvokableCases, Values, Names;

    case HERO = 'hero';
    case PRODUCTS = 'products';
    case CATEGORIES = 'categories';
    case BRANDS = 'brands';
    case BANNERS = 'banners';
}
