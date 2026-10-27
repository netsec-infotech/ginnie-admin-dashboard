<?php

use App\Enums\Payment\PaymentTypeEnum;
use App\Enums\PaymentStatusEnum;
use App\Enums\SettingTypeEnum;
use App\Enums\Subscription\SubscriptionTransactionStatusEnum;
use App\Enums\Wallet\WalletTransactionStatusEnum;
use App\Http\Controllers\Payments\XenditController;
use App\Http\Middleware\CheckInstallation;
use App\Http\Middleware\VerifyLicense;
use App\Models\DeliveryZone;
use App\Models\Order;
use App\Models\OrderPaymentTransaction;
use App\Models\Seller;
use App\Models\SellerSubscription;
use App\Models\Setting;
use App\Models\SubscriptionPlan;
use App\Models\SubscriptionTransaction;
use App\Models\User;
use App\Models\Wallet;
use App\Models\WalletTransaction;
use App\Services\SettingTestService;
use App\Types\Settings\PaymentSettingType;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->withoutMiddleware([VerifyLicense::class, CheckInstallation::class]);

    // Seed Xendit Config
    Setting::updateOrCreate(
        ['variable' => SettingTypeEnum::PAYMENT()],
        [
            'value' => json_encode([
                'xenditPayment' => '1',
                'xenditPaymentMode' => 'test',
                'xenditSecretKey' => 'xnd_test_123',
                'xenditWebhookVerificationToken' => 'token_123',
                'xenditCurrencyCode' => 'IDR',
            ]),
            'status' => 1,
        ]
    );
});

function createXenditTestOrder(User $user, array $attributes = []): Order
{
    $zone = DeliveryZone::first() ?? DeliveryZone::factory()->create();

    return Order::create(array_merge([
        'user_id' => $user->id,
        'delivery_zone_id' => $zone->id,
        'uuid' => (string) Str::uuid(),
        'slug' => 'order-'.Str::random(8),
        'email' => 'customer@xendit.com',
        'ip_address' => '127.0.0.1',
        'currency_code' => 'IDR',
        'currency_rate' => 1,
        'payment_method' => 'xenditPayment',
        'payment_status' => 'pending',
        'subtotal' => 1000.00,
        'total_payable' => 1000.00,
        'final_total' => 1000.00,
        'wallet_balance' => 0.00,
        'promo_discount' => 0.00,
        'gift_card_discount' => 0.00,
        'delivery_charge' => 0.00,
        'handling_charges' => 0.00,
        'per_store_drop_off_fee' => 0.00,
        'billing_name' => 'John Doe',
        'shipping_name' => 'John Doe',
        'billing_address_type' => 'home',
        'shipping_address_type' => 'home',
    ], $attributes));
}

it('validates xendit credentials during setting saving', function () {
    $class = new ReflectionClass(PaymentSettingType::class);
    $method = $class->getMethod('getValidationRules');
    $method->setAccessible(true);
    $rules = $method->invoke(null);

    expect($rules)->toHaveKey('xenditSecretKey')
        ->and($rules)->toHaveKey('xenditWebhookVerificationToken')
        ->and($rules)->toHaveKey('xenditCurrencyCode');
});

it('creates an order checkout invoice from xendit', function () {
    Http::fake([
        'https://api.xendit.co/v2/invoices' => Http::response([
            'id' => 'inv_test_1',
            'external_id' => 'ORDER-1-123456789',
            'amount' => 1000,
            'currency' => 'IDR',
            'invoice_url' => 'https://checkout.xendit.co/web/inv_test_1',
            'status' => 'PENDING',
        ], 200),
    ]);

    $user = User::factory()->create();
    $order = createXenditTestOrder($user);

    $controller = app(XenditController::class);
    $response = $controller->createOrder([
        'amount' => 1000,
        'order_id' => $order->id,
        'redirect_url' => 'https://mysite.com/callback',
    ]);

    expect($response['success'])->toBeTrue()
        ->and($response['data']['link'])->toBe('https://checkout.xendit.co/web/inv_test_1');

    $this->assertDatabaseHas('order_payment_transactions', [
        'order_id' => $order->id,
        'transaction_id' => 'inv_test_1',
        'payment_method' => 'xenditPayment',
        'payment_status' => (string) PaymentStatusEnum::PENDING(),
    ]);
});

