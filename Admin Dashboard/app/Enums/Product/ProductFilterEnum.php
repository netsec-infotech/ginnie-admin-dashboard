<?php

namespace App\Enums\Product;

use ArchTech\Enums\InvokableCases;
use ArchTech\Enums\Names;
use ArchTech\Enums\Values;

enum ProductFilterEnum: string
{
    use InvokableCases, Names, Values;

    case FEATURED   = 'featured';
    case HAS_BADGE  = 'has_badge';
    case LOW_STOCK  = 'low_stock';
    case OUT_OF_STOCK = 'out_of_stock';
}
