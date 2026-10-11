<?php

namespace App\Enums\Pos;

use ArchTech\Enums\InvokableCases;
use ArchTech\Enums\Names;
use ArchTech\Enums\Values;

/**
 * @method static PENDING()
 * @method static PAID()
 * @method static FAILED()
 * @method static EXPIRED()
 * @method static CANCELLED()
 */
enum PosPaymentSessionStatusEnum: string
{
    use InvokableCases, Names, Values;

    case PENDING = 'pending';
    case PAID = 'paid';
    case FAILED = 'failed';
    case EXPIRED = 'expired';
    case CANCELLED = 'cancelled';
}
