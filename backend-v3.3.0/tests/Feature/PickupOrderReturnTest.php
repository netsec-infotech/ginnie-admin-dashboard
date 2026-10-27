<?php

use App\Models\User;
use App\Models\Seller;
use App\Models\Store;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\StoreProductVariant;
use App\Models\Setting;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\SellerOrder;
use App\Models\PosRefund;
use App\Models\PosRefundLine;
use App\Services\OrderService;
use App\Enums\Order\OrderDeliveryTypeEnum;
use App\Enums\Order\OrderStatusEnum;
use App\Enums\Order\OrderItemStatusEnum;
use App\Enums\GuardNameEnum;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;

uses(RefreshDatabase::class);

function makePickupReturnTestOrder(User $user, float $total, string $status): Order
{
    return Order::forceCreate([
        'uuid'                  => (string) \Illuminate\Support\Str::uuid(),
        'user_id'               => $user->id,
        'delivery_zone_id'      => \App\Models\DeliveryZone::first()?->id ?? \App\Models\DeliveryZone::factory()->create()->id,
        'slug'                  => 'order-test-'.uniqid(),
        'email'                 => $user->email,
        'ip_address'            => '127.0.0.1',
        'currency_code'         => 'USD',
        'currency_rate'         => 1,
        'payment_method'        => 'cod',
        'payment_status'        => 'pending',
        'fulfillment_type'      => 'hyperlocal',
        'wallet_balance'        => 0,
        'subtotal'              => $total,
        'total_payable'         => $total,
        'final_total'           => $total,
        'status'                => $status,
        'billing_name'          => $user->name,
        'billing_address_1'     => 'Test street',
        'billing_landmark'      => 'Test landmark',
        'billing_zip'           => '123456',
        'billing_phone'         => '1234567890',
        'billing_address_type'  => 'home',
        'billing_latitude'      => 12.97159888,
        'billing_longitude'     => 77.59456666,
        'billing_city'          => 'Bangalore',
        'billing_state'         => 'Karnataka',
        'billing_country'       => 'India',
        'billing_country_code'  => 'IN',
        'shipping_name'         => $user->name,
        'shipping_address_1'    => 'Test street',
        'shipping_address_2'    => '',
        'shipping_landmark'     => 'Test landmark',
        'shipping_zip'          => '123456',
        'shipping_phone'        => '1234567890',
        'shipping_address_type' => 'home',
        'shipping_latitude'     => 12.97159888,
        'shipping_longitude'    => 77.59456666,
        'shipping_city'         => 'Bangalore',
        'shipping_state'        => 'Karnataka',
        'shipping_country'      => 'India',
        'shipping_country_code' => 'IN',
    ]);
}

beforeEach(function () {
    Event::fake();

    // Enable pickup globally
    Setting::forceCreate([
        'variable' => 'pickup',
        'value' => json_encode(['enabled' => true]),
    ]);

    Setting::forceCreate([
        'variable' => 'system',
        'value' => json_encode([
            'checkoutType' => 'multi_store',
            'maximumItemsAllowedInCart' => 10,
            'systemVendorType' => 'multiple',
            'currencySymbol' => '$',
        ]),
    ]);

    Setting::forceCreate([
        'variable' => 'subscription',
        'value' => json_encode([
            'enableSubscription' => false,
        ]),
    ]);

    Setting::forceCreate([
        'variable' => 'payment',
        'value' => json_encode([
            'cod' => true,
            'wallet' => false,
        ]),
    ]);

    // Setup models
    $this->user = User::forceCreate([
        'name' => 'John Doe',
        'email' => 'john.doe@example.com',
        'password' => bcrypt('password'),
        'access_panel' => GuardNameEnum::WEB->value,
    ]);

    $this->sellerUser = User::forceCreate([
        'name' => 'Store Manager',
        'email' => 'manager@example.com',
        'password' => bcrypt('password'),
        'access_panel' => GuardNameEnum::SELLER->value,
    ]);

    $this->seller = Seller::factory()->create([
        'user_id' => $this->sellerUser->id,
        'verification_status' => 'approved',
    ]);

    $roleSeller = \Spatie\Permission\Models\Role::findOrCreate(\App\Enums\DefaultSystemRolesEnum::SELLER->value, GuardNameEnum::SELLER->value);
    $roleWeb = \Spatie\Permission\Models\Role::findOrCreate(\App\Enums\DefaultSystemRolesEnum::SELLER->value, 'web');
    $this->sellerUser->assignRole($roleSeller, $roleWeb);

    $this->store = Store::forceCreate(testStoreData($this->seller->id, [
        'name' => 'Central Market Store',
        'allows_pickup' => true,
        'pickup_instructions' => 'Go to checkout counter 3.',
    ]));

    $this->product = Product::forceCreate(testProductData($this->seller->id, [
        'title' => 'Fresh Milk',
        'is_returnable' => '1',
        'returnable_days' => 7,
    ]));

    $this->variant = ProductVariant::forceCreate(testVariantData($this->product->id, [
        'title' => '1 Litre',
    ]));

    $this->storeVariant = StoreProductVariant::forceCreate([
        'product_variant_id' => $this->variant->id,
        'store_id' => $this->store->id,
        'sku' => 'MILK-1L',
        'price' => 50,
        'special_price' => 45,
        'cost' => 30,
        'stock' => 10,
    ]);
});

