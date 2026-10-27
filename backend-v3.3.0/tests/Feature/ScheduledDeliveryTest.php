<?php

use App\Enums\Order\OrderItemStatusEnum;
use App\Models\Cart;
use App\Models\CartItem;
use App\Models\DeliveryTimeSlot;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\Store;
use App\Models\User;
use App\Models\Wallet;
use App\Services\DeliveryScheduleService;
use App\Services\OrderService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->withoutMiddleware(\App\Http\Middleware\VerifyLicense::class);
});

if (! function_exists('createTestProductVariant')) {
    function createTestProductVariant(Store $store): array {
        $product = Product::factory()->create(['seller_id' => $store->seller_id]);
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
        return [$product->id, $variantId];
    }
}

if (! function_exists('createTestOrder')) {
    function createTestOrder(User $user, Store $store, ?int $slotId = null, ?string $date = null, string $paymentMethod = 'prepaid', ?string $status = null): Order {
        return Order::create([
            'uuid' => fake()->uuid(),
            'user_id' => $user->id,
            'delivery_zone_id' => \App\Models\DeliveryZone::factory()->create()->id,
            'slug' => 'ord-' . uniqid(),
            'email' => $user->email ?? 'test@example.com',
            'ip_address' => '127.0.0.1',
            'currency_code' => 'USD',
            'currency_rate' => 1.0,
            'payment_method' => $paymentMethod,
            'payment_status' => (string) \App\Enums\PaymentStatusEnum::COMPLETED(),
            'status' => (string) ($status ?? \App\Enums\Order\OrderStatusEnum::PENDING()),
            'fulfillment_type' => 'hyperlocal',
            'delivery_time_slot_id' => $slotId,
            'scheduled_date' => $date,
            'wallet_balance' => 0.0,
            'subtotal' => 500.0,
            'total_payable' => 500.0,
            'final_total' => 500.0,
            'billing_name' => 'John Doe',
            'billing_address_1' => '123 Test St',
            'billing_landmark' => 'Near Park',
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
            'shipping_landmark' => 'Near Park',
            'shipping_zip' => '10001',
            'shipping_phone' => '1234567890',
            'shipping_address_type' => 'home',
            'shipping_latitude' => 10.0,
            'shipping_longitude' => 20.0,
            'shipping_city' => 'Test City',
            'shipping_state' => 'Test State',
            'shipping_country' => 'Test Country',
            'shipping_country_code' => 'USA',
            'store_id' => $store->id,
            'is_wallet_used' => false,
            'wallet_amount' => 0,
            'total' => 500.0,
            'grand_total' => 500.0,
            'reschedule_deadline_hours' => 24,
        ]);
    }
}

function createTestOrderItem(Order $order, Store $store, string|OrderItemStatusEnum $status, float $price = 500.0): OrderItem {
    [$productId, $variantId] = createTestProductVariant($store);
    $statusValue = $status instanceof OrderItemStatusEnum ? $status->value : (string) $status;
    return OrderItem::create([
        'order_id' => $order->id,
        'product_id' => $productId,
        'product_variant_id' => $variantId,
        'store_id' => $store->id,
        'title' => 'Test Product Item',
        'variant_title' => 'Default Variant',
        'sku' => 'SKU-' . uniqid(),
        'quantity' => 1,
        'price' => $price,
        'subtotal' => $price,
        'status' => $statusValue,
        'is_refunded' => false,
    ]);
}

it('validates single store cart requirement for scheduled delivery', function () {
    $service = app(DeliveryScheduleService::class);
    $user = User::factory()->create();
    $store1 = Store::factory()->create(['is_scheduled_delivery_enabled' => true]);
    $store2 = Store::factory()->create(['is_scheduled_delivery_enabled' => true]);

    $cart = Cart::create(['user_id' => $user->id, 'uuid' => fake()->uuid()]);

    [$prod1, $var1] = createTestProductVariant($store1);
    CartItem::create([
        'cart_id' => $cart->id,
        'store_id' => $store1->id,
        'product_id' => $prod1,
        'product_variant_id' => $var1,
        'quantity' => 1,
    ]);

    $result1 = $service->validateCartForScheduledDelivery($cart->fresh());
    expect($result1['is_valid'])->toBeTrue()
        ->and($result1['store_id'])->toBe($store1->id);

    [$prod2, $var2] = createTestProductVariant($store2);
    CartItem::create([
        'cart_id' => $cart->id,
        'store_id' => $store2->id,
        'product_id' => $prod2,
        'product_variant_id' => $var2,
        'quantity' => 1,
    ]);

    $result2 = $service->validateCartForScheduledDelivery($cart->fresh());
    expect($result2['is_valid'])->toBeFalse()
        ->and($result2['message'])->toContain('single store');
});

it('returns empty slots as scheduled delivery is date-only', function () {
    $service = app(DeliveryScheduleService::class);
    $store = Store::factory()->create([
        'is_scheduled_delivery_enabled' => true,
        'max_advance_booking_days' => 7,
    ]);

    $targetDate = Carbon::now()->addDays(2)->format('Y-m-d');
    $slotsResult = $service->getAvailableSlotsForStore($store, $targetDate);

    expect($slotsResult['is_enabled'])->toBeTrue()
        ->and($slotsResult['slots'])->toBeEmpty()
        ->and($slotsResult['message'])->toContain('date-only');
});

