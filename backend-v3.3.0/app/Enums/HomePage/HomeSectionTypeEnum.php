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
 * @method static PLAY_IMAGE()
 * @method static STORIES()
 * @method static REELS()
 */
enum HomeSectionTypeEnum: string
{
    use InvokableCases, Values, Names;

    case HERO = 'hero';
    case PRODUCTS = 'products';
    case CATEGORIES = 'categories';
    case BRANDS = 'brands';
    case BANNERS = 'banners';
    case PLAY_IMAGE = 'play_image';
    case STORIES = 'stories';
    case REELS = 'reels';
}
