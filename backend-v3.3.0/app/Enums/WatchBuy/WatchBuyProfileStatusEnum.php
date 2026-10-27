<?php

namespace App\Enums\WatchBuy;

use ArchTech\Enums\InvokableCases;
use ArchTech\Enums\Names;
use ArchTech\Enums\Values;

enum WatchBuyProfileStatusEnum: string
{
    use InvokableCases, Names, Values;

    case ACTIVE = 'active';
    case SUSPENDED = 'suspended';
}
