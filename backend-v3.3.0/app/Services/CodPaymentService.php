<?php

namespace App\Services;

use App\Enums\Payment\PaymentTypeEnum;
use App\Enums\PaymentStatusEnum;
use App\Enums\SettingTypeEnum;
use App\Models\Order;
use App\Models\OrderPaymentTransaction;
use Exception;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Razorpay\Api\Api as RazorpayApi;
use Stripe\Checkout\Session as StripeCheckoutSession;
use Stripe\Stripe;

class CodPaymentService
{
    protected SettingService $settingService;

    public function __construct(
        SettingService $settingService,
        private PaymentCurrencyAvailabilityService $paymentCurrencyAvailabilityService,
        private PaymentCurrencyEnforcementService $paymentCurrencyEnforcementService
    ) {
        $this->settingService = $settingService;
    }

    /**
     * Get payment configuration from settings.
     *
     * @return array
     */
    private function paymentConfig(): array
    {
        $setting = $this->settingService->getSettingByVariable(SettingTypeEnum::PAYMENT());
        return (array) ($setting?->value ?? []);
    }

    /**
     * Get Razorpay Key ID for public view.
     *
     * @return string
     */
    public function razorpayKeyId(): string
    {
        return $this->paymentConfig()['razorpayKeyId'] ?? '';
    }

    /**
     * Returns the list of gateways that are configured and can be used right now.
     *
     * @param Order $order
     * @return array
     */
    public function availableGateways(Order $order): array
    {
        $cfg = $this->paymentConfig();
        $currencyCode = $order->currency_code ?: null;
        $out = [];

        if (filter_var($cfg['razorpayPayment'] ?? false, FILTER_VALIDATE_BOOLEAN) && !empty($cfg['razorpayKeyId']) && !empty($cfg['razorpaySecretKey'])) {
            $availability = $this->paymentCurrencyAvailabilityService->availabilityForMethod(PaymentTypeEnum::RAZORPAY(), $currencyCode);
            if ($availability['is_available']) {
                $out[] = array_merge(['name' => 'razorpay', 'label' => 'Razorpay', 'mode' => 'inline'], $availability);
            }
        }
        if (filter_var($cfg['stripePayment'] ?? false, FILTER_VALIDATE_BOOLEAN) && !empty($cfg['stripeSecretKey']) && !empty($cfg['stripePublishableKey'])) {
            $availability = $this->paymentCurrencyAvailabilityService->availabilityForMethod(PaymentTypeEnum::STRIPE(), $currencyCode);
            if ($availability['is_available']) {
                $out[] = array_merge(['name' => 'stripe', 'label' => 'Stripe', 'mode' => 'redirect'], $availability);
            }
        }
        if (filter_var($cfg['paystackPayment'] ?? false, FILTER_VALIDATE_BOOLEAN) && !empty($cfg['paystackSecretKey'])) {
            $availability = $this->paymentCurrencyAvailabilityService->availabilityForMethod(PaymentTypeEnum::PAYSTACK(), $currencyCode);
            if ($availability['is_available']) {
                $out[] = array_merge(['name' => 'paystack', 'label' => 'Paystack', 'mode' => 'redirect'], $availability);
            }
        }
        if (filter_var($cfg['flutterwavePayment'] ?? false, FILTER_VALIDATE_BOOLEAN) && !empty($cfg['flutterwaveSecretKey'])) {
            $availability = $this->paymentCurrencyAvailabilityService->availabilityForMethod(PaymentTypeEnum::FLUTTERWAVE(), $currencyCode);
            if ($availability['is_available']) {
                $out[] = array_merge(['name' => 'flutterwave', 'label' => 'Flutterwave', 'mode' => 'redirect'], $availability);
            }
        }
        if (filter_var($cfg['xenditPayment'] ?? false, FILTER_VALIDATE_BOOLEAN) && !empty($cfg['xenditSecretKey'])) {
            $availability = $this->paymentCurrencyAvailabilityService->availabilityForMethod(PaymentTypeEnum::XENDIT(), $currencyCode);
            if ($availability['is_available']) {
                $out[] = array_merge(['name' => 'xendit', 'label' => 'Xendit', 'mode' => 'redirect'], $availability);
            }
        }

        return $out;
    }

