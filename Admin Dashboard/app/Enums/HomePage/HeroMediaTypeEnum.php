<?php

namespace App\Enums\HomePage;

use ArchTech\Enums\InvokableCases;
use ArchTech\Enums\Names;
use ArchTech\Enums\Values;

/**
 * Background media kind for a hero section.
 * @method static IMAGE()
 * @method static LOTTIE()
 */
enum HeroMediaTypeEnum: string
{
    use InvokableCases, Values, Names;

    case IMAGE = 'image';
    case LOTTIE = 'lottie';
}
