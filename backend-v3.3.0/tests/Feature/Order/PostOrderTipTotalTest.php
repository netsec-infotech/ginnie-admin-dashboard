<?php

use App\Enums\Order\OrderStatusEnum;
use App\Enums\Payment\PaymentTypeEnum;
use App\Models\Order;
use App\Models\User;
use App\Services\OrderService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;

use App\Models\DeliveryZone;

uses(RefreshDatabase::class);

function createTestOrder(array $attributes = []): Order
{
    $zone = DeliveryZone::first() ?? DeliveryZone::factory()->create();

    return Order::create(array_merge([
        'uuid' => (string) Str::uuid(),
        'slug' => 'order-'.Str::random(8),
        'user_id' => User::factory()->create()->id,
        'delivery_zone_id' => $zone->id,
        'email' => 'test@example.com',
        'ip_address' => '127.0.0.1',
        'currency_code' => 'USD',
        'currency_rate' => 1,
        'payment_method' => 'stripe',
        'payment_status' => 'pending',
        'fulfillment_type' => 'hyperlocal',
        'status' => OrderStatusEnum::ACCEPTED_BY_SELLER(),
        'subtotal' => 100.00,
        'total_payable' => 100.00,
        'final_total' => 100.00,
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
        'rider_tip_amount' => 0.00,
    ], $attributes));
}

it('keeps final_total and total_payable unchanged when adding a post-order tip to a COD order', function () {
    $order = createTestOrder([
        'payment_method' => PaymentTypeEnum::COD(),
    ]);

    $orderService = app(OrderService::class);
    $result = $orderService->applyCompletedTipToOrder($order, 15.00, 'tx_tip_cod_test');

    expect($result['success'])->toBeTrue();

    $order->refresh();
    expect((float) $order->rider_tip_amount)->toBe(15.00)
        ->and((float) $order->final_total)->toBe(100.00)
        ->and((float) $order->total_payable)->toBe(100.00);
});

it('increments final_total and total_payable when adding a post-order tip to a prepaid order', function () {
    $order = createTestOrder([
        'payment_method' => PaymentTypeEnum::STRIPE(),
    ]);

    $orderService = app(OrderService::class);
    $result = $orderService->applyCompletedTipToOrder($order, 20.00, 'tx_tip_prepaid_test');

    expect($result['success'])->toBeTrue();

    $order->refresh();
    expect((float) $order->rider_tip_amount)->toBe(20.00)
        ->and((float) $order->final_total)->toBe(120.00)
        ->and((float) $order->total_payable)->toBe(120.00);
});
