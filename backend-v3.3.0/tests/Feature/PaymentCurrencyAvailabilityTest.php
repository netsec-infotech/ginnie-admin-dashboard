<?php

use App\Enums\Payment\PaymentTypeEnum;
use App\Enums\DefaultSystemRolesEnum;
use App\Enums\GuardNameEnum;
use App\Enums\SettingTypeEnum;
use App\Http\Resources\Setting\PaymentSettingResource;
use App\Models\DeliveryZone;
use App\Models\Order;
use App\Models\Seller;
use App\Models\Setting;
use App\Models\Store;
use App\Models\User;
use App\Services\CodPaymentService;
use App\Services\PaymentCurrencyEnforcementService;
use App\Services\PaymentCurrencyAvailabilityService;
use App\Services\PosPaymentSessionService;
use App\Services\SettingService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

beforeEach(function () {
    app(SettingService::class)->clearSettingCache(SettingTypeEnum::PAYMENT());
});

function phase13Admin(): User
{
    $admin = User::factory()->create(['access_panel' => GuardNameEnum::ADMIN->value]);
    Role::findOrCreate(DefaultSystemRolesEnum::SUPER_ADMIN->value, GuardNameEnum::ADMIN->value);
    $admin->assignRole(DefaultSystemRolesEnum::SUPER_ADMIN->value);

    return $admin;
}

function phase12PaymentSetting(array $overrides = []): void
{
    Setting::query()->updateOrCreate(
        ['variable' => SettingTypeEnum::PAYMENT()],
        ['value' => json_encode(array_merge([
            'stripePayment' => false,
            'stripeCurrencyCode' => '',
            'razorpayPayment' => false,
            'razorpayInternationalPayments' => false,
            'razorpayAllowedCurrencyCodes' => [],
            'paystackPayment' => false,
            'paystackCurrencyCode' => '',
            'flutterwavePayment' => false,
            'flutterwaveCurrencyCode' => '',
            'xenditPayment' => false,
            'xenditCurrencyCode' => '',
            'mercadoPagoPayment' => false,
            'mercadoPagoCurrencyCode' => '',
            'walletPayment' => false,
            'cod' => false,
        ], $overrides))]
    );

    app(SettingService::class)->clearSettingCache(SettingTypeEnum::PAYMENT());
}

function phase14Order(array $attributes = []): Order
{
    $user = $attributes['user'] ?? User::factory()->create();
    unset($attributes['user']);

    return Order::create(array_merge([
        'uuid' => (string) Str::uuid(),
        'user_id' => $user->id,
        'slug' => 'phase14-order-'.Str::random(10),
        'email' => 'phase14@example.test',
        'ip_address' => '127.0.0.1',
        'currency_code' => 'AED',
        'currency_rate' => 1,
        'payment_method' => 'cod',
        'payment_status' => 'pending',
        'delivery_zone_id' => DeliveryZone::factory()->create()->id,
        'wallet_balance' => 0,
        'subtotal' => 100,
        'total_payable' => 100,
        'final_total' => 100,
        'billing_name' => 'Phase 14 Customer',
        'billing_address_1' => 'Hidden',
        'billing_landmark' => 'Hidden',
        'billing_zip' => '00000',
        'billing_phone' => '0000000000',
        'billing_address_type' => 'home',
        'billing_latitude' => 1,
        'billing_longitude' => 1,
        'billing_city' => 'Hidden',
        'billing_state' => 'Hidden',
        'billing_country' => 'Hidden',
        'billing_country_code' => 'AE',
        'shipping_name' => 'Phase 14 Customer',
        'shipping_address_1' => 'Hidden',
        'shipping_landmark' => 'Hidden',
        'shipping_zip' => '00000',
        'shipping_phone' => '0000000000',
        'shipping_address_type' => 'home',
        'shipping_latitude' => 1,
        'shipping_longitude' => 1,
        'shipping_city' => 'Hidden',
        'shipping_state' => 'Hidden',
        'shipping_country' => 'Hidden',
        'shipping_country_code' => 'AE',
    ], $attributes));
}

