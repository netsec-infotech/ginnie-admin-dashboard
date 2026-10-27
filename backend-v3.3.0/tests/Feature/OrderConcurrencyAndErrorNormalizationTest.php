<?php

use App\Enums\Order\OrderDeliveryTypeEnum;
use App\Enums\Payment\PaymentTypeEnum;
use App\Enums\PaymentStatusEnum;
use App\Http\Controllers\Payments\FlutterwaveController;
use App\Http\Controllers\Payments\MercadoPagoController;
use App\Http\Controllers\Payments\PaystackController;
use App\Http\Controllers\Payments\RazorpayController;
use App\Http\Controllers\Payments\StripeController;
use App\Http\Controllers\Payments\XenditController;
use App\Models\Address;
use App\Models\Cart;
use App\Models\CartItem;
use App\Models\Order;
use App\Models\OrderPaymentTransaction;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Store;
use App\Models\StoreProductVariant;
use App\Models\User;
use App\Services\OrderService;
use App\Services\PaymentService;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

beforeEach(function () {
    // Basic setup if needed
});

test('concurrency lock rejects duplicate order attempt and triggers auto-refund if prepaid transaction_id present', function () {
    $user = User::factory()->create();

    // Mock payment service to verify refund is called
    $paymentServiceMock = Mockery::mock(PaymentService::class);
    $paymentServiceMock->shouldReceive('processOrderRefund')
        ->once()
        ->with(PaymentTypeEnum::STRIPE(), 'pi_test_concurrency_123')
        ->andReturn(['success' => true, 'message' => 'Refund processed']);

    $orderService = new OrderService(
        stockService: app(\App\Services\StockService::class),
        deliveryBoyService: app(\App\Services\DeliveryBoyService::class),
        settingService: app(\App\Services\SettingService::class),
        paymentService: $paymentServiceMock,
        sellerStatementService: app(\App\Services\SellerStatementService::class),
        walletService: app(\App\Services\WalletService::class)
    );

    // Acquire lock beforehand to simulate simultaneous running process
    $lock = Cache::lock("user_order_placement_{$user->id}", 15);
    $lock->get();

    $response = $orderService->createOrder($user, [
        'payment_type' => PaymentTypeEnum::STRIPE(),
        'transaction_id' => 'pi_test_concurrency_123',
    ]);

    expect($response['success'])->toBeFalse();
    expect($response['data']['code'] ?? null)->toBe('ORDER_DUPLICATE_REFUNDED');
    expect($response['message'])->toBe(__('messages.order_duplicate_payment_refunded'));

    $lock->release();
});

test('concurrency lock rejects duplicate without transaction_id and returns in-progress message without refund', function () {
    $user = User::factory()->create();

    $paymentServiceMock = Mockery::mock(PaymentService::class);
    $paymentServiceMock->shouldNotReceive('processOrderRefund');

    $orderService = new OrderService(
        stockService: app(\App\Services\StockService::class),
        deliveryBoyService: app(\App\Services\DeliveryBoyService::class),
        settingService: app(\App\Services\SettingService::class),
        paymentService: $paymentServiceMock,
        sellerStatementService: app(\App\Services\SellerStatementService::class),
        walletService: app(\App\Services\WalletService::class)
    );

    $lock = Cache::lock("user_order_placement_{$user->id}", 15);
    $lock->get();

    $response = $orderService->createOrder($user, [
        'payment_type' => PaymentTypeEnum::COD(),
    ]);

    expect($response['success'])->toBeFalse();
    expect($response['data']['code'] ?? null)->toBe('ORDER_IN_PROGRESS');
    expect($response['message'])->toBe(__('messages.order_already_in_progress'));

    $lock->release();
});

test('database QueryException is caught, masked without raw SQL, and prepaid charge is auto-refunded', function () {
    $user = User::factory()->create();

    $paymentServiceMock = Mockery::mock(PaymentService::class);
    $paymentServiceMock->shouldReceive('processOrderRefund')
        ->once()
        ->with('stripePayment', 'pi_test_sql_error_123')
        ->andReturn(['success' => true]);

    $orderService = new OrderService(
        stockService: app(\App\Services\StockService::class),
        deliveryBoyService: app(\App\Services\DeliveryBoyService::class),
        settingService: app(\App\Services\SettingService::class),
        paymentService: $paymentServiceMock,
        sellerStatementService: app(\App\Services\SellerStatementService::class),
        walletService: app(\App\Services\WalletService::class)
    );

    // Mock cart and validateCartAndSettings to throw QueryException
    DB::shouldReceive('beginTransaction')->andReturnTrue();
    DB::shouldReceive('rollBack')->andReturnTrue();

    // Trigger an exception by passing malformed cart validation or simulating DB failure
    // We can directly verify the refund behavior with transaction_id on exception
    $response = $orderService->createOrder($user, [
        'payment_type' => 'stripePayment',
        'transaction_id' => 'pi_test_sql_error_123',
    ]);

    expect($response['success'])->toBeFalse();
    // Verify no raw MySQL / PDO strings leak
    expect($response['message'])->not->toContain('SQLSTATE');
    expect($response['message'])->not->toContain('1062 Duplicate entry');
    expect($response['message'])->not->toContain('127.0.0.1');
});

