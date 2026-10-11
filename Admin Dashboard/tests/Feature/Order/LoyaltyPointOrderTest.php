<?php

use App\Enums\LoyaltyPoint\LoyaltyPointTransactionTypeEnum;
use App\Enums\Payment\PaymentTypeEnum;
use App\Enums\Product\ProductStatusEnum;
use App\Enums\SettingTypeEnum;
use App\Http\Middleware\VerifyLicense;
use App\Models\Address;
use App\Models\Cart;
use App\Models\CartItem;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Setting;
use App\Models\Store;
use App\Models\StoreProductVariant;
use App\Models\User;
use App\Services\LoyaltyPointService;
use App\Services\SettingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->withoutMiddleware([VerifyLicense::class]);

    $this->artisan('db:seed', ['--class' => 'CountriesSeeder']);

    // Enable loyalty point program with 10% max redemption cap, 100 point rate, min order 200
    Setting::updateOrCreate(
        ['variable' => SettingTypeEnum::LOYALTY_POINT()],
        [
            'value' => json_encode([
                'is_enabled' => true,
                'point_rate' => 100.0,
                'redemption_value' => 1.0,
                'max_redemption_percentage' => 10.0,
                'min_order_value_to_redeem' => 200.0,
            ]),
        ]
    );

    Setting::updateOrCreate(
        ['variable' => SettingTypeEnum::PAYMENT()],
        [
            'value' => json_encode([
                'cod' => true,
                'wallet' => true,
                'walletPayment' => true,
            ]),
        ]
    );

    Setting::updateOrCreate(
        ['variable' => SettingTypeEnum::SYSTEM()],
        [
            'value' => json_encode([
                'currency' => 'USD',
                'minimumCartAmount' => 1,
                'checkoutType' => 'multi_store',
            ]),
        ]
    );

    Cache::flush();
    $settingService = app(SettingService::class);
    foreach (SettingTypeEnum::values() as $v) {
        $settingService->clearSettingCache($v);
    }

    $this->user = User::factory()->create();
    $this->store = Store::factory()->create(['status' => 'online', 'verification_status' => 'approved', 'allows_pickup' => true]);
    $this->product = Product::factory()->create(['status' => ProductStatusEnum::ACTIVE()]);
    $this->variant = ProductVariant::create([
        'uuid' => (string) \Illuminate\Support\Str::uuid(),
        'product_id' => $this->product->id,
        'title' => 'Default Variant',
        'slug' => 'default-variant-'.time(),
        'price' => 500.00,
        'availability' => 'in_stock',
        'barcode' => 'BC-'.rand(1000, 9999),
        'visibility' => 'visible',
        'is_default' => true,
    ]);

    $this->spv = StoreProductVariant::create([
        'store_id' => $this->store->id,
        'product_variant_id' => $this->variant->id,
        'sku' => 'SKU-'.rand(1000, 9999),
        'cost' => 300.00,
        'price' => 500.00,
        'special_price' => 500.00,
        'stock' => 50,
    ]);

    // Give user 100 loyalty points balance
    app(LoyaltyPointService::class)->adjustBalance(
        userId: $this->user->id,
        points: 100,
        type: LoyaltyPointTransactionTypeEnum::EARNED(),
        description: 'Test balance'
    );
});

it('calculates loyalty summary in cart response', function () {
    Sanctum::actingAs($this->user);

    $cart = Cart::create([
        'user_id' => $this->user->id,
        'uuid' => (string) \Illuminate\Support\Str::uuid(),
        'use_loyalty_points' => true,
    ]);

    CartItem::create([
        'cart_id' => $cart->id,
        'store_id' => $this->store->id,
        'product_id' => $this->product->id,
        'product_variant_id' => $this->variant->id,
        'quantity' => 1,
        'price' => 500.00,
    ]);

    $response = $this->getJson('/api/user/cart?use_loyalty_points=1');

    $response->assertOk()
        ->assertJsonPath('data.payment_summary.loyalty_summary.is_loyalty_enabled', true)
        ->assertJsonPath('data.payment_summary.loyalty_summary.user_points_balance', 100)
        ->assertJsonPath('data.payment_summary.loyalty_summary.is_eligible_for_redemption', true)
        // 10% of 500 = 50 max points redeemable
        ->assertJsonPath('data.payment_summary.loyalty_summary.max_points_redeemable', 50)
        ->assertJsonPath('data.payment_summary.loyalty_summary.loyalty_points_redeemed', 50)
        ->assertJsonPath('data.payment_summary.loyalty_summary.loyalty_points_discount_amount', 50)
        // Potential points to earn on net 450 = floor(450 / 100) = 4 points
        ->assertJsonPath('data.payment_summary.loyalty_summary.potential_points_to_earn', 4);
});

