<?php

namespace App\Enums\Advertisement;

use ArchTech\Enums\InvokableCases;
use ArchTech\Enums\Names;
use ArchTech\Enums\Values;

/**
 * Event types tracked per campaign for billing and stats.
 *
 * @method static CLICK()
 * @method static IMPRESSION()
 */
enum AdEventTypeEnum: string
{
    use InvokableCases, Values, Names;

    case CLICK       = 'click';
    case IMPRESSION  = 'impression';
}