    /**
     * Initialize a chosen gateway against this order. Idempotent per gateway.
     *
     * @param Order $order
     * @param string $gateway
     * @return array
     * @throws Exception
     */
    public function initGateway(Order $order, string $gateway): array
    {
        if ((string) $order->payment_status === (string) PaymentStatusEnum::COMPLETED() || $order->is_cod_paid_online) {
            throw new Exception(__('labels.order_already_paid'));
        }

        $this->ensureGatewayAvailable($gateway, $order->currency_code);

        if ($order->cod_online_gateway && $order->cod_online_gateway !== $gateway) {
            $order->update(['cod_online_gateway' => null, 'cod_online_reference' => null]);
            $order->refresh();
        }

        return match ($gateway) {
            'razorpay'    => $this->initRazorpay($order),
            'stripe'      => $this->initStripe($order),
            'paystack'    => $this->initPaystack($order),
            'flutterwave' => $this->initFlutterwave($order),
            'xendit'      => $this->initXendit($order),
            default       => throw new Exception(__('labels.unknown_gateway')),
        };
    }

    /**
     * Verify a customer-side success callback and mark the order as completed online.
     *
     * @param Order $order
     * @param string $gateway
     * @param array $payload
     * @return int
     * @throws Exception
     */
    public function verify(Order $order, string $gateway, array $payload): int
    {
        if ((string) $order->payment_status === (string) PaymentStatusEnum::COMPLETED() || $order->is_cod_paid_online) {
            return (int) $order->id;
        }

        if ($order->cod_online_gateway && $order->cod_online_gateway !== $gateway) {
            throw new Exception(__('labels.gateway_mismatch'));
        }

        $paymentId = match ($gateway) {
            'razorpay'    => $this->verifyRazorpay($order, $payload),
            'stripe'      => $this->verifyStripe($order, $payload),
            'paystack'    => $this->verifyPaystack($order, $payload),
            'flutterwave' => $this->verifyFlutterwave($order, $payload),
            'xendit'      => $this->verifyXendit($order, $payload),
            default       => throw new Exception(__('labels.unknown_gateway')),
        };

        return $this->markOrderPaidOnline($order, $gateway, $paymentId);
    }

    /**
     * Mark order as paid online and log the transaction.
     *
     * @param Order $order
     * @param string $gateway
     * @param string $paymentId
     * @return int
     */
    private function markOrderPaidOnline(Order $order, string $gateway, string $paymentId): int
    {
        return DB::transaction(function () use ($order, $gateway, $paymentId) {
            $order->refresh();
            if ((string) $order->payment_status === (string) PaymentStatusEnum::COMPLETED() || $order->is_cod_paid_online) {
                return (int) $order->id;
            }

            // Update order payment status and flag
            $order->update([
                'payment_status' => PaymentStatusEnum::COMPLETED(),
                'is_cod_paid_online' => true,
                'cod_online_gateway' => $gateway,
                'cod_online_reference' => $paymentId,
            ]);

            // Create transaction log
            OrderPaymentTransaction::createTransaction(
                [
                    'order_id' => $order->id,
                    'user_id' => $order->user_id,
                    'payment_id' => $paymentId,
                    'amount' => $order->total_payable,
                    'currency' => $order->currency_code ?: 'INR',
                ],
                $gateway,
                PaymentStatusEnum::COMPLETED()
            );

            Log::info("COD order marked as paid online via {$gateway}", [
                'order_id' => $order->id,
                'uuid' => $order->uuid,
                'payment_id' => $paymentId,
            ]);

            return (int) $order->id;
        });
    }

    // -- Razorpay --------------------------------------------------------

    private function initRazorpay(Order $order): array
    {
        $cfg = $this->paymentConfig();
        if (empty($cfg['razorpayKeyId']) || empty($cfg['razorpaySecretKey'])) {
            throw new Exception(__('labels.razorpay_not_configured'));
        }

        if (!$order->cod_online_reference || $order->cod_online_gateway !== 'razorpay') {
            $api = new RazorpayApi($cfg['razorpayKeyId'], $cfg['razorpaySecretKey']);
            $rzpOrder = $api->order->create([
                'amount'          => (int) round(((float) $order->total_payable) * 100),
                'currency'        => $order->currency_code ?: 'INR',
                'receipt'         => 'cod_' . substr($order->uuid, 0, 30),
                'payment_capture' => 1,
                'notes'           => [
                    'type'       => 'cod_doorstep',
                    'order_uuid' => $order->uuid,
                    'order_id'   => (string) $order->id,
                ],
            ]);
            $order->update(['cod_online_gateway' => 'razorpay', 'cod_online_reference' => $rzpOrder['id']]);
            $order->refresh();
        }

        return [
            'mode'     => 'inline',
            'key'      => $cfg['razorpayKeyId'],
            'order_id' => $order->cod_online_reference,
            'amount'   => (int) round(((float) $order->total_payable) * 100),
            'currency' => strtoupper($order->currency_code ?: 'INR'),
        ];
    }

