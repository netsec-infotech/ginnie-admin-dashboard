<?php

namespace App\Enums\HomePage;

use ArchTech\Enums\InvokableCases;
use ArchTech\Enums\Names;
use ArchTech\Enums\Values;

/**
 * Which storefront a home layout drives.
 * @method static APP()
 * @method static WEB()
 */
enum HomePagePlatformEnum: string
{
    use InvokableCases, Values, Names;

    case APP = 'app';
    case WEB = 'web';
}
