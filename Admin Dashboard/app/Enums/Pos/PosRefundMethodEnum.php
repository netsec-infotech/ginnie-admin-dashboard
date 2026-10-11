<?php

namespace App\Enums\Pos;

use ArchTech\Enums\InvokableCases;
use ArchTech\Enums\Names;
use ArchTech\Enums\Values;

/**
 * @method static CASH()
 * @method static OTHER()
 */
enum PosRefundMethodEnum: string
{
    use InvokableCases, Names, Values;

    case CASH = 'cash';
    case OTHER = 'other';

    public static function label(string $method): string
    {
        return match ($method) {
            self::CASH()  => __('labels.pos_refund_cash'),
            self::OTHER() => __('labels.pos_refund_other'),
            default       => $method,
        };
    }
}
