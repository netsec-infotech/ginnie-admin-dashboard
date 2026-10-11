<?php

namespace App\Services;

use App\Enums\Payment\PaymentTypeEnum;
use App\Enums\PaymentStatusEnum;
use App\Enums\Pos\PosPaymentSessionStatusEnum;
use App\Enums\SettingTypeEnum;
use App\Models\PosPaymentSession;
use App\Models\Seller;
use App\Models\Store;
use Exception;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Razorpay\Api\Api as RazorpayApi;
use Stripe\Stripe;
use Stripe\Checkout\Session as StripeCheckoutSession;

/**
 * Lifecycle owner for POS Online Payment QR sessions.
 *
 * Multi-gateway: Razorpay (inline checkout), Stripe (hosted Checkout), Paystack
 * (hosted authorization page), and Flutterwave (hosted page). The cashier
 * generates a session at the POS; the customer scans, picks one of the
 * enabled gateways on the public payment page, and pays. We promote the
 * session into a real Order via PosOrderService once the gateway confirms.
 */
class PosPaymentSessionService
{
    public const SESSION_TTL_MINUTES = 15;

    public function __construct(private SettingService $settingService) {}

    // -- Session lifecycle -----------------------------------------------

    public function create(Seller $seller, Store $store, array $payload): PosPaymentSession
    {
        $amount      = (float) ($payload['amount'] ?? 0);
        $cashPortion = (float) ($payload['cash_portion'] ?? 0);
        if ($amount <= 0) {
            throw new Exception(__('labels.pos_online_portion_must_be_positive'));
        }
        if ($cashPortion < 0) {
            throw new Exception(__('labels.pos_cash_portion_cannot_be_negative'));
        }
        if (empty($payload['items']) || !is_array($payload['items'])) {
            throw new Exception(__('labels.pos_cart_missing_items'));
        }

        return PosPaymentSession::create([
            'token'          => (string) Str::uuid(),
            'store_id'       => $store->id,
            'seller_id'      => $seller->id,
            'payload'        => $payload,
            'amount'         => $amount,
            'cash_portion'   => $cashPortion,
            'currency_code'  => $store->currency_code ?? 'INR',
            'status'         => PosPaymentSessionStatusEnum::PENDING(),
            'expires_at'     => now()->addMinutes(self::SESSION_TTL_MINUTES),
        ]);
    }

    public function expireIfDue(PosPaymentSession $session): PosPaymentSession
    {
        if ($session->status === PosPaymentSessionStatusEnum::PENDING && $session->expires_at && $session->expires_at->isPast()) {
            $session->update(['status' => PosPaymentSessionStatusEnum::EXPIRED()]);
            return $session->fresh();
        }
        return $session;
    }

    public function cancel(PosPaymentSession $session, ?string $reason = null): PosPaymentSession
    {
        if ($session->status !== PosPaymentSessionStatusEnum::PENDING) return $session;
        $session->update(['status' => PosPaymentSessionStatusEnum::CANCELLED(), 'failure_reason' => $reason]);
        return $session->fresh();
    }

    // -- Gateway dispatch ------------------------------------------------

    /**
     * Returns the list of gateways that are configured + can be used right
     * now for this session's currency.
     */
    public function availableGateways(PosPaymentSession $session): array
    {
        $cfg = $this->paymentConfig();
        $out = [];

        if (!empty($cfg['razorpayKeyId']) && !empty($cfg['razorpaySecretKey'])) {
            $out[] = ['name' => 'razorpay', 'label' => 'Razorpay', 'mode' => 'inline'];
        }
        if (!empty($cfg['stripeSecretKey']) && !empty($cfg['stripePublishableKey'])) {
            $out[] = ['name' => 'stripe', 'label' => 'Stripe', 'mode' => 'redirect'];
        }
        if (!empty($cfg['paystackSecretKey'])) {
            $out[] = ['name' => 'paystack', 'label' => 'Paystack', 'mode' => 'redirect'];
        }
        if (!empty($cfg['flutterwaveSecretKey'])) {
            $out[] = ['name' => 'flutterwave', 'label' => 'Flutterwave', 'mode' => 'redirect'];
        }
        if (!empty($cfg['xenditSecretKey'])) {
            $out[] = ['name' => 'xendit', 'label' => 'Xendit', 'mode' => 'redirect'];
        }
        return $out;
    }

