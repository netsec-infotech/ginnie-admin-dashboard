<?php

namespace App\Enums;

use ArchTech\Enums\InvokableCases;
use ArchTech\Enums\Names;
use ArchTech\Enums\Values;

/**
 * @method static SCHEDULE()
 * @method static MANUAL()
 */
enum CommandRunTriggerEnum: string
{
    use InvokableCases, Values, Names;

    case SCHEDULE = 'schedule';
    case MANUAL   = 'manual';
}