test('customer return request api fails for pickup orders with in-store return message', function () {
    // Setup order
    $order = makePickupReturnTestOrder($this->user, 90.0, OrderStatusEnum::DELIVERED());

    $sellerOrder = SellerOrder::create([
        'order_id' => $order->id,
        'seller_id' => $this->seller->id,
        'total_price' => 90,
        'delivery_type' => OrderDeliveryTypeEnum::PICKUP(),
    ]);

    $orderItem = OrderItem::create([
        'order_id' => $order->id,
        'product_id' => $this->product->id,
        'product_variant_id' => $this->variant->id,
        'store_id' => $this->store->id,
        'title' => 'Fresh Milk',
        'variant_title' => '1 Litre',
        'sku' => 'MILK-1L',
        'quantity' => 2,
        'price' => 45,
        'subtotal' => 90,
        'status' => OrderItemStatusEnum::DELIVERED(),
        'return_eligible' => true,
        'returnable_days' => 7,
        'return_deadline' => now()->addDays(7)->toDateString(),
    ]);

    $orderService = app(OrderService::class);
    $response = $orderService->returnOrderItem($this->user, [
        'order_item_id' => $orderItem->id,
        'reason' => 'Bad quality',
    ]);

    expect($response['success'])->toBeFalse();
    expect($response['message'])->toBe('To return this item, please visit the store where the order was collected.');
});

test('isEligibleForPickupReturn helper evaluates correctly', function () {
    $order = makePickupReturnTestOrder($this->user, 45.0, OrderStatusEnum::READY_FOR_PICKUP());

    $orderItem = OrderItem::create([
        'order_id' => $order->id,
        'product_id' => $this->product->id,
        'product_variant_id' => $this->variant->id,
        'store_id' => $this->store->id,
        'title' => 'Fresh Milk',
        'variant_title' => '1 Litre',
        'sku' => 'MILK-1L',
        'quantity' => 1,
        'price' => 45,
        'subtotal' => 45,
        'status' => OrderItemStatusEnum::ACCEPTED(),
        'return_eligible' => true,
        'returnable_days' => 7,
        'return_deadline' => null,
    ]);

    // Item not delivered/picked up yet -> not eligible
    expect($orderItem->isEligibleForPickupReturn())->toBeFalse();

    // Deliver the item
    $orderItem->update([
        'status' => OrderItemStatusEnum::DELIVERED(),
        'return_deadline' => now()->addDays(7)->toDateString(),
    ]);
    expect($orderItem->isEligibleForPickupReturn())->toBeTrue();

    // Even if return_eligible is false, pickup order returns are allowed
    $orderItem->update([
        'return_eligible' => false,
    ]);
    expect($orderItem->isEligibleForPickupReturn())->toBeTrue();

    // Even if return_deadline has expired, pickup order returns are allowed
    $orderItem->update([
        'return_eligible' => true,
        'return_deadline' => now()->subDays(1)->toDateString(),
    ]);
    expect($orderItem->isEligibleForPickupReturn())->toBeTrue();


    // Fully refunded -> not eligible
    $refund = PosRefund::create([
        'order_id' => $order->id,
        'store_id' => $this->store->id,
        'refunded_by_user_id' => $this->sellerUser->id,
        'total_amount' => 45,
        'refund_method' => 'cash',
    ]);
    PosRefundLine::create([
        'pos_refund_id' => $refund->id,
        'order_item_id' => $orderItem->id,
        'quantity' => 1,
        'amount' => 45,
    ]);

    expect($orderItem->isEligibleForPickupReturn())->toBeFalse();
});

