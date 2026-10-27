<?php

namespace App\Enums\Order;

use ArchTech\Enums\InvokableCases;
use ArchTech\Enums\Names;
use ArchTech\Enums\Values;

/**
 * Responsible party for return rejection at store inspection.
 *
 * @method static CUSTOMER()
 * @method static RIDER()
 */
enum ReturnResponsiblePartyEnum: string
{
    use InvokableCases, Names, Values;

    case CUSTOMER = 'customer';
    case RIDER = 'rider';
}