it('prevents point redemption when cart subtotal is below minimum order value threshold', function () {
    Sanctum::actingAs($this->user);

    // Cheap item ₹100 < min ₹200
    $cheapVariant = ProductVariant::create([
        'uuid' => (string) \Illuminate\Support\Str::uuid(),
        'product_id' => $this->product->id,
        'title' => 'Cheap Variant',
        'slug' => 'cheap-variant-'.time(),
        'price' => 100.00,
        'availability' => 'in_stock',
        'barcode' => 'BC-CHEAP-'.rand(1000, 9999),
        'visibility' => 'visible',
        'is_default' => false,
    ]);

    StoreProductVariant::create([
        'store_id' => $this->store->id,
        'product_variant_id' => $cheapVariant->id,
        'sku' => 'SKU-CHEAP-'.rand(1000, 9999),
        'cost' => 50.00,
        'price' => 100.00,
        'special_price' => 100.00,
        'stock' => 50,
    ]);

    $cart = Cart::create([
        'user_id' => $this->user->id,
        'uuid' => (string) \Illuminate\Support\Str::uuid(),
        'use_loyalty_points' => true,
    ]);

    CartItem::create([
        'cart_id' => $cart->id,
        'store_id' => $this->store->id,
        'product_id' => $this->product->id,
        'product_variant_id' => $cheapVariant->id,
        'quantity' => 1,
        'price' => 100.00,
    ]);

    $response = $this->getJson('/api/user/cart?use_loyalty_points=1');

    $response->assertOk()
        ->assertJsonPath('data.payment_summary.loyalty_summary.is_eligible_for_redemption', false)
        ->assertJsonPath('data.payment_summary.loyalty_summary.loyalty_points_redeemed', 0)
        ->assertJsonPath('data.payment_summary.loyalty_summary.loyalty_points_discount_amount', 0);
});

it('deducts points from user balance and logs order loyalty fields upon placement', function () {
    Sanctum::actingAs($this->user);

    $cart = Cart::create([
        'user_id' => $this->user->id,
        'uuid' => (string) \Illuminate\Support\Str::uuid(),
        'use_loyalty_points' => true,
    ]);

    CartItem::create([
        'cart_id' => $cart->id,
        'store_id' => $this->store->id,
        'product_id' => $this->product->id,
        'product_variant_id' => $this->variant->id,
        'quantity' => 1,
        'price' => 500.00,
    ]);

    $address = Address::create([
        'user_id' => $this->user->id,
        'title' => 'Home',
        'mobile' => '1234567890',
        'address_line1' => '123 Main St',
        'city' => 'City',
        'state' => 'State',
        'country' => 'Country',
        'country_code' => 'US',
        'zipcode' => '12345',
        'latitude' => 12.9716,
        'longitude' => 77.5946,
        'address_type' => 'home',
    ]);

    $zone = \App\Models\DeliveryZone::factory()->create([
        'center_latitude' => 12.9716,
        'center_longitude' => 77.5946,
        'radius_km' => 100.0,
        'boundary_json' => null,
        'status' => \App\Enums\ActiveInactiveStatusEnum::ACTIVE(),
    ]);

    DB::table('store_zone')->insert([
        'store_id' => $this->store->id,
        'zone_id' => $zone->id,
    ]);

    $orderService = app(\App\Services\OrderService::class);
    $result = $orderService->createOrder($this->user, [
        'payment_type' => PaymentTypeEnum::COD(),
        'delivery_type' => 'delivery',
        'address_id' => $address->id,
        'rush_delivery' => false,
        'use_loyalty_points' => true,
        'minimumCartAmount' => 0,
    ]);

    expect($result['success'])->toBeTrue();
    $order = $result['data'];

    expect($order->loyalty_points_redeemed)->toBe(50)
        ->and((float) $order->loyalty_points_discount_amount)->toBe(50.0)
        ->and($order->loyalty_points_earned)->toBe(4);

    // User balance had 100, redeemed 50 -> remaining balance is 50
    expect($this->user->fresh()->loyalty_points_balance)->toBe(50);
});

it('calculates custom redemption_value rate (e.g. 50% / 0.50 value per point)', function () {
    Sanctum::actingAs($this->user);

    // Set redemption value to 0.50 (50% value)
    Setting::updateOrCreate(
        ['variable' => SettingTypeEnum::LOYALTY_POINT()],
        [
            'value' => json_encode([
                'is_enabled' => true,
                'point_rate' => 100.0,
                'redemption_value' => 0.50,
                'max_redemption_percentage' => 10.0,
                'min_order_value_to_redeem' => 200.0,
            ]),
        ]
    );

    app(SettingService::class)->clearSettingCache(SettingTypeEnum::LOYALTY_POINT());

    $cart = Cart::create([
        'user_id' => $this->user->id,
        'uuid' => (string) \Illuminate\Support\Str::uuid(),
        'use_loyalty_points' => true,
    ]);

    CartItem::create([
        'cart_id' => $cart->id,
        'store_id' => $this->store->id,
        'product_id' => $this->product->id,
        'product_variant_id' => $this->variant->id,
        'quantity' => 1,
        'price' => 500.00,
    ]);

    $response = $this->getJson('/api/user/cart?use_loyalty_points=1');

    $response->assertOk()
        // 10% of 500 = 50 max discount allowed. With 0.50 per point, 100 points redeemed = 50 discount
        ->assertJsonPath('data.payment_summary.loyalty_summary.max_points_redeemable', 100)
        ->assertJsonPath('data.payment_summary.loyalty_summary.loyalty_points_redeemed', 100)
        ->assertJsonPath('data.payment_summary.loyalty_summary.loyalty_points_discount_amount', 50);
});