    /**
     * Initialise a chosen gateway against this session. Idempotent per gateway.
     */
    public function initGateway(PosPaymentSession $session, string $gateway): array
    {
        if ($session->status !== PosPaymentSessionStatusEnum::PENDING) {
            throw new Exception(__('labels.pos_session_not_pending'));
        }
        if ($session->expires_at && $session->expires_at->isPast()) {
            $session->update(['status' => PosPaymentSessionStatusEnum::EXPIRED()]);
            throw new Exception(__('labels.pos_session_expired'));
        }

        if ($session->gateway && $session->gateway !== $gateway) {
            $session->update(['gateway' => null, 'gateway_order_id' => null]);
            $session->refresh();
        }

        return match ($gateway) {
            'razorpay'    => $this->initRazorpay($session),
            'stripe'      => $this->initStripe($session),
            'paystack'    => $this->initPaystack($session),
            'flutterwave' => $this->initFlutterwave($session),
            'xendit'      => $this->initXendit($session),
            default       => throw new Exception(__('labels.pos_unknown_gateway')),
        };
    }

    /**
     * Verify a customer-side success callback.
     */
    public function verify(
        PosPaymentSession $session,
        string $gateway,
        array $payload,
        PosOrderService $orderService
    ): int {
        if ($session->status === PosPaymentSessionStatusEnum::PAID) return (int) $session->order_id;
        if ($session->status !== PosPaymentSessionStatusEnum::PENDING) {
            throw new Exception(__('labels.pos_session_not_pending'));
        }
        if ($session->expires_at && $session->expires_at->isPast()) {
            $session->update(['status' => PosPaymentSessionStatusEnum::EXPIRED()]);
            throw new Exception(__('labels.pos_session_expired'));
        }
        if ($session->gateway && $session->gateway !== $gateway) {
            throw new Exception(__('labels.pos_gateway_mismatch'));
        }

        $paymentId = match ($gateway) {
            'razorpay'    => $this->verifyRazorpay($session, $payload),
            'stripe'      => $this->verifyStripe($session, $payload),
            'paystack'    => $this->verifyPaystack($session, $payload),
            'flutterwave' => $this->verifyFlutterwave($session, $payload),
            'xendit'      => $this->verifyXendit($session, $payload),
            default       => throw new Exception(__('labels.pos_unknown_gateway')),
        };

        return $this->markPaidAndCreateOrder($session, $paymentId, $orderService);
    }

    // -- Webhook backstop ------------------------------------------------

    public function handleWebhookCapture(
        PosPaymentSession $session,
        string $gatewayPaymentId,
        PosOrderService $orderService
    ): void {
        if ($session->status === PosPaymentSessionStatusEnum::PAID || $session->status !== PosPaymentSessionStatusEnum::PENDING) return;
        try {
            $this->markPaidAndCreateOrder($session, $gatewayPaymentId, $orderService);
        } catch (Exception $e) {
            Log::error('POS session webhook capture failed', [
                'session_token' => $session->token,
                'error'         => $e->getMessage(),
            ]);
        }
    }

    public function handleWebhookFailure(PosPaymentSession $session, ?string $reason = null): void
    {
        if ($session->status !== PosPaymentSessionStatusEnum::PENDING) return;
        $session->update(['status' => PosPaymentSessionStatusEnum::FAILED(), 'failure_reason' => $reason]);
    }

    // -- Razorpay --------------------------------------------------------

    private function initRazorpay(PosPaymentSession $session): array
    {
        $cfg = $this->paymentConfig();
        if (empty($cfg['razorpayKeyId']) || empty($cfg['razorpaySecretKey'])) {
            throw new Exception(__('labels.pos_razorpay_not_configured'));
        }

        if (!$session->gateway_order_id || $session->gateway !== 'razorpay') {
            $api = new RazorpayApi($cfg['razorpayKeyId'], $cfg['razorpaySecretKey']);
            $rzpOrder = $api->order->create([
                'amount'          => (int) round(((float) $session->amount) * 100),
                'currency'        => $session->currency_code ?: 'INR',
                'receipt'         => 'pos_' . substr($session->token, 0, 30),
                'payment_capture' => 1,
                'notes'           => [
                    'type'              => 'pos_session',
                    'pos_session_token' => $session->token,
                    'store_id'          => (string) $session->store_id,
                ],
            ]);
            $session->update(['gateway' => 'razorpay', 'gateway_order_id' => $rzpOrder['id']]);
            $session->refresh();
        }

        return [
            'mode'     => 'inline',
            'key'      => $cfg['razorpayKeyId'],
            'order_id' => $session->gateway_order_id,
            'amount'   => (int) round(((float) $session->amount) * 100),
            'currency' => strtoupper($session->currency_code ?: 'INR'),
        ];
    }

