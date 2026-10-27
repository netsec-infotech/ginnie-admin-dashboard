<?php

namespace App\Enums;

use ArchTech\Enums\InvokableCases;
use ArchTech\Enums\Names;
use ArchTech\Enums\Values;

/**
 * @method static RUNNING()
 * @method static SUCCESS()
 * @method static FAILED()
 */
enum CommandRunStatusEnum: string
{
    use InvokableCases, Values, Names;

    case RUNNING = 'running';
    case SUCCESS = 'success';
    case FAILED  = 'failed';
}
