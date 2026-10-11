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
use App\Services\SettingService;
use App\Types\Api\ApiResponseType;
use Exception;
use Illuminate\Contracts\View\Factory;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Razorpay\Api\Api;

class RazorpayController extends Controller
{
    private Api $razorpayApi;
    private string $keyId;
    private string $secretKey;
    private string $webhookSecret;

    public function __construct(SettingService $settingService)
    {
        $setting = $settingService->getSettingByVariable(SettingTypeEnum::PAYMENT());

        $this->keyId = $setting->value['razorpayKeyId'] ?? "";
        $this->secretKey = $setting->value['razorpaySecretKey'] ?? "";
        $this->webhookSecret = $setting->value['razorpayWebhookSecret'] ?? "";

        $this->razorpayApi = new Api($this->keyId, $this->secretKey);
    }

    /**
     * Render a web view for paying a subscription transaction via Razorpay Checkout
     */
    public function showSubscriptionPaymentPage(string $transaction): Factory|View
    {
        $txn = SubscriptionTransaction::with('subscription')
            ->where('uuid', $transaction)
            ->firstOrFail();

        if ((string)$txn->payment_gateway !== (string)PaymentTypeEnum::RAZORPAY()) {
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

        // Razorpay order should be stored as transaction_id; if missing, abort
        $orderId = $txn->transaction_id;
        if (empty($orderId)) {
            abort(400, 'Payment is not initialized');
        }

        $amountPaise = (int)round(((float)$txn->amount) * 100);
        $sellerId = $txn->seller_id;
        $subscriptionId = $txn->seller_subscription_id;

        $plan = $txn->plan;
        $planName = optional($plan)->name ?? 'Subscription Plan';

        return view('payments.payment', [
            'gateway' => 'razorpay',
            'keyId' => $this->keyId,
            'orderId' => $orderId,
            'amount' => (float)$txn->amount,
            'amountPaise' => $amountPaise,
            'currency' => 'INR',
            'transactionId' => $txn->id,
            'transactionUuid' => $txn->uuid,
            'sellerId' => $sellerId,
            'subscriptionId' => $subscriptionId,
            'plan' => $plan,
            'planName' => $planName,
            'payButtonLabel' => __('labels.pay_with_razorpay'),
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
     * Handle Razorpay Webhook
     */
    public function handleWebhook(Request $request): JsonResponse
    {
        $payload = $request->getContent();
        $signature = $request->header('X-Razorpay-Signature');

        Log::info("Razorpay Webhook Payload: " . $payload);

        DB::beginTransaction();
        try {
            if (!$this->isValidSignature($payload, $signature)) {
                Log::error("Invalid Razorpay Webhook signature.");
                return response()->json(['error' => 'Invalid signature'], 400);
            }

            $data = json_decode($payload, true);
            $event = $data['event'] ?? null;
            $paymentEntity = $data['payload']['payment']['entity'] ?? [];
            $paymentType = $paymentEntity['notes']['type'] ?? 'order_payment';

            $transaction = $this->findTransaction($paymentType, $paymentEntity);

            $this->processEvent($event, $paymentType, $paymentEntity, $transaction);

            DB::commit();
            return response()->json(['status' => 'success'], 200);

        } catch (Exception $e) {
            DB::rollBack();
            Log::error("Razorpay Webhook Error: " . $e->getMessage());
            return response()->json(['error' => 'Server Error'], 500);
        }
    }

    /**
     * Create a new Razorpay order
     */
    public function createOrder(Request $request): JsonResponse
    {
        try {
            $input = $request->validate([
                'amount' => 'required|numeric|min:1',
                'currency' => 'required|string|in:INR',
                'receipt' => 'required|string'
            ]);

            $order = $this->razorpayApi->order->create([
                'amount' => (int)$input['amount'] * 100, // Convert to paisa
                'currency' => $input['currency'],
                'receipt' => $input['receipt'],
                'payment_capture' => 1,
                'notes' => [
                    'user_id' => auth()->id(),
                ],
            ]);

            return ApiResponseType::sendJsonResponse(
                success: true,
                message: 'Razorpay Order created successfully',
                data: $order->toArray()
            );

        } catch (Exception $e) {
            Log::error('Razorpay order creation failed: ' . $e->getMessage());
            return ApiResponseType::sendJsonResponse(
                success: false,
                message: 'Unable to create order',
                data: ['error' => $e->getMessage()]
            );
        }
    }

    /**
     * Render the payment page for a wallet recharge transaction via Razorpay.
     */
    public function showWalletPaymentPage(int $transaction): Factory|View
    {
        $txn = WalletTransaction::where('id', $transaction)->firstOrFail();

        if ($txn->payment_method !== PaymentTypeEnum::RAZORPAY()) {
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

        $orderId = $txn->transaction_reference;
        if (empty($orderId)) {
            $orderRes = $this->createWalletRechargeOrder([
                'amount' => (float) $txn->amount,
                'currency' => 'INR',
                'description' => $txn->description ?? 'Wallet Top-up',
                'transaction_id' => (string) $txn->id,
            ]);
            if (!($orderRes['success'] ?? false)) {
                abort(400, $orderRes['data']['error'] ?? $orderRes['message'] ?? 'Unable to initialize Razorpay payment');
            }
            $orderId = $orderRes['data']['id'] ?? null;
            if ($orderId) {
                $txn->transaction_reference = $orderId;
                $txn->save();
            }
        }

        $amountPaise = (int) round(((float) $txn->amount) * 100);

        return view('payments.payment', [
            'gateway' => 'razorpay',
            'keyId' => $this->keyId,
            'orderId' => $orderId,
            'amount' => (float) $txn->amount,
            'amountPaise' => $amountPaise,
            'currency' => $txn->currency_code ?? 'INR',
            'transactionId' => $txn->id,
            'transactionUuid' => $txn->id,
            'sellerId' => $txn->user_id,
            'subscriptionId' => 0,
            'planName' => $txn->description ?? __('labels.wallet_topup'),
            'statusRoute' => route('wallet-payment.status', ['transaction' => $txn->id]),
            'payButtonLabel' => __('labels.pay_with_razorpay'),
            'paymentContext' => [
                'title' => __('labels.wallet_topup_payment_summary'),
                'lineItems' => [
                    ['label' => __('labels.description'), 'value' => $txn->description ?? __('labels.wallet_topup'), 'bold' => true],
                ],
            ],
        ]);
    }

    /**
     * Create a Razorpay order for wallet recharge
     */
    public function createWalletRechargeOrder(array $data): array
    {
        try {
            // Validate input manually
            $validated = validator($data, [
                'amount' => 'required|numeric|min:1',
                'currency' => 'nullable|string|in:INR',
                'description' => 'nullable|string',
                'transaction_id' => 'required|string',
            ])->validate();

            $order = $this->razorpayApi->order->create([
                'amount' => (int)$validated['amount'] * 100, // Convert to paisa
                'currency' => $validated['currency'] ?? 'INR',
                'receipt' => $validated['description'] ?? "Wallet Recharge",
                'payment_capture' => 1,
                'notes' => [
                    'user_id' => auth()->id(),
                    'type' => 'wallet_recharge', // Specify wallet recharge
                    'transaction_id' => $validated['transaction_id'],
                ],
            ]);

            return [
                'success' => true,
                'message' => 'Razorpay Wallet Recharge Order created successfully',
                'data' => $order->toArray()
            ];

        } catch (Exception $e) {
            Log::error('Razorpay wallet recharge order creation failed: ' . $e->getMessage());
            return [
                'success' => false,
                'message' => 'Unable to create wallet recharge order',
                'data' => ['error' => $e->getMessage()]
            ];
        }
    }

    /**
     * Create a Razorpay order for online cash collection settlement
     * 
     * [CHANGE] Added helper method to create a Razorpay order with type delivery_boy_settlement
     */
    public function createOnlineSettlementOrder(array $data): array
    {
        try {
            $validated = validator($data, [
                'amount' => 'required|numeric|min:1',
                'currency' => 'nullable|string',
                'description' => 'nullable|string',
                'settlement_id' => 'required|string',
            ])->validate();

            $order = $this->razorpayApi->order->create([
                'amount' => (int) round(((float)$validated['amount']) * 100), // Convert to paisa
                'currency' => $validated['currency'] ?? 'INR',
                'receipt' => $validated['description'] ?? "Cash Settlement",
                'payment_capture' => 1,
                'notes' => [
                    'type' => 'delivery_boy_settlement',
                    'settlement_id' => $validated['settlement_id'],
                ],
            ]);

            return [
                'success' => true,
                'message' => 'Razorpay Cash Settlement Order created successfully',
                'data' => $order->toArray()
            ];

        } catch (Exception $e) {
            Log::error('Razorpay cash settlement order creation failed: ' . $e->getMessage());
            return [
                'success' => false,
                'message' => 'Unable to create cash settlement order',
                'data' => ['error' => $e->getMessage()]
            ];
        }
    }

    /**
     * Create a Razorpay order for a subscription payment
     * Expects: amount, currency(optional), description(optional), subscription_transaction_id, seller_subscription_id, seller_id, user_id
     */
    public function createSubscriptionOrder(array $data): array
    {
        try {
            $validated = validator($data, [
                'amount' => 'required|numeric|min:1',
                'currency' => 'nullable|string|in:INR',
                'description' => 'nullable|string',
                'subscription_transaction_id' => 'required|integer',
                'seller_subscription_id' => 'required|integer',
                'seller_id' => 'required|integer',
                'user_id' => 'required|integer',
            ])->validate();

            $order = $this->razorpayApi->order->create([
                'amount' => (int)round($validated['amount'] * 100),
                'currency' => $validated['currency'] ?? 'INR',
                'receipt' => 'Seller Subscription',
                'payment_capture' => 1,
                'notes' => [
                    'user_id' => $validated['user_id'],
                    'type' => 'subscription_payment',
                    'subscription_transaction_id' => (string)$validated['subscription_transaction_id'],
                    'seller_subscription_id' => (string)$validated['seller_subscription_id'],
                    'seller_id' => (string)$validated['seller_id'],
                ],
            ]);

            return [
                'success' => true,
                'message' => 'Razorpay Subscription Order created successfully',
                'data' => $order->toArray(),
            ];
        } catch (Exception $e) {
            Log::error('Razorpay subscription order creation failed: ' . $e->getMessage());
            return [
                'success' => false,
                'message' => 'Unable to create subscription order',
                'data' => ['error' => $e->getMessage()],
            ];
        }
    }

    /**
     * Verify Razorpay payment signature
     */
    public function verifyPayment(array $data): array
    {
        try {
            $razorpayOrderId = $data['razorpay_order_id'];
            $razorpayPaymentId = $data['transaction_id'];
            $razorpaySignature = $data['razorpay_signature'];

            $expectedSignature = hash_hmac(
                'sha256',
                $razorpayOrderId . '|' . $razorpayPaymentId,
                $this->secretKey
            );

            if ($expectedSignature === $razorpaySignature) {
                return [
                    'success' => true,
                    'message' => 'Payment verified successfully',
                    'data' => []
                ];
            }

            return [
                'success' => false,
                'message' => 'Invalid razorpay signature',
                'data' => []
            ];

        } catch (Exception $e) {
            Log::error('Razorpay payment verification failed: ' . $e->getMessage());
            return [
                'success' => false,
                'message' => 'Payment verification failed',
                'data' => ['error' => $e->getMessage()]
            ];
        }
    }

    /**
     * Fetch payment details by payment ID
     */
    public function getPaymentDetails(string $paymentId): JsonResponse
    {
        try {
            $payment = $this->razorpayApi->payment->fetch($paymentId);
            return ApiResponseType::sendJsonResponse(
                success: true,
                message: 'Payment details fetched successfully',
                data: $payment
            );
        } catch (Exception $e) {
            Log::error('Razorpay fetch payment failed: ' . $e->getMessage());
            return ApiResponseType::sendJsonResponse(
                success: false,
                message: 'Unable to fetch payment details',
                data: ['error' => $e->getMessage()]
            );
        }
    }

    /**
     * Refund a payment
     */
    public function refundPayment($paymentId, $amount = null): array
    {
        try {

            $payment = $this->razorpayApi->payment->fetch($paymentId);

            $refundData = [];
            if (isset($amount)) {
                $refundData['amount'] = $amount * 100;
            }

            $refund = $payment->refund($refundData);

            return [
                "success" => true,
                "message" => 'Refund processed successfully',
                "data" => $refund,
            ];

        } catch (Exception $e) {
            Log::error('Razorpay refund failed: ' . $e->getMessage());
            return [
                "success" => false,
                "message" => 'Refund failed: ' . $e->getMessage(),
                "data" => ['error' => $e->getMessage()],
            ];
        }
    }


    private function isValidSignature(string $payload, ?string $signature): bool
    {
        if ($signature === null) {
            return false;
        }

        $expectedSignature = hash_hmac('sha256', $payload, $this->webhookSecret);
        return hash_equals($expectedSignature, $signature);
    }


    private function processEvent(string $event, string $paymentType, array $paymentEntity, $transaction = null): void
    {
        switch ($event) {
            case 'payment.authorized':
                Log::info('Payment authorized', ['payment_id' => $paymentEntity['id']]);
                break;

            case 'payment.captured':
                $this->handlePaymentCaptured(
                    paymentId: $paymentEntity['id'],
                    paymentEntity: $paymentEntity,
                    userId: $paymentEntity['notes']['user_id'] ?? null,
                    paymentType: $paymentType,
                    transaction: $transaction
                );
                Log::info('Payment captured', ['payment_id' => $paymentEntity['id']]);
                break;

            case 'order.paid':
                $this->handleOrderPaid(
                    event: $event,
                    paymentType: $paymentType,
                    transaction: $transaction
                );
                break;

            case 'payment.failed':
                $this->handlePaymentFailed($paymentType, $transaction, $event);
                break;

            case 'refund.processed':
                $this->handleRefund(
                    paymentType: $paymentType,
                    transaction: $transaction,
                    data: $paymentEntity
                );
                break;

            default:
                Log::warning("Unhandled Razorpay Webhook Event: {$event}");
                break;
        }
    }

    private function findTransaction(string $paymentType, array $paymentEntity)
    {
        if ($paymentType === 'order_payment' || $paymentType === 'post_order_tip') {
            $paymentId = $paymentEntity['id'] ?? '';
            $orderId = $paymentEntity['order_id'] ?? '';

            $tx = OrderPaymentTransaction::whereIn('transaction_id', array_filter([$paymentId, $orderId]))->first();

            if (! $tx && ! empty($paymentEntity['notes']['tip_transaction_id'])) {
                $tx = OrderPaymentTransaction::where('transaction_id', $paymentEntity['notes']['tip_transaction_id'])->first();
            }

            if (! $tx && ! empty($paymentEntity['notes']['order_id'])) {
                $tx = OrderPaymentTransaction::where('order_id', $paymentEntity['notes']['order_id'])
                    ->where('payment_status', PaymentStatusEnum::PENDING())
                    ->latest()
                    ->first();
            }

            return $tx;
        }

        if ($paymentType === 'wallet_recharge') {
            $transactionId = $paymentEntity['notes']['transaction_id'] ?? '';
            $transaction = WalletTransaction::find($transactionId);

            if (!$transaction) {
                Log::warning("Wallet Transaction not found for ID: {$transactionId}");
                throw new Exception('Wallet Transaction not found');
            }

            return $transaction;
        }

        if ($paymentType === 'subscription_payment') {
            $transactionId = $paymentEntity['notes']['subscription_transaction_id'] ?? '';
            if (!$transactionId) {
                return null;
            }
            return SubscriptionTransaction::find($transactionId);
        }

        // [CHANGE] Handle delivery boy online cash collection settlement
        if ($paymentType === 'delivery_boy_settlement') {
            $transactionId = $paymentEntity['notes']['settlement_id'] ?? '';
            if (!$transactionId) {
                return null;
            }
            return \App\Models\DeliveryBoyOnlineSettlement::find($transactionId);
        }

        return null;
    }

    private function handleOrderPaid($event, $paymentType, $transaction): void
    {
        if ($transaction !== null) {
            if ($paymentType === 'wallet_recharge') {
                $result = Wallet::captureRecharge($transaction->id);
                if (!$result['success']) {
                    Log::error("Wallet Recharge Failed: " . $result['message']);
                    return;
                }
                Log::info("Wallet Recharge Completed: {$event}");

            } elseif ($paymentType === 'order_payment' || $paymentType === 'post_order_tip') {
                $transaction->update([
                    'payment_status' => PaymentStatusEnum::COMPLETED(),
                    'message' => $event
                ]);
                if ($transaction->order_id === null) {
                    Log::warning("Order ID is null for transaction: {$transaction->id}");
                    return;
                }
                $order = Order::find($transaction->order_id);
                if ($order && (Str::startsWith((string) $transaction->transaction_id, 'TIP-') || $paymentType === 'post_order_tip')) {
                    app(\App\Services\OrderService::class)->applyCompletedTipToOrder($order, (float) $transaction->amount, (string) $transaction->transaction_id);
                    Log::info("Post-order tip applied via Razorpay webhook for Order #{$order->id}");
                    return;
                }
                Order::capturePayment($transaction->order_id);
                OrderItem::capturePayment($transaction->order_id);
                Log::info("Order Updated And Ready to Go: {$event}");
            }
        }
    }

    private function handleRefund($paymentType, $transaction, $data = null): void
    {
        if ($paymentType === 'wallet_recharge') {
            Wallet::captureRefund($transaction->id);
            Log::info('event refund.processed Wallet Refund Processed', ['payment_id' => $transaction->transaction_reference ?? null]);
            return;
        }
        $transaction->update([
            'payment_status' => PaymentStatusEnum::REFUNDED(),
            'message' => "Payment Refunded",
            'payment_details' => $data
        ]);
        Log::info('event refund.processed Payment Refunded', ['payment_id' => $transaction->transaction_id ?? null]);
    }

    private function handlePaymentCaptured($paymentId, $paymentEntity, $userId, $paymentType, $transaction = null): void
    {
        if ($paymentType === 'wallet_recharge') {
            $transaction->update([
                'transaction_reference' => $paymentId,
                'amount' => $paymentEntity['amount'] / 100,
                'currency_code' => $paymentEntity['currency'],
                'description' => 'Wallet Recharge Payment Captured'
            ]);
            return;
        }
        if ($paymentType === 'subscription_payment' && $transaction instanceof SubscriptionTransaction) {
            // Mark subscription transaction completed and activate subscription
            $transaction->update([
                'status' => SubscriptionTransactionStatusEnum::COMPLETED(),
                'transaction_id' => $paymentId,
                'amount' => ($paymentEntity['amount'] ?? 0) / 100,
            ]);

            // Activate related subscription and set price_paid
            $subscription = SellerSubscription::find($transaction->seller_subscription_id);
            if ($subscription) {
                $subscription->status = SellerSubscriptionStatusEnum::ACTIVE();
                $subscription->price_paid = $transaction->amount;
                $subscription->save();
            }
            Log::info('Subscription payment captured', ['payment_id' => $paymentId, 'transaction_id' => $transaction->id]);
            return;
        }

        // [CHANGE] Process captured payment for delivery boy cash settlement
        if ($paymentType === 'delivery_boy_settlement' && $transaction instanceof \App\Models\DeliveryBoyOnlineSettlement) {
            $this->completeOnlineSettlement($transaction, $paymentId);
            return;
        }

        $orderId = $transaction->order_id ?? ($paymentEntity['notes']['order_id'] ?? null);
        $paymentEntity['order_id'] = $orderId;
        $paymentEntity['user_id'] = $userId ?? ($paymentEntity['notes']['user_id'] ?? null);

        OrderPaymentTransaction::saveTransaction(data: $paymentEntity, paymentId: $paymentId, paymentMethod: PaymentTypeEnum::RAZORPAY(), paymentStatus: PaymentStatusEnum::COMPLETED());

        if ($orderId && ($paymentType === 'post_order_tip' || Str::startsWith((string) ($transaction->transaction_id ?? ''), 'TIP-'))) {
            $order = Order::find($orderId);
            if ($order) {
                $tipAmount = ($paymentEntity['amount'] ?? 0) / 100;
                app(\App\Services\OrderService::class)->applyCompletedTipToOrder($order, (float) $tipAmount, (string) $paymentId);
                Log::info("Post-order tip applied via Razorpay payment capture for Order #{$order->id}");
            }
        }
    }

    private function handlePaymentFailed(string $paymentType, $transaction = null, string $event = ''): void
    {
        if ($transaction === null) {
            return;
        }

        if ($paymentType === 'wallet_recharge') {
            $transaction->update([
                'status' => PaymentStatusEnum::FAILED(),
                'message' => $event,
            ]);
            Log::info('Wallet Recharge Failed', ['payment_id' => $transaction->id]);
        } elseif ($paymentType === 'order_payment') {
            $transaction->update([
                'payment_status' => PaymentStatusEnum::FAILED(),
                'message' => $event,
            ]);

            Order::paymentFailed($transaction->order_id);
            OrderItem::paymentFailed($transaction->order_id);
            Log::info('Order Payment Failed', ['order_id' => $transaction->order_id]);
        } elseif ($paymentType === 'subscription_payment' && $transaction instanceof SubscriptionTransaction) {
            $transaction->update([
                'status' => SubscriptionTransactionStatusEnum::FAILED(),
            ]);
            $subscription = SellerSubscription::find($transaction->seller_subscription_id);
            if ($subscription) {
                // Keep subscription pending on failure to allow retry
                $subscription->status = SellerSubscriptionStatusEnum::CANCELLED();
                $subscription->save();
            }
            Log::info('Subscription Payment Failed', ['subscription_id' => $transaction->seller_subscription_id]);
        }

        // [CHANGE] Handle failed payment for delivery boy cash settlement
        if ($paymentType === 'delivery_boy_settlement' && $transaction instanceof \App\Models\DeliveryBoyOnlineSettlement) {
            $transaction->update([
                'status' => 'failed',
            ]);
            Log::info('Delivery boy cash collection settlement failed', ['settlement_id' => $transaction->id]);
        }
    }

    /**
     * Complete an online cash collection settlement.
     * 
     * [CHANGE] Process successful settlement payment, update assignments, log cash transactions
     */
    private function completeOnlineSettlement(\App\Models\DeliveryBoyOnlineSettlement $settlement, string $paymentId): void
    {
        if ($settlement->status === 'completed') {
            return;
        }

        \Illuminate\Support\Facades\DB::transaction(function () use ($settlement, $paymentId) {
            $settlement->update([
                'status' => 'completed',
                'transaction_reference' => $paymentId,
                'paid_at' => now(),
            ]);

            // Get assignments associated with this settlement
            $assignments = $settlement->assignments()->get();

            foreach ($assignments as $assignment) {
                $amountToSettle = $assignment->pivot->amount;

                // Update the assignment totals and submission status
                $newSubmittedTotal = (float) $assignment->cod_cash_submitted + $amountToSettle;
                $newStatus = $newSubmittedTotal >= (float) $assignment->cod_cash_collected
                    ? 'submitted'
                    : 'partially_submitted';

                $assignment->update([
                    'cod_cash_submitted' => $newSubmittedTotal,
                    'cod_submission_status' => $newStatus,
                ]);

                // Create a transaction record for the cash submission
                \App\Models\DeliveryBoyCashTransaction::create([
                    'delivery_boy_assignment_id' => $assignment->id,
                    'order_id' => $assignment->order_id,
                    'delivery_boy_id' => $settlement->delivery_boy_id,
                    'amount' => $amountToSettle,
                    'transaction_type' => 'submitted',
                    'transaction_date' => now(),
                    'payment_channel' => 'online', // [CHANGE] Track payment channel
                    'payment_gateway' => 'razorpay',
                    'online_settlement_id' => $settlement->id,
                ]);
            }
        });

        Log::info('Delivery boy cash collection settlement completed', [
            'settlement_id' => $settlement->id,
            'amount' => $settlement->amount,
            'payment_id' => $paymentId,
        ]);
    }
}
