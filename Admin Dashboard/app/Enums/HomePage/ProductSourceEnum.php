<?php

namespace App\Enums\HomePage;

use ArchTech\Enums\InvokableCases;
use ArchTech\Enums\Names;
use ArchTech\Enums\Values;

/**
 * How a products section resolves its product list.
 * @method static NEWLY_ADDED()
 * @method static TOP_RATED()
 * @method static BEST_SELLER()
 * @method static FEATURED()
 * @method static RECOMMENDED()
 * @method static CUSTOM()
 */
enum ProductSourceEnum: string
{
    use InvokableCases, Values, Names;

    case NEWLY_ADDED = 'newly_added';
    case TOP_RATED = 'top_rated';
    case BEST_SELLER = 'best_seller';
    case FEATURED = 'featured';
    case RECOMMENDED = 'recommended';
    case CUSTOM = 'custom';
}
