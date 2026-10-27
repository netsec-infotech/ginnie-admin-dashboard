<?php

namespace App\Http\Controllers\Payments;

use App\Enums\Payment\MercadoPagoCurrencyCodeEnum;
use App\Enums\Payment\PaymentTypeEnum;
use App\Enums\PaymentStatusEnum;
use App\Enums\SettingTypeEnum;
use App\Enums\Subscription\SellerSubscriptionStatusEnum;
use App\Enums\Subscription\SubscriptionTransactionStatusEnum;
use App\Enums\Wallet\WalletTransactionStatusEnum;
use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\OrderPaymentTransaction;
use App\Models\SellerSubscription;
use App\Models\SubscriptionTransaction;
use App\Models\Wallet;
use App\Models\WalletTransaction;
use App\Services\OrderService;
use App\Services\SettingService;
use App\Support\Currency\CurrencyWriteGuard;
use App\Types\Api\ApiResponseType;
use Exception;
use Illuminate\Contracts\View\Factory;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class MercadoPagoController extends Controller
{
    private string $accessToken;

    private string $webhookSecret;

    private string $currencyCode;

    private string $paymentMode;

    private string $baseUrl = 'https://api.mercadopago.com/';

    public function __construct(SettingService $settingService)
    {
        $setting = $settingService->getSettingByVariable(SettingTypeEnum::PAYMENT());

        $this->accessToken = $setting->value['mercadoPagoAccessToken'] ?? '';
        $this->webhookSecret = $setting->value['mercadoPagoWebhookSecret'] ?? '';
        $this->currencyCode = $setting->value['mercadoPagoCurrencyCode'] ?? MercadoPagoCurrencyCodeEnum::ARS();
        $this->paymentMode = $setting->value['mercadoPagoPaymentMode'] ?? 'test';
    }

    public function createOrder(array $data): array
    {
        try {
            $validated = validator($data, [
                'amount' => 'required|numeric|min:1',
                'order_id' => 'required|numeric',
                'redirect_url' => 'nullable|string|url',
            ])->validate();

            $order = Order::find($validated['order_id']);
            if (! $order) {
                return ApiResponseType::toArray(success: false, message: 'Order not found');
            }

            $externalReference = 'ORDER-'.$order->id.'-'.time();
            $result = $this->createPreference([
                'amount' => (float) $validated['amount'],
                'title' => 'Order #'.($order->slug ?? $order->id),
                'external_reference' => $externalReference,
                'payer_email' => $order->email,
                'back_url' => $validated['redirect_url'] ?? route('order-payment.status', ['order' => $order->id]),
                'metadata' => [
                    'payment_type' => 'order_payment',
                    'order_id' => $order->id,
                ],
            ]);

            if (! ($result['success'] ?? false)) {
                return $result;
            }

            $preference = $result['data'];
            $transaction = OrderPaymentTransaction::createTransaction(
                data: [
                    'order_id' => $order->id,
                    'user_id' => auth()->id() ?: $order->user_id,
                    'amount' => $validated['amount'],
                    'currency' => $this->currencyCode,
                    'transaction_id' => $preference['id'],
                    'payment_id' => $preference['id'],
                ],
                paymentMethod: PaymentTypeEnum::MERCADO_PAGO(),
                paymentStatus: PaymentStatusEnum::PENDING()
            );

            $details = $transaction->payment_details ?? [];
            $details['mercado_pago_preference_id'] = $preference['id'];
            $details['mercado_pago_external_reference'] = $externalReference;
            $details['mercado_pago_checkout_url'] = $preference['checkout_url'];
            $transaction->payment_details = $details;
            $transaction->save();

            return ApiResponseType::toArray(
                success: true,
                message: 'Mercado Pago order preference created successfully',
                data: [
                    'link' => $preference['checkout_url'],
                    'checkout_url' => $preference['checkout_url'],
                    'init_point' => $preference['checkout_url'],
                    'transaction_id' => $preference['id'],
                    'payment_id' => $preference['id'],
                    'id' => $preference['id'],
                    'external_reference' => $externalReference,
                ]
            );
        } catch (Exception $e) {
            Log::error('Mercado Pago order preference creation failed: '.$e->getMessage());

            return ApiResponseType::toArray(
                success: false,
                message: 'Unable to create Mercado Pago order: '.$e->getMessage(),
                data: ['error' => $e->getMessage()]
            );
        }
    }

    public function createPendingOrderPayment(OrderPaymentTransaction $transaction, array $snapshot): array
    {
        try {
            $externalReference = 'ORDER-TXN-'.$transaction->uuid;
            $result = $this->createPreference([
                'amount' => (float) $transaction->amount,
                'title' => 'Order checkout '.$transaction->uuid,
                'external_reference' => $externalReference,
                'payer_email' => $transaction->user?->email,
                'back_url' => route('order-payment.status', ['order' => $transaction->uuid]),
                'metadata' => [
                    'payment_type' => 'order_payment',
                    'pending_transaction_id' => $transaction->id,
                    'transaction_uuid' => $transaction->uuid,
                ],
            ]);

            if (! ($result['success'] ?? false)) {
                return $result;
            }

            $preference = $result['data'];
            $details = $transaction->payment_details ?? [];
            $details['mercado_pago_preference_id'] = $preference['id'];
            $details['mercado_pago_external_reference'] = $externalReference;
            $details['mercado_pago_checkout_url'] = $preference['checkout_url'];
            $transaction->update([
                'transaction_id' => $preference['id'],
                'payment_details' => $details,
            ]);

            return ApiResponseType::toArray(
                success: true,
                message: 'Mercado Pago order preference created successfully',
                data: [
                    'link' => $preference['checkout_url'],
                    'checkout_url' => $preference['checkout_url'],
                    'payment_url' => $preference['checkout_url'],
                    'init_point' => $preference['checkout_url'],
                    'transaction_id' => $preference['id'],
                    'payment_id' => $preference['id'],
                    'id' => $preference['id'],
                    'external_reference' => $externalReference,
                ]
            );
        } catch (Exception $e) {
            Log::error('Mercado Pago pending order preference creation failed: '.$e->getMessage());

            return ApiResponseType::toArray(
                success: false,
                message: 'Unable to create Mercado Pago order: '.$e->getMessage(),
                data: ['error' => $e->getMessage()]
            );
        }
    }

    public function createSubscriptionInitialize(array $data): array
    {
        try {
            $validated = validator($data, [
                'amount' => 'required|numeric|min:1',
                'subscription_transaction_id' => 'required|integer',
                'seller_subscription_id' => 'required|integer',
                'seller_id' => 'required|integer',
                'user_id' => 'nullable|integer',
            ])->validate();

            $transaction = SubscriptionTransaction::find($validated['subscription_transaction_id']);
            $transactionUuid = $transaction?->uuid ?? (string) $validated['subscription_transaction_id'];
            $externalReference = 'SUBSCRIPTION-'.$validated['subscription_transaction_id'].'-'.time();

            $result = $this->createPreference([
                'amount' => (float) $validated['amount'],
                'title' => 'Seller Subscription Payment',
                'external_reference' => $externalReference,
                'payer_email' => auth()->user()->email ?? null,
                'back_url' => route('subscription-payment.status', ['transaction' => $transactionUuid]),
                'metadata' => [
                    'payment_type' => 'subscription_payment',
                    'subscription_transaction_id' => $validated['subscription_transaction_id'],
                    'seller_subscription_id' => $validated['seller_subscription_id'],
                    'seller_id' => $validated['seller_id'],
                ],
            ]);

            if (! ($result['success'] ?? false)) {
                return $result;
            }

            if ($transaction) {
                $transaction->transaction_id = $result['data']['id'];
                $transaction->save();
            }

            return ApiResponseType::toArray(
                success: true,
                message: 'Mercado Pago subscription preference created successfully',
                data: [
                    'id' => $result['data']['id'],
                    'transaction_id' => $result['data']['id'],
                    'payment_id' => $result['data']['id'],
                    'link' => $result['data']['checkout_url'],
                    'checkout_url' => $result['data']['checkout_url'],
                    'init_point' => $result['data']['checkout_url'],
                ]
            );
        } catch (Exception $e) {
            Log::error('Mercado Pago subscription initialization failed: '.$e->getMessage());

            return ApiResponseType::toArray(
                success: false,
                message: 'Unable to initialize Mercado Pago payment: '.$e->getMessage(),
                data: ['error' => $e->getMessage()]
            );
        }
    }

    public function createWalletRechargePayment(array $data): array
    {
        try {
            $validated = validator($data, [
                'amount' => 'required|numeric|min:1',
                'transaction_id' => 'required|string',
                'redirect_url' => 'nullable|string|url',
            ])->validate();

            $externalReference = 'WALLET-'.$validated['transaction_id'].'-'.time();
            $result = $this->createPreference([
                'amount' => (float) $validated['amount'],
                'title' => 'Wallet Top-up',
                'external_reference' => $externalReference,
                'payer_email' => auth()->user()->email ?? null,
                'back_url' => $validated['redirect_url'] ?? route('wallet-payment.status', ['transaction' => $validated['transaction_id']]),
                'metadata' => [
                    'payment_type' => 'wallet_recharge',
                    'wallet_transaction_id' => $validated['transaction_id'],
                ],
            ]);

            if (! ($result['success'] ?? false)) {
                return $result;
            }

            $transaction = WalletTransaction::find($validated['transaction_id']);
            if ($transaction) {
                $transaction->transaction_reference = $result['data']['id'];
                $transaction->save();
            }

            return ApiResponseType::toArray(
                success: true,
                message: 'Mercado Pago wallet recharge initialized successfully',
                data: [
                    'id' => $result['data']['id'],
                    'transaction_id' => $result['data']['id'],
                    'payment_id' => $result['data']['id'],
                    'link' => $result['data']['checkout_url'],
                    'checkout_url' => $result['data']['checkout_url'],
                    'init_point' => $result['data']['checkout_url'],
                ]
            );
        } catch (Exception $e) {
            Log::error('Mercado Pago wallet recharge creation failed: '.$e->getMessage());

            return ApiResponseType::toArray(
                success: false,
                message: 'Unable to create wallet recharge order: '.$e->getMessage(),
                data: ['error' => $e->getMessage()]
            );
        }
    }

    public function createTipInvoice(Order $order, float $amount, string $transactionId): array
    {
        try {
            $result = $this->createPreference([
                'amount' => $amount,
                'title' => 'Rider Tip for Order #'.($order->slug ?? $order->id),
                'external_reference' => $transactionId,
                'payer_email' => $order->email,
                'back_url' => route('tip-payment.status', ['transaction' => $transactionId]),
                'metadata' => [
                    'payment_type' => 'post_order_tip',
                    'order_id' => $order->id,
                ],
            ]);

            if (! ($result['success'] ?? false)) {
                return $result;
            }

            return ApiResponseType::toArray(
                success: true,
                message: 'Mercado Pago tip preference created successfully',
                data: [
                    'preference_id' => $result['data']['id'],
                    'checkout_url' => $result['data']['checkout_url'],
                    'invoice_url' => $result['data']['checkout_url'],
                    'link' => $result['data']['checkout_url'],
                ]
            );
        } catch (Exception $e) {
            Log::error('Mercado Pago tip preference creation failed: '.$e->getMessage());

            return ApiResponseType::toArray(
                success: false,
                message: 'Unable to create Mercado Pago tip payment: '.$e->getMessage(),
                data: ['error' => $e->getMessage()]
            );
        }
    }

    public function showSubscriptionPaymentPage(string $transaction): Factory|View
    {
        $txn = SubscriptionTransaction::with('plan')
            ->where('uuid', $transaction)
            ->firstOrFail();

        if ((string) $txn->payment_gateway !== (string) PaymentTypeEnum::MERCADO_PAGO()) {
            abort(404);
        }

        $checkoutUrl = $this->resolveCheckoutUrl($txn->transaction_id);
        $plan = $txn->plan;
        $planName = optional($plan)->name ?? 'Subscription Plan';

        return view('payments.payment', [
            'gateway' => 'mercado-pago',
            'authorizationUrl' => $checkoutUrl,
            'amount' => (float) $txn->amount,
            'currency' => $this->currencyCode,
            'transactionId' => $txn->id,
            'transactionUuid' => $txn->uuid,
            'payButtonLabel' => __('labels.pay_with_mercado_pago'),
            'statusRoute' => route('subscription-payment.status', ['transaction' => $txn->uuid]),
            'paymentContext' => [
                'title' => __('labels.subscription_payment_summary'),
                'lineItems' => [
                    ['label' => __('labels.plan'), 'value' => $planName, 'bold' => true],
                    ['label' => __('labels.duration'), 'value' => ($plan->duration_type ?? null) === 'unlimited' ? __('labels.unlimited') : (($plan->duration_days ?? 0).' '.__('labels.days'))],
                ],
            ],
        ]);
    }

    public function showWalletPaymentPage(int $transaction): Factory|View
    {
        $txn = WalletTransaction::where('id', $transaction)->firstOrFail();

        if ($txn->payment_method !== PaymentTypeEnum::MERCADO_PAGO()) {
            abort(404);
        }

        return view('payments.payment', [
            'gateway' => 'mercado-pago',
            'authorizationUrl' => $this->resolveCheckoutUrl($txn->transaction_reference),
            'amount' => (float) $txn->amount,
            'currency' => $this->currencyCode,
            'transactionId' => $txn->id,
            'transactionUuid' => $txn->id,
            'statusRoute' => route('wallet-payment.status', ['transaction' => $txn->id]),
            'payButtonLabel' => __('labels.pay_with_mercado_pago'),
            'paymentContext' => [
                'title' => __('labels.wallet_topup_payment_summary'),
                'lineItems' => [
                    ['label' => __('labels.description'), 'value' => $txn->description ?? __('labels.wallet_topup'), 'bold' => true],
                ],
            ],
        ]);
    }

    public function handleWebhook(Request $request): JsonResponse
    {
        if (! $this->isValidWebhookSignature($request)) {
            Log::error('Mercado Pago Webhook: Invalid signature.');

            return response()->json(['error' => 'Unauthorized'], 401);
        }

        $paymentId = (string) ($request->input('data.id') ?? $request->query('data_id') ?? $request->query('data.id') ?? '');
        if ($paymentId === '') {
            return response()->json(['error' => 'Payment ID missing'], 400);
        }

        DB::beginTransaction();
        try {
            $verification = $this->verifyPayment($paymentId);
            if (! ($verification['success'] ?? false)) {
                DB::rollBack();

                return response()->json(['error' => 'Verification failed'], 400);
            }

            DB::commit();

            return response()->json(['status' => 'success']);
        } catch (Exception $e) {
            DB::rollBack();
            Log::error('Mercado Pago webhook processing failed: '.$e->getMessage());

            return response()->json(['error' => 'Internal server error'], 500);
        }
    }

    public function verifyPayment(string $paymentId): array
    {
        try {
            if ($this->accessToken === '') {
                return ApiResponseType::toArray(success: false, message: 'Mercado Pago access token not configured');
            }

            $response = Http::withToken($this->accessToken)
                ->acceptJson()
                ->get($this->baseUrl.'v1/payments/'.$paymentId);

            if (! $response->successful()) {
                throw new Exception('Mercado Pago API returned error: '.$response->body());
            }

            $payment = $response->json() ?? [];
            $externalReference = (string) ($payment['external_reference'] ?? '');
            $transaction = $this->findTransaction($externalReference, $paymentId);
            $paymentType = $this->paymentTypeFromReference($externalReference);
            $status = strtolower((string) ($payment['status'] ?? ''));

            if ($status === 'approved') {
                if ($transaction) {
                    $this->handlePaymentSucceeded($paymentType, $payment, $transaction);
                }

                return ['success' => true, 'message' => 'Payment verified successfully', 'data' => $payment];
            }

            if (in_array($status, ['rejected', 'cancelled', 'refunded', 'charged_back'], true)) {
                if ($transaction) {
                    $this->handlePaymentFailed($paymentType, $transaction, $status);
                }

                return ['success' => false, 'message' => "Payment failed with status: {$status}", 'data' => $payment];
            }

            return ['success' => false, 'message' => "Payment not completed yet, status: {$status}", 'data' => $payment];
        } catch (Exception $e) {
            Log::error('Mercado Pago verification failed: '.$e->getMessage());

            return ['success' => false, 'message' => $e->getMessage(), 'data' => null];
        }
    }

    public function refundPayment(string $transactionId, $amount = null): array
    {
        try {
            if ($this->accessToken === '') {
                return ['success' => false, 'message' => 'Mercado Pago access token not configured'];
            }

            $transaction = OrderPaymentTransaction::where('transaction_id', $transactionId)->first();
            if (! $transaction) {
                return ['success' => false, 'message' => 'Transaction not found'];
            }

            if ($transaction->payment_status === PaymentStatusEnum::REFUNDED()) {
                return ['success' => true, 'message' => 'Already refunded', 'data' => []];
            }

            $details = $transaction->payment_details ?? [];
            $paymentId = $details['mercado_pago_payment_id'] ?? $transactionId;
            $payload = [];
            if ($amount !== null) {
                $payload['amount'] = (float) $amount;
            }

            $response = Http::withToken($this->accessToken)
                ->withHeaders(['X-Idempotency-Key' => 'mp-refund-'.$transaction->id.'-'.time()])
                ->acceptJson()
                ->post($this->baseUrl.'v1/payments/'.$paymentId.'/refunds', $payload);

            $body = $response->json() ?? [];
            if (! $response->successful()) {
                throw new Exception($body['message'] ?? 'Mercado Pago refund request rejected');
            }

            $details['mercado_pago_refund_details'] = $body;
            $transaction->payment_details = $details;
            $transaction->payment_status = PaymentStatusEnum::REFUNDED();
            $transaction->save();

            return ['success' => true, 'message' => 'Refund processed successfully', 'data' => $body];
        } catch (Exception $e) {
            Log::error('Mercado Pago refund failed: '.$e->getMessage());

            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    public function createOrderPaymentIntent(Request $request): JsonResponse
    {
        $request->validate([
            'amount' => 'required|numeric|min:1',
            'order_id' => 'required|numeric',
            'redirect_url' => 'nullable|string|url',
        ]);

        $result = $this->createOrder($request->all());

        return ApiResponseType::sendJsonResponse(
            (bool) ($result['success'] ?? false),
            ($result['success'] ?? false) ? 'labels.payment_intent_created_successfully' : ($result['message'] ?? 'messages.something_went_wrong'),
            $result['data'] ?? [],
            ($result['success'] ?? false) ? 200 : 400
        );
    }

    public function createTipPaymentIntent(Request $request): JsonResponse
    {
        try {
            $user = auth()->user();
            if (! $user) {
                return ApiResponseType::sendJsonResponse(false, __('labels.user_not_authenticated'), [], 401);
            }

            $validated = $request->validate([
                'order_id' => 'required',
                'rider_tip_amount' => 'required|numeric|min:1',
            ]);

            $order = Order::where('user_id', $user->id)
                ->where(function ($query) use ($validated) {
                    $orderId = (string) $validated['order_id'];
                    $query->where('slug', $orderId)
                        ->orWhere('uuid', $orderId)
                        ->orWhere('id', $orderId);
                })
                ->first();

            if (! $order) {
                return ApiResponseType::sendJsonResponse(false, __('labels.order_not_found') ?? 'Order not found', [], 404);
            }

            $tipAmount = (float) $validated['rider_tip_amount'];
            $transactionId = 'TIP-'.Str::upper(Str::random(12));
            $orderCurrency = app(CurrencyWriteGuard::class)->orderCurrency($order);
            $transaction = OrderPaymentTransaction::create([
                'uuid' => (string) Str::uuid(),
                'user_id' => $order->user_id,
                'order_id' => $order->id,
                'transaction_id' => $transactionId,
                'amount' => $tipAmount,
                'currency' => $orderCurrency,
                'order_currency_code' => $orderCurrency,
                'gateway_currency_code' => $orderCurrency,
                'gateway_amount' => $tipAmount,
                'payment_method' => PaymentTypeEnum::MERCADO_PAGO(),
                'payment_status' => PaymentStatusEnum::PENDING(),
            ]);

            $result = $this->createTipInvoice($order, $tipAmount, $transactionId);
            if (! ($result['success'] ?? false)) {
                return ApiResponseType::sendJsonResponse(false, $result['message'] ?? 'Unable to create Mercado Pago tip payment', [], 400);
            }

            $checkoutUrl = $result['data']['checkout_url'] ?? '';
            $transaction->update([
                'payment_details' => [
                    'mercado_pago_external_reference' => $transactionId,
                    'mercado_pago_preference_id' => $result['data']['preference_id'] ?? null,
                    'mercado_pago_checkout_url' => $checkoutUrl,
                ],
            ]);

            return ApiResponseType::sendJsonResponse(
                success: true,
                message: __('labels.payment_intent_created_successfully') ?? 'Mercado Pago tip payment intent created successfully',
                data: [
                    'order_id' => $order->id,
                    'transaction_id' => $transactionId,
                    'rider_tip_amount' => $tipAmount,
                    'link' => $checkoutUrl,
                    'checkout_url' => $checkoutUrl,
                    'payment_url' => $checkoutUrl,
                ]
            );
        } catch (Exception $e) {
            Log::error('Mercado Pago createTipPaymentIntent failed: '.$e->getMessage());

            return ApiResponseType::sendJsonResponse(false, 'messages.something_went_wrong', ['error' => $e->getMessage()], 500);
        }
    }

    private function createPreference(array $data): array
    {
        if ($this->accessToken === '') {
            return ApiResponseType::toArray(success: false, message: 'Mercado Pago access token not configured');
        }

        $backUrl = $data['back_url'] ?? config('app.url');
        $payerEmail = $this->resolvePayerEmail($data['payer_email'] ?? null, $data['external_reference']);
        $unitPrice = $this->normalizeUnitPrice((float) $data['amount']);

        // Mercado Pago identifies our local record through external_reference on every callback.
        // Do not force auto_return; Mercado Pago rejects it when the account/site
        // does not accept the provided back URL even if the URL is present.
        $payload = [
            'items' => [[
                'title' => $data['title'],
                'quantity' => 1,
                'unit_price' => $unitPrice,
                'currency_id' => $this->currencyCode,
            ]],
            'payer' => ['email' => $payerEmail],
            'external_reference' => $data['external_reference'],
            'back_urls' => [
                'success' => $backUrl,
                'pending' => $backUrl,
                'failure' => $backUrl,
            ],
            'metadata' => $data['metadata'] ?? [],
        ];

        $notificationUrl = $this->providerCallbackUrl(route('mercadopago.webhook'));
        if ($notificationUrl !== null) {
            $payload['notification_url'] = $notificationUrl;
        }

        $response = Http::withToken($this->accessToken)
            ->withHeaders(['X-Idempotency-Key' => (string) Str::uuid()])
            ->acceptJson()
            ->post($this->baseUrl.'checkout/preferences', $payload);

        $body = $response->json() ?? [];
        $checkoutUrl = $this->paymentMode === 'live'
            ? ($body['init_point'] ?? null)
            : ($body['sandbox_init_point'] ?? $body['init_point'] ?? null);

        if (! $response->successful() || empty($body['id']) || empty($checkoutUrl)) {
            Log::error('Mercado Pago preference creation failed: '.json_encode($body));

            return ApiResponseType::toArray(success: false, message: $body['message'] ?? 'Unable to create payment preference', data: $body ?? []);
        }

        return ApiResponseType::toArray(
            success: true,
            message: 'Mercado Pago preference created successfully',
            data: [
                'id' => $body['id'],
                'checkout_url' => $checkoutUrl,
                'raw' => $body,
            ]
        );
    }

    private function normalizeUnitPrice(float $amount): float|int
    {
        if (in_array($this->currencyCode, ['CLP', 'COP'], true)) {
            return max(1, (int) round($amount));
        }

        return max(1, round($amount, 2));
    }

    private function resolvePayerEmail(?string $payerEmail, string $externalReference): string
    {
        if ($this->paymentMode === 'live' && filter_var($payerEmail, FILTER_VALIDATE_EMAIL) !== false) {
            return $payerEmail;
        }

        // Mercado Pago sandbox expects a test-user payer email when paying with test cards.
        return 'hyperlocal-'.substr(hash('sha256', $externalReference), 0, 20).'@testuser.com';
    }

    private function resolveCheckoutUrl(?string $preferenceId): string
    {
        if (empty($preferenceId) || $this->accessToken === '') {
            return '';
        }

        try {
            $response = Http::withToken($this->accessToken)
                ->acceptJson()
                ->get($this->baseUrl.'checkout/preferences/'.$preferenceId);

            if (! $response->successful()) {
                return '';
            }

            $body = $response->json() ?? [];

            return $this->paymentMode === 'live'
                ? (string) ($body['init_point'] ?? '')
                : (string) ($body['sandbox_init_point'] ?? $body['init_point'] ?? '');
        } catch (Exception $e) {
            Log::error('Mercado Pago preference lookup failed: '.$e->getMessage());

            return '';
        }
    }

    private function providerCallbackUrl(?string $url): ?string
    {
        if (empty($url) || ! filter_var($url, FILTER_VALIDATE_URL)) {
            return null;
        }

        $scheme = parse_url($url, PHP_URL_SCHEME);
        $host = parse_url($url, PHP_URL_HOST);
        if (! in_array($scheme, ['http', 'https'], true) || empty($host)) {
            return null;
        }

        $localHosts = ['localhost', '127.0.0.1', '::1'];
        if (in_array($host, $localHosts, true) || str_ends_with($host, '.test') || str_ends_with($host, '.local')) {
            return null;
        }

        if (filter_var($host, FILTER_VALIDATE_IP) && ! filter_var($host, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
            return null;
        }

        return $url;
    }

    private function isValidWebhookSignature(Request $request): bool
    {
        if ($this->webhookSecret === '') {
            return true;
        }

        $parts = collect(explode(',', (string) $request->header('x-signature')))
            ->mapWithKeys(function (string $part): array {
                [$key, $value] = array_pad(explode('=', trim($part), 2), 2, '');

                return [$key => $value];
            });

        $timestamp = (string) $parts->get('ts', '');
        $signature = (string) $parts->get('v1', '');
        $requestId = (string) $request->header('x-request-id', '');
        $dataId = strtolower((string) ($request->query('data_id') ?? $request->query('data.id') ?? $request->input('data.id') ?? ''));

        if ($timestamp === '' || $signature === '') {
            return false;
        }

        $manifest = '';
        if ($dataId !== '') {
            $manifest .= "id:{$dataId};";
        }
        if ($requestId !== '') {
            $manifest .= "request-id:{$requestId};";
        }
        $manifest .= "ts:{$timestamp};";

        return hash_equals(hash_hmac('sha256', $manifest, $this->webhookSecret), $signature);
    }

    private function findTransaction(string $externalReference, string $paymentId)
    {
        if (Str::startsWith(strtoupper($externalReference), 'ORDER-')) {
            $identifier = $this->parseIdentifier($externalReference);

            return OrderPaymentTransaction::where('payment_details->mercado_pago_external_reference', $externalReference)
                ->orWhere('transaction_id', $paymentId)
                ->when($identifier, fn ($query) => $query->orWhere('order_id', $identifier))
                ->first();
        }

        if (Str::startsWith(strtoupper($externalReference), 'SUBSCRIPTION-')) {
            $identifier = $this->parseIdentifier($externalReference);

            return SubscriptionTransaction::where('transaction_id', $paymentId)
                ->when($identifier, fn ($query) => $query->orWhere('id', $identifier))
                ->first();
        }

        if (Str::startsWith(strtoupper($externalReference), 'WALLET-')) {
            $identifier = $this->parseIdentifier($externalReference);

            return WalletTransaction::where('transaction_reference', $paymentId)
                ->when($identifier, fn ($query) => $query->orWhere('id', $identifier))
                ->first();
        }

        if (Str::startsWith(strtoupper($externalReference), 'TIP-')) {
            return OrderPaymentTransaction::where('transaction_id', $externalReference)
                ->orWhere('transaction_id', $paymentId)
                ->first();
        }

        return null;
    }

    private function paymentTypeFromReference(string $externalReference): string
    {
        return match (true) {
            Str::startsWith(strtoupper($externalReference), 'SUBSCRIPTION-') => 'subscription_payment',
            Str::startsWith(strtoupper($externalReference), 'WALLET-') => 'wallet_recharge',
            Str::startsWith(strtoupper($externalReference), 'TIP-') => 'post_order_tip',
            default => 'order_payment',
        };
    }

    private function handlePaymentSucceeded(string $paymentType, array $payment, $transaction): void
    {
        $paymentId = (string) $payment['id'];

        if ($paymentType === 'wallet_recharge' && $transaction instanceof WalletTransaction) {
            if ($transaction->status === WalletTransactionStatusEnum::COMPLETED()) {
                return;
            }

            $transaction->update([
                'transaction_reference' => $paymentId,
                'description' => 'Wallet Recharge Payment Captured via Mercado Pago',
            ]);

            Wallet::captureRecharge($transaction->id);

            return;
        }

        if ($paymentType === 'subscription_payment' && $transaction instanceof SubscriptionTransaction) {
            if ($transaction->status === SubscriptionTransactionStatusEnum::COMPLETED()) {
                return;
            }

            $transaction->update([
                'status' => SubscriptionTransactionStatusEnum::COMPLETED(),
                'transaction_id' => $paymentId,
            ]);

            $subscription = SellerSubscription::find($transaction->seller_subscription_id);
            if ($subscription) {
                $subscription->status = SellerSubscriptionStatusEnum::ACTIVE();
                $subscription->price_paid = $transaction->amount;
                $subscription->save();
            }

            return;
        }

        if ($paymentType === 'post_order_tip' && $transaction instanceof OrderPaymentTransaction) {
            if ($transaction->payment_status === PaymentStatusEnum::COMPLETED()) {
                return;
            }

            $details = $transaction->payment_details ?? [];
            $details['mercado_pago_payment_id'] = $paymentId;
            $transaction->update([
                'payment_status' => PaymentStatusEnum::COMPLETED(),
                'payment_details' => $details,
            ]);

            $order = Order::find($transaction->order_id);
            if ($order) {
                app(OrderService::class)->applyCompletedTipToOrder($order, (float) ($payment['transaction_amount'] ?? $transaction->amount), (string) $transaction->transaction_id);
            }

            return;
        }

        if ($paymentType === 'order_payment' && $transaction instanceof OrderPaymentTransaction) {
            if ($transaction->payment_status === PaymentStatusEnum::COMPLETED() && $transaction->order_id !== null) {
                return;
            }

            if ($transaction->order_id === null) {
                $order = app(OrderService::class)->createConfirmedHostedGatewayOrder($transaction);
                if (! $order) {
                    Log::error("Mercado Pago: Unable to create order after payment succeeded for transaction #{$transaction->id}");

                    return;
                }
                $transaction->refresh();
            }

            $details = $transaction->payment_details ?? [];
            $details['mercado_pago_payment_id'] = $paymentId;
            $transaction->update([
                'payment_status' => PaymentStatusEnum::COMPLETED(),
                'payment_details' => $details,
            ]);

            if ($transaction->order_id !== null) {
                Order::capturePayment($transaction->order_id);
                OrderItem::capturePayment($transaction->order_id);
                app(OrderService::class)->clearCartAfterCapturedOrder($transaction->order_id);
            }
        }
    }

    private function handlePaymentFailed(string $paymentType, $transaction, string $status): void
    {
        if ($paymentType === 'wallet_recharge' && $transaction instanceof WalletTransaction) {
            $transaction->update([
                'status' => WalletTransactionStatusEnum::FAILED(),
                'description' => "Wallet Recharge Failed via Mercado Pago ({$status})",
            ]);

            return;
        }

        if ($paymentType === 'subscription_payment' && $transaction instanceof SubscriptionTransaction) {
            $transaction->update(['status' => SubscriptionTransactionStatusEnum::FAILED()]);
            $subscription = SellerSubscription::find($transaction->seller_subscription_id);
            if ($subscription) {
                $subscription->status = SellerSubscriptionStatusEnum::CANCELLED();
                $subscription->save();
            }

            return;
        }

        if ($transaction instanceof OrderPaymentTransaction) {
            $transaction->update([
                'payment_status' => PaymentStatusEnum::FAILED(),
                'message' => "Payment Failed ({$status})",
            ]);

            if ($transaction->order_id !== null && $paymentType !== 'post_order_tip') {
                Order::paymentFailed($transaction->order_id);
                OrderItem::paymentFailed($transaction->order_id);
            }
        }
    }

    private function parseIdentifier(string $externalReference): ?string
    {
        $parts = explode('-', $externalReference);

        return $parts[1] ?? null;
    }
}
