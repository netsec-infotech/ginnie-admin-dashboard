<?php

namespace App\Enums\HomePage;

use ArchTech\Enums\InvokableCases;
use ArchTech\Enums\Names;
use ArchTech\Enums\Values;

/**
 * Whether a hero section shows items or is a plain banner.
 * @method static BANNER_ONLY()
 * @method static WITH_ITEMS()
 */
enum HeroLayoutEnum: string
{
    use InvokableCases, Values, Names;

    case BANNER_ONLY = 'none';
    case WITH_ITEMS = 'items';
}
