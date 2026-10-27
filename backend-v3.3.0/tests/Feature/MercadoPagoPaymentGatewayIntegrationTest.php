<?php

use App\Enums\Payment\PaymentTypeEnum;
use App\Enums\PaymentStatusEnum;
use App\Enums\SettingTypeEnum;
use App\Http\Controllers\Payments\MercadoPagoController;
use App\Http\Middleware\CheckInstallation;
use App\Http\Middleware\VerifyLicense;
use App\Models\DeliveryZone;
use App\Models\Order;
use App\Models\OrderPaymentTransaction;
use App\Models\Setting;
use App\Models\User;
use App\Services\SettingTestService;
use App\Types\Settings\PaymentSettingType;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request as HttpRequest;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->withoutMiddleware([VerifyLicense::class, CheckInstallation::class]);

    Setting::updateOrCreate(
        ['variable' => SettingTypeEnum::PAYMENT()],
        [
            'value' => json_encode([
                'mercadoPagoPayment' => '1',
                'mercadoPagoPaymentMode' => 'test',
                'mercadoPagoPublicKey' => 'TEST-public-key',
                'mercadoPagoAccessToken' => 'TEST-access-token',
                'mercadoPagoWebhookSecret' => 'mp-webhook-secret',
                'mercadoPagoCurrencyCode' => 'ARS',
            ]),
            'status' => 1,
        ]
    );
});

