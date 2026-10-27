<?php

namespace App\Enums\Order;

use ArchTech\Enums\InvokableCases;
use ArchTech\Enums\Names;
use ArchTech\Enums\Values;

/**
 * Identifies who closed an order item return — the seller themselves or an
 * admin acting on their behalf. Persisted on order_item_returns.decided_by_type.
 *
 * @method static SELLER()
 * @method static ADMIN()
 */
enum OrderReturnActorEnum: string
{
    use InvokableCases, Names, Values;

    case SELLER = 'seller';
    case ADMIN = 'admin';
}