    private function verifyRazorpay(Order $order, array $payload): string
    {
        $secret = $this->paymentConfig()['razorpaySecretKey'] ?? '';
        $orderId = $payload['razorpay_order_id'] ?? '';
        $paymentId = $payload['razorpay_payment_id'] ?? '';
        $signature = $payload['razorpay_signature'] ?? '';

        if ($order->cod_online_reference !== $orderId) {
            throw new Exception(__('labels.order_id_mismatch'));
        }

        $expected = hash_hmac('sha256', $orderId . '|' . $paymentId, $secret);
        if (!hash_equals($expected, $signature)) {
            throw new Exception(__('labels.signature_verification_failed'));
        }

        $this->paymentCurrencyEnforcementService->ensureAmountMatches($order->total_payable, null, 'Razorpay COD');
        $this->paymentCurrencyEnforcementService->ensureCurrencyMatches($order->currency_code, null, 'Razorpay COD');

        return $paymentId;
    }

    // -- Stripe ----------------------------------------------------------

    private function initStripe(Order $order): array
    {
        $cfg = $this->paymentConfig();
        if (empty($cfg['stripeSecretKey'])) {
            throw new Exception(__('labels.stripe_not_configured'));
        }

        if (!$order->cod_online_reference || $order->cod_online_gateway !== 'stripe') {
            Stripe::setApiKey($cfg['stripeSecretKey']);

            $checkout = StripeCheckoutSession::create([
                'mode'                 => 'payment',
                'payment_method_types' => ['card'],
                'line_items'           => [[
                    'price_data' => [
                        'currency'     => strtolower($order->currency_code ?: 'usd'),
                        'product_data' => ['name' => 'Order #' . $order->slug],
                        'unit_amount'  => (int) round(((float) $order->total_payable) * 100),
                    ],
                    'quantity' => 1,
                ]],
                'metadata' => [
                    'type'       => 'cod_doorstep',
                    'order_uuid' => $order->uuid,
                    'order_id'   => (string) $order->id,
                ],
                'payment_intent_data' => [
                    'metadata' => [
                        'type'       => 'cod_doorstep',
                        'order_uuid' => $order->uuid,
                        'order_id'   => (string) $order->id,
                    ],
                ],
                'success_url' => route('cod.public.payment.return', ['token' => $order->uuid, 'gateway' => 'stripe']) . '?stripe_session_id={CHECKOUT_SESSION_ID}',
                'cancel_url'  => route('cod.public.payment.show', ['token' => $order->uuid]),
            ]);

            $order->update(['cod_online_gateway' => 'stripe', 'cod_online_reference' => $checkout->id]);
            $order->refresh();

            return ['mode' => 'redirect', 'url' => $checkout->url];
        }

        Stripe::setApiKey($cfg['stripeSecretKey']);
        $checkout = StripeCheckoutSession::retrieve($order->cod_online_reference);
        return ['mode' => 'redirect', 'url' => $checkout->url];
    }

    private function verifyStripe(Order $order, array $payload): string
    {
        $cfg = $this->paymentConfig();
        Stripe::setApiKey($cfg['stripeSecretKey']);

        $sid = $payload['stripe_session_id'] ?? '';
        if (!$sid) throw new Exception(__('labels.missing_stripe_session_id'));
        if ($order->cod_online_reference !== $sid) throw new Exception(__('labels.session_id_mismatch'));

        $checkout = StripeCheckoutSession::retrieve($sid);
        if ($checkout->payment_status !== 'paid') {
            throw new Exception(__('labels.stripe_payment_not_completed'));
        }
        $this->paymentCurrencyEnforcementService->ensureAmountMatches($order->total_payable, isset($checkout->amount_total) ? ((float) $checkout->amount_total / 100) : null, 'Stripe COD');
        $this->paymentCurrencyEnforcementService->ensureCurrencyMatches($order->currency_code, $checkout->currency ?? null, 'Stripe COD');

        return (string) ($checkout->payment_intent ?? $sid);
    }

    // -- Paystack --------------------------------------------------------

