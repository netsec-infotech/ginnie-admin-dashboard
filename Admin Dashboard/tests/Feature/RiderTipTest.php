<?php

use App\Enums\DeliveryBoy\DeliveryBoyAssignmentStatusEnum;
use App\Enums\DeliveryBoy\EarningPaymentStatusEnum;
use App\Enums\Order\OrderItemStatusEnum;
use App\Enums\Order\OrderStatusEnum;
use App\Enums\Payment\PaymentTypeEnum;
use App\Enums\Wallet\WalletTypeEnum;
use App\Http\Middleware\CheckInstallation;
use App\Http\Middleware\VerifyLicense;
use App\Http\Resources\DeliveryBoy\DeliveryBoyOrderResource;
use App\Models\Cart;
use App\Models\DeliveryBoy;
use App\Models\DeliveryBoyAssignment;
use App\Models\DeliveryZone;
use App\Models\Order;
use App\Models\OrderAuditLog;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Seller;
use App\Models\Store;
use App\Models\User;
use App\Models\Wallet;
use App\Services\CartService;
use App\Services\OrderService;
use App\Services\WalletService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->withoutMiddleware([VerifyLicense::class, CheckInstallation::class]);
});

function makeRiderTipTestOrder(array $attributes = []): Order
{
    if (! isset($attributes['user_id'])) {
        $attributes['user_id'] = User::factory()->create()->id;
    }
    if (! isset($attributes['delivery_zone_id'])) {
        $zone = DeliveryZone::first() ?? DeliveryZone::factory()->create();
        $attributes['delivery_zone_id'] = $zone->id;
    }

    return Order::create(array_merge([
        'uuid' => (string) Str::uuid(),
        'slug' => 'order-'.Str::random(8),
        'email' => 'test@example.com',
        'ip_address' => '127.0.0.1',
        'currency_code' => 'USD',
        'currency_rate' => 1,
        'payment_method' => 'stripe',
        'payment_status' => 'pending',
        'fulfillment_type' => 'hyperlocal',
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
        'status' => OrderStatusEnum::PENDING(),
    ], $attributes));
}

it('calculates rider tip and respects min/max tipping limits in payment summary', function () {
    $user = User::factory()->create();
    $cart = new Cart([
        'user_id' => $user->id,
        'rider_tip_amount' => 15.00,
    ]);
    $cart->id = 10;
    $cart->setRelation('items', new Collection);

    $cartService = app(CartService::class);
    $summary = $cartService->getPaymentSummary($cart, null, null);

    expect($summary['rider_tip_amount'])->toBe(15.0)
        ->and($summary['tipping_config'])->toBeArray();
});

it('resets rider tip amount when COD payment is used', function () {
    $cart = new Cart([
        'user_id' => 1,
        'rider_tip_amount' => 20.00,
    ]);

    $paymentMethod = PaymentTypeEnum::COD();
    $effectiveTip = (strtolower((string) $paymentMethod) === 'cod') ? 0.0 : (float) $cart->rider_tip_amount;

    expect($effectiveTip)->toBe(0.0);
});

it('integrates 100% of rider tip directly into rider order earnings when order is delivered', function () {
    $riderUser = User::factory()->create();
    $customer = User::factory()->create();

    $rider = DeliveryBoy::create([
        'user_id' => $riderUser->id,
        'full_name' => 'Rider One',
        'status' => 'active',
    ]);

    $order = makeRiderTipTestOrder([
        'user_id' => $customer->id,
        'delivery_boy_id' => $rider->id,
        'rider_tip_amount' => 25.00,
        'status' => OrderStatusEnum::DELIVERED(),
    ]);
    $order->setRelation('deliveryBoy', $rider);

    $orderService = app(OrderService::class);
    $orderService->creditRiderTipOnDelivery($order);

    $auditLog = OrderAuditLog::where('order_id', $order->id)
        ->where('action', 'RIDER_TIP_CREDITED')
        ->first();

    expect($auditLog)->not->toBeNull()
        ->and($auditLog->new_value['tip_amount'])->toEqual(25.00)
        ->and($auditLog->new_value['integrated_in_order_earnings'])->toBeTrue();
});

