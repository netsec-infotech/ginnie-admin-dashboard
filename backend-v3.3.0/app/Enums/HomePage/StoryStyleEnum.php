<?php

namespace App\Enums\HomePage;

use ArchTech\Enums\InvokableCases;
use ArchTech\Enums\Names;
use ArchTech\Enums\Values;

/**
 * Display style for a stories section.
 *
 * @method static AVATAR_CIRCLE()
 */
enum StoryStyleEnum: string
{
    use InvokableCases, Names, Values;

    case AVATAR_CIRCLE = 'avatar_circle';
}