it('keeps the xendit pay button redirect active for an existing pending subscription invoice', function () {
    Http::fake([
        'https://api.xendit.co/v2/invoices/inv_sub_pending' => Http::response([
            'id' => 'inv_sub_pending',
            'external_id' => 'SUBSCRIPTION-1-123456789',
            'amount' => 1000,
            'currency' => 'IDR',
            'invoice_url' => 'https://checkout.xendit.co/web/inv_sub_pending',
            'status' => 'PENDING',
        ], 200),
    ]);

    $seller = Seller::factory()->create();
    $plan = SubscriptionPlan::create([
        'name' => 'Growth',
        'price' => 1000,
        'duration_type' => 'days',
        'duration_days' => 30,
        'status' => true,
    ]);
    $subscription = SellerSubscription::create([
        'seller_id' => $seller->id,
        'plan_id' => $plan->id,
        'status' => 'pending',
        'start_date' => now(),
        'price_paid' => 1000,
    ]);
    $transaction = SubscriptionTransaction::create([
        'seller_id' => $seller->id,
        'seller_subscription_id' => $subscription->id,
        'plan_id' => $plan->id,
        'payment_gateway' => PaymentTypeEnum::XENDIT(),
        'transaction_id' => 'inv_sub_pending',
        'amount' => 1000,
        'status' => (string) SubscriptionTransactionStatusEnum::PENDING(),
    ]);

    $response = $this->get(route('subscription-payment.xendit', ['transaction' => $transaction->uuid]));

    $response->assertOk()
        ->assertViewHas('authorizationUrl', 'https://checkout.xendit.co/web/inv_sub_pending')
        ->assertSee('Pay with Xendit');
});

it('keeps the xendit pay button redirect active for an existing pending wallet invoice', function () {
    Http::fake([
        'https://api.xendit.co/v2/invoices/inv_wallet_pending' => Http::response([
            'id' => 'inv_wallet_pending',
            'external_id' => 'WALLET-1-123456789',
            'amount' => 1000,
            'currency' => 'IDR',
            'invoice_url' => 'https://checkout.xendit.co/web/inv_wallet_pending',
            'status' => 'PENDING',
        ], 200),
    ]);

    $user = User::factory()->create();
    $wallet = Wallet::factory()->create([
        'user_id' => $user->id,
        'currency_code' => 'IDR',
    ]);
    $transaction = WalletTransaction::create([
        'wallet_id' => $wallet->id,
        'user_id' => $user->id,
        'transaction_type' => 'deposit',
        'payment_method' => PaymentTypeEnum::XENDIT(),
        'amount' => 1000,
        'currency_code' => 'IDR',
        'status' => (string) WalletTransactionStatusEnum::PENDING(),
        'transaction_reference' => 'inv_wallet_pending',
        'description' => 'Wallet Top-up',
    ]);

    $response = $this->get(route('wallet-payment.xendit', ['transaction' => $transaction->id]));

    $response->assertOk()
        ->assertViewHas('authorizationUrl', 'https://checkout.xendit.co/web/inv_wallet_pending')
        ->assertSee('Pay with Xendit');
});

it('verifies payment and captures order upon receiving paid webhook', function () {
    $user = User::factory()->create();
    $order = createXenditTestOrder($user);

    $txn = OrderPaymentTransaction::create([
        'uuid' => (string) Str::uuid(),
        'order_id' => $order->id,
        'user_id' => $user->id,
        'transaction_id' => 'inv_test_1',
        'amount' => 1000,
        'currency' => 'IDR',
        'payment_method' => 'xenditPayment',
        'payment_status' => (string) PaymentStatusEnum::PENDING(),
        'payment_details' => ['xendit_external_id' => 'ORDER-1-123456789'],
    ]);

    Http::fake([
        'https://api.xendit.co/v2/invoices/inv_test_1' => Http::response([
            'id' => 'inv_test_1',
            'external_id' => 'ORDER-1-123456789',
            'amount' => 1000,
            'currency' => 'IDR',
            'status' => 'PAID',
            'payment_id' => 'pm_test_123',
        ], 200),
    ]);

    $response = $this->withHeaders([
        'x-callback-token' => 'token_123',
    ])->postJson(route('xendit.webhook'), [
        'id' => 'inv_test_1',
        'external_id' => 'ORDER-1-123456789',
        'status' => 'PAID',
        'amount' => 1000,
    ]);

    $response->assertStatus(200);

    $this->assertDatabaseHas('order_payment_transactions', [
        'id' => $txn->id,
        'payment_status' => (string) PaymentStatusEnum::COMPLETED(),
    ]);

    $order->refresh();
    expect((string) $order->payment_status)->toBe((string) PaymentStatusEnum::COMPLETED());
});