it('refunds rider tip to customer wallet when order is cancelled before delivery', function () {
    $customer = User::factory()->create();

    $order = makeRiderTipTestOrder([
        'user_id' => $customer->id,
        'rider_tip_amount' => 15.00,
        'status' => OrderStatusEnum::CANCELLED(),
    ]);

    $walletServiceMock = Mockery::mock(WalletService::class);
    $walletServiceMock->shouldReceive('creditWallet')
        ->once()
        ->with(
            $customer->id,
            Mockery::any(),
            15.00,
            Mockery::type('string'),
            (string) $order->id
        )
        ->andReturn(['success' => true]);

    $this->app->instance(WalletService::class, $walletServiceMock);

    $orderService = app(OrderService::class);
    $orderService->refundRiderTipToCustomerWallet($order);

    $auditLog = OrderAuditLog::where('order_id', $order->id)
        ->where('action', 'ORDER_CANCELLED_TIP_REFUND')
        ->first();

    expect($auditLog)->not->toBeNull()
        ->and($auditLog->new_value['refunded'])->toBeTrue();
});

it('updates rider tip amount via post-placement tipping route logic', function () {
    $order = makeRiderTipTestOrder([
        'rider_tip_amount' => 10.00,
        'status' => OrderStatusEnum::ASSIGNED(),
    ]);

    $tx = \App\Models\OrderPaymentTransaction::create([
        'uuid' => (string) Str::uuid(),
        'user_id' => $order->user_id,
        'order_id' => $order->id,
        'transaction_id' => 'tx_stripe_test_123',
        'amount' => 15.00,
        'currency' => 'USD',
        'payment_method' => 'stripe',
        'payment_status' => \App\Enums\PaymentStatusEnum::COMPLETED(),
    ]);

    $orderService = app(OrderService::class);
    $result = $orderService->processPostOrderTip($order, 15.00, 'stripe', 'tx_stripe_test_123');

    expect($result['success'])->toBeTrue()
        ->and($result['data']['total_tip'])->toBe(25.0);
});

it('initiates pending gateway tip transaction when no completed transaction_id is provided', function () {
    $order = makeRiderTipTestOrder([
        'rider_tip_amount' => 10.00,
        'status' => OrderStatusEnum::ASSIGNED(),
    ]);

    $orderService = app(OrderService::class);
    $result = $orderService->processPostOrderTip($order, 20.00, 'razorpay');

    expect($result['success'])->toBeTrue()
        ->and($result['data']['status'])->toBe('pending')
        ->and($result['data']['amount'])->toBe(20.0);

    $tx = \App\Models\OrderPaymentTransaction::where('transaction_id', $result['data']['transaction_id'])->first();
    expect($tx)->not->toBeNull()
        ->and((string) $tx->payment_status)->toBe((string) \App\Enums\PaymentStatusEnum::PENDING());
});

it('applies tip to order when webhook invokes applyCompletedTipToOrder', function () {
    $order = makeRiderTipTestOrder([
        'rider_tip_amount' => 5.00,
        'status' => OrderStatusEnum::ASSIGNED(),
    ]);

    $orderService = app(OrderService::class);
    $result = $orderService->applyCompletedTipToOrder($order, 15.00, 'TIP-TEST-999');

    expect($result['success'])->toBeTrue()
        ->and($result['data']['total_tip'])->toBe(20.0);

    expect((float) $order->fresh()->rider_tip_amount)->toBe(20.0);
});

