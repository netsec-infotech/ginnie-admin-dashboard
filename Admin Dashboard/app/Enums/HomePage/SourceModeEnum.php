<?php

namespace App\Enums\HomePage;

use ArchTech\Enums\InvokableCases;
use ArchTech\Enums\Names;
use ArchTech\Enums\Values;

/**
 * Whether a categories/brands section pulls all records or a custom pick.
 * @method static ALL()
 * @method static CUSTOM()
 */
enum SourceModeEnum: string
{
    use InvokableCases, Values, Names;

    case ALL = 'all';
    case CUSTOM = 'custom';
}
