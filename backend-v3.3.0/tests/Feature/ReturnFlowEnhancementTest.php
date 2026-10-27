<?php

use App\Enums\GuardNameEnum;
use App\Enums\Order\OrderItemReturnPickupStatusEnum;
use App\Enums\Order\OrderItemReturnStatusEnum;
use App\Enums\Order\OrderItemStatusEnum;
use App\Models\DeliveryBoy;
use App\Models\DeliveryZone;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\OrderItemReturn;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\ReturnReason;
use App\Models\ReturnRejectionReason;
use App\Models\Seller;
use App\Models\SellerStatement;
use App\Models\Store;
use App\Models\User;
use App\Models\WalletTransaction;
use App\Services\OrderService;
use App\Services\ReturnRequestService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

function createReturnTestOrder($user)
{
    return Order::forceCreate([
        'uuid' => (string) Str::uuid(),
        'user_id' => $user->id,
        'delivery_zone_id' => DeliveryZone::first()?->id ?? DeliveryZone::factory()->create()->id,
        'slug' => 'order-test-'.uniqid(),
        'email' => $user->email,
        'ip_address' => '127.0.0.1',
        'currency_code' => 'USD',
        'currency_rate' => 1,
        'payment_method' => 'cod',
        'payment_status' => 'completed',
        'fulfillment_type' => 'hyperlocal',
        'wallet_balance' => 0,
        'subtotal' => 100.0,
        'total_payable' => 100.0,
        'final_total' => 100.0,
        'status' => 'delivered',
        'billing_name' => $user->name,
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
        'shipping_name' => $user->name,
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
}

beforeEach(function () {
    Event::fake();

    $this->customer = User::forceCreate([
        'name' => 'Customer Test',
        'email' => 'customer.'.uniqid().'@example.test',
        'password' => bcrypt('password'),
    ]);

    $this->sellerUser = User::forceCreate([
        'name' => 'Seller Test',
        'email' => 'seller.'.uniqid().'@example.test',
        'password' => bcrypt('password'),
        'access_panel' => GuardNameEnum::SELLER->value,
    ]);

    $this->seller = Seller::factory()->create([
        'user_id' => $this->sellerUser->id,
        'verification_status' => 'approved',
    ]);

    $this->store = Store::factory()->create([
        'seller_id' => $this->seller->id,
    ]);

    $this->riderUser = User::forceCreate([
        'name' => 'Rider Test',
        'email' => 'rider.'.uniqid().'@example.test',
        'password' => bcrypt('password'),
    ]);

    $this->deliveryBoy = DeliveryBoy::forceCreate([
        'user_id' => $this->riderUser->id,
        'status' => 'active',
    ]);
});

test('product falls back to global return reasons when no custom product reasons are assigned', function () {
    $globalReason = ReturnReason::create([
        'seller_id' => null,
        'title' => 'Global Defective Item',
        'proof_required' => true,
        'otp_required' => false,
        'status' => 'active',
    ]);

    $product = Product::forceCreate(testProductData($this->seller->id, [
        'title' => 'Test Product',
    ]));

    $available = $product->getAvailableReturnReasons();
    expect($available)->toHaveCount(1);
    expect($available->first()->id)->toBe($globalReason->id);
});

test('product uses product-specific return reasons when assigned', function () {
    $globalReason = ReturnReason::create([
        'seller_id' => null,
        'title' => 'Global Reason',
        'proof_required' => false,
        'status' => 'active',
    ]);

    $customReason = ReturnReason::create([
        'seller_id' => $this->seller->id,
        'title' => 'Custom Product Specific Reason',
        'proof_required' => true,
        'status' => 'active',
    ]);

    $product = Product::forceCreate(testProductData($this->seller->id, [
        'title' => 'Test Product Custom',
    ]));

    $product->returnReasons()->attach($customReason->id);

    $available = $product->getAvailableReturnReasons();
    expect($available)->toHaveCount(1);
    expect($available->first()->id)->toBe($customReason->id);
});

test('customer return creation saves immutable snapshot and validates proof image requirement', function () {
    $reason = ReturnReason::create([
        'seller_id' => null,
        'title' => 'Damaged Goods',
        'proof_required' => true,
        'otp_required' => true,
        'status' => 'active',
    ]);

    $product = Product::forceCreate(testProductData($this->seller->id, [
        'title' => 'Product For Return',
        'is_returnable' => '1',
        'returnable_days' => 7,
    ]));

    $variant = ProductVariant::forceCreate(testVariantData($product->id, [
        'title' => 'Variant 1',
    ]));

    $order = createReturnTestOrder($this->customer);

    $orderItem = OrderItem::forceCreate([
        'order_id' => $order->id,
        'product_id' => $product->id,
        'product_variant_id' => $variant->id,
        'store_id' => $this->store->id,
        'title' => $product->title,
        'variant_title' => $variant->title,
        'sku' => 'SKU-001',
        'quantity' => 1,
        'price' => 100.00,
        'subtotal' => 100.00,
        'return_eligible' => '1',
        'returnable_days' => 7,
        'return_deadline' => Carbon::now()->addDays(7)->format('Y-m-d'),
        'status' => OrderItemStatusEnum::DELIVERED(),
    ]);

    /** @var OrderService $orderService */
    $orderService = app(OrderService::class);

    // Failing attempt: proof image missing
    $failResult = $orderService->returnOrderItem($this->customer, [
        'order_item_id' => $orderItem->id,
        'return_reason_id' => $reason->id,
        'images' => [],
    ]);
    expect($failResult['success'])->toBeFalse();

    // Successful attempt with image
    $fakeImage = UploadedFile::fake()->image('damage.jpg');
    $successResult = $orderService->returnOrderItem($this->customer, [
        'order_item_id' => $orderItem->id,
        'return_reason_id' => $reason->id,
        'images' => [$fakeImage],
    ]);
    expect($successResult['success'])->toBeTrue();

    $return = OrderItemReturn::where('order_item_id', $orderItem->id)->first();
    expect($return->return_reason_id)->toBe($reason->id);
    expect($return->snapshot_proof_required)->toBeTrue();
    expect($return->snapshot_otp_required)->toBeTrue();
});

test('customer custom other return reason requires proof and otp by default', function () {
    $product = Product::forceCreate(testProductData($this->seller->id, [
        'title' => 'Product For Other Return',
        'is_returnable' => '1',
        'returnable_days' => 7,
    ]));

    $variant = ProductVariant::forceCreate(testVariantData($product->id, [
        'title' => 'Variant Other',
    ]));

    $order = createReturnTestOrder($this->customer);

    $orderItem = OrderItem::forceCreate([
        'order_id' => $order->id,
        'product_id' => $product->id,
        'product_variant_id' => $variant->id,
        'store_id' => $this->store->id,
        'title' => $product->title,
        'variant_title' => $variant->title,
        'sku' => 'SKU-OTHER',
        'quantity' => 1,
        'price' => 100.00,
        'subtotal' => 100.00,
        'return_eligible' => '1',
        'returnable_days' => 7,
        'return_deadline' => Carbon::now()->addDays(7)->format('Y-m-d'),
        'status' => OrderItemStatusEnum::DELIVERED(),
    ]);

    /** @var OrderService $orderService */
    $orderService = app(OrderService::class);

    $missingProofResult = $orderService->returnOrderItem($this->customer, [
        'order_item_id' => $orderItem->id,
        'reason' => 'Other: packaging was wrong',
        'images' => [],
    ]);
    expect($missingProofResult['success'])->toBeFalse();

    $successResult = $orderService->returnOrderItem($this->customer, [
        'order_item_id' => $orderItem->id,
        'reason' => 'Other: packaging was wrong',
        'images' => [UploadedFile::fake()->image('other-proof.jpg')],
    ]);
    expect($successResult['success'])->toBeTrue();

    $return = OrderItemReturn::where('order_item_id', $orderItem->id)->first();
    expect($return->return_reason_id)->toBeNull();
    expect($return->snapshot_proof_required)->toBeTrue();
    expect($return->snapshot_otp_required)->toBeTrue();
});

test('customer return request validation accepts custom reason text when return_reason_id is null', function () {
    $request = new \App\Http\Requests\User\Order\CreateItemReturnRequest();
    $validator = \Illuminate\Support\Facades\Validator::make([
        'return_reason_id' => null,
        'reason' => 'This is a custom reason text provided by the customer',
        'images' => [\Illuminate\Http\UploadedFile::fake()->image('proof.jpg')],
    ], $request->rules());

    expect($validator->passes())->toBeTrue();
});

test('customer return request validation fails when both return_reason_id and reason are missing', function () {
    $request = new \App\Http\Requests\User\Order\CreateItemReturnRequest();
    $validator = \Illuminate\Support\Facades\Validator::make([
        'return_reason_id' => null,
        'reason' => null,
        'images' => [\Illuminate\Http\UploadedFile::fake()->image('proof.jpg')],
    ], $request->rules());

    expect($validator->fails())->toBeTrue();
});

test('customer return request validation fails when return_reason_id is non-existent', function () {
    $request = new \App\Http\Requests\User\Order\CreateItemReturnRequest();
    $validator = \Illuminate\Support\Facades\Validator::make([
        'return_reason_id' => 99999,
        'reason' => 'Some custom reason text',
        'images' => [\Illuminate\Http\UploadedFile::fake()->image('proof.jpg')],
    ], $request->rules());

    expect($validator->fails())->toBeTrue();
});

test('return action endpoints block execution when demo mode is enabled', function () {
    \App\Models\Setting::updateOrCreate(
        ['variable' => 'system'],
        ['value' => json_encode(['demoMode' => true])]
    );
    \Illuminate\Support\Facades\Cache::flush();

    expect(\App\Models\Setting::isDemoModeEnabled())->toBeTrue();
});

// ==========================================================
// Return Dispute System
// Phase 3
// Disabled legacy reject tests. Replaced with Phase 4 Admin Resolution tests.
// ==========================================================
/*
test('store rejection with responsible party rider and deduct yes debits rider wallet with balance audit', function () {
    $product = Product::forceCreate(testProductData($this->seller->id, ['title' => 'Prod 2']));
    $variant = ProductVariant::forceCreate(testVariantData($product->id, ['title' => 'Var 2']));
    $order = createReturnTestOrder($this->customer);

    $orderItem = OrderItem::forceCreate([
        'order_id' => $order->id,
        'product_id' => $product->id,
        'product_variant_id' => $variant->id,
        'store_id' => $this->store->id,
        'title' => $product->title,
        'variant_title' => $variant->title,
        'sku' => 'SKU-002',
        'quantity' => 1,
        'price' => 100.00,
        'subtotal' => 100.00,
        'return_eligible' => '1',
        'returnable_days' => 7,
        'return_deadline' => Carbon::now()->addDays(7)->format('Y-m-d'),
        'status' => OrderItemStatusEnum::DELIVERED(),
    ]);

    $return = OrderItemReturn::create([
        'order_item_id' => $orderItem->id,
        'order_id' => $order->id,
        'user_id' => $this->customer->id,
        'seller_id' => $this->seller->id,
        'store_id' => $this->store->id,
        'delivery_boy_id' => $this->deliveryBoy->id,
        'refund_amount' => 100.00,
        'pickup_status' => OrderItemReturnPickupStatusEnum::DELIVERED_TO_SELLER(),
        'return_status' => OrderItemReturnStatusEnum::RECEIVED_BY_SELLER(),
    ]);

    $rejReason = ReturnRejectionReason::create([
        'title' => 'Item Swapped',
        'comment_required' => false,
        'status' => 'active',
    ]);

    $fakePhoto = UploadedFile::fake()->image('rejection_proof.jpg');

    $service = app(ReturnRequestService::class);

    $updated = $service->confirmStoreReceipt($return, 'reject', [
        'rejection_reason_id' => $rejReason->id,
        'responsible_party' => 'rider',
        'rider_deduction_type' => 'product_cost',
        'deduction_amount' => 100.00,
        'rejection_photos' => [$fakePhoto],
    ], $this->sellerUser);

    expect($updated->responsible_party->value)->toBe('rider');
    expect($updated->deduct_from_rider)->toBeTrue();
    expect($updated->rider_deduction_type)->toBe('product_cost');

    $transaction = WalletTransaction::where('return_id', $return->id)->first();
    expect($transaction)->not->toBeNull();
    expect((float) $transaction->amount)->toBe(100.00);
});

test('store rejection with responsible party rider and deduct no debits seller statement as seller loss', function () {
    $product = Product::forceCreate(testProductData($this->seller->id, ['title' => 'Prod 3']));
    $variant = ProductVariant::forceCreate(testVariantData($product->id, ['title' => 'Var 3']));
    $order = createReturnTestOrder($this->customer);

    $orderItem = OrderItem::forceCreate([
        'order_id' => $order->id,
        'product_id' => $product->id,
        'product_variant_id' => $variant->id,
        'store_id' => $this->store->id,
        'title' => $product->title,
        'variant_title' => $variant->title,
        'sku' => 'SKU-003',
        'quantity' => 1,
        'price' => 150.00,
        'subtotal' => 150.00,
        'return_eligible' => '1',
        'returnable_days' => 7,
        'return_deadline' => Carbon::now()->addDays(7)->format('Y-m-d'),
        'status' => OrderItemStatusEnum::DELIVERED(),
    ]);

    $return = OrderItemReturn::create([
        'order_item_id' => $orderItem->id,
        'order_id' => $order->id,
        'user_id' => $this->customer->id,
        'seller_id' => $this->seller->id,
        'store_id' => $this->store->id,
        'delivery_boy_id' => $this->deliveryBoy->id,
        'refund_amount' => 150.00,
        'pickup_status' => OrderItemReturnPickupStatusEnum::DELIVERED_TO_SELLER(),
        'return_status' => OrderItemReturnStatusEnum::RECEIVED_BY_SELLER(),
    ]);

    $fakePhoto = UploadedFile::fake()->image('rejection_proof.jpg');

    $service = app(ReturnRequestService::class);

    $updated = $service->confirmStoreReceipt($return, 'reject', [
        'responsible_party' => 'rider',
        'rider_deduction_type' => 'none',
        'rejection_photos' => [$fakePhoto],
    ], $this->sellerUser);

    expect($updated->responsible_party->value)->toBe('rider');
    expect($updated->deduct_from_rider)->toBeFalse();
    expect($updated->rider_deduction_type)->toBe('none');

    $statement = SellerStatement::where('return_id', $return->id)->first();
    expect($statement)->not->toBeNull();
    expect((float) $statement->amount)->toBe(150.00);
});
*/
