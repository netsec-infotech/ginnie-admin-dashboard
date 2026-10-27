<?php

namespace App\Enums\WatchBuy;

use ArchTech\Enums\InvokableCases;
use ArchTech\Enums\Names;
use ArchTech\Enums\Values;

enum WatchBuyStatusContentTypeEnum: string
{
    use InvokableCases, Names, Values;

    case TEXT = 'text';
    case IMAGE = 'image';
    case VIDEO = 'video';
}
