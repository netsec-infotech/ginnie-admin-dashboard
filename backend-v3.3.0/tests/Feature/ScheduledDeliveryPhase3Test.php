<?php

use App\Models\Order;
use App\Models\Store;
use App\Models\User;
use App\Models\Cart;
use App\Models\CartItem;
use App\Services\OrderService;
use App\Enums\GuardNameEnum;
use App\Enums\DefaultSystemRolesEnum;
use Spatie\Permission\Models\Role;
use Carbon\Carbon;
use Laravel\Sanctum\Sanctum;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->withoutMiddleware(\App\Http\Middleware\VerifyLicense::class);
    phase3_seedSettings();
});

function phase3_seedSettings(): void {
    \App\Models\Setting::updateOrCreate(
        ['variable' => \App\Enums\SettingTypeEnum::PAYMENT()],
        ['value' => json_encode([
            'cod' => true,
            'wallet' => true,
            'stripe' => false,
            'razorpay' => false,
            'paystack' => false,
            'flutterwave' => false,
        ])]
    );

    \App\Models\Setting::updateOrCreate(
        ['variable' => \App\Enums\SettingTypeEnum::SYSTEM()],
        ['value' => json_encode([
            'currency' => 'USD',
            'minimum_cart_amount' => 0.0,
            'systemVendorType' => 'multiple',
            'checkoutType' => 'single_store',
        ])]
    );

    app(\App\Services\SettingService::class)->clearSettingCache(\App\Enums\SettingTypeEnum::PAYMENT());
    app(\App\Services\SettingService::class)->clearSettingCache(\App\Enums\SettingTypeEnum::SYSTEM());
}

function phase3_createStoreWithTimings(): Store {
    $store = Store::factory()->create([
        'status' => \App\Enums\Store\StoreStatusEnum::ONLINE(),
        'is_scheduled_delivery_enabled' => true,
        'scheduled_delivery_buffer_minutes' => 60,
        'max_advance_booking_days' => 7,
        'scheduled_delivery_slot_duration' => 60,
        'scheduled_delivery_slot_capacity' => 2,
        'reschedule_deadline_hours' => 2,
    ]);

    $store->update([
        'metadata' => [
            'operating_hours' => [
                'monday' => ['is_open' => true, 'open_time' => '09:00:00', 'close_time' => '12:00:00'],
                'tuesday' => ['is_open' => true, 'open_time' => '09:00:00', 'close_time' => '12:00:00'],
                'wednesday' => ['is_open' => true, 'open_time' => '09:00:00', 'close_time' => '12:00:00'],
                'thursday' => ['is_open' => true, 'open_time' => '09:00:00', 'close_time' => '12:00:00'],
                'friday' => ['is_open' => true, 'open_time' => '09:00:00', 'close_time' => '12:00:00'],
                'saturday' => ['is_open' => true, 'open_time' => '09:00:00', 'close_time' => '12:00:00'],
                'sunday' => ['is_open' => false, 'open_time' => '09:00:00', 'close_time' => '12:00:00'],
            ]
        ]
    ]);

    $sellerUser = $store->seller->user;
    $sellerUser->update(['access_panel' => GuardNameEnum::SELLER->value]);
    Role::findOrCreate(DefaultSystemRolesEnum::SELLER->value, GuardNameEnum::SELLER->value);
    $sellerUser->assignRole(DefaultSystemRolesEnum::SELLER->value);

    return $store;
}

function phase3_associateStoreWithDeliveryZone(Store $store): \App\Models\DeliveryZone {
    $zone = \App\Models\DeliveryZone::factory()->create([
        'status' => \App\Enums\ActiveInactiveStatusEnum::ACTIVE(),
        'boundary_json' => [
            'coordinates' => [
                [
                    ['lat' => 5.0, 'lng' => 15.0],
                    ['lat' => 15.0, 'lng' => 15.0],
                    ['lat' => 15.0, 'lng' => 25.0],
                    ['lat' => 5.0, 'lng' => 25.0],
                ],
            ],
        ],
    ]);

    DB::table('store_zone')->insert([
        'store_id' => $store->id,
        'zone_id' => $zone->id,
    ]);

    return $zone;
}