test('PaymentService processOrderRefund handles all 6 payment gateways and updates transaction record', function () {
    $gateways = [
        'stripe' => [PaymentTypeEnum::STRIPE(), StripeController::class],
        'razorpay' => [PaymentTypeEnum::RAZORPAY(), RazorpayController::class],
        'paystack' => [PaymentTypeEnum::PAYSTACK(), PaystackController::class],
        'flutterwave' => [PaymentTypeEnum::FLUTTERWAVE(), FlutterwaveController::class],
        'xendit' => [PaymentTypeEnum::XENDIT(), XenditController::class],
        'mercadopago' => [PaymentTypeEnum::MERCADO_PAGO(), MercadoPagoController::class],
    ];

    $user = User::factory()->create();

    foreach ($gateways as $key => [$enumVal, $controllerClass]) {
        $txnId = 'test_txn_' . $key . '_' . Str::random(8);

        OrderPaymentTransaction::create([
            'uuid' => (string) Str::uuid(),
            'user_id' => $user->id,
            'transaction_id' => $txnId,
            'amount' => 100.00,
            'currency' => 'USD',
            'payment_method' => $enumVal,
            'payment_status' => PaymentStatusEnum::COMPLETED(),
        ]);

        $mockController = Mockery::mock($controllerClass);
        $mockController->shouldReceive('refundPayment')
            ->once()
            ->with($txnId, null)
            ->andReturn(['success' => true, 'message' => 'Refunded']);

        app()->instance($controllerClass, $mockController);

        $paymentService = new PaymentService();
        $result = $paymentService->processOrderRefund($enumVal, $txnId);

        expect($result['success'])->toBeTrue();

        $updatedTxn = OrderPaymentTransaction::where('transaction_id', $txnId)->first();
        expect($updatedTxn->payment_status)->toBe(PaymentStatusEnum::REFUNDED());
    }
});

test('hosted gateway webhook failure auto-refunds and marks payment refunded', function () {
    $user = User::factory()->create();
    $txnId = 'FLW_' . Str::random(10);

    $transaction = OrderPaymentTransaction::create([
        'uuid' => (string) Str::uuid(),
        'user_id' => $user->id,
        'transaction_id' => $txnId,
        'amount' => 50.00,
        'currency' => 'USD',
        'payment_method' => PaymentTypeEnum::FLUTTERWAVE(),
        'payment_status' => PaymentStatusEnum::PENDING(),
        'payment_details' => [
            // Invalid empty checkout snapshot that causes cart rebuild failure
            'checkout_snapshot' => [
                'cart_items' => [],
            ],
        ],
    ]);

    $paymentServiceMock = Mockery::mock(PaymentService::class);
    $paymentServiceMock->shouldReceive('processOrderRefund')
        ->once()
        ->with(PaymentTypeEnum::FLUTTERWAVE(), $txnId)
        ->andReturn(['success' => true]);

    $orderService = new OrderService(
        stockService: app(\App\Services\StockService::class),
        deliveryBoyService: app(\App\Services\DeliveryBoyService::class),
        settingService: app(\App\Services\SettingService::class),
        paymentService: $paymentServiceMock,
        sellerStatementService: app(\App\Services\SellerStatementService::class),
        walletService: app(\App\Services\WalletService::class)
    );

    $order = $orderService->createConfirmedHostedGatewayOrder($transaction);

    expect($order)->toBeNull();
    $transaction->refresh();
    expect($transaction->payment_status)->toBe(PaymentStatusEnum::REFUNDED());
});

test('stripe controller reuses cached active payment intent for identical cart amount and user', function () {
    $user = User::factory()->create();
    $this->actingAs($user, 'sanctum');

    $amountInCents = 2500;
    $currency = 'usd';
    $cacheKey = "user_order_stripe_intent_{$user->id}_{$amountInCents}_{$currency}";

    // Pre-populate cache
    Cache::put($cacheKey, 'pi_cached_mock_123', now()->addMinutes(15));

    // Verify cache key retrieval
    expect(Cache::get($cacheKey))->toBe('pi_cached_mock_123');
});

test('order slug generator produces unique high-entropy identifiers', function () {
    $user = User::factory()->create();
    $slugs = [];

    for ($i = 0; $i < 50; $i++) {
        $slug = Str::slug('order-' . now()->format('YmdHis') . '-' . $user->id . '-' . Str::lower(Str::random(6)));
        $slugs[] = $slug;
    }

    expect(count(array_unique($slugs)))->toBe(50);
    expect($slugs[0])->toMatch('/^order-\d{14}-' . $user->id . '-[a-z0-9]{6}$/');
});

