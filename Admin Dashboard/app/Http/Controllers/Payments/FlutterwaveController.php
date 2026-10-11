<?php

namespace App\Http\Controllers\Payments;

use App\Enums\Payment\PaymentTypeEnum;
use App\Enums\PaymentStatusEnum;
use App\Enums\SettingTypeEnum;
use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\OrderPaymentTransaction;
use App\Models\SellerSubscription;
use App\Models\SubscriptionTransaction;
use App\Models\Wallet;
use App\Models\WalletTransaction;
use App\Services\SettingService;
use App\Types\Api\ApiResponseType;
use Exception;
use App\Enums\Subscription\SubscriptionTransactionStatusEnum;
use App\Enums\Subscription\SellerSubscriptionStatusEnum;
use App\Enums\Wallet\WalletTransactionStatusEnum;
use Illuminate\Contracts\View\Factory;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class FlutterwaveController extends Controller
{
    private string $publicKey;
    private string $secretKey;
    private string $encryptionKey;
    private string $webhookSecret;
    private string $baseUrl = 'https://api.flutterwave.com/v3/';
    private string $currencyCode;
    private string $logo;

    public function __construct(SettingService $settingService)
    {
        $setting = $settingService->getSettingByVariable(SettingTypeEnum::PAYMENT());

        $this->publicKey = $setting->value['flutterwavePublicKey'] ?? '';
        $this->secretKey = $setting->value['flutterwaveSecretKey'] ?? '';
        $this->encryptionKey = $setting->value['flutterwaveEncryptionKey'] ?? '';
        $this->webhookSecret = $setting->value['flutterwaveWebhookSecret'] ?? '';
        $this->currencyCode = $setting->value['flutterwaveCurrencyCode'] ?? '';
        $this->logo = $setting->value['favicon'] ?? asset('assets/landing/site-favicon.png');
    }

    /**
     * Handle Flutterwave Webhook
     */
    public function handleWebhook(Request $request): JsonResponse
    {
        $payload = $request->getContent();
        $signature = $request->header('verif-hash');

        Log::info('Flutterwave Webhook Payload: ' . $payload);

        DB::beginTransaction();
        try {
            if (!$this->isValidSignature($signature)) {
                Log::error('Invalid Flutterwave Webhook signature.');
                return response()->json(['error' => 'Invalid signature'], 400);
            }

            $data = json_decode($payload, true);
            $event = $data['event'] ?? null;
            // Flutterwave typically nests meta under data.meta; support both keys for safety
            $meta = $data['data']['meta'] ?? ($data['meta_data'] ?? []);
            $transactionData = is_array($meta) ? $meta : [];
            $transactionData['status'] = $data['data']['status'] ?? '';
            $transactionData['tx_ref'] = $data['data']['tx_ref'] ?? '';
            $transactionData['id'] = $data['data']['id'] ?? null;
            $paymentType = $transactionData['type'] ?? 'order_payment';

            // Check if tx_ref indicates a post-order tip transaction
            if (Str::startsWith(strtoupper($transactionData['tx_ref']), 'TIP-') || Str::startsWith($transactionData['tx_ref'], 'tip_')) {
                $paymentType = 'post_order_tip';
            }

            $transaction = $this->findTransaction($paymentType, $transactionData);
            Log::info($transaction);
            if ($transaction) {
                Log::info("transaction found: " . $transaction);
            }
            $this->processEvent($event, $paymentType, $transactionData, $transaction);

            DB::commit();
            Log::info('Flutterwave Webhook processed successfully');
            return response()->json(['status' => 'success'], 200);
        } catch (Exception $e) {
            DB::rollBack();
            Log::error('Flutterwave Webhook Error: ' . $e->getMessage());
            return response()->json(['error' => 'Server Error'], 500);
        }
    }

    /**
     * Create a new Flutterwave payment order
     */
    public function createOrder(array $data): array
    {
        try {
            $validated = validator($data, [
                'amount' => 'required|numeric|min:1',
                'order_id' => 'required|numeric',
                'redirect_url' => 'required|string|url',
            ])->validate();
            if (empty($this->secretKey)) {
                return ApiResponseType::toArray(
                    success: false,
                    message: 'Flutterwave secret key not configured',
                );
            }
            if (empty($this->currencyCode)) {
                return ApiResponseType::toArray(
                    success: false,
                    message: 'Flutterwave currency code not configured',
                );
            }

            $txRef = 'order_' . auth()->id() . '_' . time();
            $trsData = [
                'order_id' => $validated['order_id'],
                'user_id' => auth()->id(),
                'amount' => $validated['amount'],
                'currency' => $this->currencyCode,
            ];
            $transaction = OrderPaymentTransaction::createTransaction(data: $trsData, paymentMethod: PaymentTypeEnum::FLUTTERWAVE(), paymentStatus: PaymentStatusEnum::PENDING());

            $response = Http::withToken($this->secretKey)
                ->post($this->baseUrl . 'payments', [
                    'tx_ref' => $txRef,
                    'amount' => $validated['amount'],
                    'currency' => $this->currencyCode,
                    'payment_options' => 'card,banktransfer,ussd',
                    'redirect_url' => $validated['redirect_url'],
                    'customer' => [
                        'name' => auth()->user()->name ?? "user" . auth()->id(),
                        'email' => auth()->user()->email ?? "user" . auth()->id() . "@example.com",
                        'phonenumber' => auth()->user()->mobile,
                    ],
                    'customizations' => [
                        'title' => 'Order Payment',
                        'logo' => $this->logo
                    ],
                    'meta' => [
                        'user_id' => auth()->id(),
                        'type' => 'order_payment',
                        'order_id' => $validated['order_id'],
                        'transaction_id' => $transaction->id
                    ]
                ]);

            $data = $response->json();

            if (!$response->successful()) {
                throw new Exception($data['message'] ?? 'Unable to create payment');
            }

            return ApiResponseType::toArray(
                success: true,
                message: 'Flutterwave order created successfully',
                data: $data['data']
            );
        } catch (Exception $e) {
            Log::error('Flutterwave order creation failed: ' . $e->getMessage());
            return ApiResponseType::toArray(
                success: false,
                message: 'Unable to create Flutterwave order',
                data: ['error' => $e->getMessage()]
            );
        }
    }

    /**
     * Create wallet recharge order
     */
    public function createWalletRechargeOrder(array $data): array
    {
        try {
            $data['redirect_url'] = $data['redirect_url'] ?? config('app.url');
            $validated = validator($data, [
                'amount' => 'required|numeric|min:1',
                'transaction_id' => 'required|string',
                'redirect_url' => 'required|string|url',
            ])->validate();

            $response = Http::withToken($this->secretKey)
                ->post($this->baseUrl . 'payments', [
                    'tx_ref' => 'wallet_' . auth()->id() . '_' . time(),
                    'amount' => $validated['amount'],
                    'currency' => $this->currencyCode,
                    'redirect_url' => $validated['redirect_url'],
                    'customer' => [
                        'name' => auth()->user()->name ?? "user" . auth()->id(),
                        'email' => auth()->user()->email ?? "user" . auth()->id() . "@example.com",
                        'phonenumber' => auth()->user()->mobile,
                    ],
                    'customizations' => [
                        'title' => 'Wallet Recharge',
                        'logo' => $this->logo
                    ],
                    'meta' => [
                        'user_id' => auth()->id(),
                        'type' => 'wallet_recharge',
                        'transaction_id' => $validated['transaction_id']
                    ]
                ]);

            $data = $response->json();

            if (!$response->successful()) {
                throw new Exception($data['message'] ?? 'Unable to create wallet recharge order');
            }

            return [
                'success' => true,
                'message' => 'Flutterwave wallet recharge order created successfully',
                'data' => $data['data']
            ];
        } catch (Exception $e) {
            Log::error('Flutterwave wallet recharge order creation failed: ' . $e->getMessage());
            return [
                'success' => false,
                'message' => 'Unable to create wallet recharge order: ' . $e->getMessage(),
                'data' => ['error' => $e->getMessage()]
            ];
        }
    }

    /**
     * Initialize Flutterwave for subscription purchases
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
                return ApiResponseType::toArray(success: false, message: 'Flutterwave not configured');
            }
            if (empty($this->currencyCode)) {
                return ApiResponseType::toArray(success: false, message: 'Flutterwave currency code not configured');
            }

            $txRef = 'sub_' . $validated['seller_id'] . '_' . time();
            // Resolve UUID for redirect URL
            $st = SubscriptionTransaction::find($validated['subscription_transaction_id']);
            $transactionUuid = $st?->uuid ?? (string)$validated['subscription_transaction_id'];

            $response = Http::withToken($this->secretKey)
                ->post($this->baseUrl . 'payments', [
                    'tx_ref' => $txRef,
                    'amount' => (float)$validated['amount'],
                    'currency' => $this->currencyCode,
                    'payment_options' => 'card,banktransfer,ussd',
                    'redirect_url' => route('subscription-payment.status', ['transaction' => $transactionUuid]),
                    'customer' => [
                        'name' => auth()->user()->name ?? ("user" . ($validated['user_id'] ?? '')),
                        'email' => auth()->user()->email ?? ("user" . ($validated['user_id'] ?? '') . "@example.com"),
                        'phonenumber' => auth()->user()->mobile ?? null,
                    ],
                    'customizations' => [
                        'title' => 'Subscription Payment',
                        'logo' => $this->logo
                    ],
                    'meta' => [
                        'type' => 'subscription_payment',
                        'subscription_transaction_id' => (string)$validated['subscription_transaction_id'],
                        'seller_subscription_id' => (string)$validated['seller_subscription_id'],
                        'seller_id' => (string)$validated['seller_id'],
                        'user_id' => (string)($validated['user_id'] ?? ''),
                        'transaction_id' => (string)$validated['subscription_transaction_id'],
                    ],
                ]);

            $json = $response->json();
            if (!$response->successful()) {
                return ApiResponseType::toArray(
                    success: false,
                    message: $json['message'] ?? 'Payment initialization failed',
                    data: $json['data'] ?? null,
                );
            }

            return ApiResponseType::toArray(
                success: true,
                message: 'Payment initialized successfully',
                data: [
                    'link' => $json['data']['link'] ?? null,
                    'tx_ref' => $txRef,
                ],
            );
        } catch (Exception $e) {
            Log::error('Flutterwave subscription init failed: ' . $e->getMessage());
            return ApiResponseType::toArray(success: false, message: 'Flutterwave payment initialization failed: ' . $e->getMessage());
        }
    }

    /**
     * Render a web view for paying a subscription transaction via Flutterwave Hosted Page
     */
    public function showSubscriptionPaymentPage(string $transaction): Factory|View
    {
        $txn = SubscriptionTransaction::with('plan')
            ->where('uuid', $transaction)
            ->firstOrFail();

        if ((string)$txn->payment_gateway !== (string)PaymentTypeEnum::FLUTTERWAVE()) {
            abort(404);
        }

        if ((string)$txn->status !== 'pending') {
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
                        ['label' => __('labels.duration'), 'value' => ($plan->duration_type ?? null) === 'unlimited' ? __('labels.unlimited') : (($plan->duration_days ?? 0) . ' ' . __('labels.days'))],
                    ],
                    'backUrl' => route('seller.subscription-plans.current'),
                    'backLabel' => __('labels.back_to_subscription'),
                ],
            ]);
        }

        $init = $this->createSubscriptionInitialize([
            'amount' => (float)$txn->amount,
            'subscription_transaction_id' => $txn->id,
            'seller_subscription_id' => $txn->seller_subscription_id,
            'seller_id' => $txn->seller_id,
            'user_id' => auth()->id(),
        ]);
        if (!($init['success'] ?? false)) {
            abort(400, $init['message'] ?? 'Unable to initialize Flutterwave payment');
        }
        $authorizationUrl = $init['data']['link'] ?? null;
        $txRef = $init['data']['tx_ref'] ?? null;
        if ($txRef) {
            $txn->transaction_id = $txRef;
            $txn->save();
        }

        $currency = $this->currencyCode;
        $plan = $txn->plan;
        $planName = optional($plan)->name ?? 'Subscription Plan';

        return view('payments.payment', [
            'gateway' => 'flutterwave',
            'authorizationUrl' => $authorizationUrl,
            'amount' => (float)$txn->amount,
            'currency' => $currency,
            'transactionId' => $txn->id,
            'transactionUuid' => $txn->uuid,
            'sellerId' => $txn->seller_id,
            'subscriptionId' => $txn->seller_subscription_id,
            'plan' => $plan,
            'planName' => $planName,
            'payButtonLabel' => __('labels.pay_with_flutterwave'),
            'statusRoute' => route('subscription-payment.status', ['transaction' => $txn->uuid]),
            'paymentContext' => [
                'title' => __('labels.subscription_payment_summary'),
                'lineItems' => [
                    ['label' => __('labels.plan'), 'value' => $planName, 'bold' => true],
                    ['label' => __('labels.duration'), 'value' => ($plan->duration_type ?? null) === 'unlimited' ? __('labels.unlimited') : (($plan->duration_days ?? 0) . ' ' . __('labels.days'))],
                ],
            ],
        ]);
    }

    /**
     * Render the payment page for a wallet recharge transaction via Flutterwave.
     */
    public function showWalletPaymentPage(int $transaction): Factory|View
    {
        $txn = WalletTransaction::where('id', $transaction)->firstOrFail();

        if ($txn->payment_method !== PaymentTypeEnum::FLUTTERWAVE()) {
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

        $initRes = $this->createWalletRechargeOrder([
            'amount' => (float) $txn->amount,
            'transaction_id' => (string) $txn->id,
            'redirect_url' => route('wallet-payment.status', ['transaction' => $txn->id]),
        ]);
        if (!($initRes['success'] ?? false)) {
            abort(400, $initRes['message'] ?? 'Unable to initialize Flutterwave payment');
        }
        $authorizationUrl = $initRes['data']['link'] ?? null;
        $txRef = $initRes['data']['tx_ref'] ?? null;
        if ($txRef) {
            $txn->transaction_reference = $txRef;
            $txn->save();
        }

        return view('payments.payment', [
            'gateway' => 'flutterwave',
            'authorizationUrl' => $authorizationUrl,
            'amount' => (float) $txn->amount,
            'currency' => $this->currencyCode,
            'transactionId' => $txn->id,
            'transactionUuid' => $txn->id,
            'statusRoute' => route('wallet-payment.status', ['transaction' => $txn->id]),
            'payButtonLabel' => __('labels.pay_with_flutterwave'),
            'paymentContext' => [
                'title' => __('labels.wallet_topup_payment_summary'),
                'lineItems' => [
                    ['label' => __('labels.description'), 'value' => $txn->description ?? __('labels.wallet_topup'), 'bold' => true],
                ],
            ],
        ]);
    }

    /**
     * Verify Flutterwave payment
     */
    public function verifyPayment(string $transactionId): array
    {
        try {
            $response = Http::withToken($this->secretKey)
                ->get($this->baseUrl . "transactions/{$transactionId}/verify");

            $data = $response->json();

            if ($response->successful() && ($data['data']['status'] ?? '') === 'successful') {
                return [
                    'success' => true,
                    'message' => 'Payment verified successfully',
                    'data' => $data['data']
                ];
            }

            return [
                'success' => false,
                'message' => 'Payment verification failed',
                'data' => $data
            ];
        } catch (Exception $e) {
            Log::error('Flutterwave payment verification failed: ' . $e->getMessage());
            return [
                'success' => false,
                'message' => 'Payment verification error',
                'data' => ['error' => $e->getMessage()]
            ];
        }
    }

    /**
     * Refund payment
     */
    public function refundPayment(string $transactionId, ?float $amount = null): array
    {
        try {
            $payload = [];
            if ($amount) {
                $payload['amount'] = $amount;
            }

            $response = Http::withToken($this->secretKey)
                ->post($this->baseUrl . "transactions/{$transactionId}/refund", $payload);

            $data = $response->json();

            if (!$response->successful()) {
                throw new Exception($data['message'] ?? 'Refund failed');
            }

            return [
                'success' => true,
                'message' => 'Refund processed successfully',
                'data' => $data['data']
            ];
        } catch (Exception $e) {
            Log::error('Flutterwave refund failed: ' . $e->getMessage());
            return [
                'success' => false,
                'message' => 'Refund failed',
                'data' => ['error' => $e->getMessage()]
            ];
        }
    }

    /**
     * Validate webhook signature
     */
    private function isValidSignature(?string $signature): bool
    {
        if (!$this->webhookSecret || !$signature) {
            return false;
        }
        return $signature === $this->webhookSecret;
    }

    /**
     * Find the transaction
     */
    private function findTransaction(string $paymentType, array $transactionData)
    {
        if ($paymentType === 'order_payment' || $paymentType === 'post_order_tip') {
            $txRef = $transactionData['tx_ref'] ?? '';
            $transactionId = $transactionData['transaction_id'] ?? '';

            $txn = null;
            if (!empty($txRef)) {
                $txn = OrderPaymentTransaction::where('transaction_id', $txRef)
                    ->orWhere('payment_details->flutterwave_tx_ref', $txRef)
                    ->first();
            }
            if (!$txn && !empty($transactionId)) {
                $txn = OrderPaymentTransaction::where('transaction_id', $transactionId)->first();
                if (!$txn && is_numeric($transactionId)) {
                    $txn = OrderPaymentTransaction::find((int)$transactionId);
                }
            }
            return $txn;
        }

        if ($paymentType === 'wallet_recharge') {
            $transactionId = $transactionData['transaction_id'] ?? '';
            $transaction = WalletTransaction::find($transactionId);
            if (!$transaction) {
                throw new Exception("Wallet transaction not found for ID: {$transactionId}");
            }
            return $transaction;
        }

        if ($paymentType === 'subscription_payment') {
            // For subscription we pass subscription_transaction_id in meta
            $subscriptionTxnId = $transactionData['subscription_transaction_id'] ?? ($transactionData['transaction_id'] ?? '');
            if (!$subscriptionTxnId) {
                return null;
            }
            return SubscriptionTransaction::find($subscriptionTxnId);
        }

        return null;
    }

    /**
     * Handle webhook event types
     */
    private function processEvent(string $event, string $paymentType, array $transactionData, $transaction = null): void
    {
        switch ($event) {
            case 'charge.completed':
                $status = $transactionData['status'] ?? '';
                if ($status === 'successful') {
                    $this->handlePaymentCaptured($transactionData, $paymentType, $transaction);
                } else {
                    $this->handlePaymentFailed($paymentType, $transaction);
                }
                break;

            case 'refund.completed':
                $this->handleRefund($paymentType, $transaction, $transactionData);
                break;

            default:
                Log::warning("Unhandled Flutterwave event: {$event}");
                break;
        }
    }

    private function handlePaymentCaptured(array $data, string $paymentType, $transaction = null): void
    {
        try {
            if ($paymentType === 'wallet_recharge') {
                $transaction->update([
                    'transaction_reference' => $data['tx_ref'],
                    'description' => 'Wallet Recharge Payment Captured',
                ]);
                Wallet::captureRecharge($transaction->id);
                Log::info('Wallet Recharge Completed', ['transaction_id' => $transaction->id]);
                return;
            }

            if ($paymentType === 'subscription_payment' && $transaction instanceof SubscriptionTransaction) {
                $transaction->update([
                    'status' => SubscriptionTransactionStatusEnum::COMPLETED(),
                    'transaction_id' => $data['tx_ref'] ?? ($data['id'] ?? $transaction->transaction_id),
                ]);
                $subscription = SellerSubscription::find($transaction->seller_subscription_id);
                if ($subscription) {
                    $subscription->status = SellerSubscriptionStatusEnum::ACTIVE();
                    $subscription->price_paid = $transaction->amount;
                    $subscription->save();
                }
                Log::info('Subscription payment captured', ['transaction_id' => $transaction->id]);
                return;
            }

            if ($paymentType === 'order_payment' && $transaction) {
                $transaction->update([
                    'transaction_id' => $data['tx_ref'] ?? $transaction->transaction_id,
                    'payment_status' => PaymentStatusEnum::COMPLETED(),
                    'message' => 'Payment captured successfully',
                ]);
                if ($transaction->order_id === null) {
                    Log::warning("Order ID is null for transaction: {$transaction->id}");
                    return;
                }
                Log::info("updated trx " . $transaction);

                // Handle post-order rider tip payment completion via Flutterwave webhook
                $order = Order::find($transaction->order_id);
                if ($order && (\Str::startsWith((string) $transaction->transaction_id, 'TIP-') || $paymentType === 'post_order_tip')) {
                    app(\App\Services\OrderService::class)->applyCompletedTipToOrder(
                        $order,
                        (float) ($data['amount'] ?? $transaction->amount),
                        (string) ($data['tx_ref'] ?? $transaction->transaction_id)
                    );
                    Log::info("Post-order tip applied via Flutterwave webhook for Order #{$order->id}");
                    return;
                }

                Order::capturePayment($transaction->order_id);
                OrderItem::capturePayment($transaction->order_id);
                Log::info('Order Updated And Ready to Go', ['order_id' => $transaction->order_id]);
            }
        } catch (Exception $e) {
            Log::error('Flutterwave payment capture failed: ' . $e->getMessage());
        }
    }

    private function handleRefund(string $paymentType, $transaction = null, array $data = []): void
    {
        if ($paymentType === 'wallet_recharge') {
            Wallet::captureRefund($transaction->id);
            Log::info('Wallet Refund processed', ['transaction_id' => $transaction->id]);
            return;
        }

        if ($transaction) {
            $transaction->update([
                'payment_status' => PaymentStatusEnum::REFUNDED(),
                'message' => 'Payment refunded successfully',
                'payment_details' => $data,
            ]);
            Log::info('Order refund processed', ['transaction_id' => $transaction->transaction_id]);
        }
    }

    private function handlePaymentFailed(string $paymentType, $transaction = null): void
    {
        try {
            if (!$transaction) return;

            if ($paymentType === 'wallet_recharge') {
                $transaction->update([
                    'status' => PaymentStatusEnum::FAILED(),
                    'message' => 'Wallet recharge failed',
                ]);
            } elseif ($paymentType === 'order_payment') {
                $transaction->update([
                    'payment_status' => PaymentStatusEnum::FAILED(),
                    'message' => 'Order payment failed',
                ]);

                Order::paymentFailed($transaction->order_id);
                OrderItem::paymentFailed($transaction->order_id);
            } elseif ($paymentType === 'subscription_payment' && $transaction instanceof SubscriptionTransaction) {
                $transaction->update([
                    'status' => SubscriptionTransactionStatusEnum::FAILED(),
                ]);
                $subscription = SellerSubscription::find($transaction->seller_subscription_id);
                if ($subscription) {
                    $subscription->status = SellerSubscriptionStatusEnum::CANCELLED();
                    $subscription->save();
                }
            }
            Log::info('Flutterwave payment failed', ['transaction_id' => $transaction->id]);
        } catch (Exception $e) {
            Log::error('Flutterwave payment failed handling failed: ' . $e->getMessage());
        }
    }

    /**
     * Initialize Flutterwave payment URL for post-order rider tip
     */
    public function createTipInitialize(Order $order, float $amount, string $txId): array
    {
        try {
            if (empty($this->secretKey)) {
                return ApiResponseType::toArray(success: false, message: 'Flutterwave secret key not configured');
            }

            $email = $order->email ?: ('order+' . substr($order->uuid, 0, 8) . '@invalid.local');

            $redirectUrl = route('tip-payment.status', ['transaction' => $txId]);

            $response = Http::withToken($this->secretKey)
                ->post($this->baseUrl . 'payments', [
                    'tx_ref' => $txId,
                    'amount' => (float) $amount,
                    'currency' => $this->currencyCode,
                    'redirect_url' => $redirectUrl,
                    'customer' => [
                        'name' => $order->user->name ?? "user_" . $order->user_id,
                        'email' => $email,
                        'phonenumber' => $order->mobile ?? $order->user->mobile ?? '',
                    ],
                    'customizations' => [
                        'title' => 'Rider Tip for Order #' . ($order->slug ?? $order->id),
                        'logo' => $this->logo
                    ],
                    'meta' => [
                        'user_id' => $order->user_id,
                        'order_id' => $order->id,
                        'type' => 'post_order_tip',
                        'transaction_id' => $txId
                    ]
                ]);

            $data = $response->json();

            if (!$response->successful() || empty($data['data']['link'])) {
                Log::error('Flutterwave tip initialization failed API response: ' . json_encode($data));
                return ApiResponseType::toArray(success: false, message: $data['message'] ?? 'Unable to initialize Flutterwave tip payment');
            }

            return ApiResponseType::toArray(
                success: true,
                message: 'Flutterwave tip payment initialized successfully',
                data: [
                    'payment_url' => $data['data']['link'],
                    'link'        => $data['data']['link'],
                    'tx_ref'      => $txId,
                ]
            );
        } catch (Exception $e) {
            Log::error('Flutterwave tip initialization exception: ' . $e->getMessage());
            return ApiResponseType::toArray(
                success: false,
                message: 'Unable to initialize Flutterwave tip: ' . $e->getMessage(),
                data: ['error' => $e->getMessage()]
            );
        }
    }

    /**
     * API request to create Flutterwave post-order rider tip payment intent / link for WebViews
     */
    public function createTipPaymentIntent(Request $request): JsonResponse
    {
        try {
            $user = auth()->user();
            if (!$user) {
                return ApiResponseType::sendJsonResponse(false, __('labels.user_not_authenticated'), [], 401);
            }

            $validated = $request->validate([
                'order_id' => 'required',
                'rider_tip_amount' => 'required|numeric|min:1',
            ]);

            $orderSlug = (string)$validated['order_id'];
            $order = Order::where('user_id', $user->id)
                ->where(function ($q) use ($orderSlug) {
                    $q->where('slug', $orderSlug)
                        ->orWhere('uuid', $orderSlug)
                        ->orWhere('id', $orderSlug);
                })->first();

            if (!$order) {
                return ApiResponseType::sendJsonResponse(false, __('labels.order_not_found') ?? 'Order not found', [], 404);
            }

            $tipAmount = (float)$validated['rider_tip_amount'];
            $txId = 'TIP-' . \Str::upper(\Str::random(12));

            // Create pending transaction for Flutterwave tip payment
            $pendingTx = OrderPaymentTransaction::create([
                'uuid' => (string) \Str::uuid(),
                'user_id' => $order->user_id,
                'order_id' => $order->id,
                'transaction_id' => $txId,
                'amount' => $tipAmount,
                'currency' => $order->currency_code ?? $this->currencyCode,
                'payment_method' => PaymentTypeEnum::FLUTTERWAVE(),
                'payment_status' => PaymentStatusEnum::PENDING(),
            ]);

            $result = $this->createTipInitialize($order, $tipAmount, $txId);

            if (!($result['success'] ?? false)) {
                return ApiResponseType::sendJsonResponse(false, $result['message'] ?? 'Unable to initialize Flutterwave tip payment', [], 400);
            }

            $paymentUrl = $result['data']['payment_url'] ?? $result['data']['link'] ?? '';
            $details = [
                'flutterwave_tx_ref' => $txId,
                'flutterwave_payment_url' => $paymentUrl,
            ];
            $pendingTx->update(['payment_details' => $details]);

            return ApiResponseType::sendJsonResponse(
                success: true,
                message: __('labels.payment_intent_created_successfully') ?? 'Flutterwave tip payment intent created successfully',
                data: [
                    'order_id' => $order->id,
                    'transaction_id' => $txId,
                    'rider_tip_amount' => $tipAmount,
                    'link' => $paymentUrl,
                    'invoice_url' => $paymentUrl,
                    'payment_url' => $paymentUrl,
                ]
            );
        } catch (Exception $e) {
            Log::error('Flutterwave createTipPaymentIntent failed: ' . $e->getMessage());
            return ApiResponseType::sendJsonResponse(false, 'messages.something_went_wrong', ['error' => $e->getMessage()], 500);
        }
    }
}
