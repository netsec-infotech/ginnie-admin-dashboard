<?php

use App\Enums\Order\OrderDeliveryTypeEnum;
use App\Enums\Order\OrderStatusEnum;
use App\Enums\Payment\PaymentTypeEnum;
use App\Enums\PaymentStatusEnum;
use App\Http\Resources\OrderResource;
use App\Models\DeliveryBoy;
use App\Models\Order;
use App\Models\SellerOrder;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

/**
 * Phase 2 — assert the new fields surface in API resources.
 *
 * Real assertions need the Order/OrderItem factory chain (same constraint as
 * Phase 1A BUG-3, 1C, and 1D). When that chain lands the todos below convert
 * to real `it(...)` cases.
 */
it('caps seller-visible wallet allocation to the active seller order total', function () {
    $parentOrder = new Order([
        'uuid' => 'order-wallet-rejection',
        'email' => 'customer@example.test',
        'status' => OrderStatusEnum::ACCEPTED_BY_SELLER(),
        'payment_method' => PaymentTypeEnum::WALLET(),
        'payment_status' => PaymentStatusEnum::COMPLETED(),
        'subtotal' => 100,
        'final_total' => 100,
        'total_payable' => 0,
        'wallet_balance' => 5000,
        'handling_charges' => 0,
        'delivery_charge' => 0,
        'per_store_drop_off_fee' => 0,
        'promo_discount' => 0,
    ]);
    $parentOrder->id = 10;

    $rejectedSellerOrder = new SellerOrder([
        'order_id' => $parentOrder->id,
        'seller_id' => 1,
        'total_price' => 0,
        'delivery_type' => OrderDeliveryTypeEnum::DELIVERY(),
    ]);
    $rejectedSellerOrder->id = 11;
    $rejectedSellerOrder->setRelation('order', $parentOrder);
    $rejectedSellerOrder->setRelation('items', new Collection);

    $activeSellerOrder = new SellerOrder([
        'order_id' => $parentOrder->id,
        'seller_id' => 2,
        'total_price' => 100,
        'delivery_type' => OrderDeliveryTypeEnum::DELIVERY(),
    ]);
    $activeSellerOrder->id = 12;
    $activeSellerOrder->setRelation('order', $parentOrder);
    $activeSellerOrder->setRelation('items', new Collection);

    $parentOrder->setRelation('sellerOrders', new Collection([
        $rejectedSellerOrder,
        $activeSellerOrder,
    ]));

    $payload = (new OrderResource($activeSellerOrder))->toArray(new Request);

    expect($payload['final_total'])->toBe(100.0)
        ->and($payload['wallet_balance'])->toBe(100.0)
        ->and($payload['total_payable'])->toBe(0);
});

it('returns null delivery_boy when no delivery partner is assigned to seller order', function () {
    $parentOrder = new Order([
        'delivery_boy_id' => null,
    ]);
    $parentOrder->id = 1;

    $sellerOrder = new SellerOrder([
        'order_id' => $parentOrder->id,
        'seller_id' => 1,
        'total_price' => 50,
        'delivery_type' => OrderDeliveryTypeEnum::DELIVERY(),
    ]);
    $sellerOrder->setRelation('order', $parentOrder);
    $sellerOrder->setRelation('items', new Collection);
    $parentOrder->setRelation('sellerOrders', new Collection([$sellerOrder]));

    $payload = (new OrderResource($sellerOrder))->toArray(new Request);

    expect($payload['delivery_boy'])->toBeNull()
        ->and($payload['delivery_boy_id'])->toBeNull();
});

it('returns delivery_boy details with assignment status when rider is assigned to seller order', function () {
    $user = new User([
        'email' => 'rider@example.test',
        'mobile' => '9876543210',
    ]);

    $deliveryBoy = new DeliveryBoy([
        'full_name' => 'John Rider',
        'is_blocked' => false,
    ]);
    $deliveryBoy->id = 5;
    $deliveryBoy->setRelation('user', $user);

    $parentOrder = new Order([
        'delivery_boy_id' => 5,
    ]);
    $parentOrder->id = 1;
    $parentOrder->setRelation('deliveryBoy', $deliveryBoy);

    $sellerOrder = new SellerOrder([
        'order_id' => $parentOrder->id,
        'seller_id' => 1,
        'total_price' => 50,
        'delivery_type' => OrderDeliveryTypeEnum::DELIVERY(),
    ]);
    $sellerOrder->setRelation('order', $parentOrder);
    $sellerOrder->setRelation('items', new Collection);
    $parentOrder->setRelation('sellerOrders', new Collection([$sellerOrder]));

    $payload = (new OrderResource($sellerOrder))->toArray(new Request);

    expect($payload['delivery_boy'])->toBeArray()
        ->and($payload['delivery_boy']['id'])->toBe(5)
        ->and($payload['delivery_boy']['full_name'])->toBe('John Rider')
        ->and($payload['delivery_boy']['email'])->toBe('rider@example.test')
        ->and($payload['delivery_boy']['mobile'])->toBe('9876543210')
        ->and($payload['delivery_boy']['status'])->toBe('assigned')
        ->and($payload['delivery_boy']['status_label'])->toBe('Assigned')
        ->and($payload['delivery_boy_id'])->toBe(5);
});

todo('User OrderItemResource exposes status_label, cancellation_reason, delivery_fail_reason');

todo('User OrderResource exposes order-level status_label');

todo('Admin SellerOrderItemResource exposes status_label, cancellation_reason, delivery_fail_reason inside the orderItem block');

todo('Shared OrderResource (web + mobile) emits status_label, cancellation_reason, delivery_fail_reason on each item');

todo('Seller mobile transformOrderItem exposes status_label, cancellation_reason, delivery_fail_reason');

todo('A locale change (en -> es) flips status_label to the Spanish translation');

todo('Customer order list filter by status=cancelled returns orders whose order-level status is cancelled');