test('seller refunds routes preview and create work correctly', function () {
    // Setup order
    $order = makePickupReturnTestOrder($this->user, 90.0, OrderStatusEnum::DELIVERED());

    $sellerOrder = SellerOrder::create([
        'order_id' => $order->id,
        'seller_id' => $this->seller->id,
        'total_price' => 90,
        'delivery_type' => OrderDeliveryTypeEnum::PICKUP(),
    ]);

    $orderItem = OrderItem::create([
        'order_id' => $order->id,
        'product_id' => $this->product->id,
        'product_variant_id' => $this->variant->id,
        'store_id' => $this->store->id,
        'title' => 'Fresh Milk',
        'variant_title' => '1 Litre',
        'sku' => 'MILK-1L',
        'quantity' => 2,
        'price' => 45,
        'subtotal' => 90,
        'status' => OrderItemStatusEnum::DELIVERED(),
        'return_eligible' => true,
        'returnable_days' => 7,
        'return_deadline' => now()->addDays(7)->toDateString(),
    ]);

    \App\Models\SellerOrderItem::create([
        'seller_order_id' => $sellerOrder->id,
        'product_id' => $this->product->id,
        'product_variant_id' => $this->variant->id,
        'order_item_id' => $orderItem->id,
        'quantity' => 2,
        'price' => 45,
    ]);

    // Authenticate as seller
    $this->withoutMiddleware(\App\Http\Middleware\VerifyLicense::class);
    $this->actingAs($this->sellerUser, GuardNameEnum::SELLER->value);

    // 1. Check Refund Preview endpoint
    $response = $this->getJson(route('seller.orders.refunds.preview', $sellerOrder->id));
    $response->assertStatus(200);
    $response->assertJsonPath('success', true);
    $response->assertJsonPath('data.refundable.0.order_item_id', $orderItem->id);
    $response->assertJsonPath('data.refundable.0.max_refundable', 2);
    $response->assertJsonPath('data.refundable.0.per_unit_amount', 45);

    // 2. Create Refund (Return 1 item)
    $this->withoutMiddleware(\App\Http\Middleware\VerifyLicense::class);
    $this->actingAs($this->sellerUser, GuardNameEnum::SELLER->value);
    $response = $this->postJson(route('seller.orders.refunds.create', $sellerOrder->id), [
        'lines' => [
            ['order_item_id' => $orderItem->id, 'quantity' => 1]
        ],
        'reason' => 'Customer changed mind',
        'method' => 'cash',
    ]);
    $response->assertStatus(200);
    $response->assertJsonPath('success', true);
    $response->assertJsonPath('data.total_amount', 45);

    // Verify stock is restored
    expect($this->storeVariant->fresh()->stock)->toBe(11);

    // 3. Verify Refund History endpoint
    $this->withoutMiddleware(\App\Http\Middleware\VerifyLicense::class);
    $this->actingAs($this->sellerUser, GuardNameEnum::SELLER->value);
    $response = $this->getJson(route('seller.orders.refunds.history', $sellerOrder->id));
    $response->assertStatus(200);
    $response->assertJsonPath('success', true);
    $response->assertJsonPath('data.total_refunded', 45);
    $response->assertJsonCount(1, 'data.refunds');
    $response->assertJsonPath('data.refunds.0.lines.0.quantity', 1);
});
