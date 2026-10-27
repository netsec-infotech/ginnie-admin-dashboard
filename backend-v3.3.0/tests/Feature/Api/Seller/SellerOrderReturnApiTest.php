<?php

use App\Enums\DefaultSystemRolesEnum;
use App\Enums\GuardNameEnum;
use App\Enums\Order\OrderItemReturnPickupStatusEnum;
use App\Enums\Order\OrderItemReturnStatusEnum;
use App\Enums\Order\OrderItemStatusEnum;
use App\Enums\Order\OrderStatusEnum;
use App\Enums\Seller\SellerVerificationStatusEnum;
use App\Http\Middleware\CheckInstallation;
use App\Http\Middleware\VerifyLicense;
use App\Models\DeliveryBoy;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\OrderItemReturn;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\ReturnReason;
use App\Models\Seller;
use App\Models\Store;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->withoutMiddleware([VerifyLicense::class, CheckInstallation::class]);
    Gate::before(fn () => true);

    Storage::fake('public');

    // 1. Seller Setup
    $this->sellerUser = User::factory()->create(['access_panel' => GuardNameEnum::SELLER->value]);
    $this->seller = Seller::factory()->create([
        'user_id' => $this->sellerUser->id,
        'verification_status' => SellerVerificationStatusEnum::Approved(),
    ]);

    Role::findOrCreate(DefaultSystemRolesEnum::SELLER->value, GuardNameEnum::SELLER->value);
    $this->sellerUser->assignRole(DefaultSystemRolesEnum::SELLER->value);
    $this->sellerUser = User::find($this->sellerUser->id);

    $this->store = Store::forceCreate(testStoreData($this->seller->id, [
        'name' => 'Organic Valley Store',
    ]));

    // 2. Customer & Rider Setup
    $this->customer = User::factory()->create();
    $this->riderUser = User::factory()->create();
    $this->deliveryBoy = DeliveryBoy::factory()->create([
        'user_id' => $this->riderUser->id,
        'status' => 1,
    ]);

    // 3. Product Setup
    $this->product = Product::forceCreate(testProductData($this->seller->id, [
        'title' => 'Fresh Strawberries 500g',
        'status' => 'active',
    ]));
    $this->variant = ProductVariant::forceCreate(testVariantData($this->product->id, [
        'title' => 'Pack of 500g',
    ]));

    // 4. Order & Item Setup
    $zoneId = \App\Models\DeliveryZone::first()?->id ?? \App\Models\DeliveryZone::factory()->create()->id;

    $this->order = Order::forceCreate([
        'uuid' => (string) \Illuminate\Support\Str::uuid(),
        'user_id' => $this->customer->id,
        'delivery_zone_id' => $zoneId,
        'slug' => 'order-test-'.uniqid(),
        'email' => $this->customer->email,
        'ip_address' => '127.0.0.1',
        'currency_code' => 'USD',
        'currency_rate' => 1,
        'payment_method' => 'cod',
        'payment_status' => 'completed',
        'fulfillment_type' => 'hyperlocal',
        'wallet_balance' => 0,
        'subtotal' => 100.00,
        'total_payable' => 110.00,
        'final_total' => 110.00,
        'billing_name' => $this->customer->name,
        'billing_address_1' => '123 Test St',
        'billing_landmark' => 'Test Landmark',
        'billing_zip' => '10001',
        'billing_phone' => '1234567890',
        'billing_address_type' => 'home',
        'billing_latitude' => 0,
        'billing_longitude' => 0,
        'billing_city' => 'City',
        'billing_state' => 'State',
        'billing_country' => 'Country',
        'billing_country_code' => 'US',
        'shipping_name' => $this->customer->name,
        'shipping_address_1' => '123 Test St',
        'shipping_landmark' => 'Test Landmark',
        'shipping_zip' => '10001',
        'shipping_phone' => '1234567890',
        'shipping_address_type' => 'home',
        'shipping_latitude' => 0,
        'shipping_longitude' => 0,
        'shipping_city' => 'City',
        'shipping_state' => 'State',
        'shipping_country' => 'Country',
        'shipping_country_code' => 'US',
    ]);

    $this->orderItem = OrderItem::forceCreate([
        'order_id' => $this->order->id,
        'product_id' => $this->product->id,
        'product_variant_id' => $this->variant->id,
        'store_id' => $this->store->id,
        'title' => $this->product->title,
        'variant_title' => $this->variant->title,
        'sku' => 'STRAW-001',
        'quantity' => 2,
        'price' => 50.00,
        'subtotal' => 100.00,
        'status' => OrderItemStatusEnum::DELIVERED(),
    ]);

    $this->returnReason = ReturnReason::create([
        'seller_id' => $this->seller->id,
        'title' => 'Damaged or Expired Fruit',
        'status' => 'active',
    ]);
});

