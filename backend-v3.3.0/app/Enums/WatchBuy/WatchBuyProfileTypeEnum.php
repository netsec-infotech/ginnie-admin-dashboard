<?php

namespace App\Enums\WatchBuy;

use ArchTech\Enums\InvokableCases;
use ArchTech\Enums\Names;
use ArchTech\Enums\Values;

enum WatchBuyProfileTypeEnum: string
{
    use InvokableCases, Names, Values;

    case PLATFORM = 'platform';
    case SELLER = 'seller';
}
