<?php

namespace App\Enums\Advertisement;

use ArchTech\Enums\InvokableCases;
use ArchTech\Enums\Names;
use ArchTech\Enums\Values;

/**
 * What happens to ACTIVE campaigns when admin flips off the master ads toggle.
 *
 *  - KEEP_RUNNING → block new creates and top-ups; existing campaigns continue spending.
 *  - PAUSE_ALL    → flip every ACTIVE campaign to PAUSED_BY_ADMIN immediately.
 *
 * @method static KEEP_RUNNING()
 * @method static PAUSE_ALL()
 */
enum AdsDisableBehaviorEnum: string
{
    use InvokableCases, Values, Names;

    case KEEP_RUNNING = 'keep_running';
    case PAUSE_ALL = 'pause_all';
}