it('dispatches date-only scheduled orders to READY_FOR_PICKUP when cutoff reached', function () {
    $user = User::factory()->create();
    $todayName = strtolower(Carbon::now()->format('l'));
    $store = Store::factory()->create([
        'is_scheduled_delivery_enabled' => true,
        'scheduled_delivery_buffer_minutes' => 60,
        'metadata' => [
            'operating_hours' => [
                $todayName => [
                    'is_open' => true,
                    'open_time' => Carbon::now()->addMinutes(45)->format('H:i:s'),
                    'close_time' => Carbon::now()->addHours(5)->format('H:i:s'),
                ],
            ],
        ],
    ]);

    $order = createTestOrder($user, $store, null, Carbon::now()->format('Y-m-d'), 'stripe', (string) \App\Enums\Order\OrderStatusEnum::ACCEPTED_BY_SELLER());
    $item = createTestOrderItem($order, $store, OrderItemStatusEnum::ACCEPTED());

    $this->artisan('orders:dispatch-scheduled')
        ->assertExitCode(0);

    expect((string) $order->fresh()->status)->toBe((string) \App\Enums\Order\OrderStatusEnum::READY_FOR_PICKUP());
});

it('triggers instant wallet refund when a prepaid order item is rejected or cancelled', function () {
    $user = User::factory()->create();
    $store = Store::factory()->create();

    $order = createTestOrder($user, $store, null, null, 'stripe');
    $item = createTestOrderItem($order, $store, OrderItemStatusEnum::PENDING(), 500.0);

    $orderService = app(OrderService::class);
    $result = $orderService->processOrderItemRefund($item);

    expect($result['success'])->toBeTrue()
        ->and($result['data']['refund_amount'])->toBe(500.0);

    $wallet = Wallet::where('user_id', $user->id)->first();
    expect($wallet)->not->toBeNull()
        ->and((float) $wallet->balance)->toBe(500.0);
});

it('discovers available scheduled delivery dates based on operating hours and advance booking limits', function () {
    $service = app(DeliveryScheduleService::class);
    $store = Store::factory()->create([
        'is_scheduled_delivery_enabled' => true,
        'max_advance_booking_days' => 5,
        'timing' => 'Everyday',
    ]);

    $result = $service->getAvailableDatesForStore($store);

    expect($result['is_enabled'])->toBeTrue()
        ->and($result['max_days'])->toBe(5)
        ->and($result['available_days'])->toHaveCount(7);
});

it('validates scheduled delivery date against closed days in store operating hours', function () {
    $service = app(DeliveryScheduleService::class);
    $tomorrow = Carbon::now()->addDay();
    $tomorrowDayName = strtolower($tomorrow->format('l'));

    $store = Store::factory()->create([
        'is_scheduled_delivery_enabled' => true,
        'max_advance_booking_days' => 7,
        'metadata' => [
            'operating_hours' => [
                $tomorrowDayName => ['is_open' => false],
            ],
        ],
    ]);

    $validation = $service->validateScheduledDateForStore($store, $tomorrow->format('Y-m-d'));

    expect($validation['is_valid'])->toBeFalse()
        ->and($validation['message'])->toContain('Store is closed on');
});

it('creates date-only scheduled delivery order cleanly with default label and no time slot', function () {
    $user = User::factory()->create();
    $store = Store::factory()->create(['is_scheduled_delivery_enabled' => true]);
    $tomorrow = Carbon::now()->addDay()->format('Y-m-d');

    $order = createTestOrder($user, $store, null, $tomorrow);

    expect($order->isScheduledDelivery())->toBeTrue()
        ->and($order->delivery_time_slot_id)->toBeNull()
        ->and($order->scheduled_date->format('Y-m-d'))->toBe($tomorrow);
});

it('filters future scheduled orders and nullifies scheduled date/time slot label under specific conditions', function () {
    $user = User::factory()->create();
    $store = Store::factory()->create(['is_scheduled_delivery_enabled' => true]);

    $tomorrow = Carbon::now()->addDay()->format('Y-m-d');
    $today = Carbon::now()->format('Y-m-d');

    $futureOrder = createTestOrder($user, $store, null, $tomorrow, 'prepaid', 'pending');
    $futureOrder->scheduled_time_slot_label = 'Scheduled for Day';
    $futureOrder->save();

    $todayOrder = createTestOrder($user, $store, null, $today, 'prepaid', 'pending');
    $todayOrder->scheduled_time_slot_label = 'Scheduled for Day';
    $todayOrder->save();

    $regularOrder = createTestOrder($user, $store, null, null, 'prepaid', 'pending');

    \Laravel\Sanctum\Sanctum::actingAs($user);
    $response = $this->getJson('/api/user/orders?is_scheduled=1');
    $response->assertStatus(200);
    $data = $response->json('data.data');

    $ids = collect($data)->pluck('id')->all();
    expect($ids)->toContain($futureOrder->id)
        ->toContain($todayOrder->id)
        ->not->toContain($regularOrder->id);

    $futureOrderData = collect($data)->firstWhere('id', $futureOrder->id);
    expect($futureOrderData['scheduled_date'])->toBe($tomorrow)
        ->and($futureOrderData['scheduled_time_slot_label'])->toBe('Scheduled for Day');

    $todayOrderData = collect($data)->firstWhere('id', $todayOrder->id);
    expect($todayOrderData['scheduled_date'])->toBeNull()
        ->and($todayOrderData['scheduled_time_slot_label'])->toBeNull();

    $futureOrder->status = 'preparing';
    $futureOrder->save();

    $response2 = $this->getJson('/api/user/orders?is_scheduled=1');
    $data2 = $response2->json('data.data');
    $futureOrderData2 = collect($data2)->firstWhere('id', $futureOrder->id);
    expect($futureOrderData2['scheduled_date'])->toBeNull()
        ->and($futureOrderData2['scheduled_time_slot_label'])->toBeNull();
});