it('keeps legacy payment setting fields and adds safe gateway currency metadata', function () {
    phase12PaymentSetting([
        'stripePayment' => true,
        'stripePublishableKey' => 'pk_test_safe',
        'stripeCurrencyCode' => 'USD',
        'razorpayPayment' => true,
        'razorpayKeyId' => 'rzp_test_safe',
        'razorpayInternationalPayments' => true,
        'razorpayAllowedCurrencyCodes' => ['USD', 'AED'],
    ]);

    $setting = Setting::where('variable', SettingTypeEnum::PAYMENT())->first();
    $value = (new PaymentSettingResource($setting))->toArray(request())['value'];

    expect($value)
        ->toHaveKey('stripePayment', true)
        ->toHaveKey('razorpayPayment', true)
        ->toHaveKey('enabled_payment_gateways')
        ->toHaveKey('payment_gateway_currency_capabilities');

    $razorpay = collect($value['payment_gateway_currency_capabilities'])
        ->firstWhere('key', PaymentTypeEnum::RAZORPAY());

    expect($razorpay['allowed_currencies'])->toContain('INR', 'USD', 'AED')
        ->and($razorpay)->not->toHaveKey('razorpaySecretKey');
});

it('allows razorpay INR by default but blocks non INR without approved allowed currencies', function () {
    phase12PaymentSetting([
        'razorpayPayment' => true,
    ]);

    $service = app(PaymentCurrencyAvailabilityService::class);

    expect($service->availabilityForMethod(PaymentTypeEnum::RAZORPAY(), 'INR')['is_available'])->toBeTrue()
        ->and($service->availabilityForMethod(PaymentTypeEnum::RAZORPAY(), 'AED')['is_available'])->toBeFalse()
        ->and($service->availabilityForMethod(PaymentTypeEnum::RAZORPAY(), 'AED')['reason_code'])->toBe('currency_not_supported');
});

it('allows razorpay non INR only when international payments and that currency are configured', function () {
    phase12PaymentSetting([
        'razorpayPayment' => true,
        'razorpayInternationalPayments' => true,
        'razorpayAllowedCurrencyCodes' => ['AED'],
    ]);

    $service = app(PaymentCurrencyAvailabilityService::class);

    expect($service->availabilityForMethod('razorpay', 'AED')['is_available'])->toBeTrue()
        ->and($service->availabilityForMethod('razorpay', 'USD')['is_available'])->toBeFalse();
});

it('requires configured gateway currency to match the current currency', function () {
    phase12PaymentSetting([
        'paystackPayment' => true,
        'paystackCurrencyCode' => 'USD',
    ]);

    $service = app(PaymentCurrencyAvailabilityService::class);

    expect($service->availabilityForMethod(PaymentTypeEnum::PAYSTACK(), 'USD')['is_available'])->toBeTrue()
        ->and($service->availabilityForMethod(PaymentTypeEnum::PAYSTACK(), 'AED')['is_available'])->toBeFalse()
        ->and($service->availabilityForMethod(PaymentTypeEnum::PAYSTACK(), 'AED')['reason_code'])->toBe('currency_not_supported');
});

it('reports wallet currency mismatch separately from gateway support', function () {
    phase12PaymentSetting([
        'walletPayment' => true,
    ]);

    $service = app(PaymentCurrencyAvailabilityService::class);

    $availability = $service->availabilityForMethod(PaymentTypeEnum::WALLET(), 'AED', 'USD');

    expect($availability['is_available'])->toBeFalse()
        ->and($availability['reason_code'])->toBe('wallet_currency_mismatch');
});

it('renders and normalizes razorpay currency controls in payment settings', function () {
    $this->withoutMiddleware();

    $page = $this->actingAs(phase13Admin(), GuardNameEnum::ADMIN->value)
        ->get(route('admin.settings.show', ['setting' => SettingTypeEnum::PAYMENT()]));

    $page->assertOk()
        ->assertSee('razorpayInternationalPayments')
        ->assertSee('razorpayAllowedCurrencyCodes')
        ->assertSee('INR is always available');

    $response = $this->actingAs(phase13Admin(), GuardNameEnum::ADMIN->value)
        ->postJson(route('admin.settings.store'), [
            'type' => SettingTypeEnum::PAYMENT(),
            'razorpayPayment' => true,
            'razorpayPaymentMode' => 'test',
            'razorpayKeyId' => 'rzp_test_key',
            'razorpaySecretKey' => 'rzp_test_secret',
            'razorpayWebhookSecret' => 'rzp_test_webhook',
            'razorpayInternationalPayments' => true,
            'razorpayAllowedCurrencyCodes' => 'aed, USD, aed, , in',
        ]);

    $response->assertOk()
        ->assertJson(['success' => true]);

    $value = Setting::where('variable', SettingTypeEnum::PAYMENT())->first()->value;

    expect($value['razorpayInternationalPayments'])->toBeTrue()
        ->and($value['razorpayAllowedCurrencyCodes'])->toBe(['AED', 'USD']);
});

