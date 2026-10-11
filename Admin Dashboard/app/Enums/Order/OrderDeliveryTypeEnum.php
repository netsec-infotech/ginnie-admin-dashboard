<?php

namespace App\Enums\Order;

use ArchTech\Enums\InvokableCases;
use ArchTech\Enums\Names;
use ArchTech\Enums\Options;
use ArchTech\Enums\Values;

/**
 * @method static string DELIVERY()
 * @method static string PICKUP()
 */
enum OrderDeliveryTypeEnum: string
{
    use InvokableCases;
    use Names;
    use Options;
    use Values;

    case DELIVERY = 'delivery';
    case PICKUP = 'pickup';
}
