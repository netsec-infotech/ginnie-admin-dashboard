<?php

namespace App\Enums\DeliveryBoy;

use ArchTech\Enums\InvokableCases;
use ArchTech\Enums\Names;
use ArchTech\Enums\Values;

/**
 * @method static PENDING()
 * @method static PAID()
 * @method static REJECTED()
 */
enum EarningPaymentStatusEnum: string
{
    use InvokableCases, Values, Names;

    case PENDING = 'pending';
    case PAID = 'paid';
    // Admin reviewed the cancelled assignment and decided NOT to pay the rider.
    // Reason is captured in the order_audit_logs at the moment of the decision.
    case REJECTED = 'rejected';
}