it('enforces gateway availability before payment can start', function () {
    phase12PaymentSetting([
        'razorpayPayment' => true,
        'razorpayInternationalPayments' => false,
        'razorpayAllowedCurrencyCodes' => [],
    ]);

    app(PaymentCurrencyEnforcementService::class)->ensureAvailable(PaymentTypeEnum::RAZORPAY(), 'INR');

    app(PaymentCurrencyEnforcementService::class)->ensureAvailable(PaymentTypeEnum::RAZORPAY(), 'AED');
})->throws(RuntimeException::class, 'This payment method does not support the order currency. Please choose another method.');

it('detects verifiable gateway amount and currency mismatches', function () {
    $service = app(PaymentCurrencyEnforcementService::class);

    $service->ensureAmountMatches(100, 100.0, 'Test Gateway');
    $service->ensureCurrencyMatches('AED', 'aed', 'Test Gateway');

    $service->ensureAmountMatches(100, 99.98, 'Test Gateway');
})->throws(RuntimeException::class, 'Test Gateway amount mismatch.');

it('logs when gateway amount or currency cannot be verified', function () {
    Log::spy();

    $service = app(PaymentCurrencyEnforcementService::class);
    $service->ensureAmountMatches(25, null, 'Test Gateway');
    $service->ensureCurrencyMatches('USD', null, 'Test Gateway');

    Log::shouldHaveReceived('warning')->twice();
});

it('audits completed payment transactions with unsafe snapshot data', function () {
    Setting::query()->updateOrCreate(
        ['variable' => 'system'],
        ['value' => json_encode(['currency' => 'USD', 'currencySymbol' => '$', 'countryCode' => 'US'])]
    );

    $user = User::factory()->create();
    $order = phase14Order(['user' => $user]);

    DB::table('order_payment_transactions')->insert([
        'uuid' => (string) Str::uuid(),
        'order_id' => $order->id,
        'user_id' => $user->id,
        'transaction_id' => 'phase14-mismatch',
        'amount' => 100,
        'currency' => 'USD',
        'order_currency_code' => 'AED',
        'gateway_currency_code' => 'USD',
        'gateway_amount' => 90,
        'payment_method' => PaymentTypeEnum::STRIPE(),
        'payment_status' => 'completed',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    expect(Artisan::call('hyperlocal:currency-audit', ['--json' => true]))->toBe(0);

    $codes = collect(json_decode(Artisan::output(), true)['findings'] ?? [])->pluck('code');

    expect($codes)->toContain('payment_snapshot_currency_mismatch')
        ->and($codes)->toContain('gateway_amount_mismatch');
});

it('blocks COD online gateway initialization when the order currency is unsupported', function () {
    phase12PaymentSetting([
        'razorpayPayment' => true,
        'razorpayKeyId' => 'rzp_test_key',
        'razorpaySecretKey' => 'rzp_test_secret',
        'razorpayInternationalPayments' => false,
    ]);

    $order = phase14Order(['payment_method' => PaymentTypeEnum::COD(), 'currency_code' => 'AED']);

    app(CodPaymentService::class)->initGateway($order, 'razorpay');
})->throws(Exception::class, 'This payment method does not support the order currency. Please choose another method.');

it('blocks POS hosted gateway initialization when the session currency is unsupported', function () {
    phase12PaymentSetting([
        'razorpayPayment' => true,
        'razorpayKeyId' => 'rzp_test_key',
        'razorpaySecretKey' => 'rzp_test_secret',
        'razorpayInternationalPayments' => false,
    ]);

    $seller = Seller::factory()->create();
    $store = Store::factory()->create([
        'seller_id' => $seller->id,
        'currency_code' => 'AED',
    ]);

    $session = app(PosPaymentSessionService::class)->create($seller, $store, [
        'amount' => 25,
        'items' => [
            ['name' => 'Test item', 'quantity' => 1, 'price' => 25],
        ],
    ]);

    app(PosPaymentSessionService::class)->initGateway($session, 'razorpay');
})->throws(Exception::class, 'This payment method does not support the order currency. Please choose another method.');