    private function initPaystack(Order $order): array
    {
        $cfg = $this->paymentConfig();
        if (empty($cfg['paystackSecretKey'])) {
            throw new Exception(__('labels.paystack_not_configured'));
        }

        $currency = $cfg['paystackCurrencyCode'] ?: ($order->currency_code ?: 'NGN');
        $reference = 'cod_' . substr($order->uuid, 0, 26) . '_' . time();

        $email = $order->email ?: ('order+' . substr($order->uuid, 0, 8) . '@invalid.local');

        $resp = Http::withHeaders([
            'Authorization' => 'Bearer ' . $cfg['paystackSecretKey'],
            'Content-Type'  => 'application/json',
        ])->post('https://api.paystack.co/transaction/initialize', [
            'email'        => $email,
            'amount'       => (int) round(((float) $order->total_payable) * 100),
            'currency'     => $currency,
            'reference'    => $reference,
            'callback_url' => route('cod.public.payment.return', ['token' => $order->uuid, 'gateway' => 'paystack']),
            'metadata'     => [
                'type'       => 'cod_doorstep',
                'order_uuid' => $order->uuid,
                'order_id'   => (string) $order->id,
            ],
        ]);

        $body = $resp->json();
        if (!($body['status'] ?? false)) {
            throw new Exception(__('labels.paystack_init_failed'));
        }

        $order->update(['cod_online_gateway' => 'paystack', 'cod_online_reference' => $reference]);

        return ['mode' => 'redirect', 'url' => $body['data']['authorization_url'] ?? ''];
    }

    private function verifyPaystack(Order $order, array $payload): string
    {
        $cfg = $this->paymentConfig();
        $reference = $payload['reference'] ?? '';
        if (!$reference) throw new Exception(__('labels.missing_paystack_reference'));
        if ($order->cod_online_reference !== $reference) throw new Exception(__('labels.reference_mismatch'));

        $resp = Http::withHeaders([
            'Authorization' => 'Bearer ' . $cfg['paystackSecretKey'],
        ])->get("https://api.paystack.co/transaction/verify/{$reference}");
        $body = $resp->json();
        $status = $body['data']['status'] ?? null;
        if ($status !== 'success') {
            throw new Exception(__('labels.paystack_payment_not_successful'));
        }
        $this->paymentCurrencyEnforcementService->ensureAmountMatches($order->total_payable, isset($body['data']['amount']) ? ((float) $body['data']['amount'] / 100) : null, 'Paystack COD');
        $this->paymentCurrencyEnforcementService->ensureCurrencyMatches($order->currency_code, $body['data']['currency'] ?? null, 'Paystack COD');

        return (string) ($body['data']['id'] ?? $reference);
    }

    // -- Flutterwave -----------------------------------------------------

    private function initFlutterwave(Order $order): array
    {
        $cfg = $this->paymentConfig();
        if (empty($cfg['flutterwaveSecretKey'])) {
            throw new Exception(__('labels.flutterwave_not_configured'));
        }
        $currency = $cfg['flutterwaveCurrencyCode'] ?: ($order->currency_code ?: 'NGN');
        $txRef = 'cod_' . substr($order->uuid, 0, 24) . '_' . time();

        $email = $order->email ?: ('order+' . substr($order->uuid, 0, 8) . '@invalid.local');

        $resp = Http::withToken($cfg['flutterwaveSecretKey'])
            ->post('https://api.flutterwave.com/v3/payments', [
                'tx_ref'         => $txRef,
                'amount'         => (float) $order->total_payable,
                'currency'       => $currency,
                'redirect_url'   => route('cod.public.payment.return', ['token' => $order->uuid, 'gateway' => 'flutterwave']),
                'customer'       => [
                    'email' => $email,
                    'name'  => $order->shipping_name ?: 'Customer',
                ],
                'customizations' => [
                    'title' => 'Order #' . $order->slug,
                ],
                'meta'           => [
                    'type'       => 'cod_doorstep',
                    'order_uuid' => $order->uuid,
                    'order_id'   => (string) $order->id,
                ],
            ]);
        $body = $resp->json();
        if (!$resp->successful() || ($body['status'] ?? '') !== 'success') {
            throw new Exception(__('labels.flutterwave_init_failed'));
        }

        $order->update(['cod_online_gateway' => 'flutterwave', 'cod_online_reference' => $txRef]);
        return ['mode' => 'redirect', 'url' => $body['data']['link'] ?? ''];
    }

