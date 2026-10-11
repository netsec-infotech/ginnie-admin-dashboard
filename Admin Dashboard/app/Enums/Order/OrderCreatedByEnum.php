<?php

namespace App\Enums\Order;

use ArchTech\Enums\InvokableCases;
use ArchTech\Enums\Names;
use ArchTech\Enums\Values;

/**
 * Identifies the actor that initiated an order.
 *
 * @method static CUSTOMER()
 * @method static SELLER()
 * @method static ADMIN()
 */
enum OrderCreatedByEnum: string
{
    use InvokableCases, Names, Values;

    case CUSTOMER = 'customer';
    case SELLER = 'seller';
    case ADMIN = 'admin';
}
