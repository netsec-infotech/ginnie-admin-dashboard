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
use App\Models\OrderItem;
use App\Models\SellerStatement;
use App\Services\CartService;
use App\Services\OrderService;
use App\Services\PickupService;
use App\Services\SellerStatementService;
use App\Services\WalletService;
use App\Enums\Order\OrderDeliveryTypeEnum;
use App\Enums\Order\OrderStatusEnum;
use App\Enums\Order\OrderItemStatusEnum;
use App\Enums\GuardNameEnum;
use App\Enums\Payment\PaymentTypeEnum;
use App\Enums\Seller\SellerSettlementTypeEnum;
use App\Enums\Seller\SellerSettlementStatusEnum;
use App\Enums\Wallet\WalletTypeEnum;
use App\Enums\DefaultSystemRolesEnum;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

beforeEach(function () {
    Event::fake();

    $this->withoutMiddleware(\App\Http\Middleware\VerifyLicense::class);

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

    // Create payment settings to enable COD, disable wallet
    Setting::forceCreate([
        'variable' => 'payment',
        'value' => json_encode([
            'cod' => true,
            'wallet' => false,
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
        'access_panel' => GuardNameEnum::WEB(),
    ]);

    $this->seller = Seller::factory()->create();

    $this->store = Store::forceCreate(testStoreData($this->seller->id, [
        'name' => 'Central Market Store',
        'allows_pickup' => true,
        'is_pickup_cod_enabled' => false,
        'pickup_instructions' => 'Go to counter 3.',
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
        'price' => 100,
        'special_price' => 100,
        'cost' => 50,
        'stock' => 10,
    ]);
});

function createDummyOrder(int $userId): Order
{
    return Order::forceCreate([
        'uuid'                  => (string) Str::uuid(),
        'user_id'               => $userId,
        'slug'                  => 'order-test-'.uniqid(),
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
        'billing_landmark'     => 'Test landmark',
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

test('checkout fails when store is_pickup_cod_enabled is false', function () {
    $cart = Cart::create(['user_id' => $this->user->id, 'uuid' => (string) \Illuminate\Support\Str::uuid()]);
    CartItem::create([
        'cart_id' => $cart->id,
        'store_id' => $this->store->id,
        'product_id' => $this->product->id,
        'product_variant_id' => $this->variant->id,
        'quantity' => 1,
    ]);

    $this->store->update(['is_pickup_cod_enabled' => false]);

    // Instantiate FormRequest manually and validate
    $request = \App\Http\Requests\User\Order\CreateOrderRequest::create('/api/user/orders', 'POST', [
        'payment_type' => 'cod',
        'delivery_type' => 'pickup',
        'use_wallet' => '0',
        'rush_delivery' => false,
    ]);
    $request->setUserResolver(fn() => $this->user);

    $validator = Validator::make($request->all(), $request->rules(), $request->messages());
    $request->withValidator($validator);

    expect($validator->fails())->toBeTrue();
    expect($validator->errors()->has('payment_type'))->toBeTrue();
});

test('checkout succeeds when store is_pickup_cod_enabled is true', function () {
    $cart = Cart::create(['user_id' => $this->user->id, 'uuid' => (string) \Illuminate\Support\Str::uuid()]);
    CartItem::create([
        'cart_id' => $cart->id,
        'store_id' => $this->store->id,
        'product_id' => $this->product->id,
        'product_variant_id' => $this->variant->id,
        'quantity' => 1,
    ]);

    $this->store->update(['is_pickup_cod_enabled' => true]);

    $request = \App\Http\Requests\User\Order\CreateOrderRequest::create('/api/user/orders', 'POST', [
        'payment_type' => 'cod',
        'delivery_type' => 'pickup',
        'use_wallet' => '0',
        'rush_delivery' => false,
    ]);
    $request->setUserResolver(fn() => $this->user);

    $validator = Validator::make($request->all(), $request->rules(), $request->messages());
    $request->withValidator($validator);

    expect($validator->fails())->toBeFalse();
});

test('order delivery creates debit statement instead of credit statement for pickup COD', function () {
    $cart = Cart::create(['user_id' => $this->user->id, 'uuid' => (string) \Illuminate\Support\Str::uuid()]);
    CartItem::create([
        'cart_id' => $cart->id,
        'store_id' => $this->store->id,
        'product_id' => $this->product->id,
        'product_variant_id' => $this->variant->id,
        'quantity' => 1,
    ]);

    $this->store->update(['is_pickup_cod_enabled' => true]);

    // Force set category commission to 10%
    $this->product->category->update(['commission' => 10]);

    $orderService = app(OrderService::class);
    $result = $orderService->createOrder($this->user, [
        'payment_type' => 'cod',
        'delivery_type' => 'pickup',
        'use_wallet' => '0',
        'rush_delivery' => false,
    ]);

    expect($result['success'])->toBeTrue($result['message'] ?? 'Failed to place order');
    
    $order = $result['order'] ?? $result['data'];
    $sellerOrder = $order->sellerOrders()->first();
    $orderItem = $sellerOrder->items()->first()->orderItem;

    expect((float)$orderItem->admin_commission_amount)->toEqual(10.0);

    // Accept order and deliver it
    $orderItem->update(['status' => OrderItemStatusEnum::ACCEPTED()]);
    $order->fresh()->update(['status' => OrderStatusEnum::ACCEPTED_BY_SELLER()]);

    $pickupService = app(PickupService::class);
    $res = $pickupService->markReadyForPickup($sellerOrder);
    expect($res['success'])->toBeTrue();

    // Verify OTP to trigger delivery & statement creation
    $res = $pickupService->verifyPickupOtp($sellerOrder, $orderItem->otp);
    expect($res['success'])->toBeTrue();

    // 1. Verify no credit statement was created for this order item
    $this->assertDatabaseMissing('seller_statements', [
        'order_item_id' => $orderItem->id,
        'entry_type' => SellerSettlementTypeEnum::CREDIT(),
    ]);

    // 2. Verify a debit statement was created for the admin commission
    $this->assertDatabaseHas('seller_statements', [
        'order_item_id' => $orderItem->id,
        'entry_type' => SellerSettlementTypeEnum::DEBIT(),
        'amount' => 10.00,
        'settlement_status' => SellerSettlementStatusEnum::PENDING(),
    ]);
});

test('settlement nets credit statement against pending debit statement', function () {
    // Setup Dummy Orders
    $order1 = createDummyOrder($this->user->id);
    $order2 = createDummyOrder($this->user->id);

    // 1. Setup a pending debit statement (COD pickup commission) of $10.00
    $debitItem = OrderItem::create([
        'order_id' => $order1->id,
        'product_id' => $this->product->id,
        'product_variant_id' => $this->variant->id,
        'store_id' => $this->store->id,
        'title' => $this->product->title,
        'variant_title' => 'Default',
        'gift_card_discount' => 0,
        'admin_commission_amount' => 10.00,
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
    ]);
    
    $debitStatement = SellerStatement::create([
        'seller_id' => $this->seller->id,
        'entry_type' => SellerSettlementTypeEnum::DEBIT(),
        'amount' => 10.00,
        'order_id' => $debitItem->order_id,
        'order_item_id' => $debitItem->id,
        'reference_type' => 'order_item_commission',
        'reference_id' => $debitItem->id,
        'settlement_status' => SellerSettlementStatusEnum::PENDING(),
        'posted_at' => now(),
    ]);

    // 2. Setup a pending credit statement (Online order earning) of $45.00
    $creditItem = OrderItem::create([
        'order_id' => $order2->id,
        'product_id' => $this->product->id,
        'product_variant_id' => $this->variant->id,
        'store_id' => $this->store->id,
        'title' => $this->product->title,
        'variant_title' => 'Default',
        'gift_card_discount' => 0,
        'admin_commission_amount' => 5.00,
        'seller_commission_amount' => 0,
        'commission_settled' => '0',
        'return_eligible' => false,
        'returnable_days' => 0,
        'is_cancelable' => false,
        'cancelable_till' => null,
        'discounted_price' => 50,
        'promo_discount' => 0,
        'discount' => 0,
        'tax_amount' => 0,
        'tax_percent' => 0,
        'sku' => 'SKU-200',
        'quantity' => 1,
        'price' => 50,
        'subtotal' => 50,
        'status' => OrderItemStatusEnum::COLLECTED(),
    ]);
    
    $creditStatement = SellerStatement::create([
        'seller_id' => $this->seller->id,
        'entry_type' => SellerSettlementTypeEnum::CREDIT(),
        'amount' => 45.00, // Gross earning: 50 - 5 = 45
        'order_id' => $creditItem->order_id,
        'order_item_id' => $creditItem->id,
        'reference_type' => 'order_item_delivery',
        'reference_id' => $creditItem->id,
        'settlement_status' => SellerSettlementStatusEnum::PENDING(),
        'posted_at' => now(),
    ]);

    // Initialize seller wallet at 0
    $walletService = app(WalletService::class);
    $walletService->getWallet($this->seller->user_id, WalletTypeEnum::SELLER);

    // Create Super Admin user & assign role for route authorization under web guard
    $superAdminRole = \Spatie\Permission\Models\Role::firstOrCreate([
        'name' => DefaultSystemRolesEnum::SUPER_ADMIN(),
        'guard_name' => 'web'
    ]);
    
    $adminUser = User::forceCreate([
        'name' => 'Admin User',
        'email' => 'admin@example.com',
        'password' => bcrypt('password'),
        'access_panel' => GuardNameEnum::ADMIN(),
    ]);
    $adminUser->assignRole($superAdminRole);

    // Call settlement route via HTTP POST acting as web guard
    $response = $this->actingAs($adminUser)
        ->postJson("/admin/commissions/{$creditStatement->id}/settle");
    
    $response->assertStatus(200);

    // 3. Verify Credit and Debit statements are marked settled
    expect($creditStatement->fresh()->settlement_status)->toBe(SellerSettlementStatusEnum::SETTLED());
    expect($debitStatement->fresh()->settlement_status)->toBe(SellerSettlementStatusEnum::SETTLED());

    // 4. Verify seller's wallet is credited only by the net amount: $45.00 - $10.00 = $35.00
    $wallet = $walletService->getWallet($this->seller->user_id, WalletTypeEnum::SELLER)['data'];
    expect((float)$wallet->balance)->toEqual(35.00);

    // 5. Verify transaction log exists with the correct net offset description
    $transaction = \App\Models\WalletTransaction::where('wallet_id', $wallet->id)->first();
    expect($transaction)->not->toBeNull();
    expect((float)$transaction->amount)->toEqual(35.00);
    expect($transaction->description)->toContain('Gross:');
    expect($transaction->description)->toContain('Deducted COD Commissions:');
    expect($transaction->description)->toContain('Net Payout:');
});

test('settlement splits debit statement if credit is smaller than pending debit', function () {
    // Setup Dummy Orders
    $order1 = createDummyOrder($this->user->id);
    $order2 = createDummyOrder($this->user->id);

    // 1. Setup a pending debit statement of $50.00
    $debitItem = OrderItem::create([
        'order_id' => $order1->id,
        'product_id' => $this->product->id,
        'product_variant_id' => $this->variant->id,
        'store_id' => $this->store->id,
        'title' => $this->product->title,
        'variant_title' => 'Default',
        'gift_card_discount' => 0,
        'admin_commission_amount' => 50.00,
        'seller_commission_amount' => 0,
        'commission_settled' => '0',
        'return_eligible' => false,
        'returnable_days' => 0,
        'is_cancelable' => false,
        'cancelable_till' => null,
        'discounted_price' => 200,
        'promo_discount' => 0,
        'discount' => 0,
        'tax_amount' => 0,
        'tax_percent' => 0,
        'sku' => 'SKU-100',
        'quantity' => 1,
        'price' => 200,
        'subtotal' => 200,
        'status' => OrderItemStatusEnum::COLLECTED(),
    ]);
    
    $debitStatement = SellerStatement::create([
        'seller_id' => $this->seller->id,
        'entry_type' => SellerSettlementTypeEnum::DEBIT(),
        'amount' => 50.00,
        'order_id' => $debitItem->order_id,
        'order_item_id' => $debitItem->id,
        'reference_type' => 'order_item_commission',
        'reference_id' => $debitItem->id,
        'settlement_status' => SellerSettlementStatusEnum::PENDING(),
        'posted_at' => now(),
    ]);

    // 2. Setup a pending credit statement of $30.00
    $creditItem = OrderItem::create([
        'order_id' => $order2->id,
        'product_id' => $this->product->id,
        'product_variant_id' => $this->variant->id,
        'store_id' => $this->store->id,
        'title' => $this->product->title,
        'variant_title' => 'Default',
        'gift_card_discount' => 0,
        'admin_commission_amount' => 5.00,
        'seller_commission_amount' => 0,
        'commission_settled' => '0',
        'return_eligible' => false,
        'returnable_days' => 0,
        'is_cancelable' => false,
        'cancelable_till' => null,
        'discounted_price' => 35,
        'promo_discount' => 0,
        'discount' => 0,
        'tax_amount' => 0,
        'tax_percent' => 0,
        'sku' => 'SKU-200',
        'quantity' => 1,
        'price' => 35,
        'subtotal' => 35,
        'status' => OrderItemStatusEnum::COLLECTED(),
    ]);
    
    $creditStatement = SellerStatement::create([
        'seller_id' => $this->seller->id,
        'entry_type' => SellerSettlementTypeEnum::CREDIT(),
        'amount' => 30.00,
        'order_id' => $creditItem->order_id,
        'order_item_id' => $creditItem->id,
        'reference_type' => 'order_item_delivery',
        'reference_id' => $creditItem->id,
        'settlement_status' => SellerSettlementStatusEnum::PENDING(),
        'posted_at' => now(),
    ]);

    $walletService = app(WalletService::class);
    $walletService->getWallet($this->seller->user_id, WalletTypeEnum::SELLER);

    // Create Super Admin user & assign role for route authorization under web guard name
    $superAdminRole = \Spatie\Permission\Models\Role::firstOrCreate([
        'name' => DefaultSystemRolesEnum::SUPER_ADMIN(),
        'guard_name' => 'web'
    ]);
    
    $adminUser = User::forceCreate([
        'name' => 'Admin User',
        'email' => 'admin@example.com',
        'password' => bcrypt('password'),
        'access_panel' => GuardNameEnum::ADMIN(),
    ]);
    $adminUser->assignRole($superAdminRole);

    // Call settlement route via HTTP POST acting as web guard
    $response = $this->actingAs($adminUser)
        ->postJson("/admin/commissions/{$creditStatement->id}/settle");
    
    $response->assertStatus(200);

    // 3. Verify Credit statement is marked settled
    expect($creditStatement->fresh()->settlement_status)->toBe(SellerSettlementStatusEnum::SETTLED());

    // 4. Verify original debit statement is updated to remaining $20.00 and stays PENDING
    $debitStatement = $debitStatement->fresh();
    expect((float)$debitStatement->amount)->toEqual(20.00);
    expect($debitStatement->settlement_status)->toBe(SellerSettlementStatusEnum::PENDING());

    // 5. Verify a new settled debit statement of $30.00 is created
    $this->assertDatabaseHas('seller_statements', [
        'seller_id' => $this->seller->id,
        'entry_type' => SellerSettlementTypeEnum::DEBIT(),
        'amount' => 30.00,
        'settlement_status' => SellerSettlementStatusEnum::SETTLED(),
        'settlement_reference' => 'STMT-ADJUST-' . $creditStatement->id,
    ]);

    // 6. Verify seller's wallet is credited by $0.00
    $wallet = $walletService->getWallet($this->seller->user_id, WalletTypeEnum::SELLER)['data'];
    expect((float)$wallet->balance)->toEqual(0.00);

    // 7. Verify transaction log exists of amount $0.00
    $transaction = \App\Models\WalletTransaction::where('wallet_id', $wallet->id)->first();
    expect($transaction)->not->toBeNull();
    expect((float)$transaction->amount)->toEqual(0.00);
    expect($transaction->description)->toContain('Gross:');
    expect($transaction->description)->toContain('Deducted COD Commissions:');
    expect($transaction->description)->toContain('Net Payout: $0.00');
});

test('disabling pickup in configuration forces pickup COD to be disabled', function () {
    $this->withoutMiddleware([
        \App\Http\Middleware\SellerQueryTokenAuthenticate::class,
        \App\Http\Middleware\ValidateSeller::class,
    ]);

    // Assign seller role to authorization user
    $sellerRole = \Spatie\Permission\Models\Role::firstOrCreate([
        'name' => DefaultSystemRolesEnum::SELLER(),
        'guard_name' => 'web'
    ]);
    $this->seller->user->assignRole($sellerRole);

    // 1. Initially enable pickup and COD
    $this->store->update([
        'allows_pickup' => true,
        'is_pickup_cod_enabled' => true,
    ]);

    // Call storeConfiguration route via HTTP POST updating allows_pickup to false
    $response = $this->actingAs($this->seller->user)
        ->postJson("/seller/stores/{$this->store->id}/configuration", [
            'status' => 'online',
            'allows_pickup' => '0',
            'is_pickup_cod_enabled' => '1', // Submitted as true, but should be forced false
            'return_replacement_policy' => 'Policy details',
            'refund_policy' => 'Policy details',
            'terms_and_conditions' => 'Terms details',
            'delivery_policy' => 'Policy details',
        ]);
    
    $response->assertStatus(200);

    // 2. Verify store allows_pickup is false and is_pickup_cod_enabled is forced false
    $store = $this->store->fresh();
    expect($store->allows_pickup)->toBeFalse();
    expect($store->is_pickup_cod_enabled)->toBeFalse();
});