    private function verifyFlutterwave(Order $order, array $payload): string
    {
        $cfg = $this->paymentConfig();
        $txnId = $payload['transaction_id'] ?? '';
        $txRef = $payload['tx_ref'] ?? '';
        if (!$txnId) throw new Exception(__('labels.missing_flutterwave_transaction_id'));
        if ($txRef && $order->cod_online_reference !== $txRef) throw new Exception(__('labels.tx_ref_mismatch'));

        $resp = Http::withToken($cfg['flutterwaveSecretKey'])
            ->get("https://api.flutterwave.com/v3/transactions/{$txnId}/verify");
        $body = $resp->json();
        $data = $body['data'] ?? [];
        if (($data['status'] ?? null) !== 'successful') {
            throw new Exception(__('labels.flutterwave_payment_not_successful'));
        }

        $expectAmount = (float) $order->total_payable;
        $gotAmount    = (float) ($data['amount'] ?? 0);
        if (abs($expectAmount - $gotAmount) > 0.01) {
            throw new Exception(__('labels.flutterwave_amount_mismatch'));
        }
        $this->paymentCurrencyEnforcementService->ensureCurrencyMatches($order->currency_code, $data['currency'] ?? null, 'Flutterwave COD');

        return (string) ($data['id'] ?? $txnId);
    }

    private function initXendit(Order $order): array
    {
        $cfg = $this->paymentConfig();
        if (empty($cfg['xenditSecretKey'])) {
            throw new Exception(__('labels.xendit_not_configured'));
        }
        $currency = $cfg['xenditCurrencyCode'] ?: ($order->currency_code ?: 'IDR');
        $externalId = 'order_' . $order->uuid . '_' . time();

        $email = $order->email ?: ('order+' . substr($order->uuid, 0, 8) . '@invalid.local');

        $resp = Http::withBasicAuth($cfg['xenditSecretKey'], '')
            ->post('https://api.xendit.co/v2/invoices', [
                'external_id'          => $externalId,
                'amount'               => (float) $order->total_payable,
                'currency'             => $currency,
                'success_redirect_url' => route('cod.public.payment.return', ['token' => $order->uuid, 'gateway' => 'xendit']),
                'failure_redirect_url' => route('cod.public.payment.show', ['token' => $order->uuid]),
                'payer_email'          => $email,
                'description'          => 'Order #' . $order->slug,
            ]);

        $body = $resp->json();
        if (!$resp->successful() || empty($body['invoice_url'])) {
            throw new Exception(__('labels.xendit_init_failed'));
        }

        $order->update(['cod_online_gateway' => 'xendit', 'cod_online_reference' => $body['id']]);
        return ['mode' => 'redirect', 'url' => $body['invoice_url']];
    }

    private function verifyXendit(Order $order, array $payload): string
    {
        $cfg = $this->paymentConfig();
        $invoiceId = $order->cod_online_reference;
        if (!$invoiceId) {
            throw new Exception(__('labels.pos_session_id_mismatch'));
        }

        $resp = Http::withBasicAuth($cfg['xenditSecretKey'], '')
            ->get("https://api.xendit.co/v2/invoices/{$invoiceId}");

        if (!$resp->successful()) {
            throw new Exception(__('labels.pos_signature_verification_failed'));
        }

        $body = $resp->json();
        if (($body['status'] ?? '') !== 'PAID' && ($body['status'] ?? '') !== 'SETTLED') {
            throw new Exception(__('labels.xendit_payment_not_successful'));
        }

        $expectAmount = (float) $order->total_payable;
        $gotAmount    = (float) ($body['amount'] ?? 0);
        if (abs($expectAmount - $gotAmount) > 0.01) {
            throw new Exception(__('labels.xendit_amount_mismatch'));
        }

        $expectCurrency = strtoupper($cfg['xenditCurrencyCode'] ?: ($order->currency_code ?: 'IDR'));
        $gotCurrency    = strtoupper($body['currency'] ?? '');
        if ($expectCurrency !== $gotCurrency) {
            throw new Exception(__('labels.xendit_currency_mismatch'));
        }

        return (string) ($body['id'] ?? $invoiceId);
    }

    private function ensureGatewayAvailable(string $gateway, ?string $currencyCode): void
    {
        $method = match ($gateway) {
            'razorpay' => PaymentTypeEnum::RAZORPAY(),
            'stripe' => PaymentTypeEnum::STRIPE(),
            'paystack' => PaymentTypeEnum::PAYSTACK(),
            'flutterwave' => PaymentTypeEnum::FLUTTERWAVE(),
            'xendit' => PaymentTypeEnum::XENDIT(),
            default => throw new Exception(__('labels.unknown_gateway')),
        };

        try {
            $this->paymentCurrencyEnforcementService->ensureAvailable($method, $currencyCode);
        } catch (\RuntimeException $e) {
            throw new Exception($e->getMessage());
        }
    }
}