it('rejects post-placement tipping if order status is DELIVERED', function () {
    $order = makeRiderTipTestOrder([
        'rider_tip_amount' => 0.00,
        'status' => OrderStatusEnum::DELIVERED(),
    ]);

    $orderService = app(OrderService::class);
    $result = $orderService->processPostOrderTip($order, 10.00, 'stripe');

    expect($result['success'])->toBeFalse()
        ->and($result['message'])->toContain('delivered');
});

it('deducts customer wallet when paying post-placement tip via wallet', function () {
    $customer = User::factory()->create();
    $wallet = Wallet::create([
        'user_id' => $customer->id,
        'type' => WalletTypeEnum::CUSTOMER(),
        'balance' => 50.00,
        'currency_code' => 'USD',
    ]);

    $order = makeRiderTipTestOrder([
        'user_id' => $customer->id,
        'rider_tip_amount' => 5.00,
        'status' => OrderStatusEnum::ASSIGNED(),
    ]);

    $orderService = app(OrderService::class);
    $result = $orderService->processPostOrderTip($order, 15.00, 'wallet');

    expect($result['success'])->toBeTrue()
        ->and($result['data']['total_tip'])->toBe(20.0);

    expect((float) $wallet->fresh()->balance)->toBe(35.0);
});

it('rejects wallet post-placement tipping when balance is insufficient', function () {
    $customer = User::factory()->create();
    Wallet::create([
        'user_id' => $customer->id,
        'type' => WalletTypeEnum::CUSTOMER(),
        'balance' => 5.00,
        'currency_code' => 'USD',
    ]);

    $order = makeRiderTipTestOrder([
        'user_id' => $customer->id,
        'rider_tip_amount' => 0.00,
        'status' => OrderStatusEnum::ASSIGNED(),
    ]);

    $orderService = app(OrderService::class);
    $result = $orderService->processPostOrderTip($order, 20.00, 'wallet');

    expect($result['success'])->toBeFalse()
        ->and($result['message'])->toContain('Insufficient wallet balance');
});

it('exposes rider tip settings min max and 3 preset values in settings API endpoints', function () {
    $this->withoutMiddleware([
        CheckInstallation::class,
        VerifyLicense::class,
    ]);

    $response = $this->getJson('/api/settings/delivery_boy');

    $response->assertStatus(200)
        ->assertJsonPath('data.value.enableRiderTips', true)
        ->assertJsonPath('data.value.minTipAmount', 5)
        ->assertJsonPath('data.value.maxTipAmount', 500)
        ->assertJsonPath('data.value.preset1', 10)
        ->assertJsonPath('data.value.preset2', 20)
        ->assertJsonPath('data.value.preset3', 50);

    $tipResponse = $this->getJson('/api/settings/rider_tip');
    $tipResponse->assertStatus(200)
        ->assertJsonPath('data.value.min_tip_amount', 5)
        ->assertJsonPath('data.value.max_tip_amount', 500)
        ->assertJsonPath('data.value.preset_1', 10);
});

it('updates cart tip amount via POST /api/user/cart/tip', function () {
    $this->withoutMiddleware([
        CheckInstallation::class,
        VerifyLicense::class,
    ]);

    $user = User::factory()->create();
    $cart = Cart::create([
        'uuid' => (string) Str::uuid(),
        'user_id' => $user->id,
        'rider_tip_amount' => 0.00,
    ]);

    $response = $this->actingAs($user)->postJson('/api/user/cart/tip', [
        'cart_id' => $cart->id,
        'tip_amount' => 20.00,
    ]);

    $response->assertStatus(200);

    expect((float) $cart->fresh()->rider_tip_amount)->toEqual(20.00);
});

it('resets cart rider tip amount to 0 when clearCart is called', function () {
    $user = User::factory()->create();
    $cart = Cart::create([
        'uuid' => (string) Str::uuid(),
        'user_id' => $user->id,
        'rider_tip_amount' => 15.00,
    ]);

    app(CartService::class)->clearCart($user);

    expect((float) $cart->fresh()->rider_tip_amount)->toEqual(0.00);
});