test('seller can list their order returns with insight counters via API', function () {
    Sanctum::actingAs($this->sellerUser);

    OrderItemReturn::create([
        'order_item_id' => $this->orderItem->id,
        'order_id' => $this->order->id,
        'user_id' => $this->customer->id,
        'seller_id' => $this->seller->id,
        'store_id' => $this->store->id,
        'reason' => 'Damaged or Expired Fruit',
        'refund_amount' => 100.00,
        'return_status' => OrderItemReturnStatusEnum::REQUESTED(),
    ]);

    $response = $this->getJson(route('seller.api.order_returns.index'));

    $response->assertSuccessful()
        ->assertJsonPath('success', true)
        ->assertJsonStructure([
            'success',
            'message',
            'data' => [
                'insights' => ['total', 'under_review', 'approved', 'refund_processed', 'disputed'],
                'current_page',
                'data' => [
                    '*' => ['id', 'order_id', 'order_number', 'order_item_id', 'return_status', 'refund_amount', 'customer', 'product'],
                ],
                'total',
            ],
        ]);

    expect($response->json('data.insights.total'))->toBe(1)
        ->and($response->json('data.insights.under_review'))->toBe(1);
});

test('seller cannot see return requests belonging to another seller', function () {
    $otherUser = User::factory()->create(['access_panel' => GuardNameEnum::SELLER->value]);
    $otherSeller = Seller::factory()->create(['user_id' => $otherUser->id]);
    Role::findOrCreate(DefaultSystemRolesEnum::SELLER->value, GuardNameEnum::SELLER->value);
    $otherUser->assignRole(DefaultSystemRolesEnum::SELLER->value);

    // Return belonging to other seller
    $otherReturn = OrderItemReturn::create([
        'order_item_id' => $this->orderItem->id,
        'order_id' => $this->order->id,
        'user_id' => $this->customer->id,
        'seller_id' => $otherSeller->id,
        'store_id' => $this->store->id,
        'reason' => 'Other seller return',
        'refund_amount' => 50.00,
        'return_status' => OrderItemReturnStatusEnum::REQUESTED(),
    ]);

    Sanctum::actingAs($this->sellerUser);

    // List should return empty
    $listResponse = $this->getJson(route('seller.api.order_returns.index'));
    $listResponse->assertSuccessful()
        ->assertJsonPath('data.total', 0);

    // Direct show should return 404
    $showResponse = $this->getJson(route('seller.api.order_returns.show', $otherReturn->id));
    $showResponse->assertStatus(404);
});

test('seller can fetch return enums and dispute options', function () {
    Sanctum::actingAs($this->sellerUser);

    $response = $this->getJson(route('seller.api.order_returns.enums'));

    $response->assertSuccessful()
        ->assertJsonPath('success', true)
        ->assertJsonStructure([
            'data' => [
                'statuses',
                'dispute_reasons',
                'decision_actions',
                'store_confirm_actions',
            ],
        ]);

    expect($response->json('data.dispute_reasons'))->toContain('Used or Damaged by Customer');
});

test('seller can view detailed order return including customer request and refund breakdown', function () {
    Sanctum::actingAs($this->sellerUser);

    $return = OrderItemReturn::create([
        'order_item_id' => $this->orderItem->id,
        'order_id' => $this->order->id,
        'user_id' => $this->customer->id,
        'seller_id' => $this->seller->id,
        'store_id' => $this->store->id,
        'delivery_boy_id' => $this->deliveryBoy->id,
        'reason' => 'Berries were squished during transit',
        'refund_amount' => 100.00,
        'return_status' => OrderItemReturnStatusEnum::REQUESTED(),
    ]);

    $response = $this->getJson(route('seller.api.order_returns.show', $return->id));

    $response->assertSuccessful()
        ->assertJsonPath('success', true)
        ->assertJsonPath('data.id', $return->id)
        ->assertJsonPath('data.customer.name', $this->customer->name)
        ->assertJsonPath('data.product.title', $this->product->title)
        ->assertJsonPath('data.allowed_actions.can_decide', true)
        ->assertJsonPath('data.allowed_actions.can_confirm_receipt', false);
});

test('seller can make initial approve decision on return request', function () {
    Sanctum::actingAs($this->sellerUser);

    $return = OrderItemReturn::create([
        'order_item_id' => $this->orderItem->id,
        'order_id' => $this->order->id,
        'user_id' => $this->customer->id,
        'seller_id' => $this->seller->id,
        'store_id' => $this->store->id,
        'reason' => 'Wrong size',
        'refund_amount' => 100.00,
        'return_status' => OrderItemReturnStatusEnum::REQUESTED(),
    ]);

    $response = $this->postJson(route('seller.api.order_returns.decision', $return->id), [
        'action' => 'approve',
        'seller_comment' => 'Approved for courier pickup',
    ]);

    $response->assertSuccessful()
        ->assertJsonPath('success', true)
        ->assertJsonPath('data.return_status', OrderItemReturnStatusEnum::SELLER_APPROVED());

    $return->refresh();
    expect($return->return_status)->toBe(OrderItemReturnStatusEnum::SELLER_APPROVED())
        ->and($return->seller_comment)->toBe('Approved for courier pickup')
        ->and($return->seller_approved_at)->not->toBeNull();
});

