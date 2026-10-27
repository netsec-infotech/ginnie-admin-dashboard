<?php

namespace App\Enums\Payment;

use ArchTech\Enums\InvokableCases;
use ArchTech\Enums\Names;
use ArchTech\Enums\Values;

/**
 * Mercado Pago Currency Code Enum
 *
 * This enum defines the currency codes supported by Mercado Pago Checkout Pro.
 */
enum MercadoPagoCurrencyCodeEnum: string
{
    use InvokableCases, Names, Values;

    case ARS = 'ARS';
    case BRL = 'BRL';
    case CLP = 'CLP';
    case COP = 'COP';
    case MXN = 'MXN';
    case PEN = 'PEN';
    case UYU = 'UYU';
}
