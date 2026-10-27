<?php

use App\Enums\Order\OrderItemStatusEnum;
use App\Enums\Order\OrderStatusEnum;
use App\Enums\Order\OrderDeliveryTypeEnum;
use App\Enums\Payment\PaymentTypeEnum;
use App\Enums\PaymentStatusEnum;
use App\Enums\SettingTypeEnum;
use App\Models\DeliveryZone;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\SellerOrder;
use App\Models\SellerOrderItem;
use App\Models\Setting;
use App\Models\Store;
use App\Models\User;
use App\Services\OrderService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

function autoRejectSeedScheduledDeliverySettings(array $overrides = []): void
{
    Setting::updateOrCreate(
        ['variable' => SettingTypeEnum::SCHEDULED_DELIVERY()],
        ['value' => json_encode(array_merge([
            'enableScheduledDelivery' => true,
            'maxAdvanceBookingDaysLimit' => 7,
        ], $overrides))]
    );

    app(\App\Services\SettingService::class)->clearSettingCache(SettingTypeEnum::SCHEDULED_DELIVERY());
}

function autoRejectSeedSellerSettings(array $overrides = []): void
{
    Setting::updateOrCreate(
        ['variable' => SettingTypeEnum::SELLER()],
        ['value' => json_encode(array_merge([
            'termsCondition' => '',
            'privacyPolicy' => '',
            'return_seller_inspection_sla_hours' => 48,
            'regularOrderAcceptanceTimeoutEnabled' => true,
            'regularOrderAcceptanceTimeoutMinutes' => 10,
        ], $overrides))]
    );

    app(\App\Services\SettingService::class)->clearSettingCache(SettingTypeEnum::SELLER());
}

function autoRejectCreateOrderWithItem(Store $store, array $orderOverrides = [], array $itemOverrides = []): array
{
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

    $order = Order::create(array_merge([
        'uuid' => fake()->uuid(),
        'user_id' => User::factory()->create()->id,
        'slug' => 'ord-' . uniqid(),
        'email' => 'test@example.com',
        'ip_address' => '127.0.0.1',
        'currency_code' => 'USD',
        'currency_rate' => 1.0,
        'payment_method' => PaymentTypeEnum::COD(),
        'payment_status' => PaymentStatusEnum::PENDING(),
        'status' => OrderStatusEnum::AWAITING_STORE_RESPONSE(),
        'fulfillment_type' => 'hyperlocal',
        'delivery_zone_id' => DeliveryZone::factory()->create()->id,
        'wallet_balance' => 0.0,
        'subtotal' => 100.0,
        'total_payable' => 100.0,
        'final_total' => 100.0,
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
    ], $orderOverrides));

    $item = OrderItem::create(array_merge([
        'order_id' => $order->id,
        'product_id' => $product->id,
        'product_variant_id' => $variantId,
        'store_id' => $store->id,
        'title' => 'Test Product Item',
        'variant_title' => 'Default Variant',
        'sku' => 'SKU-' . uniqid(),
        'quantity' => 1,
        'price' => 100.0,
        'subtotal' => 100.0,
        'status' => OrderItemStatusEnum::AWAITING_STORE_RESPONSE(),
        'is_refunded' => false,
    ], $itemOverrides));

    return [$order, $item];
}

it('auto rejects regular orders after the configured acceptance timeout', function () {
    autoRejectSeedScheduledDeliverySettings();
    autoRejectSeedSellerSettings(['regularOrderAcceptanceTimeoutMinutes' => 10]);

    $store = Store::factory()->create();
    [$order, $item] = autoRejectCreateOrderWithItem($store);

    DB::table('order_items')->where('id', $item->id)->update(['updated_at' => now()->subMinutes(11)]);

    $result = app(OrderService::class)->autoRejectExpiredUnacceptedOrders();

    expect($result['rejected'])->toBe(1)
        ->and($item->fresh()->status)->toBe(OrderItemStatusEnum::REJECTED())
        ->and($order->fresh()->status)->toBe(OrderStatusEnum::REJECTED_BY_SELLER());
});