function phase3_createTestProductVariant(Store $store): array {
    $product = \App\Models\Product::factory()->create(['seller_id' => $store->seller_id]);
    $variantId = DB::table('product_variants')->insertGetId([
        'uuid' => fake()->uuid(),
        'product_id' => $product->id,
        'title' => 'Default Variant',
        'slug' => 'var-' . uniqid(),
        'weight' => 1.0,
        'height' => 1.0,
        'breadth' => 1.0,
        'length' => 1.0,
        'availability' => true,
        'provider' => 'self',
        'barcode' => '1234567890',
        'visibility' => 'published',
        'is_default' => true,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    DB::table('store_product_variants')->insert([
        'product_variant_id' => $variantId,
        'store_id' => $store->id,
        'sku' => 'SKU-' . uniqid(),
        'price' => 100.00,
        'special_price' => 90.00,
        'cost' => 50.00,
        'stock' => 10,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    return [$product->id, $variantId];
}

function phase3_createCartForUserAndStore(User $user, Store $store): Cart {
    Cart::where('user_id', $user->id)->delete();

    $cart = Cart::create(['user_id' => $user->id, 'uuid' => fake()->uuid()]);
    [$productId, $variantId] = phase3_createTestProductVariant($store);

    CartItem::create([
        'cart_id' => $cart->id,
        'product_id' => $productId,
        'product_variant_id' => $variantId,
        'quantity' => 1,
        'store_id' => $store->id,
    ]);

    return $cart;
}

function phase3_createTestOrderWithItem(User $user, Store $store, array $orderExtra = []): Order {
    $order = Order::create(array_merge([
        'uuid' => fake()->uuid(),
        'user_id' => $user->id,
        'slug' => 'ord-' . uniqid(),
        'email' => $user->email ?? 'test@example.com',
        'ip_address' => '127.0.0.1',
        'currency_code' => 'USD',
        'currency_rate' => 1.0,
        'payment_method' => 'prepaid',
        'payment_status' => 'completed',
        'status' => 'pending',
        'fulfillment_type' => 'hyperlocal',
        'delivery_zone_id' => \App\Models\DeliveryZone::factory()->create()->id,
        'wallet_balance' => 0.0,
        'subtotal' => 500.0,
        'total_payable' => 500.0,
        'final_total' => 500.0,
        'billing_name' => 'John Doe',
        'billing_address_1' => '123 Test St',
        'billing_zip' => '10001',
        'billing_phone' => '1234567890',
        'billing_address_type' => 'home',
        'billing_latitude' => 10.0,
        'billing_longitude' => 20.0,
        'billing_city' => 'Test City',
        'billing_state' => 'Test State',
        'billing_country' => 'Test Country',
        'billing_country_code' => 'USA',
        'shipping_name' => 'John Doe',
        'shipping_address_1' => '123 Test St',
        'shipping_zip' => '10001',
        'shipping_phone' => '1234567890',
        'shipping_address_type' => 'home',
        'shipping_latitude' => 10.0,
        'shipping_longitude' => 20.0,
        'shipping_city' => 'Test City',
        'shipping_state' => 'Test State',
        'shipping_country' => 'Test Country',
        'shipping_country_code' => 'USA',
        'total' => 500.0,
        'grand_total' => 500.0,
    ], $orderExtra));

    [$productId, $variantId] = phase3_createTestProductVariant($store);

    \App\Models\OrderItem::create([
        'order_id' => $order->id,
        'product_id' => $productId,
        'product_variant_id' => $variantId,
        'store_id' => $store->id,
        'title' => 'Test Product Item',
        'variant_title' => 'Default Variant',
        'sku' => 'SKU-' . uniqid(),
        'quantity' => 1,
        'price' => 500.0,
        'subtotal' => 500.0,
        'status' => 'pending',
        'is_refunded' => false,
    ]);

    return $order;
}

it('previews timing change impact count correctly', function () {
    $store = phase3_createStoreWithTimings();
    $sellerUser = $store->seller->user;
    
    $monday = Carbon::now()->next(Carbon::MONDAY)->format('Y-m-d');
    $tuesday = Carbon::now()->next(Carbon::TUESDAY)->format('Y-m-d');
    
    $user = User::factory()->create();

    // Order 1: Monday 09:00 - 10:00 (impacted if new hours start at 10:00)
    phase3_createTestOrderWithItem($user, $store, [
        'scheduled_date' => $monday,
        'scheduled_slot_start_time' => '09:00:00',
        'scheduled_slot_end_time' => '10:00:00',
    ]);
    sleep(1);

    // Order 2: Monday 10:00 - 11:00 (not impacted if new hours start at 10:00)
    phase3_createTestOrderWithItem($user, $store, [
        'scheduled_date' => $monday,
        'scheduled_slot_start_time' => '10:00:00',
        'scheduled_slot_end_time' => '11:00:00',
    ]);
    sleep(1);

    // Order 3: Tuesday 09:00 - 10:00 (impacted if Tuesday is closed)
    phase3_createTestOrderWithItem($user, $store, [
        'scheduled_date' => $tuesday,
        'scheduled_slot_start_time' => '09:00:00',
        'scheduled_slot_end_time' => '10:00:00',
    ]);

    $newOperatingHours = [
        'monday' => ['is_open' => true, 'open_time' => '10:00:00', 'close_time' => '12:00:00'],
        'tuesday' => ['is_open' => false, 'open_time' => '09:00:00', 'close_time' => '12:00:00'],
        'wednesday' => ['is_open' => true, 'open_time' => '09:00:00', 'close_time' => '12:00:00'],
        'thursday' => ['is_open' => true, 'open_time' => '09:00:00', 'close_time' => '12:00:00'],
        'friday' => ['is_open' => true, 'open_time' => '09:00:00', 'close_time' => '12:00:00'],
        'saturday' => ['is_open' => true, 'open_time' => '09:00:00', 'close_time' => '12:00:00'],
        'sunday' => ['is_open' => false, 'open_time' => '09:00:00', 'close_time' => '12:00:00'],
    ];

    Sanctum::actingAs($sellerUser);

    $response = $this->postJson("/api/seller/stores/{$store->id}/timing-preview", [
        'operating_hours' => $newOperatingHours,
    ]);

    $response->assertStatus(200)
        ->assertJson([
            'success' => true,
            'message' => 'Timing impact calculated successfully',
            'data' => [
                'impacted_orders_count' => 2, // Monday 09-10 and Tuesday 09-10 (since Tuesday is closed)
            ],
        ]);
});

it('applies timing updates and flags affected orders with deadlines', function () {
    $store = phase3_createStoreWithTimings();
    $sellerUser = $store->seller->user;
    
    $monday = Carbon::now()->next(Carbon::MONDAY)->format('Y-m-d');
    $user = User::factory()->create();

    // Create an order inside the timing change target window
    $order = phase3_createTestOrderWithItem($user, $store, [
        'scheduled_date' => $monday,
        'scheduled_slot_start_time' => '09:00:00',
        'scheduled_slot_end_time' => '10:00:00',
    ]);

    $newOperatingHours = [
        'monday' => ['is_open' => true, 'open_time' => '10:00:00', 'close_time' => '12:00:00'],
        'tuesday' => ['is_open' => true, 'open_time' => '09:00:00', 'close_time' => '12:00:00'],
        'wednesday' => ['is_open' => true, 'open_time' => '09:00:00', 'close_time' => '12:00:00'],
        'thursday' => ['is_open' => true, 'open_time' => '09:00:00', 'close_time' => '12:00:00'],
        'friday' => ['is_open' => true, 'open_time' => '09:00:00', 'close_time' => '12:00:00'],
        'saturday' => ['is_open' => true, 'open_time' => '09:00:00', 'close_time' => '12:00:00'],
        'sunday' => ['is_open' => false, 'open_time' => '09:00:00', 'close_time' => '12:00:00'],
    ];

    Sanctum::actingAs($sellerUser);

    $response = $this->postJson("/api/seller/stores/{$store->id}/timing", [
        'operating_hours' => $newOperatingHours,
    ]);

    $response->assertStatus(200);

    $order->refresh();

    expect($order->reschedule_status)->toBe('needs_reschedule')
        ->and($order->reschedule_reason)->toBe('Store timing changed')
        ->and($order->reschedule_deadline)->not->toBeNull();

    // Verify stored store timing metadata has updated
    $store->refresh();
    expect($store->metadata['operating_hours']['monday']['open_time'])->toBe('10:00:00');
});

it('toggles temporary store closure and invalidates all future scheduled orders', function () {
    $store = phase3_createStoreWithTimings();
    $sellerUser = $store->seller->user;
    
    $monday = Carbon::now()->next(Carbon::MONDAY)->format('Y-m-d');
    $user = User::factory()->create();

    $order = phase3_createTestOrderWithItem($user, $store, [
        'scheduled_date' => $monday,
        'scheduled_slot_start_time' => '10:00:00',
        'scheduled_slot_end_time' => '11:00:00',
    ]);

    Sanctum::actingAs($sellerUser);

    $response = $this->postJson("/api/seller/stores/{$store->id}/temporary-closure", [
        'is_temporarily_closed' => true,
        'temporary_closure_reason' => 'Renovation Works',
    ]);

    $response->assertStatus(200);

    $store->refresh();
    expect($store->is_temporarily_closed)->toBeTrue()
        ->and($store->temporary_closure_reason)->toBe('Renovation Works');

    $order->refresh();
    expect($order->reschedule_status)->toBe('needs_reschedule')
        ->and($order->reschedule_reason)->toBe('Renovation Works');
});

it('blocks checkout order placement when store is temporarily closed', function () {
    $store = phase3_createStoreWithTimings();
    $zone = phase3_associateStoreWithDeliveryZone($store);
    
    // Toggle closure on
    $store->is_temporarily_closed = true;
    $store->temporary_closure_reason = 'Holiday closure';
    $store->save();

    $monday = Carbon::now()->next(Carbon::MONDAY)->format('Y-m-d');
    $user = User::factory()->create();
    $cart = phase3_createCartForUserAndStore($user, $store);

    $address = \App\Models\Address::create([
        'user_id' => $user->id,
        'title' => 'Home',
        'address_line1' => 'Street 1',
        'address_line2' => 'Area 1',
        'zipcode' => '12345',
        'mobile' => '1234567890',
        'address_type' => 'home',
        'latitude' => 10.0,
        'longitude' => 20.0,
        'city' => 'Test City',
        'state' => 'Test State',
        'country' => 'Test Country',
        'country_code' => 'USA',
    ]);

    $checkoutData = [
        'payment_type' => 'cod',
        'delivery_type' => 'delivery',
        'address_id' => $address->id,
        'scheduled_date' => $monday,
        'scheduled_slot_start_time' => '09:00:00',
        'scheduled_slot_end_time' => '10:00:00',
        'minimumCartAmount' => 0.0,
        'minimum_cart_amount' => 0.0,
        'rush_delivery' => false,
    ];

    $orderService = app(OrderService::class);

    // Book order (should fail since store is closed)
    $res = $orderService->createOrder($user, $checkoutData);

    expect($res['success'])->toBeFalse()
        ->and($res['message'])->toContain('Holiday closure');
});
