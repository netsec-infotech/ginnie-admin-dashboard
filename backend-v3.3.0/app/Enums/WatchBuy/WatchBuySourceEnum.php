<?php

namespace App\Enums\WatchBuy;

use ArchTech\Enums\InvokableCases;
use ArchTech\Enums\Names;
use ArchTech\Enums\Values;

enum WatchBuySourceEnum: string
{
    use InvokableCases, Names, Values;

    case SELLER = 'seller';
    case SELLER_BEHALF = 'seller_behalf';
    case ADMIN_DIRECT = 'admin_direct';
}
