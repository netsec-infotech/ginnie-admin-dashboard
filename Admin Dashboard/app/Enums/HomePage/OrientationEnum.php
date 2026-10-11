<?php

namespace App\Enums\HomePage;

use ArchTech\Enums\InvokableCases;
use ArchTech\Enums\Names;
use ArchTech\Enums\Values;

/**
 * Scroll direction for a products section.
 * @method static VERTICAL()
 * @method static HORIZONTAL()
 */
enum OrientationEnum: string
{
    use InvokableCases, Values, Names;

    case VERTICAL = 'vertical';
    case HORIZONTAL = 'horizontal';
}