it('rejects unauthorized callback token webhook requests', function () {
    $response = $this->withHeaders([
        'x-callback-token' => 'wrong_token',
    ])->postJson(route('xendit.webhook'), [
        'id' => 'inv_test_1',
        'external_id' => 'ORDER-1-123456789',
        'status' => 'PAID',
    ]);

    $response->assertStatus(401);
});

it('provides idempotent duplicate protection for webhook callback', function () {
    $user = User::factory()->create();
    $order = createXenditTestOrder($user, ['payment_status' => 'completed']);

    $txn = OrderPaymentTransaction::create([
        'uuid' => (string) Str::uuid(),
        'order_id' => $order->id,
        'user_id' => $user->id,
        'transaction_id' => 'inv_test_1',
        'amount' => 1000,
        'currency' => 'IDR',
        'payment_method' => 'xenditPayment',
        'payment_status' => (string) PaymentStatusEnum::COMPLETED(),
        'payment_details' => ['xendit_external_id' => 'ORDER-1-123456789', 'xendit_payment_id' => 'pm_test_123'],
    ]);

    Http::fake([
        'https://api.xendit.co/v2/invoices/inv_test_1' => Http::response([
            'id' => 'inv_test_1',
            'external_id' => 'ORDER-1-123456789',
            'amount' => 1000,
            'currency' => 'IDR',
            'status' => 'PAID',
            'payment_id' => 'pm_test_123',
        ], 200),
    ]);

    // Send callback again
    $response = $this->withHeaders([
        'x-callback-token' => 'token_123',
    ])->postJson(route('xendit.webhook'), [
        'id' => 'inv_test_1',
        'external_id' => 'ORDER-1-123456789',
        'status' => 'PAID',
        'amount' => 1000,
    ]);

    $response->assertStatus(200);
});

it('triggers refund request against Xendit API', function () {
    $user = User::factory()->create();
    $order = createXenditTestOrder($user, ['payment_status' => 'completed']);

    $txn = OrderPaymentTransaction::create([
        'uuid' => (string) Str::uuid(),
        'order_id' => $order->id,
        'user_id' => $user->id,
        'transaction_id' => 'inv_test_1',
        'amount' => 1000,
        'currency' => 'IDR',
        'payment_method' => 'xenditPayment',
        'payment_status' => (string) PaymentStatusEnum::COMPLETED(),
        'payment_details' => ['xendit_external_id' => 'ORDER-1-123456789', 'xendit_payment_id' => 'pm_test_123'],
    ]);

    Http::fake([
        'https://api.xendit.co/refunds' => Http::response([
            'id' => 'ref_1',
            'payment_id' => 'pm_test_123',
            'amount' => 1000,
            'status' => 'SUCCEEDED',
        ], 200),
    ]);

    $controller = app(XenditController::class);
    $result = $controller->refundPayment('inv_test_1');

    expect($result['success'])->toBeTrue()
        ->and($result['data']['status'])->toBe('SUCCEEDED');

    $txn->refresh();
    expect((string) $txn->payment_status)->toBe((string) PaymentStatusEnum::REFUNDED());
});

it('probes and verifies Xendit credentials connection successfully', function () {
    Http::fake([
        'https://api.xendit.co/balance' => Http::response([
            'balance' => 15000000,
        ], 200),
    ]);

    $service = app(SettingTestService::class);
    $result = $service->testPaymentGateway('xendit', [
        'secret_key' => 'xnd_test_123',
    ]);

    expect($result['success'])->toBeTrue()
        ->and($result['gateway_status'])->toBe(200);
});