    private function verifyRazorpay(PosPaymentSession $session, array $payload): string
    {
        $secret = $this->paymentConfig()['razorpaySecretKey'] ?? '';
        $orderId = $payload['razorpay_order_id'] ?? '';
        $paymentId = $payload['razorpay_payment_id'] ?? '';
        $signature = $payload['razorpay_signature'] ?? '';

        if ($session->gateway_order_id !== $orderId) throw new Exception(__('labels.pos_order_id_mismatch'));

        $expected = hash_hmac('sha256', $orderId . '|' . $paymentId, $secret);
        if (!hash_equals($expected, $signature)) {
            $session->update(['status' => PosPaymentSessionStatusEnum::FAILED(), 'failure_reason' => 'Razorpay signature mismatch']);
            throw new Exception(__('labels.pos_signature_verification_failed'));
        }
        return $paymentId;
    }

    // -- Stripe ----------------------------------------------------------

    private function initStripe(PosPaymentSession $session): array
    {
        $cfg = $this->paymentConfig();
        if (empty($cfg['stripeSecretKey'])) {
            throw new Exception(__('labels.pos_stripe_not_configured'));
        }

        if (!$session->gateway_order_id || $session->gateway !== 'stripe') {
            Stripe::setApiKey($cfg['stripeSecretKey']);

            $checkout = StripeCheckoutSession::create([
                'mode'                 => 'payment',
                'payment_method_types' => ['card'],
                'line_items'           => [[
                    'price_data' => [
                        'currency'     => strtolower($session->currency_code ?: 'usd'),
                        'product_data' => ['name' => 'POS Sale'],
                        'unit_amount'  => (int) round(((float) $session->amount) * 100),
                    ],
                    'quantity' => 1,
                ]],
                'metadata' => [
                    'type'              => 'pos_session',
                    'pos_session_token' => $session->token,
                    'store_id'          => (string) $session->store_id,
                ],
                'payment_intent_data' => [
                    'metadata' => [
                        'type'              => 'pos_session',
                        'pos_session_token' => $session->token,
                        'store_id'          => (string) $session->store_id,
                    ],
                ],
                'success_url' => route('pos.public.payment.return', ['token' => $session->token, 'gateway' => 'stripe']) . '?stripe_session_id={CHECKOUT_SESSION_ID}',
                'cancel_url'  => route('pos.public.payment.show', ['token' => $session->token]),
            ]);

            $session->update(['gateway' => 'stripe', 'gateway_order_id' => $checkout->id]);
            $session->refresh();

            return ['mode' => 'redirect', 'url' => $checkout->url];
        }

        Stripe::setApiKey($cfg['stripeSecretKey']);
        $checkout = StripeCheckoutSession::retrieve($session->gateway_order_id);
        return ['mode' => 'redirect', 'url' => $checkout->url];
    }

    private function verifyStripe(PosPaymentSession $session, array $payload): string
    {
        $cfg = $this->paymentConfig();
        Stripe::setApiKey($cfg['stripeSecretKey']);

        $sid = $payload['stripe_session_id'] ?? '';
        if (!$sid) throw new Exception(__('labels.pos_missing_stripe_session_id'));
        if ($session->gateway_order_id !== $sid) throw new Exception(__('labels.pos_session_id_mismatch'));

        $checkout = StripeCheckoutSession::retrieve($sid);
        if ($checkout->payment_status !== 'paid') {
            throw new Exception(__('labels.pos_stripe_payment_not_completed'));
        }
        return (string) ($checkout->payment_intent ?? $sid);
    }

    // -- Paystack --------------------------------------------------------

