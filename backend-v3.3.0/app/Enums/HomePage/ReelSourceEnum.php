<?php

namespace App\Enums\HomePage;

use ArchTech\Enums\InvokableCases;
use ArchTech\Enums\Names;
use ArchTech\Enums\Values;

/**
 * Feed source for a reels section.
 *
 * @method static TRENDING()
 * @method static LATEST()
 * @method static MOST_LIKED()
 */
enum ReelSourceEnum: string
{
    use InvokableCases, Names, Values;

    case TRENDING = 'trending';
    case LATEST = 'latest';
    case MOST_LIKED = 'most_liked';
}
