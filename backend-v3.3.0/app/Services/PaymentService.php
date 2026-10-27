<?php

namespace App\Services;

use App\Enums\Payment\PaymentTypeEnum;
use App\Enums\PaymentStatusEnum;
use App\Http\Controllers\Payments\FlutterwaveController;
use App\Http\Controllers\Payments\MercadoPagoController;
use App\Http\Controllers\Payments\PaystackController;
use App\Http\Controllers\Payments\RazorpayController;
use App\Http\Controllers\Payments\StripeController;
use App\Http\Controllers\Payments\XenditController;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\OrderPaymentTransaction;
use App\Support\Currency\CurrencyWriteGuard;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class PaymentService
{
    protected RazorpayController $razorpayController;

    protected StripeController $stripeController;

    protected PaystackController $paystackController;

    protected FlutterwaveController $flutterwaveController;

    protected XenditController $xenditController;

    protected MercadoPagoController $mercadoPagoController;

    protected CurrencyWriteGuard $currencyWriteGuard;

    protected PaymentCurrencyEnforcementService $currencyEnforcement;

    public function __construct()
    {
        $this->razorpayController = app(RazorpayController::class);
        $this->stripeController = app(StripeController::class);
        $this->paystackController = app(PaystackController::class);
        $this->flutterwaveController = app(FlutterwaveController::class);
        $this->xenditController = app(XenditController::class);
        $this->mercadoPagoController = app(MercadoPagoController::class);
        $this->currencyWriteGuard = app(CurrencyWriteGuard::class);
        $this->currencyEnforcement = app(PaymentCurrencyEnforcementService::class);
    }

    public function prePaymentOrderVerification($transactionId, $order): void
    {
        switch ($order->payment_method) {
            case PaymentTypeEnum::RAZORPAY():
            case PaymentTypeEnum::STRIPE():
            case PaymentTypeEnum::PAYSTACK():
                $transaction = OrderPaymentTransaction::where('transaction_id', $transactionId)->get()->first();

                if ($transaction !== null && $transaction->order_id === null) {
                    $transaction->update(['order_id' => $order->id]);
                    $status = $transaction->payment_status;

                    if ($status === PaymentStatusEnum::COMPLETED()) {
                        Order::capturePayment($transaction->order_id);
                        OrderItem::capturePayment($transaction->order_id);
                        Log::info('operation place order: order update from webhook');
                    }
                    Log::info('operation place order: transaction exist');

                    return;
                }
                Log::info('operation place order: transaction not exist');
                if ($order->payment_method !== PaymentTypeEnum::PAYSTACK()) {
                    $paymentInfo = [
                        'transaction_id' => $transactionId ?? null,
                        'amount' => $order->total_payable,
                        'currency' => $order->currency_code,
                        'payment_method' => $order->payment_method,
                        'message' => 'Payment pending verification',
                    ];
                    $this->makeOrderPaymentTransaction($order, $paymentInfo, PaymentStatusEnum::PENDING());
                }
                break;
            default:
                break;
        }
    }

    public function postPaymentInitialtion($order, $redirectUrl = null): array
    {
        $this->currencyEnforcement->ensureAvailable(
            (string) $order->payment_method,
            $this->currencyWriteGuard->orderCurrency($order)
        );

        switch ($order->payment_method) {
            case PaymentTypeEnum::FLUTTERWAVE():
                $data = [
                    'order_id' => $order->id,
                    'amount' => $order->total_payable,
                    'redirect_url' => $redirectUrl ?? route('order-payment.status', ['order' => $order->id]),
                ];
                $result = $this->flutterwaveController->createOrder($data);
                if ($result['success'] === false) {
                    throw new \Exception($result['message']);
                }

                return $result;
            case PaymentTypeEnum::XENDIT():
            case PaymentTypeEnum::MERCADO_PAGO():
                $data = [
                    'order_id' => $order->id,
                    'amount' => $order->total_payable,
                    'redirect_url' => $redirectUrl ?? route('order-payment.status', ['order' => $order->id]),
                ];
                $result = $order->payment_method === PaymentTypeEnum::XENDIT()
                    ? $this->xenditController->createOrder($data)
                    : $this->mercadoPagoController->createOrder($data);
                if ($result['success'] === false) {
                    throw new \Exception($result['message']);
                }

                return $result;
            default:
                return [];
        }

    }

    public function makeOrderPaymentTransaction($order, $paymentData, $status): void
    {
        $orderCurrency = $this->currencyWriteGuard->orderCurrency($order);
        $gatewayCurrency = $this->currencyWriteGuard->requireValid($paymentData['currency'] ?? $orderCurrency, 'Payment transaction');

        $transactionData = [
            'uuid' => Str::uuid(),
            'order_id' => $order->id,
            'user_id' => $order->user_id,
            'transaction_id' => $paymentData['transaction_id'] ?? null,
            'amount' => $paymentData['amount'] ?? 0,
            'currency' => $gatewayCurrency,
            'order_currency_code' => $orderCurrency,
            'gateway_currency_code' => $gatewayCurrency,
            'gateway_amount' => $paymentData['amount'] ?? null,
            'payment_method' => $paymentData['payment_method'] ?? 'unknown',
            'payment_status' => $status,
            'message' => $paymentData['message'] ?? null,
            'payment_details' => isset($paymentData['details']) ? json_encode($paymentData['details']) : null,
        ];

        if (empty($paymentData['transaction_id'])) {
            OrderPaymentTransaction::create($transactionData);
        } else {
            OrderPaymentTransaction::updateOrCreate(
                ['transaction_id' => $paymentData['transaction_id']],
                $transactionData
            );
        }
    }

    public function verifyOnlinePayment(array $data): array
    {
        $verifiedResponse = [
            'success' => true,
            'message' => __('labels.payment_verified_successfully'),
        ];
        switch ($data['payment_type']) {
            case PaymentTypeEnum::RAZORPAY():
                $verificationResult = $this->razorpayController->verifyPayment($data);
                if (! $verificationResult['success']) {
                    return [
                        'success' => false,
                        'message' => $verificationResult['message'],
                        'data' => $verificationResult['data'],
                    ];
                }

                return $verifiedResponse;
            case PaymentTypeEnum::STRIPE():
                $verification = $this->stripeController->verifyPayment($data['transaction_id']);
                if (! $verification['success']) {
                    return [
                        'success' => false,
                        'message' => $verification['message'],
                        'data' => $verification['data'],
                    ];
                }

                return $verifiedResponse;
            case PaymentTypeEnum::PAYSTACK():
                $verification = $this->paystackController->verifyPayment($data['transaction_id']);
                if (! $verification['success']) {
                    return [
                        'success' => false,
                        'message' => $verification['message'],
                        'data' => $verification['data'] ?? [],
                    ];
                }

                return $verifiedResponse;
            case PaymentTypeEnum::COD():
            case PaymentTypeEnum::FLUTTERWAVE():
            case PaymentTypeEnum::WALLET():
                return $verifiedResponse;
            case PaymentTypeEnum::XENDIT():
                if (! empty($data['transaction_id'])) {
                    $verification = $this->xenditController->verifyPayment($data['transaction_id']);
                    if (! $verification['success']) {
                        return [
                            'success' => false,
                            'message' => $verification['message'],
                            'data' => $verification['data'],
                        ];
                    }
                }

                return $verifiedResponse;
            case PaymentTypeEnum::MERCADO_PAGO():
                if (! empty($data['transaction_id'])) {
                    $verification = $this->mercadoPagoController->verifyPayment($data['transaction_id']);
                    if (! $verification['success']) {
                        return [
                            'success' => false,
                            'message' => $verification['message'],
                            'data' => $verification['data'],
                        ];
                    }
                }

                return $verifiedResponse;

            default:
                return [
                    'success' => false,
                    'message' => __('labels.unsupported_payment_method'),
                    'data' => [],
                ];
        }
    }

    public function processOrderRefund($paymentMethod, $transactionId, ?float $amount = null): array
    {
        if (empty($transactionId)) {
            return [
                'success' => false,
                'message' => 'Transaction ID is required for refund',
                'data' => [],
            ];
        }

        try {
            $methodVal = is_object($paymentMethod) && property_exists($paymentMethod, 'value')
                ? $paymentMethod->value
                : (string) $paymentMethod;

            $result = match ($methodVal) {
                PaymentTypeEnum::RAZORPAY(), PaymentTypeEnum::RAZORPAY->value, 'razorpay' => $this->razorpayController->refundPayment($transactionId, $amount),
                PaymentTypeEnum::STRIPE(), PaymentTypeEnum::STRIPE->value, 'stripe' => $this->stripeController->refundPayment($transactionId, $amount),
                PaymentTypeEnum::PAYSTACK(), PaymentTypeEnum::PAYSTACK->value, 'paystack' => $this->paystackController->refundPayment($transactionId, $amount),
                PaymentTypeEnum::FLUTTERWAVE(), PaymentTypeEnum::FLUTTERWAVE->value, 'flutterwave' => $this->flutterwaveController->refundPayment($transactionId, $amount),
                PaymentTypeEnum::XENDIT(), PaymentTypeEnum::XENDIT->value, 'xendit' => $this->xenditController->refundPayment($transactionId, $amount),
                PaymentTypeEnum::MERCADO_PAGO(), PaymentTypeEnum::MERCADO_PAGO->value, 'mercadopago' => $this->mercadoPagoController->refundPayment($transactionId, $amount),
                default => [
                    'success' => false,
                    'message' => 'Unsupported payment method for refund: ' . $methodVal,
                    'data' => [],
                ],
            };

            if ($result['success'] ?? false) {
                OrderPaymentTransaction::where('transaction_id', $transactionId)
                    ->update([
                        'payment_status' => PaymentStatusEnum::REFUNDED(),
                        'message' => 'Order placement failed - automatically refunded',
                    ]);

                Log::info("Payment {$transactionId} refunded successfully via {$methodVal}");
            } else {
                Log::error("Failed to refund payment {$transactionId} via {$methodVal}: " . ($result['message'] ?? 'Unknown error'));
            }

            return $result;
        } catch (\Exception $e) {
            Log::error('Exception during order refund', [
                'payment_method' => $paymentMethod,
                'transaction_id' => $transactionId,
                'error' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'message' => __('labels.error_processing_refund') . ': ' . $e->getMessage(),
                'data' => [],
            ];
        }
    }
}
