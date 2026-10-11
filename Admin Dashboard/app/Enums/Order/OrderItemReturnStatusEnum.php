<?php

namespace App\Enums\Order;

use ArchTech\Enums\InvokableCases;
use ArchTech\Enums\Names;
use ArchTech\Enums\Values;

/**
 * @method static CANCELLED()
 * @method static REQUESTED()
 * @method static SELLER_APPROVED()
 * @method static SELLER_REJECTED()
 * @method static PICKUP_ASSIGNED()
 * @method static PICKED_UP()
 * @method static RECEIVED_BY_SELLER()
 * @method static REFUND_PROCESSED()
 * @method static COMPLETED()
 * @method static REJECTED_AT_STORE()
 * @method static DISPUTED()
 * @method static REJECTED()
 */
enum OrderItemReturnStatusEnum: string
{
    use InvokableCases, Names, Values;

    case CANCELLED = 'cancelled';
   case REQUESTED = 'requested';
   case SELLER_APPROVED = 'seller_approved';
   case SELLER_REJECTED = 'seller_rejected';
   case PICKUP_ASSIGNED = 'pickup_assigned';
   case PICKED_UP = 'picked_up';
   case RECEIVED_BY_SELLER = 'received_by_seller';
   case REFUND_PROCESSED = 'refund_processed';
   case COMPLETED = 'completed';
   case REJECTED_AT_STORE = 'rejected_at_store';
   
   // ==========================================================
   // Return Dispute System
   // Phase 1
   // Add disputed state to Return Status Enum
   // ==========================================================
   case DISPUTED = 'disputed';

    // ==========================================================
    // Return Dispute System
    // Phase 4
    // Add rejected case to Return Status Enum
    // ==========================================================
    case REJECTED = 'rejected';
}
