<?php

use App\Enums\Order\OrderItemStatusEnum;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Seller;
use App\Models\Store;
use App\Models\User;
use App\Services\OrderService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

function makeSnapshotTestOrder(int $userId): Order
{
    return Order::forceCreate([
        'uuid'                  => (string) Str::uuid(),
        'user_id'               => $userId,
        'slug'                  => 'order-snapshot-'.uniqid(),
        'delivery_zone_id'      => 1,
        'email'                 => 'test@example.com',
        'ip_address'            => '127.0.0.1',
        'currency_code'         => 'USD',
        'currency_rate'         => 1,
        'payment_method'        => 'cod',
        'payment_status'        => 'pending',
        'fulfillment_type'      => 'hyperlocal',
        'wallet_balance'        => 0,
        'subtotal'              => 100.0,
        'total_payable'         => 100.0,
        'final_total'           => 100.0,
        'status'                => 'pending',
        'billing_name'          => 'Test User',
        'billing_address_1'     => 'Test street',
        'billing_landmark'      => 'Test landmark',
        'billing_zip'           => '00000',
        'billing_phone'         => '+10000000000',
        'billing_address_type'  => 'home',
        'billing_latitude'      => 0,
        'billing_longitude'     => 0,
        'billing_city'          => 'City',
        'billing_state'         => 'State',
        'billing_country'       => 'Country',
        'billing_country_code'  => 'US',
        'shipping_name'         => 'Test User',
        'shipping_address_1'    => 'Test street',
        'shipping_landmark'     => 'Test landmark',
        'shipping_zip'          => '00000',
        'shipping_phone'        => '+10000000000',
        'shipping_address_type' => 'home',
        'shipping_latitude'     => 0,
        'shipping_longitude'    => 0,
        'shipping_city'         => 'City',
        'shipping_state'        => 'State',
        'shipping_country'      => 'Country',
        'shipping_country_code' => 'US',
    ]);
}

test('order item snapshots requires_otp as false at creation time and ignores future product toggle', function () {
    $user = User::forceCreate([
        'name' => 'Test User',
        'email' => 'test.' . uniqid() . '@example.com',
        'password' => bcrypt('secret'),
    ]);
    $seller = Seller::factory()->create();
    $store = Store::forceCreate(testStoreData($seller->id, ['name' => 'Store 1']));
    $product = Product::forceCreate(testProductData($seller->id, ['requires_otp' => false]));
    $variant = ProductVariant::forceCreate(testVariantData($product->id));
    $order = makeSnapshotTestOrder($user->id);

    $orderItem = OrderItem::create([
        'order_id' => $order->id,
        'product_id' => $product->id,
        'product_variant_id' => $variant->id,
        'store_id' => $store->id,
        'title' => $product->title,
        'variant_title' => 'Default',
        'gift_card_discount' => 0,
        'admin_commission_amount' => 0,
        'seller_commission_amount' => 0,
        'commission_settled' => '0',
        'return_eligible' => false,
        'returnable_days' => 0,
        'is_cancelable' => false,
        'cancelable_till' => null,
        'discounted_price' => 100,
        'promo_discount' => 0,
        'discount' => 0,
        'tax_amount' => 0,
        'tax_percent' => 0,
        'sku' => 'SKU-100',
        'quantity' => 1,
        'price' => 100,
        'subtotal' => 100,
        'status' => OrderItemStatusEnum::COLLECTED(),
        'requires_otp' => $product->requires_otp,
        'otp' => null,
    ]);

    expect($orderItem->requires_otp)->toBeFalse();

    // Now admin or seller enables requires_otp on the product
    $product->update(['requires_otp' => true]);

    // Refresh order item from database
    $orderItem->refresh();

    // The snapshot must still be false
    expect($orderItem->requires_otp)->toBeFalse();
});

test('updateOrderItemStatusByDeliveryBoy respects snapshot requires_otp even if live product requires otp', function () {
    $user = User::forceCreate([
        'name' => 'Test User 2',
        'email' => 'test2.' . uniqid() . '@example.com',
        'password' => bcrypt('secret'),
    ]);
    $seller = Seller::factory()->create();
    $store = Store::forceCreate(testStoreData($seller->id, ['name' => 'Store 2']));
    $product = Product::forceCreate(testProductData($seller->id, ['requires_otp' => true]));
    $variant = ProductVariant::forceCreate(testVariantData($product->id));
    $order = makeSnapshotTestOrder($user->id);

    $orderItem = OrderItem::create([
        'order_id' => $order->id,
        'product_id' => $product->id,
        'product_variant_id' => $variant->id,
        'store_id' => $store->id,
        'title' => 'Snapshot Item',
        'variant_title' => 'Default',
        'gift_card_discount' => 0,
        'admin_commission_amount' => 0,
        'seller_commission_amount' => 0,
        'commission_settled' => '0',
        'return_eligible' => false,
        'returnable_days' => 0,
        'is_cancelable' => false,
        'cancelable_till' => null,
        'discounted_price' => 100,
        'promo_discount' => 0,
        'discount' => 0,
        'tax_amount' => 0,
        'tax_percent' => 0,
        'sku' => 'SKU-100',
        'quantity' => 1,
        'price' => 100,
        'subtotal' => 100,
        'status' => OrderItemStatusEnum::COLLECTED(),
        'requires_otp' => false, // Snapshotted as false at order creation time
        'otp' => null,
    ]);

    $service = app(OrderService::class);
    $result = $service->updateOrderItemStatusByDeliveryBoy($orderItem->id, OrderItemStatusEnum::DELIVERED(), 10);

    expect($result['success'])->toBeTrue()
        ->and($orderItem->refresh()->status)->toBe(OrderItemStatusEnum::DELIVERED());
});
