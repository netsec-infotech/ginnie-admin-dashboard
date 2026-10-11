<?php

use App\Models\User;
use App\Models\Seller;
use App\Models\Store;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\StoreProductVariant;
use App\Models\Setting;
use App\Models\Cart;
use App\Models\CartItem;
use App\Models\Order;
use App\Models\SellerOrder;
use App\Services\CartService;
use App\Services\OrderService;
use App\Services\PickupService;
use App\Enums\Order\OrderDeliveryTypeEnum;
use App\Enums\Order\OrderStatusEnum;
use App\Enums\Order\OrderItemStatusEnum;
use App\Enums\GuardNameEnum;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Cache;

uses(RefreshDatabase::class);

beforeEach(function () {
    Event::fake();

    // Enable pickup globally
    Setting::forceCreate([
        'variable' => 'pickup',
        'value' => json_encode(['enabled' => true]),
    ]);

    // Create system settings to avoid cart max items validation issues and set systemVendorType
    Setting::forceCreate([
        'variable' => 'system',
        'value' => json_encode([
            'checkoutType' => 'multi_store',
            'maximumItemsAllowedInCart' => 10,
            'systemVendorType' => 'multiple',
        ]),
    ]);

    // Seed subscription decision to bypass middleware redirects
    Setting::forceCreate([
        'variable' => 'subscription',
        'value' => json_encode([
            'enableSubscription' => false,
        ]),
    ]);

    // Create payment settings to enable COD, disable wallet, and enable flutterwavePayment
    Setting::forceCreate([
        'variable' => 'payment',
        'value' => json_encode([
            'cod' => true,
            'wallet' => false,
            'flutterwavePayment' => true,
        ]),
    ]);

    // Create active delivery zone covering the store location
    \App\Models\DeliveryZone::factory()->create([
        'name' => 'Central Zone',
        'slug' => 'central-zone',
        'center_latitude' => 12.97159888,
        'center_longitude' => 77.59456666,
        'radius_km' => 100.0,
        'boundary_json' => null,
        'status' => \App\Enums\ActiveInactiveStatusEnum::ACTIVE(),
    ]);

    // Setup models
    $this->user = User::forceCreate([
        'name' => 'John Doe',
        'email' => 'john.doe@example.com',
        'password' => bcrypt('password'),
        'access_panel' => GuardNameEnum::WEB->value,
    ]);

    $this->seller = Seller::factory()->create();

    $this->store = Store::forceCreate(testStoreData($this->seller->id, [
        'name' => 'Central Market Store',
        'allows_pickup' => true,
        'pickup_instructions' => 'Go to checkout counter 3.',
    ]));

    $this->product = Product::forceCreate(testProductData($this->seller->id, [
        'title' => 'Fresh Milk',
    ]));

    $this->variant = ProductVariant::forceCreate(testVariantData($this->product->id, [
        'title' => '1 Litre',
    ]));

    StoreProductVariant::forceCreate([
        'product_variant_id' => $this->variant->id,
        'store_id' => $this->store->id,
        'sku' => 'MILK-1L',
        'price' => 50,
        'special_price' => 45,
        'cost' => 30,
        'stock' => 10,
    ]);
});

test('cart validation does not check pickup support', function () {
    $this->store->update(['allows_pickup' => false]);

    // Add item to cart
    $cart = Cart::create(['user_id' => $this->user->id, 'uuid' => (string) \Illuminate\Support\Str::uuid()]);
    CartItem::create([
        'cart_id' => $cart->id,
        'store_id' => $this->store->id,
        'product_id' => $this->product->id,
        'product_variant_id' => $this->variant->id,
        'quantity' => 1,
    ]);

    $cartService = app(CartService::class);
    $response = $cartService->getCart($this->user);

    expect($response['success'])->toBeTrue();
});

