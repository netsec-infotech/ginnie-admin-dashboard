<?php

namespace App\Enums\HomePage;

use ArchTech\Enums\InvokableCases;
use ArchTech\Enums\Names;
use ArchTech\Enums\Values;

/**
 * Background fill for a section (products/categories).
 * @method static NONE()
 * @method static COLOR()
 * @method static IMAGE()
 */
enum BackgroundTypeEnum: string
{
    use InvokableCases, Values, Names;

    case NONE = 'none';
    case COLOR = 'color';
    case IMAGE = 'image';
}
