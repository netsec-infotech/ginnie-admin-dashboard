<?php

namespace App\Enums\DeliveryBoy;

use ArchTech\Enums\InvokableCases;
use ArchTech\Enums\Names;
use ArchTech\Enums\Values;

/**
 * @method static ASSIGNED()
 * @method static IN_PROGRESS()
 * @method static COMPLETED()
 * @method static CANCELED()
 * @method static DROPPED()
 * @method static CANCELLED_BY_ADMIN()
 */
enum DeliveryBoyAssignmentStatusEnum: string
{
    use InvokableCases, Values, Names;
    case ASSIGNED = 'assigned';
    case IN_PROGRESS = 'in_progress';
    case COMPLETED = 'completed';
    case CANCELED = 'canceled';
    // Rider voluntarily dropped the order (pre- or post-collect).
    case DROPPED = 'dropped';
    // The order itself was killed by an admin force-cancel — the rider isn't
    // at fault. Earnings are preserved on the row and the admin reviews via
    // the Settle Earnings panel to decide Approve (pay) or Reject (don't pay).
    case CANCELLED_BY_ADMIN = 'cancelled_by_admin';
}