test('cart pricing returns default charges without a delivery location', function () {
    // Add item to cart
    $cart = Cart::create(['user_id' => $this->user->id, 'uuid' => (string) \Illuminate\Support\Str::uuid()]);
    CartItem::create([
        'cart_id' => $cart->id,
        'store_id' => $this->store->id,
        'product_id' => $this->product->id,
        'product_variant_id' => $this->variant->id,
        'quantity' => 2,
    ]);

    $cartService = app(CartService::class);
    $response = $cartService->getCart($this->user);

    expect($response['success'])->toBeTrue();
    $summary = $response['data']['cart']->payment_summary;
    expect((float)$summary['delivery_charges'])->toEqual(0.0);
    expect((float)$summary['handling_charges'])->toEqual(0.0);
    expect((float)$summary['per_store_drop_off_fee'])->toEqual(0.0);
});

test('checkout creates pickup orders with OTP and no delivery assignment', function () {
    $cart = Cart::create(['user_id' => $this->user->id, 'uuid' => (string) \Illuminate\Support\Str::uuid()]);
    CartItem::create([
        'cart_id' => $cart->id,
        'store_id' => $this->store->id,
        'product_id' => $this->product->id,
        'product_variant_id' => $this->variant->id,
        'quantity' => 1,
    ]);

    $orderService = app(OrderService::class);
    $result = $orderService->createOrder($this->user, [
        'payment_type' => 'flutterwavePayment',
        'delivery_type' => 'pickup',
        'use_wallet' => '0',
        'rush_delivery' => false,
    ]);

    expect($result['success'])->toBeTrue($result['message'] ?? 'No message');
    $order = $result['data'];

    expect($order->status)->toBe('awaiting_store_response');
    
    $sellerOrder = $order->sellerOrders()->first();
    expect($sellerOrder->delivery_type->value)->toBe('pickup');

    $orderItem = $sellerOrder->items()->first()->orderItem;
    expect($orderItem->otp)->not->toBeNull();
    expect(strlen($orderItem->otp))->toBe(6);

    // Verify no rider assignment exists
    $this->assertDatabaseMissing('delivery_boy_assignments', [
        'order_id' => $order->id,
    ]);
});

test('seller can mark order ready for pickup and complete verification via OTP', function () {
    $cart = Cart::create(['user_id' => $this->user->id, 'uuid' => (string) \Illuminate\Support\Str::uuid()]);
    CartItem::create([
        'cart_id' => $cart->id,
        'store_id' => $this->store->id,
        'product_id' => $this->product->id,
        'product_variant_id' => $this->variant->id,
        'quantity' => 1,
    ]);

    $orderService = app(OrderService::class);
    $result = $orderService->createOrder($this->user, [
        'payment_type' => 'flutterwavePayment',
        'delivery_type' => 'pickup',
        'use_wallet' => '0',
        'rush_delivery' => false,
    ]);

    $order = $result['data'];
    $sellerOrder = $order->sellerOrders()->first();
    $orderItem = $sellerOrder->items()->first()->orderItem;
    
    // Move order to accepted so we can test mark ready
    $orderItem->update(['status' => OrderItemStatusEnum::ACCEPTED()]);
    $order->fresh()->update(['status' => OrderStatusEnum::ACCEPTED_BY_SELLER()]);

    $pickupService = app(PickupService::class);

    // 1. Mark ready for pickup
    $res = $pickupService->markReadyForPickup($sellerOrder);
    expect($res['success'])->toBeTrue();
    expect($order->fresh()->status)->toBe(OrderStatusEnum::READY_FOR_PICKUP());
    expect($sellerOrder->fresh()->ready_for_pickup_at)->not->toBeNull();

    // 2. Try incorrect OTP
    $res = $pickupService->verifyPickupOtp($sellerOrder, '000000');
    expect($res['success'])->toBeFalse();
    expect($res['message'])->toContain('Invalid OTP');

    // 3. Verify failure locks after 5 attempts
    $cacheKey = 'pickup_otp_fail:' . $sellerOrder->id;
    Cache::put($cacheKey, 5, 300);
    $res = $pickupService->verifyPickupOtp($sellerOrder, $orderItem->otp);
    expect($res['success'])->toBeFalse();
    expect($res['message'])->toContain('Too many invalid OTP attempts');

    // Clear lock
    Cache::forget($cacheKey);

    // 4. Verify correct OTP
    $res = $pickupService->verifyPickupOtp($sellerOrder, $orderItem->otp);
    expect($res['success'])->toBeTrue();
    expect($sellerOrder->fresh()->picked_up_at)->not->toBeNull();
    expect($orderItem->fresh()->status)->toBe(OrderItemStatusEnum::DELIVERED());
    expect($orderItem->fresh()->otp_verified)->toBeTruthy();
    expect($order->fresh()->status)->toBe(OrderStatusEnum::DELIVERED());
    expect($order->fresh()->payment_status)->toBe('completed');

    // Verify that seller statement is created
    $this->assertDatabaseHas('seller_statements', [
        'order_id' => $order->id,
        'order_item_id' => $orderItem->id,
        'entry_type' => \App\Enums\Seller\SellerSettlementTypeEnum::CREDIT(),
        'settlement_status' => \App\Enums\Seller\SellerSettlementStatusEnum::PENDING(),
        'amount' => $orderItem->subtotal - $orderItem->admin_commission_amount,
    ]);
});