it('does not auto reject regular orders inside the configured acceptance timeout', function () {
    autoRejectSeedScheduledDeliverySettings();
    autoRejectSeedSellerSettings(['regularOrderAcceptanceTimeoutMinutes' => 10]);

    $store = Store::factory()->create();
    [$order, $item] = autoRejectCreateOrderWithItem($store);

    DB::table('order_items')->where('id', $item->id)->update(['updated_at' => now()->subMinutes(5)]);

    $result = app(OrderService::class)->autoRejectExpiredUnacceptedOrders();

    expect($result['rejected'])->toBe(0)
        ->and($item->fresh()->status)->toBe(OrderItemStatusEnum::AWAITING_STORE_RESPONSE())
        ->and($order->fresh()->status)->toBe(OrderStatusEnum::AWAITING_STORE_RESPONSE());
});

it('does not auto reject regular orders when the timeout is disabled', function () {
    autoRejectSeedScheduledDeliverySettings();
    autoRejectSeedSellerSettings([
        'regularOrderAcceptanceTimeoutEnabled' => false,
        'regularOrderAcceptanceTimeoutMinutes' => 10,
    ]);

    $store = Store::factory()->create();
    [$order, $item] = autoRejectCreateOrderWithItem($store);

    DB::table('order_items')->where('id', $item->id)->update(['updated_at' => now()->subMinutes(30)]);

    $result = app(OrderService::class)->autoRejectExpiredUnacceptedOrders();

    expect($result['rejected'])->toBe(0)
        ->and($item->fresh()->status)->toBe(OrderItemStatusEnum::AWAITING_STORE_RESPONSE())
        ->and($order->fresh()->status)->toBe(OrderStatusEnum::AWAITING_STORE_RESPONSE());
});

it('uses the order snapshot even after admin disables the regular timeout', function () {
    autoRejectSeedScheduledDeliverySettings();
    autoRejectSeedSellerSettings([
        'regularOrderAcceptanceTimeoutEnabled' => true,
        'regularOrderAcceptanceTimeoutMinutes' => 10,
    ]);

    $store = Store::factory()->create();
    [$order, $item] = autoRejectCreateOrderWithItem($store, [
        'acceptance_timeout_snapshot' => app(OrderService::class)->orderAcceptanceTimeoutSnapshot(),
    ]);

    autoRejectSeedSellerSettings([
        'regularOrderAcceptanceTimeoutEnabled' => false,
        'regularOrderAcceptanceTimeoutMinutes' => 10,
    ]);
    DB::table('order_items')->where('id', $item->id)->update(['updated_at' => now()->subMinutes(11)]);

    $result = app(OrderService::class)->autoRejectExpiredUnacceptedOrders();

    expect($result['rejected'])->toBe(1)
        ->and($item->fresh()->status)->toBe(OrderItemStatusEnum::REJECTED())
        ->and($order->fresh()->status)->toBe(OrderStatusEnum::REJECTED_BY_SELLER());
});

it('keeps a disabled snapshot disabled even after admin enables the regular timeout', function () {
    autoRejectSeedScheduledDeliverySettings();
    autoRejectSeedSellerSettings([
        'regularOrderAcceptanceTimeoutEnabled' => false,
        'regularOrderAcceptanceTimeoutMinutes' => 10,
    ]);

    $store = Store::factory()->create();
    [$order, $item] = autoRejectCreateOrderWithItem($store, [
        'acceptance_timeout_snapshot' => app(OrderService::class)->orderAcceptanceTimeoutSnapshot(),
    ]);

    autoRejectSeedSellerSettings([
        'regularOrderAcceptanceTimeoutEnabled' => true,
        'regularOrderAcceptanceTimeoutMinutes' => 10,
    ]);
    DB::table('order_items')->where('id', $item->id)->update(['updated_at' => now()->subMinutes(30)]);

    $result = app(OrderService::class)->autoRejectExpiredUnacceptedOrders();

    expect($result['rejected'])->toBe(0)
        ->and($item->fresh()->status)->toBe(OrderItemStatusEnum::AWAITING_STORE_RESPONSE())
        ->and($order->fresh()->status)->toBe(OrderStatusEnum::AWAITING_STORE_RESPONSE());
});