    private function initPaystack(PosPaymentSession $session): array
    {
        $cfg = $this->paymentConfig();
        if (empty($cfg['paystackSecretKey'])) {
            throw new Exception(__('labels.pos_paystack_not_configured'));
        }

        $currency = $cfg['paystackCurrencyCode'] ?: ($session->currency_code ?: 'NGN');
        $reference = 'pos_' . substr($session->token, 0, 26) . '_' . time();

        $resp = Http::withHeaders([
            'Authorization' => 'Bearer ' . $cfg['paystackSecretKey'],
            'Content-Type'  => 'application/json',
        ])->post('https://api.paystack.co/transaction/initialize', [
            'email'        => $this->paystackEmailFor($session),
            'amount'       => (int) round(((float) $session->amount) * 100),
            'currency'     => $currency,
            'reference'    => $reference,
            'callback_url' => route('pos.public.payment.return', ['token' => $session->token, 'gateway' => 'paystack']),
            'metadata'     => [
                'type'              => 'pos_session',
                'pos_session_token' => $session->token,
                'store_id'          => (string) $session->store_id,
                'transaction_id'    => $session->token,
            ],
        ]);

        $body = $resp->json();
        if (!($body['status'] ?? false)) {
            throw new Exception(__('labels.pos_paystack_init_failed'));
        }

        $session->update(['gateway' => 'paystack', 'gateway_order_id' => $reference]);

        return ['mode' => 'redirect', 'url' => $body['data']['authorization_url'] ?? ''];
    }

    private function verifyPaystack(PosPaymentSession $session, array $payload): string
    {
        $cfg = $this->paymentConfig();
        $reference = $payload['reference'] ?? '';
        if (!$reference) throw new Exception(__('labels.pos_missing_paystack_reference'));
        if ($session->gateway_order_id !== $reference) throw new Exception(__('labels.pos_reference_mismatch'));

        $resp = Http::withHeaders([
            'Authorization' => 'Bearer ' . $cfg['paystackSecretKey'],
        ])->get("https://api.paystack.co/transaction/verify/{$reference}");
        $body = $resp->json();
        $status = $body['data']['status'] ?? null;
        if ($status !== 'success') {
            throw new Exception(__('labels.pos_paystack_payment_not_successful'));
        }
        return (string) ($body['data']['id'] ?? $reference);
    }

    // -- Flutterwave -----------------------------------------------------

    private function initFlutterwave(PosPaymentSession $session): array
    {
        $cfg = $this->paymentConfig();
        if (empty($cfg['flutterwaveSecretKey'])) {
            throw new Exception(__('labels.pos_flutterwave_not_configured'));
        }
        $currency = $cfg['flutterwaveCurrencyCode'] ?: ($session->currency_code ?: 'NGN');
        $txRef = 'pos_' . substr($session->token, 0, 24) . '_' . time();

        $resp = Http::withToken($cfg['flutterwaveSecretKey'])
            ->post('https://api.flutterwave.com/v3/payments', [
                'tx_ref'         => $txRef,
                'amount'         => (float) $session->amount,
                'currency'       => $currency,
                'redirect_url'   => route('pos.public.payment.return', ['token' => $session->token, 'gateway' => 'flutterwave']),
                'customer'       => [
                    'email' => $this->paystackEmailFor($session),
                    'name'  => 'Customer',
                ],
                'customizations' => [
                    'title' => 'POS Sale',
                ],
                'meta'           => [
                    'type'              => 'pos_session',
                    'pos_session_token' => $session->token,
                    'store_id'          => (string) $session->store_id,
                    'transaction_id'    => $txRef,
                ],
            ]);
        $body = $resp->json();
        if (!$resp->successful() || ($body['status'] ?? '') !== 'success') {
            throw new Exception(__('labels.pos_flutterwave_init_failed'));
        }

        $session->update(['gateway' => 'flutterwave', 'gateway_order_id' => $txRef]);
        return ['mode' => 'redirect', 'url' => $body['data']['link'] ?? ''];
    }

    private function verifyFlutterwave(PosPaymentSession $session, array $payload): string
    {
        $cfg = $this->paymentConfig();
        $txnId = $payload['transaction_id'] ?? '';
        $txRef = $payload['tx_ref'] ?? '';
        if (!$txnId) throw new Exception(__('labels.pos_missing_flutterwave_transaction_id'));
        if ($txRef && $session->gateway_order_id !== $txRef) throw new Exception(__('labels.pos_tx_ref_mismatch'));

        $resp = Http::withToken($cfg['flutterwaveSecretKey'])
            ->get("https://api.flutterwave.com/v3/transactions/{$txnId}/verify");
        $body = $resp->json();
        $data = $body['data'] ?? [];
        if (($data['status'] ?? null) !== 'successful') {
            throw new Exception(__('labels.pos_flutterwave_payment_not_successful'));
        }

        $expectAmount = (float) $session->amount;
        $gotAmount    = (float) ($data['amount'] ?? 0);
        if (abs($expectAmount - $gotAmount) > 0.01) {
            throw new Exception(__('labels.pos_flutterwave_amount_mismatch'));
        }
        return (string) ($data['id'] ?? $txnId);
    }