test('checkout fails when global pickup is disabled', function () {
    // Disable pickup globally
    Setting::where('variable', 'pickup')->delete();
    Setting::forceCreate([
        'variable' => 'pickup',
        'value' => json_encode(['enabled' => false]),
    ]);

    $cart = Cart::create(['user_id' => $this->user->id, 'uuid' => (string) \Illuminate\Support\Str::uuid()]);
    CartItem::create([
        'cart_id' => $cart->id,
        'store_id' => $this->store->id,
        'product_id' => $this->product->id,
        'product_variant_id' => $this->variant->id,
        'quantity' => 1,
    ]);

    $orderService = app(OrderService::class);
    $result = $orderService->createOrder($this->user, [
        'payment_type' => 'flutterwavePayment',
        'delivery_type' => 'pickup',
        'use_wallet' => '0',
        'rush_delivery' => false,
    ]);

    expect($result['success'])->toBeFalse();
    expect($result['message'])->toBe(__('messages.pickup_not_enabled_globally'));
});

test('checkout fails when a store in the cart does not support pickup', function () {
    $this->store->update(['allows_pickup' => false]);

    $cart = Cart::create(['user_id' => $this->user->id, 'uuid' => (string) \Illuminate\Support\Str::uuid()]);
    CartItem::create([
        'cart_id' => $cart->id,
        'store_id' => $this->store->id,
        'product_id' => $this->product->id,
        'product_variant_id' => $this->variant->id,
        'quantity' => 1,
    ]);

    $orderService = app(OrderService::class);
    $result = $orderService->createOrder($this->user, [
        'payment_type' => 'flutterwavePayment',
        'delivery_type' => 'pickup',
        'use_wallet' => '0',
        'rush_delivery' => false,
    ]);

    expect($result['success'])->toBeFalse();
    expect($result['message'])->toContain('To place a pickup order, please remove items from the following stores');
});

