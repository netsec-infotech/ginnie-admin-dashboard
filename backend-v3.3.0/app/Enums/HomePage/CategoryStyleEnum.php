<?php

namespace App\Enums\HomePage;

use ArchTech\Enums\InvokableCases;
use ArchTech\Enums\Names;
use ArchTech\Enums\Values;

/**
 * Card style for a categories section.
 * @method static FULL()
 * @method static CARD()
 * @method static OVERLAY()
 */
enum CategoryStyleEnum: string
{
    use InvokableCases, Values, Names;

    case FULL = 'full';
    case CARD = 'card';
    case OVERLAY = 'overlay';
    case CUSTOM_IMAGE = 'custom_image';

    case Default = 'default';
}