    // -- Xendit -----------------------------------------------------------

    private function initXendit(PosPaymentSession $session): array
    {
        $cfg = $this->paymentConfig();
        if (empty($cfg['xenditSecretKey'])) {
            throw new Exception(__('labels.pos_xendit_not_configured'));
        }
        $currency = $cfg['xenditCurrencyCode'] ?: ($session->currency_code ?: 'IDR');
        $externalId = 'pos_' . $session->token . '_' . time();

        $resp = Http::withBasicAuth($cfg['xenditSecretKey'], '')
            ->post('https://api.xendit.co/v2/invoices', [
                'external_id'          => $externalId,
                'amount'               => (float) $session->amount,
                'currency'             => $currency,
                'success_redirect_url' => route('pos.public.payment.return', ['token' => $session->token, 'gateway' => 'xendit']),
                'failure_redirect_url' => route('pos.public.payment.show', ['token' => $session->token]),
                'payer_email'          => $this->paystackEmailFor($session),
                'description'          => 'POS Sale',
            ]);

        $body = $resp->json();
        if (!$resp->successful() || empty($body['invoice_url'])) {
            throw new Exception(__('labels.pos_xendit_init_failed'));
        }

        $session->update(['gateway' => 'xendit', 'gateway_order_id' => $body['id']]);
        return ['mode' => 'redirect', 'url' => $body['invoice_url']];
    }

    private function verifyXendit(PosPaymentSession $session, array $payload): string
    {
        $cfg = $this->paymentConfig();
        $invoiceId = $session->gateway_order_id;
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
            throw new Exception(__('labels.pos_xendit_payment_not_successful'));
        }

        $expectAmount = (float) $session->amount;
        $gotAmount    = (float) ($body['amount'] ?? 0);
        if (abs($expectAmount - $gotAmount) > 0.01) {
            throw new Exception(__('labels.pos_xendit_amount_mismatch'));
        }

        $expectCurrency = strtoupper($cfg['xenditCurrencyCode'] ?: ($session->currency_code ?: 'IDR'));
        $gotCurrency    = strtoupper($body['currency'] ?? '');
        if ($expectCurrency !== $gotCurrency) {
            throw new Exception(__('labels.pos_xendit_amount_mismatch'));
        }

        return (string) ($body['id'] ?? $invoiceId);
    }

    // -- Helpers ----------------------------------------------------------

    private function markPaidAndCreateOrder(
        PosPaymentSession $session,
        string $paymentId,
        PosOrderService $orderService
    ): int {
        return DB::transaction(function () use ($session, $paymentId, $orderService) {
            $session->refresh();
            if ($session->status === PosPaymentSessionStatusEnum::PAID) {
                return (int) $session->order_id;
            }

            $payload = $session->payload;
            $payload['payment_method'] = match ($session->gateway) {
                'stripe'      => 'stripe',
                'paystack'    => 'paystack',
                'flutterwave' => 'flutterwave',
                'xendit'      => 'xendit',
                default       => 'razorpay',
            };
            if ((float) $session->cash_portion > 0) {
                $payload['split_cash_portion'] = (float) $session->cash_portion;
            }

            $seller = Seller::findOrFail($session->seller_id);
            $result = $orderService->create($seller, $payload);
            if (!$result['success']) {
                throw new Exception(__('labels.pos_order_creation_failed'));
            }
            $orderId = (int) $result['data']['order']['id'];
            $session->update([
                'status'             => PosPaymentSessionStatusEnum::PAID(),
                'gateway_payment_id' => $paymentId,
                'order_id'           => $orderId,
            ]);
            return $orderId;
        });
    }

    /**
     * Stable email for gateways that require one.
     */
    private function paystackEmailFor(PosPaymentSession $session): string
    {
        $mobile = $session->payload['walkin_customer_mobile'] ?? null;
        if ($mobile) {
            $clean = preg_replace('/\D+/', '', (string) $mobile);
            if ($clean) return "pos+{$clean}@invalid.local";
        }
        return 'pos+' . substr($session->token, 0, 8) . '@invalid.local';
    }

    private function paymentConfig(): array
    {
        $setting = $this->settingService->getSettingByVariable(SettingTypeEnum::PAYMENT());
        return (array) ($setting?->value ?? []);
    }

    public function razorpayKeyId(): string
    {
        return $this->paymentConfig()['razorpayKeyId'] ?? '';
    }
}
