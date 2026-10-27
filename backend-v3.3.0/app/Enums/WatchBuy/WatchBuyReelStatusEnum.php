<?php

namespace App\Enums\WatchBuy;

use ArchTech\Enums\InvokableCases;
use ArchTech\Enums\Names;
use ArchTech\Enums\Values;

enum WatchBuyReelStatusEnum: string
{
    use InvokableCases, Names, Values;

    case PUBLISHED = 'published';
    case UNPUBLISHED = 'unpublished';
    case BLOCKED = 'blocked';
}
