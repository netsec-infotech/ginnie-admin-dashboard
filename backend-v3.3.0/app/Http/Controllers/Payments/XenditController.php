<?php

namespace App\Http\Controllers\Payments;

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
use App\Services\CartService;
use App\Services\OrderService;
use App\Services\SettingService;
use App\Support\Currency\CurrencyWriteGuard;
use App\Types\Api\ApiResponseType;
use Exception;
use Illuminate\Contracts\View\Factory;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class XenditController extends Controller
{
    private string $secretKey;

    private string $webhookToken;

    private string $currencyCode;

    private string $baseUrl = 'https://api.xendit.co/';

    public function __construct(SettingService $settingService)
    {
        $setting = $settingService->getSettingByVariable(SettingTypeEnum::PAYMENT());

        $this->secretKey = $setting->value['xenditSecretKey'] ?? '';
        $this->webhookToken = $setting->value['xenditWebhookVerificationToken'] ?? '';
        $this->currencyCode = $setting->value['xenditCurrencyCode'] ?? 'IDR';
    }

    /**
     * Create invoice for Order checkout
     */
    public function createOrder(array $data): array
    {
        try {
            $data['redirect_url'] = $data['redirect_url'] ?? config('app.url');

            $validated = validator($data, [
                'amount' => 'required|numeric|min:1',
                'order_id' => 'required|numeric',
                'redirect_url' => 'required|string|url',
            ])->validate();

            if (empty($this->secretKey)) {
                return ApiResponseType::toArray(success: false, message: 'Xendit secret key not configured');
            }

            $order = Order::find($validated['order_id']);
            if (! $order) {
                return ApiResponseType::toArray(success: false, message: 'Order not found');
            }

            $externalId = 'ORDER-'.$order->id.'-'.time();
            $email = $order->email ?: ('order+'.substr($order->uuid, 0, 8).'@invalid.local');

            $resp = Http::withBasicAuth($this->secretKey, '')
                ->post($this->baseUrl.'v2/invoices', [
                    'external_id' => $externalId,
                    'amount' => (float) $validated['amount'],
                    'currency' => $this->currencyCode,
                    'success_redirect_url' => $validated['redirect_url'],
                    'failure_redirect_url' => $validated['redirect_url'],
                    'payer_email' => $email,
                    'description' => 'Order #'.$order->slug,
                ]);

            $body = $resp->json();

            if (! $resp->successful() || empty($body['invoice_url'])) {
                Log::error('Xendit Order invoice creation failed API Response: '.json_encode($body));
                throw new Exception($body['message'] ?? 'Unable to create payment invoice');
            }

            $txnData = [
                'order_id' => $validated['order_id'],
                'user_id' => auth()->id() ?: $order->user_id,
                'amount' => $validated['amount'],
                'currency' => $this->currencyCode,
                'payment_id' => $body['id'],
                'transaction_id' => $body['id'],
            ];

            // Create Transaction record in pending state
            $transaction = OrderPaymentTransaction::createTransaction(
                data: $txnData,
                paymentMethod: PaymentTypeEnum::XENDIT(),
                paymentStatus: PaymentStatusEnum::PENDING()
            );

            // Save external ID and url details
            $details = $transaction->payment_details ?? [];
            $details['xendit_external_id'] = $externalId;
            $details['xendit_invoice_url'] = $body['invoice_url'];
            $transaction->payment_details = $details;
            $transaction->transaction_id = $body['id'];
            $transaction->save();

            Log::info("Xendit Order invoice created: {$body['id']} for Order: {$order->id}");

            return ApiResponseType::toArray(
                success: true,
                message: 'Xendit order invoice created successfully',
                data: [
                    'link' => $body['invoice_url'],
                    'invoice_url' => $body['invoice_url'],
                    'transaction_id' => $body['id'],
                    'payment_id' => $body['id'],
                    'id' => $body['id'],
                    'external_id' => $externalId,
                ]
            );
        } catch (Exception $e) {
            Log::error('Xendit order invoice creation failed: '.$e->getMessage());

            return ApiResponseType::toArray(
                success: false,
                message: 'Unable to create Xendit order: '.$e->getMessage(),
                data: ['error' => $e->getMessage()]
            );
        }
    }

    public function createPendingOrderPayment(OrderPaymentTransaction $transaction, array $snapshot): array
    {
        try {
            $redirectUrl = route('order-payment.status', ['order' => $transaction->uuid]);

            if (empty($this->secretKey)) {
                return ApiResponseType::toArray(success: false, message: 'Xendit secret key not configured');
            }

            $externalId = 'ORDER-TXN-'.$transaction->uuid;
            $email = $transaction->user?->email ?: ('order+'.substr($transaction->uuid, 0, 8).'@invalid.local');

            $resp = Http::withBasicAuth($this->secretKey, '')
                ->post($this->baseUrl.'v2/invoices', [
                    'external_id' => $externalId,
                    'amount' => (float) $transaction->amount,
                    'currency' => $this->currencyCode,
                    'success_redirect_url' => $redirectUrl,
                    'failure_redirect_url' => $redirectUrl,
                    'payer_email' => $email,
                    'description' => 'Order checkout '.$transaction->uuid,
                ]);

            $body = $resp->json();

            if (! $resp->successful() || empty($body['invoice_url'])) {
                Log::error('Xendit pending order invoice creation failed API Response: '.json_encode($body));
                throw new Exception($body['message'] ?? 'Unable to create payment invoice');
            }

            $details = $transaction->payment_details ?? [];
            $details['xendit_external_id'] = $externalId;
            $details['xendit_invoice_url'] = $body['invoice_url'];
            $details['xendit_invoice_id'] = $body['id'] ?? null;
            $transaction->update([
                'transaction_id' => $body['id'],
                'payment_details' => $details,
            ]);

            return ApiResponseType::toArray(
                success: true,
                message: 'Xendit order invoice created successfully',
                data: [
                    'link' => $body['invoice_url'],
                    'invoice_url' => $body['invoice_url'],
                    'payment_url' => $body['invoice_url'],
                    'transaction_id' => $body['id'],
                    'payment_id' => $body['id'],
                    'id' => $body['id'],
                    'external_id' => $externalId,
                ]
            );
        } catch (Exception $e) {
            Log::error('Xendit pending order invoice creation failed: '.$e->getMessage());

            return ApiResponseType::toArray(
                success: false,
                message: 'Unable to create Xendit order invoice: '.$e->getMessage(),
                data: ['error' => $e->getMessage()]
            );
        }
    }

    /**
     * Initialize Xendit for subscription purchases
     */
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

            if (empty($this->secretKey)) {
                return ApiResponseType::toArray(success: false, message: 'Xendit secret key not configured');
            }

            $externalId = 'SUBSCRIPTION-'.$validated['subscription_transaction_id'].'-'.time();
            $st = SubscriptionTransaction::find($validated['subscription_transaction_id']);
            $transactionUuid = $st?->uuid ?? (string) $validated['subscription_transaction_id'];
            $email = auth()->user()->email ?? 'seller-'.$validated['seller_id'].'@invalid.local';

            $resp = Http::withBasicAuth($this->secretKey, '')
                ->post($this->baseUrl.'v2/invoices', [
                    'external_id' => $externalId,
                    'amount' => (float) $validated['amount'],
                    'currency' => $this->currencyCode,
                    'success_redirect_url' => route('subscription-payment.status', ['transaction' => $transactionUuid]),
                    'failure_redirect_url' => route('subscription-payment.status', ['transaction' => $transactionUuid]),
                    'payer_email' => $email,
                    'description' => 'Seller Subscription Payment',
                ]);

            $body = $resp->json();

            if (! $resp->successful() || empty($body['invoice_url'])) {
                Log::error('Xendit Subscription invoice creation failed API Response: '.json_encode($body));
                throw new Exception($body['message'] ?? 'Unable to create payment invoice');
            }

            if ($st) {
                $st->transaction_id = $body['id'];
                $st->save();
            }

            Log::info("Xendit Subscription invoice created: {$body['id']} for Transaction UUID: {$transactionUuid}");

            return ApiResponseType::toArray(
                success: true,
                message: 'Xendit subscription invoice created successfully',
                data: [
                    'id' => $body['id'],
                    'transaction_id' => $body['id'],
                    'payment_id' => $body['id'],
                    'link' => $body['invoice_url'],
                    'invoice_url' => $body['invoice_url'],
                ]
            );
        } catch (Exception $e) {
            Log::error('Xendit subscription initialization failed: '.$e->getMessage());

            return ApiResponseType::toArray(
                success: false,
                message: 'Unable to initialize Xendit payment: '.$e->getMessage(),
                data: ['error' => $e->getMessage()]
            );
        }
    }

    /**
     * Create wallet recharge payment invoice
     */
    public function createWalletRechargePayment(array $data): array
    {
        try {
            $data['redirect_url'] = $data['redirect_url'] ?? (isset($data['transaction_id']) ? route('wallet-payment.status', ['transaction' => $data['transaction_id']]) : config('app.url'));

            $validated = validator($data, [
                'amount' => 'required|numeric|min:1',
                'transaction_id' => 'required|string',
                'redirect_url' => 'required|string|url',
            ])->validate();

            if (empty($this->secretKey)) {
                return ApiResponseType::toArray(success: false, message: 'Xendit secret key not configured');
            }

            $externalId = 'WALLET-'.$validated['transaction_id'].'-'.time();
            $txn = WalletTransaction::find($validated['transaction_id']);
            $email = auth()->user()->email ?? 'user-'.auth()->id().'@invalid.local';

            $resp = Http::withBasicAuth($this->secretKey, '')
                ->post($this->baseUrl.'v2/invoices', [
                    'external_id' => $externalId,
                    'amount' => (float) $validated['amount'],
                    'currency' => $this->currencyCode,
                    'success_redirect_url' => $validated['redirect_url'],
                    'failure_redirect_url' => $validated['redirect_url'],
                    'payer_email' => $email,
                    'description' => 'Wallet Top-up',
                ]);

            $body = $resp->json();

            if (! $resp->successful() || empty($body['invoice_url'])) {
                Log::error('Xendit Wallet Recharge invoice creation failed API Response: '.json_encode($body));
                throw new Exception($body['message'] ?? 'Unable to create payment invoice');
            }

            if ($txn) {
                $txn->transaction_reference = $body['id'];
                $txn->save();
            }

            Log::info("Xendit Wallet Recharge invoice created: {$body['id']} for Wallet Transaction ID: {$validated['transaction_id']}");

            return [
                'success' => true,
                'message' => 'Xendit wallet recharge initialized successfully',
                'data' => [
                    'id' => $body['id'],
                    'transaction_id' => $body['id'],
                    'payment_id' => $body['id'],
                    'link' => $body['invoice_url'],
                    'invoice_url' => $body['invoice_url'],
                ],
            ];
        } catch (Exception $e) {
            Log::error('Xendit wallet recharge order creation failed: '.$e->getMessage());

            return [
                'success' => false,
                'message' => 'Unable to create wallet recharge order: '.$e->getMessage(),
                'data' => ['error' => $e->getMessage()],
            ];
        }
    }

    /**
     * Render the payment page for a subscription transaction via Xendit
     */
    public function showSubscriptionPaymentPage(string $transaction): Factory|View
    {
        $txn = SubscriptionTransaction::with('plan')
            ->where('uuid', $transaction)
            ->firstOrFail();

        if ((string) $txn->payment_gateway !== (string) PaymentTypeEnum::XENDIT()) {
            abort(404);
        }

        if ((string) $txn->status !== 'pending') {
            $plan = $txn->plan;
            $planName = optional($plan)->name ?? 'Subscription Plan';

            return view('payments.payment-status', [
                'transaction' => $txn,
                'completedStatus' => SubscriptionTransactionStatusEnum::COMPLETED(),
                'failedStatus' => SubscriptionTransactionStatusEnum::FAILED(),
                'paymentContext' => [
                    'title' => __('labels.subscription_payment_summary'),
                    'lineItems' => [
                        ['label' => __('labels.plan'), 'value' => $planName, 'bold' => true],
                        ['label' => __('labels.duration'), 'value' => ($plan->duration_type ?? null) === 'unlimited' ? __('labels.unlimited') : (($plan->duration_days ?? 0).' '.__('labels.days'))],
                    ],
                    'backUrl' => route('seller.subscription-plans.current'),
                    'backLabel' => __('labels.back_to_subscription'),
                ],
            ]);
        }

        $invoiceId = $txn->transaction_id;
        $invoiceUrl = '';

        if (empty($invoiceId)) {
            $init = $this->createSubscriptionInitialize([
                'amount' => (float) $txn->amount,
                'subscription_transaction_id' => $txn->id,
                'seller_subscription_id' => $txn->seller_subscription_id,
                'seller_id' => $txn->seller_id,
                'user_id' => auth()->id(),
            ]);

            if (! ($init['success'] ?? false)) {
                abort(400, $init['message'] ?? 'Unable to initialize Xendit payment');
            }

            $invoiceId = $init['data']['id'] ?? null;
            $invoiceUrl = $init['data']['link'] ?? '';

            if ($invoiceId) {
                $txn->transaction_id = $invoiceId;
                $txn->save();
            }
        } else {
            // Retrieve invoice details to get invoice URL securely
            $verification = $this->verifyPayment($invoiceId);
            $invoiceUrl = $verification['data']['invoice_url'] ?? '';
        }

        $plan = $txn->plan;
        $planName = optional($plan)->name ?? 'Subscription Plan';

        return view('payments.payment', [
            'gateway' => 'xendit',
            'authorizationUrl' => $invoiceUrl,
            'amount' => (float) $txn->amount,
            'currency' => $this->currencyCode,
            'transactionId' => $txn->id,
            'transactionUuid' => $txn->uuid,
            'sellerId' => $txn->seller_id,
            'subscriptionId' => $txn->seller_subscription_id,
            'plan' => $plan,
            'planName' => $planName,
            'payButtonLabel' => __('labels.pay_with_xendit'),
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

    /**
     * Render the payment page for a wallet recharge transaction via Xendit
     */
    public function showWalletPaymentPage(int $transaction): Factory|View
    {
        $txn = WalletTransaction::where('id', $transaction)->firstOrFail();

        if ($txn->payment_method !== PaymentTypeEnum::XENDIT()) {
            abort(404);
        }

        if ($txn->status !== WalletTransactionStatusEnum::PENDING()) {
            return view('payments.payment-status', [
                'transaction' => $txn,
                'completedStatus' => WalletTransactionStatusEnum::COMPLETED(),
                'failedStatus' => WalletTransactionStatusEnum::FAILED(),
                'paymentContext' => [
                    'title' => __('labels.wallet_topup_payment_summary'),
                    'lineItems' => [
                        ['label' => __('labels.description'), 'value' => $txn->description ?? __('labels.wallet_topup'), 'bold' => true],
                    ],
                    'backUrl' => route('seller.ads.wallet.index'),
                    'backLabel' => __('labels.back_to_ad_wallet'),
                ],
            ]);
        }

        $invoiceId = $txn->transaction_reference;
        $invoiceUrl = '';

        if (empty($invoiceId)) {
            $init = $this->createWalletRechargePayment([
                'amount' => (float) $txn->amount,
                'transaction_id' => (string) $txn->id,
                'redirect_url' => route('wallet-payment.status', ['transaction' => $txn->id]),
            ]);

            if (! ($init['success'] ?? false)) {
                abort(400, $init['message'] ?? 'Unable to initialize Xendit payment');
            }

            $invoiceId = $init['data']['id'] ?? null;
            $invoiceUrl = $init['data']['link'] ?? '';

            if ($invoiceId) {
                $txn->transaction_reference = $invoiceId;
                $txn->save();
            }
        } else {
            // Retrieve invoice details to get invoice URL securely
            $verification = $this->verifyPayment($invoiceId);
            $invoiceUrl = $verification['data']['invoice_url'] ?? '';
        }

        return view('payments.payment', [
            'gateway' => 'xendit',
            'authorizationUrl' => $invoiceUrl,
            'amount' => (float) $txn->amount,
            'currency' => $this->currencyCode,
            'transactionId' => $txn->id,
            'transactionUuid' => $txn->id,
            'statusRoute' => route('wallet-payment.status', ['transaction' => $txn->id]),
            'payButtonLabel' => __('labels.pay_with_xendit'),
            'paymentContext' => [
                'title' => __('labels.wallet_topup_payment_summary'),
                'lineItems' => [
                    ['label' => __('labels.description'), 'value' => $txn->description ?? __('labels.wallet_topup'), 'bold' => true],
                ],
            ],
        ]);
    }

    /**
     * Handle Xendit Webhook
     */
    public function handleWebhook(Request $request): JsonResponse
    {
        $callbackToken = $request->header('x-callback-token');
        if (empty($callbackToken) || $callbackToken !== $this->webhookToken) {
            Log::error('Xendit Webhook: Invalid callback token.');

            return response()->json(['error' => 'Unauthorized'], 401);
        }

        $payload = $request->all();
        $status = $payload['status'] ?? '';
        $externalId = $payload['external_id'] ?? '';
        $invoiceId = $payload['id'] ?? '';

        Log::info("Xendit Webhook received: Invoice: {$invoiceId}, Status: {$status}, ExternalID: {$externalId}");

        DB::beginTransaction();
        try {
            // Retrieve invoice from Xendit's API for secure validation (MANDATORY VERIFICATION)
            $verification = $this->verifyPayment($invoiceId);
            if (! $verification['success']) {
                Log::error("Xendit Webhook: Invoice verification failed for Invoice ID: {$invoiceId}");

                return response()->json(['error' => 'Verification failed'], 400);
            }

            $invoice = $verification['data'];
            $vStatus = $invoice['status'] ?? '';
            $vExternalId = $invoice['external_id'] ?? '';
            $vAmount = (float) ($invoice['amount'] ?? 0);
            $vCurrency = $invoice['currency'] ?? '';

            // Find transaction record in our database
            $transaction = $this->findTransaction($vExternalId, $invoiceId);
            if (! $transaction) {
                Log::error("Xendit Webhook: No transaction found in our system for External ID: {$vExternalId}, Invoice ID: {$invoiceId}");

                return response()->json(['error' => 'Transaction not found'], 404);
            }

            // Verify External ID match (Idempotency / Reference check)
            $storedExternalId = (isset($transaction->payment_details) && is_array($transaction->payment_details))
                ? ($transaction->payment_details['xendit_external_id'] ?? null)
                : null;
            if ($storedExternalId && $vExternalId !== $storedExternalId) {
                Log::error("Xendit Webhook: External ID mismatch. Expected: {$storedExternalId}, Got: {$vExternalId}");

                return response()->json(['error' => 'External ID mismatch'], 400);
            }

            // Verify Amount
            $expectedAmount = (float) $transaction->amount;
            if (abs($expectedAmount - $vAmount) > 0.01) {
                Log::error("Xendit Webhook: Amount mismatch. Expected: {$expectedAmount}, Got: {$vAmount}");

                return response()->json(['error' => 'Amount mismatch'], 400);
            }

            // Verify Currency
            if (strtoupper($vCurrency) !== strtoupper($this->currencyCode)) {
                Log::error("Xendit Webhook: Currency mismatch. Expected: {$this->currencyCode}, Got: {$vCurrency}");

                return response()->json(['error' => 'Currency mismatch'], 400);
            }

            // Determine transaction type based on External ID prefix
            $paymentType = 'order_payment';
            if (Str::startsWith(strtoupper($vExternalId), 'SUBSCRIPTION-') || Str::startsWith($vExternalId, 'sub_')) {
                $paymentType = 'subscription_payment';
            } elseif (Str::startsWith(strtoupper($vExternalId), 'WALLET-') || Str::startsWith($vExternalId, 'wallet_')) {
                $paymentType = 'wallet_recharge';
            } elseif (Str::startsWith(strtoupper($vExternalId), 'TIP-') || Str::startsWith($vExternalId, 'tip_')) {
                // Set paymentType to post_order_tip for rider tip transactions via Xendit
                $paymentType = 'post_order_tip';
            }

            // Check if status is PAID or SETTLED (success states)
            if ($vStatus === 'PAID' || $vStatus === 'SETTLED') {
                $this->handlePaymentSucceeded($paymentType, $invoice, $transaction);
            } elseif ($vStatus === 'EXPIRED' || $vStatus === 'FAILED') {
                $this->handlePaymentFailed($paymentType, $transaction, $vStatus);
            } else {
                Log::info("Xendit Webhook: Ignored invoice state: {$vStatus} for Invoice ID: {$invoiceId}");
            }

            DB::commit();

            return response()->json(['status' => 'success'], 200);
        } catch (Exception $e) {
            DB::rollBack();
            Log::error('Xendit Webhook processing error: '.$e->getMessage());

            return response()->json(['error' => 'Internal server error'], 500);
        }
    }

    /**
     * Handle payment success fulfillment
     */
    private function handlePaymentSucceeded(string $paymentType, array $invoice, $transaction): void
    {
        $invoiceId = $invoice['id'];

        if ($paymentType === 'wallet_recharge' && $transaction instanceof WalletTransaction) {
            // DUPLICATE PROTECTION: return immediately if already completed
            if ($transaction->status === WalletTransactionStatusEnum::COMPLETED()) {
                Log::info("Xendit Webhook: Wallet recharge transaction #{$transaction->id} already completed.");

                return;
            }

            $transaction->update([
                'transaction_reference' => $invoiceId,
                'description' => 'Wallet Recharge Payment Captured via Xendit',
            ]);

            // Let the wallet capture helper credit the balance and mark the transaction completed atomically.
            $result = Wallet::captureRecharge($transaction->id);
            if (! $result['success']) {
                Log::error('Xendit Wallet Recharge Failed: '.$result['message']);

                return;
            }
            Log::info("Xendit Wallet Recharge Completed successfully for Transaction: {$transaction->id}");

            return;
        }

        if ($paymentType === 'subscription_payment' && $transaction instanceof SubscriptionTransaction) {
            // DUPLICATE PROTECTION: return immediately if already completed
            if ($transaction->status === SubscriptionTransactionStatusEnum::COMPLETED()) {
                Log::info("Xendit Webhook: Subscription transaction #{$transaction->id} already completed.");

                return;
            }

            $transaction->update([
                'status' => SubscriptionTransactionStatusEnum::COMPLETED(),
                'transaction_id' => $invoiceId,
            ]);

            $subscription = SellerSubscription::find($transaction->seller_subscription_id);
            if ($subscription) {
                $subscription->status = SellerSubscriptionStatusEnum::ACTIVE();
                $subscription->price_paid = $transaction->amount;
                $subscription->save();
            }
            Log::info("Xendit Subscription payment succeeded for Transaction: {$transaction->id}");

            return;
        }

        // Handle post-order rider tip payment completion via Xendit webhook
        if (($paymentType === 'post_order_tip' || (isset($transaction->transaction_id) && Str::startsWith((string) $transaction->transaction_id, 'TIP-'))) && $transaction instanceof OrderPaymentTransaction) {
            if ($transaction->payment_status === PaymentStatusEnum::COMPLETED()) {
                Log::info("Xendit Webhook: Rider tip transaction #{$transaction->id} already completed.");

                return;
            }

            $details = $transaction->payment_details ?? [];
            $details['xendit_payment_id'] = $invoice['payment_id'] ?? $invoiceId;
            $transaction->update([
                'payment_status' => PaymentStatusEnum::COMPLETED(),
                'payment_details' => $details,
            ]);

            $order = Order::find($transaction->order_id);
            if ($order) {
                app(OrderService::class)->applyCompletedTipToOrder(
                    $order,
                    (float) ($invoice['amount'] ?? $transaction->amount),
                    (string) ($transaction->transaction_id ?? $invoiceId)
                );
                Log::info("Post-order tip applied via Xendit webhook for Order #{$order->id}");
            }

            return;
        }

        if ($paymentType === 'order_payment' && $transaction instanceof OrderPaymentTransaction) {
            // DUPLICATE PROTECTION: return immediately if already completed
            if ($transaction->payment_status === PaymentStatusEnum::COMPLETED() && $transaction->order_id !== null) {
                Log::info("Xendit Webhook: Order payment transaction #{$transaction->id} already completed.");

                return;
            }

            $order = Order::find($transaction->order_id);
            if ($order && (string) $order->payment_status === (string) PaymentStatusEnum::COMPLETED()) {
                Log::info("Xendit Webhook: Order #{$order->id} payment already captured.");

                return;
            }

            if ($transaction->order_id === null) {
                $order = app(OrderService::class)->createConfirmedHostedGatewayOrder($transaction);
                if (! $order) {
                    Log::error("Xendit Webhook: Unable to create order after payment succeeded for transaction #{$transaction->id}");

                    return;
                }
                $transaction->refresh();
            }

            // Save details
            $details = $transaction->payment_details ?? [];
            $details['xendit_payment_id'] = $invoice['payment_id'] ?? $invoiceId;
            $transaction->update([
                'payment_status' => PaymentStatusEnum::COMPLETED(),
                'payment_details' => $details,
            ]);

            if ($transaction->order_id !== null) {
                Order::capturePayment($transaction->order_id);
                OrderItem::capturePayment($transaction->order_id);
                app(OrderService::class)->clearCartAfterCapturedOrder($transaction->order_id);
                Log::info("Xendit Webhook: Order Updated After Payment Succeeded: {$transaction->order_id}");
            }
        }
    }

    /**
     * Handle payment failure/expiration
     */
    private function handlePaymentFailed(string $paymentType, $transaction, string $status): void
    {
        if ($paymentType === 'wallet_recharge') {
            $transaction->update([
                'status' => WalletTransactionStatusEnum::FAILED(),
                'description' => "Wallet Recharge Failed via Xendit ({$status})",
            ]);
            Log::info("Xendit Wallet Recharge failed for Transaction: {$transaction->id}");
        } elseif ($paymentType === 'subscription_payment' && $transaction instanceof SubscriptionTransaction) {
            $transaction->update([
                'status' => SubscriptionTransactionStatusEnum::FAILED(),
            ]);
            $subscription = SellerSubscription::find($transaction->seller_subscription_id);
            if ($subscription) {
                $subscription->status = SellerSubscriptionStatusEnum::CANCELLED();
                $subscription->save();
            }
            Log::info("Xendit Subscription payment failed for Transaction: {$transaction->id}");
        } else {
            $transaction->update([
                'payment_status' => PaymentStatusEnum::FAILED(),
                'message' => "Payment Failed ({$status})",
            ]);
            if ($transaction->order_id !== null) {
                Order::paymentFailed($transaction->order_id);
                OrderItem::paymentFailed($transaction->order_id);
            }
            Log::warning("Xendit Order payment failed for Transaction: {$transaction->id}");
        }
    }

    /**
     * verifyPayment API call & On-Demand Order Capture
     */
    public function verifyPayment(string $invoiceId): array
    {
        try {
            $resp = Http::withBasicAuth($this->secretKey, '')
                ->get($this->baseUrl."v2/invoices/{$invoiceId}");

            if (! $resp->successful()) {
                throw new Exception('Xendit API returned error: '.$resp->body());
            }

            $body = $resp->json();
            $status = strtoupper($body['status'] ?? '');
            $vExternalId = $body['external_id'] ?? '';

            // Find matching transaction in database
            $transaction = $this->findTransaction($vExternalId, $invoiceId);

            // Determine transaction type
            $paymentType = 'order_payment';
            if (Str::startsWith(strtoupper($vExternalId), 'SUBSCRIPTION-') || Str::startsWith($vExternalId, 'sub_')) {
                $paymentType = 'subscription_payment';
            } elseif (Str::startsWith(strtoupper($vExternalId), 'WALLET-') || Str::startsWith($vExternalId, 'wallet_')) {
                $paymentType = 'wallet_recharge';
            }

            if ($status === 'PAID' || $status === 'SETTLED') {
                if ($transaction) {
                    $this->handlePaymentSucceeded($paymentType, $body, $transaction);
                }

                return [
                    'success' => true,
                    'message' => 'Payment verified successfully',
                    'data' => $body,
                ];
            }

            if ($status === 'EXPIRED' || $status === 'FAILED') {
                if ($transaction) {
                    $this->handlePaymentFailed($paymentType, $transaction, $status);
                }

                return [
                    'success' => false,
                    'message' => "Payment failed with status: {$status}",
                    'data' => $body,
                ];
            }

            return [
                'success' => false,
                'message' => "Payment not completed yet, status: {$status}",
                'data' => $body,
            ];
        } catch (Exception $e) {
            Log::error('Xendit verification failed: '.$e->getMessage());

            return [
                'success' => false,
                'message' => $e->getMessage(),
                'data' => null,
            ];
        }
    }

    /**
     * Refund a paid transaction via Xendit Refunds API
     */
    public function refundPayment(string $transactionId, $amount = null): array
    {
        try {
            if (empty($this->secretKey)) {
                return ['success' => false, 'message' => 'Xendit secret key not configured'];
            }

            // Find payment transaction
            $transaction = OrderPaymentTransaction::where('transaction_id', $transactionId)->first()
                        ?? WalletTransaction::where('transaction_reference', $transactionId)->first();

            if (! $transaction) {
                return ['success' => false, 'message' => 'Transaction not found'];
            }

            // DUPLICATE PROTECTION: return success if already refunded
            if (isset($transaction->payment_status) && $transaction->payment_status === PaymentStatusEnum::REFUNDED()) {
                return ['success' => true, 'message' => 'Already refunded', 'data' => []];
            }

            $refundAmount = $amount ?? $transaction->amount;
            $xenditPaymentId = (isset($transaction->payment_details) && is_array($transaction->payment_details))
                ? ($transaction->payment_details['xendit_payment_id'] ?? $transactionId)
                : $transactionId;

            Log::info("Xendit Refund Request: Refund payment: {$xenditPaymentId}, Amount: {$refundAmount}");

            $resp = Http::withBasicAuth($this->secretKey, '')
                ->withHeaders([
                    'idempotency-key' => 'ref_'.$transactionId.'_'.time(),
                ])
                ->post($this->baseUrl.'refunds', [
                    'payment_id' => $xenditPaymentId,
                    'amount' => (float) $refundAmount,
                    'reason' => 'REQUESTED_BY_CUSTOMER',
                ]);

            $body = $resp->json();
            Log::info('Xendit Refund Response: '.json_encode($body));

            if (! $resp->successful()) {
                throw new Exception($body['message'] ?? 'Xendit refund request rejected');
            }

            if ($transaction instanceof OrderPaymentTransaction) {
                $details = $transaction->payment_details ?? [];
                $details['xendit_refund_details'] = $body;
                $transaction->payment_details = $details;
                $transaction->payment_status = PaymentStatusEnum::REFUNDED();
                $transaction->save();
            }

            return [
                'success' => true,
                'message' => 'Refund processed successfully',
                'data' => $body,
            ];
        } catch (Exception $e) {
            Log::error('Xendit Refund failed: '.$e->getMessage());

            return [
                'success' => false,
                'message' => $e->getMessage(),
            ];
        }
    }

    /**
     * API request to create order payment invoice
     */
    public function createOrderPaymentIntent(Request $request): JsonResponse
    {
        try {
            $validated = $request->validate([
                'amount' => 'nullable|numeric',
                'order_id' => 'required',
                'redirect_url' => 'required|string|url',
            ]);

            $orderIdentifier = (string) $validated['order_id'];
            $order = Order::where(function ($query) use ($orderIdentifier): void {
                $query->where('id', $orderIdentifier)
                    ->orWhere('slug', $orderIdentifier)
                    ->orWhere('uuid', $orderIdentifier);
            })
                ->when(auth()->id(), fn ($query) => $query->where('user_id', auth()->id()))
                ->first();

            $amount = (float) ($validated['amount'] ?? 0);
            if ($amount < 1) {
                $amount = (float) ($order?->total_payable ?? 0);
            }

            if ($amount < 1 && auth()->user()) {
                $cart = CartService::getUserCart(auth()->user());
                if ($cart) {
                    $summary = app(CartService::class)->getPaymentSummary(
                        cart: $cart,
                        latitude: $request->input('latitude'),
                        longitude: $request->input('longitude'),
                        isRushDelivery: filter_var($request->input('rush_delivery', false), FILTER_VALIDATE_BOOLEAN),
                        useWallet: filter_var($request->input('use_wallet', false), FILTER_VALIDATE_BOOLEAN),
                        promoCode: $request->input('promo_code'),
                        useLoyaltyPoints: $request->has('use_loyalty_points') ? filter_var($request->input('use_loyalty_points'), FILTER_VALIDATE_BOOLEAN) : null
                    );
                    $amount = (float) ($summary['payable_amount'] ?? 0);
                }
            }

            if ($amount < 1) {
                return ApiResponseType::sendJsonResponse(
                    success: false,
                    message: 'Online payment amount must be at least 1.',
                    data: ['amount' => $amount],
                    status: 422
                );
            }

            if (! $order) {
                return ApiResponseType::sendJsonResponse(
                    success: false,
                    message: 'Order not found',
                    data: [],
                    status: 404
                );
            }

            $result = $this->createOrder(array_merge($request->all(), [
                'amount' => $amount,
                'order_id' => $order->id,
            ]));

            if (! $result['success']) {
                return ApiResponseType::sendJsonResponse(false, $result['message'], $result['data'] ?? [], 400);
            }

            return ApiResponseType::sendJsonResponse(true, 'labels.payment_intent_created_successfully', $result['data']);
        } catch (ValidationException $e) {
            return ApiResponseType::sendJsonResponse(
                success: false,
                message: $e->validator->errors()->first(),
                data: ['errors' => $e->errors()],
                status: 422
            );
        } catch (Exception $e) {
            return ApiResponseType::sendJsonResponse(false, 'messages.something_went_wrong', ['error' => $e->getMessage()], 500);
        }
    }

    /**
     * API request to create Xendit post-order rider tip payment intent / invoice for WebViews
     */
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

            $orderSlug = (string) $validated['order_id'];
            $order = Order::where('user_id', $user->id)
                ->where(function ($q) use ($orderSlug) {
                    $q->where('slug', $orderSlug)
                        ->orWhere('uuid', $orderSlug)
                        ->orWhere('id', $orderSlug);
                })->first();

            if (! $order) {
                return ApiResponseType::sendJsonResponse(false, __('labels.order_not_found') ?? 'Order not found', [], 404);
            }

            $tipAmount = (float) $validated['rider_tip_amount'];
            $txId = 'TIP-'.Str::upper(Str::random(12));
            $orderCurrency = app(CurrencyWriteGuard::class)->orderCurrency($order);

            // Create pending transaction for Xendit tip payment
            $pendingTx = OrderPaymentTransaction::create([
                'uuid' => (string) Str::uuid(),
                'user_id' => $order->user_id,
                'order_id' => $order->id,
                'transaction_id' => $txId,
                'amount' => $tipAmount,
                'currency' => $orderCurrency,
                'order_currency_code' => $orderCurrency,
                'gateway_currency_code' => $orderCurrency,
                'gateway_amount' => $tipAmount,
                'payment_method' => PaymentTypeEnum::XENDIT(),
                'payment_status' => PaymentStatusEnum::PENDING(),
            ]);

            $result = $this->createTipInvoice($order, $tipAmount, $txId);

            if (! ($result['success'] ?? false)) {
                return ApiResponseType::sendJsonResponse(false, $result['message'] ?? 'Unable to create Xendit tip invoice', [], 400);
            }

            $invoiceUrl = $result['data']['invoice_url'] ?? '';
            $details = [
                'xendit_external_id' => $txId,
                'xendit_invoice_url' => $invoiceUrl,
                'xendit_invoice_id' => $result['data']['invoice_id'] ?? null,
            ];
            $pendingTx->update(['payment_details' => $details]);

            return ApiResponseType::sendJsonResponse(
                success: true,
                message: __('labels.payment_intent_created_successfully') ?? 'Xendit tip payment intent created successfully',
                data: [
                    'order_id' => $order->id,
                    'transaction_id' => $txId,
                    'rider_tip_amount' => $tipAmount,
                    'link' => $invoiceUrl,
                    'invoice_url' => $invoiceUrl,
                    'payment_url' => $invoiceUrl,
                ]
            );
        } catch (Exception $e) {
            Log::error('Xendit createTipPaymentIntent failed: '.$e->getMessage());

            return ApiResponseType::sendJsonResponse(false, 'messages.something_went_wrong', ['error' => $e->getMessage()], 500);
        }
    }

    /**
     * Resolve transaction record helper
     */
    private function findTransaction(string $externalId, string $invoiceId)
    {
        // Support rider tip transactions with TIP- prefix
        if (Str::startsWith(strtoupper($externalId), 'TIP-') || Str::startsWith($externalId, 'tip_')) {
            $txn = OrderPaymentTransaction::where('transaction_id', $invoiceId)
                ->orWhere('transaction_id', $externalId)
                ->first();
            if (! $txn) {
                $txn = OrderPaymentTransaction::where('payment_details->xendit_external_id', $externalId)->first();
            }
            if (! $txn) {
                $identifier = $this->parseIdentifier($externalId);
                if ($identifier) {
                    $txn = OrderPaymentTransaction::where('order_id', $identifier)
                        ->orWhereHas('order', function ($q) use ($identifier) {
                            $q->where('uuid', $identifier)->orWhere('slug', $identifier);
                        })->first();
                }
            }

            return $txn;
        }

        if (Str::startsWith(strtoupper($externalId), 'ORDER-') || Str::startsWith($externalId, 'order_')) {
            $txn = OrderPaymentTransaction::where('transaction_id', $invoiceId)->first();
            if (! $txn) {
                $identifier = $this->parseIdentifier($externalId);
                if ($identifier) {
                    $txn = OrderPaymentTransaction::where('order_id', $identifier)
                        ->orWhereHas('order', function ($q) use ($identifier) {
                            $q->where('uuid', $identifier)->orWhere('slug', $identifier);
                        })->first();
                }
            }

            return $txn;
        }

        if (Str::startsWith(strtoupper($externalId), 'SUBSCRIPTION-') || Str::startsWith($externalId, 'sub_')) {
            $txn = SubscriptionTransaction::where('transaction_id', $invoiceId)->first();
            if (! $txn) {
                $identifier = $this->parseIdentifier($externalId);
                if ($identifier) {
                    $txn = SubscriptionTransaction::where('id', $identifier)
                        ->orWhere('uuid', $identifier)->first();
                }
            }

            return $txn;
        }

        if (Str::startsWith(strtoupper($externalId), 'WALLET-') || Str::startsWith($externalId, 'wallet_')) {
            $txn = WalletTransaction::where('transaction_reference', $invoiceId)->first();
            if (! $txn) {
                $identifier = $this->parseIdentifier($externalId);
                if ($identifier) {
                    $txn = WalletTransaction::find($identifier);
                }
            }

            return $txn;
        }

        return null;
    }

    /**
     * Create Xendit invoice for post-order rider tip payment
     */
    public function createTipInvoice(Order $order, float $amount, string $txId): array
    {
        try {
            if (empty($this->secretKey)) {
                return ApiResponseType::toArray(success: false, message: 'Xendit secret key not configured');
            }

            $externalId = $txId;
            $email = $order->email ?: ('order+'.substr($order->uuid, 0, 8).'@invalid.local');

            $redirectUrl = route('tip-payment.status', ['transaction' => $txId]);

            $resp = Http::withBasicAuth($this->secretKey, '')
                ->post($this->baseUrl.'v2/invoices', [
                    'external_id' => $externalId,
                    'amount' => (float) $amount,
                    'currency' => $this->currencyCode,
                    'success_redirect_url' => $redirectUrl,
                    'failure_redirect_url' => $redirectUrl,
                    'payer_email' => $email,
                    'description' => 'Rider Tip for Order #'.($order->slug ?? $order->id),
                ]);

            $body = $resp->json();

            if (! $resp->successful() || empty($body['invoice_url'])) {
                Log::error('Xendit Tip invoice creation failed API Response: '.json_encode($body));

                return ApiResponseType::toArray(success: false, message: $body['message'] ?? 'Unable to create Xendit tip invoice');
            }

            return ApiResponseType::toArray(
                success: true,
                message: 'Xendit tip invoice created successfully',
                data: [
                    'invoice_url' => $body['invoice_url'],
                    'link' => $body['invoice_url'],
                    'invoice_id' => $body['id'],
                    'external_id' => $externalId,
                ]
            );
        } catch (Exception $e) {
            Log::error('Xendit tip invoice creation exception: '.$e->getMessage());

            return ApiResponseType::toArray(
                success: false,
                message: 'Unable to create Xendit tip invoice: '.$e->getMessage(),
                data: ['error' => $e->getMessage()]
            );
        }
    }

    private function parseIdentifier(string $externalId): ?string
    {
        $delimiters = ['-', '_'];
        foreach ($delimiters as $delim) {
            if (strpos($externalId, $delim) !== false) {
                $parts = explode($delim, $externalId);

                return $parts[1] ?? null;
            }
        }

        return null;
    }

    /**
     * Show order payment status return page
     */
    public function orderPaymentStatus(string $order): Factory|View|RedirectResponse
    {
        $orderModel = is_numeric($order) ? Order::find((int) $order) : null;
        $transaction = $orderModel
            ? OrderPaymentTransaction::where('order_id', $orderModel->id)
                ->where('payment_method', $orderModel->payment_method)
                ->latest()
                ->first()
            : OrderPaymentTransaction::where(function ($query) use ($order): void {
                $query->where('uuid', $order)
                    ->orWhere('transaction_id', $order);
            })
                ->whereIn('payment_method', [
                    PaymentTypeEnum::FLUTTERWAVE(),
                    PaymentTypeEnum::XENDIT(),
                    PaymentTypeEnum::MERCADO_PAGO(),
                ])
                ->latest()
                ->firstOrFail();

        // A hosted gateway redirect is not payment proof; verify only with the matching gateway API.
        if ($transaction && $transaction->payment_method === PaymentTypeEnum::XENDIT() && ($transaction->payment_id || $transaction->transaction_id)) {
            $invoiceId = $transaction->payment_id ?: $transaction->transaction_id;
            $this->verifyPayment($invoiceId);
            $transaction->refresh();
            $orderModel = $transaction->order_id ? Order::find($transaction->order_id) : $orderModel;
            $orderModel?->refresh();
        } elseif ($transaction && $transaction->payment_method === PaymentTypeEnum::MERCADO_PAGO()) {
            $paymentId = request('payment_id') ?? request('collection_id');
            if (! empty($paymentId)) {
                app(MercadoPagoController::class)->verifyPayment((string) $paymentId);
            }
            $transaction->refresh();
            $orderModel = $transaction->order_id ? Order::find($transaction->order_id) : $orderModel;
            $orderModel?->refresh();
        } elseif ($transaction && $transaction->payment_method === PaymentTypeEnum::FLUTTERWAVE()) {
            $flutterwaveTransactionId = request('transaction_id');
            if (! empty($flutterwaveTransactionId)) {
                $verification = app(FlutterwaveController::class)->verifyPayment((string) $flutterwaveTransactionId);
                if (($verification['success'] ?? false) && $transaction->order_id === null) {
                    app(OrderService::class)->createConfirmedHostedGatewayOrder($transaction);
                }
                if ($verification['success'] ?? false) {
                    $details = $transaction->payment_details ?? [];
                    $details['flutterwave_transaction_id'] = $verification['data']['id'] ?? $flutterwaveTransactionId;
                    $details['flutterwave_tx_ref'] = $verification['data']['tx_ref'] ?? $transaction->transaction_id;
                    $transaction->update([
                        'transaction_id' => $verification['data']['tx_ref'] ?? $transaction->transaction_id,
                        'payment_status' => PaymentStatusEnum::COMPLETED(),
                        'message' => 'Payment captured successfully',
                        'payment_details' => $details,
                    ]);
                }
            }
            $transaction->refresh();
            $orderModel = $transaction->order_id ? Order::find($transaction->order_id) : $orderModel;
            $orderModel?->refresh();
        }

        if ($transaction && $orderModel && (string) $transaction->payment_status === PaymentStatusEnum::COMPLETED()) {
            return redirect()->away($this->hostedOrderSuccessRedirectUrl($transaction, $orderModel));
        }

        return view('payments.payment-status', [
            'transaction' => $transaction ?? $orderModel,
            'completedStatus' => PaymentStatusEnum::COMPLETED(),
            'failedStatus' => PaymentStatusEnum::FAILED(),
            'paymentContext' => [
                'title' => __('labels.order_payment_summary'),
                'lineItems' => [
                    ['label' => __('labels.order_id'), 'value' => $orderModel ? '#'.($orderModel->slug ?? $orderModel->id) : '#'.$transaction->uuid, 'bold' => true],
                    ['label' => __('labels.amount'), 'value' => $orderModel?->total_payable ?? $transaction->amount],
                ],
                'backUrl' => url('/'),
                'backLabel' => __('labels.back_to_home'),
            ],
        ]);
    }

    private function hostedOrderSuccessRedirectUrl(OrderPaymentTransaction $transaction, Order $order): string
    {
        $snapshotRedirectUrl = $transaction->payment_details['checkout_snapshot']['redirect_url'] ?? null;
        $redirectUrl = $this->isTrustedHostedRedirectUrl($snapshotRedirectUrl)
            ? (string) $snapshotRedirectUrl
            : url('/');
        if (str_starts_with($redirectUrl, '/')) {
            $redirectUrl = url($redirectUrl);
        }

        $query = [
            'transaction_uuid' => $transaction->uuid,
            'payment_status' => PaymentStatusEnum::COMPLETED(),
            'order_slug' => $order->slug ?? $order->id,
        ];

        $separator = str_contains($redirectUrl, '?') ? '&' : '?';

        return $redirectUrl.$separator.http_build_query($query);
    }

    private function isTrustedHostedRedirectUrl(?string $redirectUrl): bool
    {
        if (empty($redirectUrl)) {
            return false;
        }

        if (str_starts_with($redirectUrl, '/') && ! str_starts_with($redirectUrl, '//')) {
            return true;
        }

        $host = parse_url($redirectUrl, PHP_URL_HOST);
        if (empty($host)) {
            return false;
        }

        $allowedHosts = collect([
            parse_url((string) config('app.url'), PHP_URL_HOST),
            parse_url((string) config('app.frontendUrl'), PHP_URL_HOST),
            request()->getHost(),
        ])
            ->filter()
            ->map(fn (string $allowedHost): string => strtolower($allowedHost))
            ->unique()
            ->all();

        return in_array(strtolower($host), $allowedHosts, true);
    }

    /**
     * Show rider tip payment status return page for WebViews (Supports Xendit & Flutterwave)
     */
    public function tipPaymentStatus(string $transaction): Factory|View
    {
        $txn = OrderPaymentTransaction::where('transaction_id', $transaction)
            ->orWhere('uuid', $transaction)
            ->orWhere('id', is_numeric($transaction) ? (int) $transaction : 0)
            ->firstOrFail();

        // Perform verification or status application if status is pending
        $txnStatusStr = is_callable($txn->payment_status) ? $txn->payment_status() : ($txn->payment_status->value ?? (string) $txn->payment_status);
        if (strtolower($txnStatusStr) === 'pending') {
            $reqStatus = strtolower((string) request('status', ''));
            if (in_array($reqStatus, ['completed', 'successful', 'paid'], true)) {
                $txn->update(['payment_status' => PaymentStatusEnum::COMPLETED()]);
                if ($txn->order) {
                    app(OrderService::class)->applyCompletedTipToOrder(
                        $txn->order,
                        (float) $txn->amount,
                        (string) ($txn->transaction_id ?? $transaction)
                    );
                }
                $txn->refresh();
            } else {
                $xenditInvoiceId = request('invoice_id') ?? ($txn->payment_details['xendit_invoice_id'] ?? null);
                if ($xenditInvoiceId) {
                    $this->verifyPayment($xenditInvoiceId);
                    $txn->refresh();
                }

                $txnStatusStr = is_callable($txn->payment_status) ? $txn->payment_status() : ($txn->payment_status->value ?? (string) $txn->payment_status);
                if (strtolower($txnStatusStr) === 'pending') {
                    $flwTxId = request('transaction_id') ?? ($txn->payment_details['flutterwave_tx_ref'] ?? null);
                    if ($flwTxId) {
                        try {
                            app(FlutterwaveController::class)->verifyPayment((string) $flwTxId);
                            $txn->refresh();
                        } catch (\Throwable $e) {
                            Log::error("Flutterwave tip verification failed on return page: {$e->getMessage()}");
                        }
                    }
                }
            }
        }

        $orderModel = $txn->order;

        return view('payments.payment-status', [
            'transaction' => $txn,
            'completedStatus' => PaymentStatusEnum::COMPLETED(),
            'failedStatus' => PaymentStatusEnum::FAILED(),
            'paymentContext' => [
                'title' => 'Rider Tip Payment Summary',
                'lineItems' => [
                    ['label' => 'Order ID', 'value' => '#'.($orderModel->slug ?? $orderModel->id ?? '-'), 'bold' => true],
                    ['label' => 'Tip Amount', 'value' => number_format((float) $txn->amount, 2)],
                ],
                'backUrl' => 'javascript:window.close();',
                'backLabel' => 'Close',
            ],
        ]);
    }
}
