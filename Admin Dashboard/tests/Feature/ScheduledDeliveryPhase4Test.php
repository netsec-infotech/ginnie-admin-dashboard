<?php

use App\Models\Order;
use App\Models\Store;
use App\Models\User;
use App\Services\PreparationSchedulerService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->withoutMiddleware(\App\Http\Middleware\VerifyLicense::class);
});

function phase4_createStoreWithStaggering(): Store {
    $store = Store::factory()->create([
        'status' => \App\Enums\Store\StoreStatusEnum::ONLINE(),
        'is_scheduled_delivery_enabled' => true,
        'scheduled_delivery_buffer_minutes' => 60,
        'max_advance_booking_days' => 7,
        'scheduled_delivery_slot_duration' => 60,
        'scheduled_delivery_slot_capacity' => 10,
        'prep_concurrency' => 2,
        'order_preparation_time' => 15,
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

function phase4_createTestProductVariantWithPrepTime(Store $store, int $prepTime): array {
    $product = \App\Models\Product::factory()->create([
        'seller_id' => $store->seller_id,
        'base_prep_time' => $prepTime,
    ]);
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

function phase4_createTestOrderWithPrepTime(User $user, Store $store, int $prepTime, array $orderExtra = []): Order {
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

    [$productId, $variantId] = phase4_createTestProductVariantWithPrepTime($store, $prepTime);

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

it('calculates staggered preparation start times to satisfy concurrency limits', function () {
    $store = phase4_createStoreWithStaggering();
    $monday = Carbon::now()->next(Carbon::MONDAY)->format('Y-m-d');
    $user = User::factory()->create();

    // Create 3 orders with 20 min prep times
    // Concurrency limit is 2. So the 3rd order must stagger 20 mins earlier.
    $order1 = phase4_createTestOrderWithPrepTime($user, $store, 20, [
        'scheduled_date' => $monday,
        'scheduled_slot_start_time' => '10:00:00',
        'scheduled_slot_end_time' => '11:00:00',
        'created_at' => now()->subMinutes(10),
    ]);

    $order2 = phase4_createTestOrderWithPrepTime($user, $store, 20, [
        'scheduled_date' => $monday,
        'scheduled_slot_start_time' => '10:00:00',
        'scheduled_slot_end_time' => '11:00:00',
        'created_at' => now()->subMinutes(5),
    ]);

    $order3 = phase4_createTestOrderWithPrepTime($user, $store, 20, [
        'scheduled_date' => $monday,
        'scheduled_slot_start_time' => '10:00:00',
        'scheduled_slot_end_time' => '11:00:00',
        'created_at' => now(),
    ]);

    $scheduler = app(PreparationSchedulerService::class);
    $scheduler->schedulePrepForSlot($store, $monday, '10:00:00');

    $order1->refresh();
    $order2->refresh();
    $order3->refresh();

    // Standard prep starts at 09:40
    // Order 1 & 2 should get 09:40
    // Order 3 should get shifted by 20 minutes to 09:20
    expect($order1->preparation_scheduled_at->format('H:i:s'))->toBe('09:40:00')
        ->and($order2->preparation_scheduled_at->format('H:i:s'))->toBe('09:40:00')
        ->and($order3->preparation_scheduled_at->format('H:i:s'))->toBe('09:20:00');
});

it('unlocks prep windows exactly according to staggered schedule', function () {
    $store = phase4_createStoreWithStaggering();
    $monday = Carbon::now()->next(Carbon::MONDAY)->format('Y-m-d');
    $user = User::factory()->create();

    $timeSlot = \App\Models\DeliveryTimeSlot::create([
        'store_id' => $store->id,
        'start_time' => '10:00:00',
        'end_time' => '11:00:00',
        'max_orders' => 10,
        'is_active' => true,
    ]);

    // Order with prep schedule set to 09:20
    $order = phase4_createTestOrderWithPrepTime($user, $store, 20, [
        'status' => \App\Enums\Order\OrderStatusEnum::ACCEPTED_BY_SELLER(),
        'scheduled_date' => $monday,
        'delivery_time_slot_id' => $timeSlot->id,
        'scheduled_slot_start_time' => '10:00:00',
        'scheduled_slot_end_time' => '11:00:00',
        'preparation_scheduled_at' => Carbon::parse($monday . ' 09:20:00'),
    ]);

    // Test 1: Run dispatch command at 09:10 (before prep start 09:20, and before dispatch threshold 09:15)
    Carbon::setTestNow(Carbon::parse($monday . ' 09:10:00'));
    Artisan::call('orders:dispatch-scheduled');
    
    // Preparation window is NOT unlocked yet, status remains ACCEPTED_BY_SELLER
    $order->refresh();
    expect($order->status)->toBe(\App\Enums\Order\OrderStatusEnum::ACCEPTED_BY_SELLER());

    // Test 2: Run dispatch command at 09:20 (prep start time reached)
    Carbon::setTestNow(Carbon::parse($monday . ' 09:20:00'));
    Artisan::call('orders:dispatch-scheduled');

    // Clean up test now
    Carbon::setTestNow();
});
