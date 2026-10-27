<?php

namespace App\Enums\Payment;

use ArchTech\Enums\InvokableCases;
use ArchTech\Enums\Names;
use ArchTech\Enums\Values;

/**
 * Payment Type Enum
 *
 * This enum defines the available payment types in the application.
 *
 * @method static COD()
 * @method static WALLET()
 * @method static RAZORPAY()
 * @method static STRIPE()
 * @method static PAYSTACK()
 * @method static FLUTTERWAVE()
 * @method static MERCADO_PAGO()
 * @method static POS_CASH()
 * @method static POS_UPI()
 * @method static POS_CUSTOM()
 */
enum PaymentTypeEnum: string
{
    use InvokableCases, Names, Values;

    case COD = 'cod'; // Cash On Delivery
    case WALLET = 'wallet';

    case RAZORPAY = 'razorpayPayment';
    case STRIPE = 'stripePayment';

    case PAYSTACK = 'paystackPayment';
    case FLUTTERWAVE = 'flutterwavePayment';
    case XENDIT = 'xenditPayment';
    case MERCADO_PAGO = 'mercadoPagoPayment';
    case POS_CASH = 'posCash';
    case POS_UPI = 'posUpi';
    case POS_CUSTOM = 'posCustom';
}
