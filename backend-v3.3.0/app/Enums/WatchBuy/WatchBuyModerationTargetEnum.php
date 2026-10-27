<?php

namespace App\Enums\WatchBuy;

use ArchTech\Enums\InvokableCases;
use ArchTech\Enums\Names;
use ArchTech\Enums\Values;

enum WatchBuyModerationTargetEnum: string
{
    use InvokableCases, Names, Values;

    case PROFILE = 'profile';
    case REEL = 'reel';
}