function makePickupTestOrder(User $user, string $status = 'pending'): Order
{
    return Order::forceCreate([
        'uuid'                  => (string) \Illuminate\Support\Str::uuid(),
        'user_id'               => $user->id,
        'slug'                  => 'order-pickup-'.uniqid(),
        'delivery_zone_id'      => \App\Models\DeliveryZone::first()?->id ?? \App\Models\DeliveryZone::factory()->create()->id,
        'email'                 => $user->email,
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
        'status'                => $status,
        'billing_name'          => $user->name,
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
        'shipping_name'         => $user->name,
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

test('order resource exposes ready_for_pickup_at and picked_up_at for admin and seller', function () {
    $this->store->update(['allows_pickup' => true]);

    $order = makePickupTestOrder($this->user, \App\Enums\Order\OrderStatusEnum::ACCEPTED_BY_SELLER());

    $sellerOrder = SellerOrder::create([
        'order_id' => $order->id,
        'seller_id' => $this->seller->id,
        'total_price' => 100.0,
        'delivery_type' => \App\Enums\Order\OrderDeliveryTypeEnum::PICKUP,
        'ready_for_pickup_at' => now(),
        'picked_up_at' => now()->addMinutes(10),
    ]);

    // Test transformed resource for SellerOrder
    $resourceSeller = new \App\Http\Resources\OrderResource($sellerOrder);
    $dataSeller = $resourceSeller->toArray(request());

    expect($dataSeller)->toHaveKeys(['ready_for_pickup_at', 'picked_up_at', 'delivery_type']);
    expect($dataSeller['delivery_type'])->toBe('pickup');

    // Test transformed resource for Order (Admin side)
    $order->setRelation('sellerOrders', collect([$sellerOrder]));
    $resourceAdmin = new \App\Http\Resources\OrderResource($order);
    $dataAdmin = $resourceAdmin->toArray(request());

    expect($dataAdmin)->toHaveKeys(['ready_for_pickup_at', 'picked_up_at', 'delivery_type']);
    expect($dataAdmin['delivery_type'])->toBe('pickup');
});

test('order grouped listing datatable includes self-pickup badge', function () {
    $this->store->update(['allows_pickup' => true]);

    $order = makePickupTestOrder($this->user, \App\Enums\Order\OrderStatusEnum::ACCEPTED_BY_SELLER());

    $sellerOrder = SellerOrder::create([
        'order_id' => $order->id,
        'seller_id' => $this->seller->id,
        'total_price' => 100.0,
        'delivery_type' => \App\Enums\Order\OrderDeliveryTypeEnum::PICKUP,
    ]);

    // Create an admin user
    $adminUser = User::forceCreate([
        'name' => 'Admin User',
        'email' => 'admin@example.com',
        'password' => bcrypt('password'),
        'access_panel' => GuardNameEnum::ADMIN->value,
    ]);

    $this->actingAs($adminUser, GuardNameEnum::ADMIN->value);
    config(['app.url' => 'http://127.0.0.1:8000']);
    \Illuminate\Support\Facades\URL::forceRootUrl('http://127.0.0.1:8000');
    $response = $this->get('/admin/orders/list-datatable');
    $response->assertStatus(200);

    $data = $response->json('data');
    expect($data)->not->toBeEmpty();
    expect($data[0]['order_id'])->toContain('Self-Pickup');
});

test('self-pickup orders are not shown in delivery boy available orders list', function () {
    config(['app.url' => 'http://127.0.0.1:8000']);
    \Illuminate\Support\Facades\URL::forceRootUrl('http://127.0.0.1:8000');
    $this->store->update(['allows_pickup' => true]);

    // 1. Create a self-pickup order
    $pickupOrder = makePickupTestOrder($this->user, \App\Enums\Order\OrderStatusEnum::READY_FOR_PICKUP());
    $sellerPickupOrder = SellerOrder::create([
        'order_id' => $pickupOrder->id,
        'seller_id' => $this->seller->id,
        'total_price' => 100.0,
        'delivery_type' => \App\Enums\Order\OrderDeliveryTypeEnum::PICKUP,
    ]);

    // 2. Create a regular delivery order
    $deliveryOrder = makePickupTestOrder($this->user, \App\Enums\Order\OrderStatusEnum::READY_FOR_PICKUP());
    $sellerDeliveryOrder = SellerOrder::create([
        'order_id' => $deliveryOrder->id,
        'seller_id' => $this->seller->id,
        'total_price' => 100.0,
        'delivery_type' => \App\Enums\Order\OrderDeliveryTypeEnum::DELIVERY,
    ]);

    // Create a delivery boy user and row
    $riderUser = User::forceCreate([
        'name' => 'Rider User',
        'email' => 'rider@example.com',
        'password' => bcrypt('password'),
    ]);

    \Illuminate\Support\Facades\DB::table('delivery_boys')->insert([
        'user_id' => $riderUser->id,
        'status' => 'active',
        'verification_status' => 'verified',
        'delivery_zone_id' => $pickupOrder->delivery_zone_id,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    \Laravel\Sanctum\Sanctum::actingAs($riderUser);

    // Call the available orders API
    $response = $this->getJson('/api/delivery-boy/orders/available');
    $response->assertStatus(200);

    $data = $response->json('data');
    
    // We expect only 1 order in the list (the delivery one), and the pickup order should be excluded.
    expect($data['orders'])->toHaveCount(1);
    expect($data['orders'][0]['id'])->toBe($deliveryOrder->id);
});

test('OrderObserver does not notify delivery boys if order is a pickup order', function () {
    \Illuminate\Support\Facades\Notification::fake();

    // Create a delivery boy in the zone
    $riderUser = User::forceCreate([
        'name' => 'Rider User 2',
        'email' => 'rider2@example.com',
        'password' => bcrypt('password'),
    ]);

    \Illuminate\Support\Facades\DB::table('delivery_boys')->insert([
        'user_id' => $riderUser->id,
        'status' => 'active',
        'verification_status' => 'verified',
        'delivery_zone_id' => \App\Models\DeliveryZone::first()->id,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    // 1. Create a pickup order and call the observer's updated method
    $pickupOrder = makePickupTestOrder($this->user, \App\Enums\Order\OrderStatusEnum::ACCEPTED_BY_SELLER());
    $sellerPickupOrder = SellerOrder::create([
        'order_id' => $pickupOrder->id,
        'seller_id' => $this->seller->id,
        'total_price' => 100.0,
        'delivery_type' => \App\Enums\Order\OrderDeliveryTypeEnum::PICKUP,
    ]);

    $pickupOrder->status = OrderStatusEnum::READY_FOR_PICKUP();
    $pickupOrder->save(); // This populates changes so wasChanged('status') returns true

    $observer = new \App\Observers\OrderObserver();
    $observer->updated($pickupOrder);

    \Illuminate\Support\Facades\Notification::assertNotSentTo(
        $riderUser,
        \App\Notifications\Delivery\OrderReadyForPickupNotification::class
    );

    // 2. Create a delivery order and call the observer's updated method
    $deliveryOrder = makePickupTestOrder($this->user, \App\Enums\Order\OrderStatusEnum::ACCEPTED_BY_SELLER());
    $sellerDeliveryOrder = SellerOrder::create([
        'order_id' => $deliveryOrder->id,
        'seller_id' => $this->seller->id,
        'total_price' => 100.0,
        'delivery_type' => \App\Enums\Order\OrderDeliveryTypeEnum::DELIVERY,
    ]);

    $deliveryOrder->status = OrderStatusEnum::READY_FOR_PICKUP();
    $deliveryOrder->save();

    $observer->updated($deliveryOrder);

    \Illuminate\Support\Facades\Notification::assertSentTo(
        $riderUser,
        \App\Notifications\Delivery\OrderReadyForPickupNotification::class
    );
});

test('admin order show page does not render action buttons for self-pickup orders', function () {
    $this->store->update(['allows_pickup' => true]);

    // Create a self-pickup order
    $pickupOrder = makePickupTestOrder($this->user, \App\Enums\Order\OrderStatusEnum::PENDING());
    $sellerPickupOrder = SellerOrder::create([
        'order_id' => $pickupOrder->id,
        'seller_id' => $this->seller->id,
        'total_price' => 100.0,
        'delivery_type' => \App\Enums\Order\OrderDeliveryTypeEnum::PICKUP,
    ]);

    // Create a delivery order
    $deliveryOrder = makePickupTestOrder($this->user, \App\Enums\Order\OrderStatusEnum::PENDING());
    $sellerDeliveryOrder = SellerOrder::create([
        'order_id' => $deliveryOrder->id,
        'seller_id' => $this->seller->id,
        'total_price' => 100.0,
        'delivery_type' => \App\Enums\Order\OrderDeliveryTypeEnum::DELIVERY,
    ]);

    // Create an admin user
    $adminUser = User::forceCreate([
        'name' => 'Admin User',
        'email' => 'admin@example.com',
        'password' => bcrypt('password'),
        'access_panel' => GuardNameEnum::ADMIN->value,
    ]);

    // Set up roles and permissions on both admin and web guards
    $permissionViewAdmin = \Spatie\Permission\Models\Permission::findOrCreate(\App\Enums\AdminPermissionEnum::ORDER_VIEW(), 'admin');
    $permissionEditAdmin = \Spatie\Permission\Models\Permission::findOrCreate(\App\Enums\AdminPermissionEnum::ORDER_EDIT(), 'admin');
    $permissionViewWeb = \Spatie\Permission\Models\Permission::findOrCreate(\App\Enums\AdminPermissionEnum::ORDER_VIEW(), 'web');
    $permissionEditWeb = \Spatie\Permission\Models\Permission::findOrCreate(\App\Enums\AdminPermissionEnum::ORDER_EDIT(), 'web');
    $adminUser->givePermissionTo($permissionViewAdmin, $permissionEditAdmin, $permissionViewWeb, $permissionEditWeb);

    $this->actingAs($adminUser, GuardNameEnum::ADMIN->value);

    // 1. Fetch pickup order show page
    $response = $this->get("/admin/orders/{$pickupOrder->id}");
    $response->assertStatus(200);

    // Assert "Mark Payment Received" and "Force Cancel" are not in the response
    $response->assertDontSee('id="btnMarkPaymentReceived"', false);
    $response->assertDontSee('data-bs-target="#adminForceCancelModal"', false);
    $response->assertDontSee('<th>Actions</th>', false);

    // 2. Fetch delivery order show page
    $response = $this->get("/admin/orders/{$deliveryOrder->id}");
    $response->assertStatus(200);

    // Assert "Mark Payment Received" and "Force Cancel" are in the response
    $response->assertSee('id="btnMarkPaymentReceived"', false);
    $response->assertSee('data-bs-target="#adminForceCancelModal"', false);
    $response->assertSee('Actions');
});

test('applying promo code on pickup cart via API works and returns promo_applied details', function () {
    // Create a flat promo code
    $promo = \App\Models\Promo::create([
        'code' => 'SAVE10',
        'description' => 'Save 10',
        'discount_type' => 'flat',
        'discount_amount' => 10.00,
        'promo_mode' => 'instant',
        'usage_count' => 0,
        'min_order_total' => 20.00,
        'start_date' => now()->subDay(),
        'end_date' => now()->addDay(),
    ]);

    \Laravel\Sanctum\Sanctum::actingAs($this->user);

    // Add item to cart
    $cart = Cart::create(['user_id' => $this->user->id, 'uuid' => (string) \Illuminate\Support\Str::uuid()]);
    CartItem::create([
        'cart_id' => $cart->id,
        'store_id' => $this->store->id,
        'product_id' => $this->product->id,
        'product_variant_id' => $this->variant->id,
        'quantity' => 2, // 2 * 45 = 90
    ]);

    $response = $this->getJson('/api/cart?promo_code=SAVE10');

    $response->assertStatus(200);
    $data = $response->json('data');
    expect($data['payment_summary']['promo_code'])->toBe('SAVE10');
    expect((float)$data['payment_summary']['promo_discount'])->toEqual(10.0);
    expect($data['payment_summary']['promo_applied'])->not->toBeEmpty();
    expect($data['payment_summary']['promo_applied']['code'])->toBe('SAVE10');
});

test('seller order and admin order show panels display actual net payable amount when promo and wallet are used', function () {
    $this->store->update(['allows_pickup' => true]);

    // Create a flat promo code
    $promo = \App\Models\Promo::create([
        'code' => 'SAVE10',
        'description' => 'Save 10',
        'discount_type' => 'flat',
        'discount_amount' => 10.00,
        'promo_mode' => 'instant',
        'usage_count' => 0,
        'min_order_total' => 20.00,
        'start_date' => now()->subDay(),
        'end_date' => now()->addDay(),
    ]);

    // Create system settings to avoid wallet validation issues
    \App\Models\Setting::where('variable', 'payment')->update([
        'value' => json_encode([
            'cod' => true,
            'wallet' => true,
            'flutterwavePayment' => true,
        ]),
    ]);

    // Create a wallet and top it up
    $wallet = \App\Models\Wallet::firstOrCreate(
        ['user_id' => $this->user->id],
        ['balance' => 50.00]
    );
    $wallet->update(['balance' => 50.00]);

    // Add item to cart
    $cart = Cart::create(['user_id' => $this->user->id, 'uuid' => (string) \Illuminate\Support\Str::uuid()]);
    CartItem::create([
        'cart_id' => $cart->id,
        'store_id' => $this->store->id,
        'product_id' => $this->product->id,
        'product_variant_id' => $this->variant->id,
        'quantity' => 2, // subtotal = 90
    ]);

    // Create order using order service with wallet and promo
    $orderService = app(OrderService::class);
    $result = $orderService->createOrder($this->user, [
        'payment_type' => 'flutterwavePayment',
        'delivery_type' => 'pickup',
        'use_wallet' => '1',
        'promo_code' => 'SAVE10',
        'rush_delivery' => false,
    ]);

    expect($result['success'])->toBeTrue($result['message'] ?? '');
    $order = $result['data'];

    // E.g. subtotal = 90, promo = 10, wallet = 50, payable = 30
    expect((float)$order->subtotal)->toEqual(90.0);
    expect((float)$order->promo_discount)->toEqual(10.0);
    expect((float)$order->wallet_balance)->toEqual(50.0);
    expect((float)$order->total_payable)->toEqual(30.0);

    // Eager load relations for SellerOrder
    $sellerOrder = SellerOrder::where('order_id', $order->id)->first();
    expect($sellerOrder)->not->toBeNull();

    // Verify SellerOrderResource (OrderResource wrapping SellerOrder)
    $resource = new \App\Http\Resources\OrderResource($sellerOrder);
    $serialized = $resource->toArray(request());

    expect((float)$serialized['subtotal'])->toEqual(90.0);
    expect((float)$serialized['promo_discount'])->toEqual(10.0);
    expect((float)$serialized['wallet_balance'])->toEqual(50.0);
    expect((float)$serialized['total_payable'])->toEqual(30.0);
    expect((float)$serialized['final_total'])->toEqual(80.0);

    // Test Seller Order Detail page
    $sellerUser = $this->seller->user;
    $this->actingAs($sellerUser, GuardNameEnum::SELLER->value);
    $response = $this->get("/seller/orders/{$sellerOrder->id}");
    $response->assertStatus(200);

    // Assert actual net payable amount ($30.00) is visible on the page
    $response->assertSee('$30.00'); // total payable
    $response->assertSee('-$50.00'); // wallet used
    $response->assertSee('-$10.00'); // promo discount
});

test('checkout fails when pickup order has COD payment option', function () {
    $cart = Cart::create(['user_id' => $this->user->id, 'uuid' => (string) \Illuminate\Support\Str::uuid()]);
    CartItem::create([
        'cart_id' => $cart->id,
        'store_id' => $this->store->id,
        'product_id' => $this->product->id,
        'product_variant_id' => $this->variant->id,
        'quantity' => 1,
    ]);

    $orderService = app(OrderService::class);
    $result = $orderService->createOrder($this->user, [
        'payment_type' => 'cod',
        'delivery_type' => 'pickup',
        'use_wallet' => '0',
        'rush_delivery' => false,
    ]);

    expect($result['success'])->toBeFalse();
    expect($result['message'])->toBe(__('messages.pickup_no_cod'));
});

test('seller statement is not created when a COD pickup order is delivered', function () {
    // Manually force create a pickup order with COD payment method (bypassing validation)
    $order = makePickupTestOrder($this->user, \App\Enums\Order\OrderStatusEnum::ACCEPTED_BY_SELLER());
    $order->update(['payment_method' => 'cod']);

    $sellerOrder = SellerOrder::create([
        'order_id' => $order->id,
        'seller_id' => $this->seller->id,
        'total_price' => 100.0,
        'delivery_type' => \App\Enums\Order\OrderDeliveryTypeEnum::PICKUP,
    ]);

    $orderItem = \App\Models\OrderItem::create([
        'order_id' => $order->id,
        'store_id' => $this->store->id,
        'product_id' => $this->product->id,
        'product_variant_id' => $this->variant->id,
        'quantity' => 1,
        'price' => 100.0,
        'subtotal' => 100.0,
        'admin_commission_amount' => 10.0,
        'status' => \App\Enums\Order\OrderItemStatusEnum::ACCEPTED(),
    ]);

    \App\Models\SellerOrderItem::create([
        'seller_order_id' => $sellerOrder->id,
        'product_id' => $this->product->id,
        'product_variant_id' => $this->variant->id,
        'order_item_id' => $orderItem->id,
        'quantity' => 1,
        'price' => 100.0,
    ]);

    $orderService = app(OrderService::class);
    $orderService->deliverOrderItemByPickup($orderItem, $this->seller->id);

    // Verify that NO CREDIT statement is created
    $this->assertDatabaseMissing('seller_statements', [
        'order_id' => $order->id,
        'order_item_id' => $orderItem->id,
        'entry_type' => \App\Enums\Seller\SellerSettlementTypeEnum::CREDIT(),
    ]);

    // Verify that a DEBIT statement is created for the admin commission
    $this->assertDatabaseHas('seller_statements', [
        'order_id' => $order->id,
        'order_item_id' => $orderItem->id,
        'entry_type' => \App\Enums\Seller\SellerSettlementTypeEnum::DEBIT(),
        'amount' => 10.0,
    ]);
});

test('cart delivery charges are calculated based on user latest address when coordinates are not passed', function () {
    // 1. Setup a saved address for the user within the delivery zone
    \App\Models\Address::create([
        'user_id' => $this->user->id,
        'address_line1' => 'Main Street',
        'latitude' => 12.97159888,
        'longitude' => 77.59456666,
    ]);

    // 2. Set delivery zone charges
    $zone = \App\Models\DeliveryZone::first();
    $zone->update([
        'regular_delivery_charges' => 25.00,
        'handling_charges' => 5.00,
    ]);

    // 3. Add item to cart
    $cart = Cart::create(['user_id' => $this->user->id, 'uuid' => (string) \Illuminate\Support\Str::uuid()]);
    CartItem::create([
        'cart_id' => $cart->id,
        'store_id' => $this->store->id,
        'product_id' => $this->product->id,
        'product_variant_id' => $this->variant->id,
        'quantity' => 1,
    ]);

    // 4. Call getCart with NO coordinates or address_id
    $cartService = app(CartService::class);
    $response = $cartService->getCart($this->user);

    // 5. Assert that the delivery and handling charges are correctly resolved via the fallback address
    expect($response['success'])->toBeTrue();
    $summary = $response['data']['cart']->payment_summary;
    expect((float)$summary['delivery_charges'])->toEqual(25.00);
    expect((float)$summary['handling_charges'])->toEqual(5.00);
    expect((float)$summary['total_delivery_charges'])->toEqual(25.00);
});

test('pickup order has null address fields and exposes store details and otp in item resource', function () {
    $cart = Cart::create(['user_id' => $this->user->id, 'uuid' => (string) \Illuminate\Support\Str::uuid()]);
    CartItem::create([
        'cart_id' => $cart->id,
        'store_id' => $this->store->id,
        'product_id' => $this->product->id,
        'product_variant_id' => $this->variant->id,
        'quantity' => 1,
    ]);

    $orderService = app(OrderService::class);
    $result = $orderService->createOrder($this->user, [
        'payment_type' => 'flutterwavePayment',
        'delivery_type' => 'pickup',
        'use_wallet' => '0',
        'rush_delivery' => false,
    ]);

    expect($result['success'])->toBeTrue($result['message'] ?? 'No message');
    $order = $result['data']->fresh();

    // Verify order address fields are null
    expect($order->shipping_address_1)->toBeNull();
    expect($order->shipping_city)->toBeNull();
    expect($order->billing_address_1)->toBeNull();
    expect($order->billing_city)->toBeNull();

    // Verify OrderResource does not have pickup_otp
    $orderResource = new \App\Http\Resources\User\OrderResource($order);
    $responseData = $orderResource->toArray(request());

    expect(array_key_exists('pickup_otp', $responseData))->toBeFalse();

    // Verify OrderItemResource has store address details and otp
    $orderItem = $order->items->first();
    $orderItemResource = new \App\Http\Resources\User\OrderItemResource($orderItem);
    $itemData = $orderItemResource->toArray(request());

    expect($itemData['otp'])->not->toBeNull();
    expect($itemData['store'])->toHaveKey('address');
    expect($itemData['store']['address'])->toBe($this->store->address);
    expect($itemData['store']['pickup_instructions'])->toBe($this->store->pickup_instructions);
});


