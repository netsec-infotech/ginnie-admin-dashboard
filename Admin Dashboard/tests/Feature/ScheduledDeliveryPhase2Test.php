<?php

use App\Models\Order;
use App\Models\Store;
use App\Models\StoreSlotOverride;
use App\Models\User;
use App\Models\Cart;
use App\Models\CartItem;
use App\Services\DeliveryScheduleService;
use App\Services\OrderService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->withoutMiddleware(\App\Http\Middleware\VerifyLicense::class);
    phase2_seedSettings();
});

function phase2_seedSettings(): void {
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

function phase2_createStoreWithTimings(): Store {
    $store = Store::factory()->create([
        'status' => \App\Enums\Store\StoreStatusEnum::ONLINE(),
        'is_scheduled_delivery_enabled' => true,
        'scheduled_delivery_buffer_minutes' => 60,
        'max_advance_booking_days' => 7,
        'scheduled_delivery_slot_duration' => 60,
        'scheduled_delivery_slot_capacity' => 2,
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

    return $store;
}

function phase2_associateStoreWithDeliveryZone(Store $store): \App\Models\DeliveryZone {
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

function phase2_createTestProductVariant(Store $store): array {
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

function phase2_createCartForUserAndStore(User $user, Store $store): Cart {
    Cart::where('user_id', $user->id)->delete();

    $cart = Cart::create(['user_id' => $user->id, 'uuid' => fake()->uuid()]);
    [$productId, $variantId] = phase2_createTestProductVariant($store);

    CartItem::create([
        'cart_id' => $cart->id,
        'product_id' => $productId,
        'product_variant_id' => $variantId,
        'quantity' => 1,
        'store_id' => $store->id,
    ]);

    return $cart;
}

function phase2_createTestOrderWithItem(User $user, Store $store, array $orderExtra = []): Order {
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

    [$productId, $variantId] = phase2_createTestProductVariant($store);

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

it('generates dynamic slots based on store hours', function () {
    $store = phase2_createStoreWithTimings();
    
    // Set target date to a Monday
    $monday = Carbon::now()->next(Carbon::MONDAY)->format('Y-m-d');
    
    $service = app(DeliveryScheduleService::class);
    $result = $service->getAvailableSlotsForStore($store, $monday);
    
    expect($result['is_enabled'])->toBeTrue()
        ->and($result['slots'])->toHaveCount(3) // 09-10, 10-11, 11-12
        ->and($result['slots'][0]['start_time'])->toBe('09:00:00')
        ->and($result['slots'][0]['end_time'])->toBe('10:00:00')
        ->and($result['slots'][0]['remaining_capacity'])->toBe(2);
});

it('returns empty slots when store is closed on the target day', function () {
    $store = phase2_createStoreWithTimings();
    
    // Set target date to a Sunday (which is closed)
    $sunday = Carbon::now()->next(Carbon::SUNDAY)->format('Y-m-d');
    
    $service = app(DeliveryScheduleService::class);
    $result = $service->getAvailableSlotsForStore($store, $sunday);
    
    expect($result['slots'])->toBeEmpty();
});

it('applies overrides (blocks and capacity adjustments) to generated slots', function () {
    $store = phase2_createStoreWithTimings();
    $monday = Carbon::now()->next(Carbon::MONDAY)->format('Y-m-d');
    
    // Block the 09:00 - 10:00 slot
    StoreSlotOverride::create([
        'store_id' => $store->id,
        'override_date' => $monday,
        'start_time' => '09:00:00',
        'end_time' => '10:00:00',
        'is_blocked' => true,
    ]);

    // Override capacity of 10:00 - 11:00 slot to 5
    StoreSlotOverride::create([
        'store_id' => $store->id,
        'override_date' => $monday,
        'start_time' => '10:00:00',
        'end_time' => '11:00:00',
        'is_blocked' => false,
        'capacity' => 5,
    ]);

    $service = app(DeliveryScheduleService::class);
    $result = $service->getAvailableSlotsForStore($store, $monday);

    // The blocked slot should be hidden, so we only have 2 slots left (10:00 - 11:00 and 11:00 - 12:00)
    expect($result['slots'])->toHaveCount(2)
        ->and($result['slots'][0]['start_time'])->toBe('10:00:00')
        ->and($result['slots'][0]['remaining_capacity'])->toBe(5) // Overridden capacity
        ->and($result['slots'][1]['start_time'])->toBe('11:00:00')
        ->and($result['slots'][1]['remaining_capacity'])->toBe(2); // Default capacity
});

it('hides slots that have reached capacity', function () {
    $store = phase2_createStoreWithTimings();
    $monday = Carbon::now()->next(Carbon::MONDAY)->format('Y-m-d');
    $user = User::factory()->create();

    // Create 2 bookings for the 09:00 - 10:00 slot (since capacity is 2)
    for ($i = 0; $i < 2; $i++) {
        phase2_createTestOrderWithItem($user, $store, [
            'scheduled_date' => $monday,
            'scheduled_slot_start_time' => '09:00:00',
            'scheduled_slot_end_time' => '10:00:00',
        ]);
        sleep(1);
    }

    $service = app(DeliveryScheduleService::class);
    $result = $service->getAvailableSlotsForStore($store, $monday);

    // The 09:00 - 10:00 slot is full, so it should be hidden. Only 2 slots are returned (10-11, 11-12)
    expect($result['slots'])->toHaveCount(2)
        ->and($result['slots'][0]['start_time'])->toBe('10:00:00')
        ->and($result['slots'][1]['start_time'])->toBe('11:00:00');
});

it('validates dynamic slot and prevents overbooking during order placement', function () {
    $store = phase2_createStoreWithTimings();
    $zone = phase2_associateStoreWithDeliveryZone($store);
    
    $monday = Carbon::now()->next(Carbon::MONDAY)->format('Y-m-d');
    $user = User::factory()->create();
    $cart = phase2_createCartForUserAndStore($user, $store);

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

    // Book first order (capacity left: 1)
    $res1 = $orderService->createOrder($user, $checkoutData);
    if (!$res1['success']) {
        dump($res1);
    }
    expect($res1['success'])->toBeTrue();

    sleep(1); // Sleep to avoid duplicate slug in unique constraint

    // Book second order (capacity left: 0)
    $cart2 = phase2_createCartForUserAndStore($user, $store);
    $res2 = $orderService->createOrder($user, $checkoutData);
    if (!$res2['success']) {
        dump($res2);
    }
    expect($res2['success'])->toBeTrue();

    sleep(1); // Sleep to avoid duplicate slug in unique constraint

    // Book third order (should fail as capacity is full)
    $cart3 = phase2_createCartForUserAndStore($user, $store);
    $res3 = $orderService->createOrder($user, $checkoutData);
    expect($res3['success'])->toBeFalse()
        ->and($res3['message'])->toContain('capacity');
});
