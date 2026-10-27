<?php

namespace App\Enums;

use ArchTech\Enums\InvokableCases;
use ArchTech\Enums\Names;
use ArchTech\Enums\Values;

/**
 * @method static CURRENT()
 * @method static STAGED_NEXT()
 * @method static ARCHIVED()
 */
enum SplashSlotEnum: string
{
    use InvokableCases, Names, Values;

    case CURRENT = 'current';
    case STAGED_NEXT = 'staged_next';
    case ARCHIVED = 'archived';
}
