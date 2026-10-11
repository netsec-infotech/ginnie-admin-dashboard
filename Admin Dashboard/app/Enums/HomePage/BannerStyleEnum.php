<?php

namespace App\Enums\HomePage;

use ArchTech\Enums\InvokableCases;
use ArchTech\Enums\Names;
use ArchTech\Enums\Values;

/**
 * Layout style for a banners section.
 * @method static FULL_WIDTH()
 * @method static PEEK()
 */
enum BannerStyleEnum: string
{
    use InvokableCases, Values, Names;

    case FULL_WIDTH = 'full';
    case PEEK = 'peek';
}
