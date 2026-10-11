<?php

namespace App\Enums\HomePage;

use ArchTech\Enums\InvokableCases;
use ArchTech\Enums\Names;
use ArchTech\Enums\Values;

/**
 * What entity a hero section's items point at.
 * @method static PRODUCTS()
 * @method static BRANDS()
 * @method static CATEGORIES()
 */
enum ItemSourceEnum: string
{
    use InvokableCases, Values, Names;

    case PRODUCTS = 'products';
    case BRANDS = 'brands';
    case CATEGORIES = 'categories';
}