it('rejects immediately when seller accepts after the acceptance timeout', function () {
    autoRejectSeedScheduledDeliverySettings();
    autoRejectSeedSellerSettings(['regularOrderAcceptanceTimeoutMinutes' => 10]);

    $store = Store::factory()->create();
    [$order, $item] = autoRejectCreateOrderWithItem($store);
    $sellerOrder = SellerOrder::create([
        'order_id' => $order->id,
        'seller_id' => $store->seller_id,
        'delivery_type' => OrderDeliveryTypeEnum::DELIVERY(),
        'total_price' => 100.00,
    ]);
    SellerOrderItem::create([
        'seller_order_id' => $sellerOrder->id,
        'product_id' => $item->product_id,
        'product_variant_id' => $item->product_variant_id,
        'order_item_id' => $item->id,
        'quantity' => 1,
        'price' => 100.00,
    ]);

    DB::table('order_items')->where('id', $item->id)->update(['updated_at' => now()->subMinutes(11)]);

    $result = app(OrderService::class)->updateOrderStatusBySeller($item->id, 'accept', $store->seller_id);

    expect($result['success'])->toBeFalse()
        ->and($result['message'])->toBe(__('labels.order_acceptance_window_expired'))
        ->and($item->fresh()->status)->toBe(OrderItemStatusEnum::REJECTED())
        ->and($order->fresh()->status)->toBe(OrderStatusEnum::REJECTED_BY_SELLER());
});

it('does not apply acceptance timeout auto rejection to scheduled orders', function () {
    autoRejectSeedScheduledDeliverySettings();
    autoRejectSeedSellerSettings();

    $store = Store::factory()->create([
        'metadata' => [
            'operating_hours' => [
                strtolower(now()->addDays(2)->format('l')) => ['is_open' => true, 'open_time' => '09:00:00', 'close_time' => '22:00:00'],
            ],
        ],
    ]);

    [$order, $item] = autoRejectCreateOrderWithItem(
        $store,
        [
            'payment_method' => 'prepaid',
            'payment_status' => PaymentStatusEnum::PENDING(),
            'status' => OrderStatusEnum::PENDING(),
            'scheduled_date' => now()->subDay()->toDateString(),
            'scheduled_slot_start_time' => now()->subHours(2)->format('H:i:s'),
            'scheduled_slot_end_time' => now()->subHour()->format('H:i:s'),
        ],
        ['status' => OrderItemStatusEnum::PENDING()]
    );

    $result = app(OrderService::class)->autoRejectExpiredUnacceptedOrders();

    expect($result['rejected'])->toBe(0)
        ->and($item->fresh()->status)->toBe(OrderItemStatusEnum::PENDING())
        ->and($order->fresh()->status)->toBe(OrderStatusEnum::PENDING());
});

it('validates regular acceptance timeout settings as minutes', function () {
    $sellerPayload = [
        'type' => SettingTypeEnum::SELLER(),
        'termsCondition' => '',
        'privacyPolicy' => '',
        'return_seller_inspection_sla_hours' => 48,
        'regularOrderAcceptanceTimeoutEnabled' => true,
        'regularOrderAcceptanceTimeoutMinutes' => 0,
    ];

    expect(fn () => \App\Types\Settings\SellerSettingType::fromArray($sellerPayload))
        ->toThrow(\Illuminate\Validation\ValidationException::class);
});