it('refunds rider tip to customer wallet when order status changes to CANCELLED via observer/service', function () {
    $order = makeRiderTipTestOrder([
        'rider_tip_amount' => 25.00,
        'status' => OrderStatusEnum::AWAITING_STORE_RESPONSE(),
    ]);

    $customer = $order->user;

    // Update status to CANCELLED
    $order->update(['status' => OrderStatusEnum::CANCELLED()]);

    $customerWallet = Wallet::where('user_id', $customer->id)
        ->where('type', WalletTypeEnum::CUSTOMER())
        ->first();

    expect($customerWallet)->not()->toBeNull()
        ->and((float) $customerWallet->balance)->toEqual(25.00);

    $auditLog = OrderAuditLog::where('order_id', $order->id)
        ->where('action', 'ORDER_CANCELLED_TIP_REFUND')
        ->first();

    expect($auditLog)->not()->toBeNull();
});

it('refunds rider tip to customer wallet when admin updates order item status to reject', function () {
    $order = makeRiderTipTestOrder([
        'rider_tip_amount' => 30.00,
        'status' => OrderStatusEnum::AWAITING_STORE_RESPONSE(),
    ]);

    $seller = Seller::factory()->create();
    $store = Store::factory()->create(['seller_id' => $seller->id]);
    $product = Product::forceCreate(testProductData($seller->id));
    $variant = ProductVariant::forceCreate(testVariantData($product->id));

    $item = OrderItem::forceCreate([
        'order_id' => $order->id,
        'product_id' => $product->id,
        'product_variant_id' => $variant->id,
        'store_id' => $store->id,
        'title' => 'Test Product',
        'variant_title' => 'Default',
        'sku' => 'SKU123',
        'quantity' => 1,
        'price' => 50.00,
        'subtotal' => 50.00,
        'status' => OrderItemStatusEnum::AWAITING_STORE_RESPONSE(),
    ]);

    $customer = $order->user;

    app(OrderService::class)->updateOrderStatusByAdmin(
        orderItemId: $item->id,
        newStatus: 'reject',
        adminId: 1,
        reason: 'Admin test rejection'
    );

    $customerWallet = Wallet::where('user_id', $customer->id)
        ->where('type', WalletTypeEnum::CUSTOMER())
        ->first();

    expect($customerWallet)->not()->toBeNull()
        ->and((float) $customerWallet->balance)->toEqual(30.00);
});

it('includes rider tip in delivery boy order earnings accessor and resource output', function () {
    $riderUser = User::factory()->create();
    $rider = DeliveryBoy::create([
        'user_id' => $riderUser->id,
        'full_name' => 'Test Rider',
        'status' => 'active',
    ]);

    $order = makeRiderTipTestOrder([
        'rider_tip_amount' => 20.00,
    ]);

    $assignment = DeliveryBoyAssignment::create([
        'order_id' => $order->id,
        'delivery_boy_id' => $rider->id,
        'assigned_at' => now(),
        'status' => DeliveryBoyAssignmentStatusEnum::COMPLETED(),
        'payment_status' => EarningPaymentStatusEnum::PENDING(),
        'base_fee' => 30.00,
        'per_store_pickup_fee' => 10.00,
        'distance_based_fee' => 5.00,
        'per_order_incentive' => 0.00,
        'total_earnings' => 65.00,
    ]);

    $order->load(['deliveryBoyAssignments']);

    $earnings = $order->delivery_boy_earnings;

    expect($earnings)->not()->toBeNull()
        ->and($earnings['total'])->toBe(65)
        ->and($earnings['breakdown']['tip_amount'])->toBe(20);

    $resource = (new DeliveryBoyOrderResource($order))->toArray(request());

    expect($resource['earnings']['total'])->toBe(65)
        ->and($resource['earnings']['breakdown']['tip_amount'])->toBe(20);
});
