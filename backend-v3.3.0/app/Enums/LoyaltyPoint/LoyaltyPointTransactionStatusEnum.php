<?php

namespace App\Enums\LoyaltyPoint;

use ArchTech\Enums\InvokableCases;
use ArchTech\Enums\Names;
use ArchTech\Enums\Values;

/**
 * @method static PENDING()
 * @method static CREDITED()
 * @method static CANCELLED()
 */
enum LoyaltyPointTransactionStatusEnum: string
{
    use InvokableCases, Names, Values;

    case PENDING = 'pending';
    case CREDITED = 'credited';
    case DEBITED = 'debited';
    case CANCELLED = 'cancelled';
}
