<?php

namespace App\Enums\LoyaltyPoint;

use ArchTech\Enums\InvokableCases;
use ArchTech\Enums\Names;
use ArchTech\Enums\Values;

/**
 * @method static EARNED()
 * @method static REDEEMED()
 * @method static REFUNDED()
 * @method static ADMIN_ADJUSTMENT()
 * @method static EXPIRED()
 */
enum LoyaltyPointTransactionTypeEnum: string
{
    use InvokableCases, Names, Values;

    case EARNED = 'earned';
    case REDEEMED = 'redeemed';
    case REFUNDED = 'refunded';
    case ADMIN_ADJUSTMENT = 'admin_adjustment';
    case EXPIRED = 'expired';
}
