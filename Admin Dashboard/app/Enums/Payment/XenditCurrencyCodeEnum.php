<?php

namespace App\Enums\Payment;

use ArchTech\Enums\InvokableCases;
use ArchTech\Enums\Names;
use ArchTech\Enums\Values;

/**
 * Xendit Currency Code Enum
 *
 * This enum defines the currency codes officially supported by Xendit.
 */
enum XenditCurrencyCodeEnum: string
{
    use InvokableCases, Names, Values;

    case IDR = 'IDR';
    case PHP = 'PHP';
    case SGD = 'SGD';
    case USD = 'USD';
    case MYR = 'MYR';
    case VND = 'VND';
    case THB = 'THB';
}
