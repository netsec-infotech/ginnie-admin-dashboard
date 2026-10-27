<?php

namespace App\Enums\Order;

use ArchTech\Enums\InvokableCases;
use ArchTech\Enums\Names;
use ArchTech\Enums\Values;

/**
 * ==========================================================
 * Return Dispute System
 * Phase 4 Refinement
 * Admin Loss Bearer Enum
 * ==========================================================
 *
 * @method static CUSTOMER()
 * @method static SELLER()
 * @method static RIDER()
 * @method static PLATFORM()
 */
enum AdminLossBearerEnum: string
{
    use InvokableCases, Names, Values;

    case CUSTOMER = 'customer';
    case SELLER = 'seller';
    case RIDER = 'rider';
    case PLATFORM = 'platform';
}