function createMercadoPagoTestOrder(User $user, array $attributes = []): Order
{
    $zone = DeliveryZone::first() ?? DeliveryZone::factory()->create();

    return Order::create(array_merge([
        'user_id' => $user->id,
        'delivery_zone_id' => $zone->id,
        'uuid' => (string) Str::uuid(),
        'slug' => 'order-'.Str::random(8),
        'email' => 'customer@example.com',
        'ip_address' => '127.0.0.1',
        'currency_code' => 'ARS',
        'currency_rate' => 1,
        'payment_method' => PaymentTypeEnum::MERCADO_PAGO(),
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

it('validates mercado pago credentials during setting saving', function () {
    $class = new ReflectionClass(PaymentSettingType::class);
    $method = $class->getMethod('getValidationRules');
    $method->setAccessible(true);
    $rules = $method->invoke(null);

    expect($rules)->toHaveKey('mercadoPagoPublicKey')
        ->and($rules)->toHaveKey('mercadoPagoAccessToken')
        ->and($rules)->toHaveKey('mercadoPagoWebhookSecret')
        ->and($rules)->toHaveKey('mercadoPagoCurrencyCode');
});

it('creates an order checkout preference from mercado pago', function () {
    Http::fake([
        'https://api.mercadopago.com/checkout/preferences' => Http::response([
            'id' => 'pref_test_1',
            'init_point' => 'https://www.mercadopago.com/checkout/v1/redirect?pref_id=pref_test_1',
            'sandbox_init_point' => 'https://sandbox.mercadopago.com/checkout/v1/redirect?pref_id=pref_test_1',
        ], 201),
    ]);

    $user = User::factory()->create();
    $order = createMercadoPagoTestOrder($user);

    $controller = app(MercadoPagoController::class);
    $response = $controller->createOrder([
        'amount' => 1000,
        'order_id' => $order->id,
        'redirect_url' => 'https://mysite.test/callback',
    ]);

    expect($response['success'])->toBeTrue()
        ->and($response['data']['link'])->toContain('sandbox.mercadopago.com');

    Http::assertSent(function (HttpRequest $request) {
        $payload = $request->data();

        return $request->url() === 'https://api.mercadopago.com/checkout/preferences'
            && isset($payload['back_urls']['success'])
            && str_ends_with($payload['payer']['email'] ?? '', '@testuser.com')
            && ! array_key_exists('notification_url', $payload)
            && ! array_key_exists('auto_return', $payload);
    });

    $this->assertDatabaseHas('order_payment_transactions', [
        'order_id' => $order->id,
        'transaction_id' => 'pref_test_1',
        'payment_method' => PaymentTypeEnum::MERCADO_PAGO(),
        'payment_status' => (string) PaymentStatusEnum::PENDING(),
    ]);
});

it('sends mercado pago notification url only for public callback hosts', function () {
    $originalRootUrl = config('app.url');
    URL::forceRootUrl('https://merchant.example.com');

    Http::fake([
        'https://api.mercadopago.com/checkout/preferences' => Http::response([
            'id' => 'pref_test_1',
            'sandbox_init_point' => 'https://sandbox.mercadopago.com/checkout/v1/redirect?pref_id=pref_test_1',
        ], 201),
    ]);

    $user = User::factory()->create();
    $order = createMercadoPagoTestOrder($user);

    app(MercadoPagoController::class)->createOrder([
        'amount' => 1000,
        'order_id' => $order->id,
        'redirect_url' => 'https://merchant.example.com/order-return',
    ]);

    Http::assertSent(function (HttpRequest $request) {
        $payload = $request->data();
        $notificationUrl = $payload['notification_url'] ?? null;

        return is_string($notificationUrl)
            && filter_var($notificationUrl, FILTER_VALIDATE_URL)
            && str_contains($notificationUrl, 'mercadopago/webhook');
    });

    URL::forceRootUrl($originalRootUrl);
});

it('verifies payment and captures order from mercado pago webhook', function () {
    $user = User::factory()->create();
    $order = createMercadoPagoTestOrder($user);

    $transaction = OrderPaymentTransaction::create([
        'uuid' => (string) Str::uuid(),
        'order_id' => $order->id,
        'user_id' => $user->id,
        'transaction_id' => 'pref_test_1',
        'amount' => 1000,
        'currency' => 'ARS',
        'payment_method' => PaymentTypeEnum::MERCADO_PAGO(),
        'payment_status' => (string) PaymentStatusEnum::PENDING(),
        'payment_details' => ['mercado_pago_external_reference' => 'ORDER-'.$order->id.'-123456789'],
    ]);

    Http::fake([
        'https://api.mercadopago.com/v1/payments/pay_test_1' => Http::response([
            'id' => 123456789,
            'status' => 'approved',
            'external_reference' => 'ORDER-'.$order->id.'-123456789',
            'transaction_amount' => 1000,
            'currency_id' => 'ARS',
        ], 200),
    ]);

    $timestamp = '1700000000';
    $requestId = 'request-test-1';
    $signature = hash_hmac('sha256', 'id:pay_test_1;request-id:'.$requestId.';ts:'.$timestamp.';', 'mp-webhook-secret');

    $response = $this->withHeaders([
        'x-request-id' => $requestId,
        'x-signature' => 'ts='.$timestamp.',v1='.$signature,
    ])->postJson(route('mercadopago.webhook'), [
        'data' => ['id' => 'pay_test_1'],
    ]);

    $response->assertStatus(200);

    $this->assertDatabaseHas('order_payment_transactions', [
        'id' => $transaction->id,
        'payment_status' => (string) PaymentStatusEnum::COMPLETED(),
    ]);

    $order->refresh();
    expect((string) $order->payment_status)->toBe((string) PaymentStatusEnum::COMPLETED());
});

it('rejects mercado pago webhook requests with an invalid signature', function () {
    $response = $this->withHeaders([
        'x-request-id' => 'request-test-1',
        'x-signature' => 'ts=1700000000,v1=invalid',
    ])->postJson(route('mercadopago.webhook'), [
        'data' => ['id' => 'pay_test_1'],
    ]);

    $response->assertStatus(401);
});

it('probes mercado pago credentials successfully', function () {
    Http::fake([
        'https://api.mercadopago.com/users/me' => Http::response([
            'id' => 123456,
            'nickname' => 'TESTSELLER',
        ], 200),
    ]);

    $service = app(SettingTestService::class);
    $result = $service->testPaymentGateway('mercadopago', [
        'secret_key' => 'TEST-access-token',
    ]);

    expect($result['success'])->toBeTrue()
        ->and($result['gateway_status'])->toBe(200);
});
