<?php

namespace App\Enums\HomePage;

use ArchTech\Enums\InvokableCases;
use ArchTech\Enums\Names;
use ArchTech\Enums\Values;

/**
 * Card style for a brands section.
 * @method static FULL()
 * @method static IMAGE_TITLE()
 */
enum BrandStyleEnum: string
{
    use InvokableCases, Values, Names;

    case FULL = 'full';
    case IMAGE_TITLE = 'image_title';
    case CUSTOM_IMAGE = 'custom_image';
}
