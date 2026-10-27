<?php

namespace App\Enums\WatchBuy;

use ArchTech\Enums\InvokableCases;
use ArchTech\Enums\Names;
use ArchTech\Enums\Values;

enum WatchBuyModerationActionEnum: string
{
    use InvokableCases, Names, Values;

    case SUSPEND = 'suspend';
    case UNSUSPEND = 'unsuspend';
    case BLOCK = 'block';
    case UNBLOCK = 'unblock';
    case DELETE = 'delete';
}
