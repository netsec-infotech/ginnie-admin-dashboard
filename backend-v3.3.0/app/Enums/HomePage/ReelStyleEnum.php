<?php

namespace App\Enums\HomePage;

use ArchTech\Enums\InvokableCases;
use ArchTech\Enums\Names;
use ArchTech\Enums\Values;

/**
 * Display style for a reels section.
 *
 * @method static CARD_SLIDER()
 * @method static GRID()
 */
enum ReelStyleEnum: string
{
    use InvokableCases, Names, Values;

    case CARD_SLIDER = 'card_slider';
    case GRID = 'grid';
}
