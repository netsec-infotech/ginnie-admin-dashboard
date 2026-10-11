<?php

namespace App\Enums\Order;

use ArchTech\Enums\InvokableCases;
use ArchTech\Enums\Names;
use ArchTech\Enums\Values;

/**
 * @method static PENDING()
 * @method static AWAITING_STORE_RESPONSE()
 * @method static ACCEPTED()
 * @method static REJECTED()
 * @method static PREPARING()
 * @method static COLLECTED()
 * @method static DELIVERED()
 * @method static DELIVERY_FAILED()
 * @method static RETURNING_TO_STORE()
 * @method static RETURNED()
 * @method static REFUNDED()
 * @method static CANCELLED()
 * @method static FAILED()
 */
enum OrderItemStatusEnum: string
{
    use InvokableCases, Names, Values;

    case PENDING = 'pending';
    case AWAITING_STORE_RESPONSE = 'awaiting_store_response';
    case ACCEPTED = 'accepted';
    case REJECTED = 'rejected';
    case PREPARING = 'preparing';
    case COLLECTED = 'collected';
    case DELIVERED = 'delivered';
    // Rider attempted delivery but couldn't complete it (customer unavailable / refused).
    case DELIVERY_FAILED = 'delivery_failed';
    // Items physically being returned to the seller; finalises to CANCELLED on seller confirmation.
    case RETURNING_TO_STORE = 'returning_to_store';
    case RETURNED = 'returned';
    case REFUNDED = 'refunded';
    case CANCELLED = 'cancelled';
    case FAILED = 'failed';
}