test('seller can make initial reject decision on return request with comment', function () {
    Sanctum::actingAs($this->sellerUser);

    $return = OrderItemReturn::create([
        'order_item_id' => $this->orderItem->id,
        'order_id' => $this->order->id,
        'user_id' => $this->customer->id,
        'seller_id' => $this->seller->id,
        'store_id' => $this->store->id,
        'reason' => 'Change of mind after 10 days',
        'refund_amount' => 100.00,
        'return_status' => OrderItemReturnStatusEnum::REQUESTED(),
    ]);

    $response = $this->postJson(route('seller.api.order_returns.decision', $return->id), [
        'action' => 'reject',
        'seller_comment' => 'Return window exceeded policy',
    ]);

    $response->assertSuccessful()
        ->assertJsonPath('success', true)
        ->assertJsonPath('data.return_status', OrderItemReturnStatusEnum::SELLER_REJECTED());

    $return->refresh();
    expect($return->return_status)->toBe(OrderItemReturnStatusEnum::SELLER_REJECTED())
        ->and($return->seller_comment)->toBe('Return window exceeded policy');
});

test('seller can confirm physical store receipt and complete return', function () {
    Sanctum::actingAs($this->sellerUser);

    $return = OrderItemReturn::create([
        'order_item_id' => $this->orderItem->id,
        'order_id' => $this->order->id,
        'user_id' => $this->customer->id,
        'seller_id' => $this->seller->id,
        'store_id' => $this->store->id,
        'delivery_boy_id' => $this->deliveryBoy->id,
        'refund_amount' => 100.00,
        'pickup_status' => OrderItemReturnPickupStatusEnum::DELIVERED_TO_SELLER(),
        'return_status' => OrderItemReturnStatusEnum::RECEIVED_BY_SELLER(),
    ]);

    $response = $this->postJson(route('seller.api.order_returns.store_confirm', $return->id), [
        'action' => 'approve',
        'remarks' => 'Item received back in good condition',
    ]);

    $response->assertSuccessful()
        ->assertJsonPath('success', true)
        ->assertJsonPath('data.return_status', OrderItemReturnStatusEnum::COMPLETED());

    $return->refresh();
    expect($return->return_status)->toBe(OrderItemReturnStatusEnum::COMPLETED())
        ->and($return->refund_processed_at)->not->toBeNull();
});

test('seller can escalate store receipt into dispute with inspection photos and reason', function () {
    Sanctum::actingAs($this->sellerUser);

    $return = OrderItemReturn::create([
        'order_item_id' => $this->orderItem->id,
        'order_id' => $this->order->id,
        'user_id' => $this->customer->id,
        'seller_id' => $this->seller->id,
        'store_id' => $this->store->id,
        'delivery_boy_id' => $this->deliveryBoy->id,
        'refund_amount' => 100.00,
        'pickup_status' => OrderItemReturnPickupStatusEnum::DELIVERED_TO_SELLER(),
        'return_status' => OrderItemReturnStatusEnum::RECEIVED_BY_SELLER(),
    ]);

    $fakePhoto = UploadedFile::fake()->image('broken_box.jpg');

    $response = $this->postJson(route('seller.api.order_returns.store_confirm', $return->id), [
        'action' => 'escalate',
        'dispute_reason' => 'Used or Damaged by Customer',
        'remarks' => 'Package was returned empty with missing contents.',
        'inspection_photos' => [$fakePhoto],
    ]);

    $response->assertSuccessful()
        ->assertJsonPath('success', true)
        ->assertJsonPath('data.return_status', OrderItemReturnStatusEnum::DISPUTED())
        ->assertJsonPath('data.is_disputed', true)
        ->assertJsonPath('data.dispute_reason', 'Used or Damaged by Customer');

    $return->refresh();
    expect($return->return_status)->toBe(OrderItemReturnStatusEnum::DISPUTED())
        ->and($return->disputed_at)->not->toBeNull()
        ->and($return->dispute_reason)->toBe('Used or Damaged by Customer');
});

test('store confirmation validation fails if photos or dispute reason are missing when escalating', function () {
    Sanctum::actingAs($this->sellerUser);

    $return = OrderItemReturn::create([
        'order_item_id' => $this->orderItem->id,
        'order_id' => $this->order->id,
        'user_id' => $this->customer->id,
        'seller_id' => $this->seller->id,
        'store_id' => $this->store->id,
        'refund_amount' => 100.00,
        'return_status' => OrderItemReturnStatusEnum::RECEIVED_BY_SELLER(),
    ]);

    // Missing dispute_reason and inspection_photos
    $response = $this->postJson(route('seller.api.order_returns.store_confirm', $return->id), [
        'action' => 'escalate',
    ]);

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['dispute_reason', 'inspection_photos', 'remarks']);
});
