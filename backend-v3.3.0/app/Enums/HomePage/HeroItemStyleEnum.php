<?php

namespace App\Enums\HomePage;

use ArchTech\Enums\InvokableCases;
use ArchTech\Enums\Names;
use ArchTech\Enums\Values;

/**
 * Card style for hero items.
 * @method static FULL()
 * @method static IMAGE_TITLE()
 */
enum HeroItemStyleEnum: string
{
    use InvokableCases, Values, Names;

    case FULL = 'full';
    case IMAGE_TITLE = 'image_title';
}
