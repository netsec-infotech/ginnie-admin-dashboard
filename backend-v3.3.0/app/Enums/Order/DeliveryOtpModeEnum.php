<?php

namespace App\Enums\Order;

use ArchTech\Enums\InvokableCases;
use ArchTech\Enums\Names;
use ArchTech\Enums\Options;
use ArchTech\Enums\Values;

/**
 * @method static string ITEM_WISE()
 * @method static string ORDER_WISE()
 */
enum DeliveryOtpModeEnum: string
{
    use InvokableCases;
    use Names;
    use Options;
    use Values;

    case ITEM_WISE = 'item_wise';
    case ORDER_WISE = 'order_wise';
}
