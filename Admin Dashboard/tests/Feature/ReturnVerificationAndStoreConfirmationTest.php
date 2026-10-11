<?php

use App\Enums\DefaultSystemRolesEnum;
use App\Enums\GuardNameEnum;
use App\Enums\Order\OrderItemReturnPickupStatusEnum;
use App\Enums\Order\OrderItemReturnStatusEnum;
use App\Enums\Order\OrderItemStatusEnum;
use App\Enums\Wallet\WalletTypeEnum;
use App\Http\Middleware\VerifyLicense;
use App\Models\DeliveryBoy;
use App\Models\DeliveryZone;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\OrderItemReturn;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\ReturnRejectionReason;
use App\Models\Seller;
use App\Models\Store;
use App\Models\User;
use App\Enums\SpatieMediaCollectionName;
use App\Services\ReturnRequestService;
use App\Models\Wallet;
use App\Services\ReturnPickupService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

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

    $roleSeller = Role::findOrCreate(DefaultSystemRolesEnum::SELLER->value, GuardNameEnum::SELLER->value);
    $roleWeb = Role::findOrCreate(DefaultSystemRolesEnum::SELLER->value, 'web');
    $this->sellerUser->assignRole($roleSeller, $roleWeb);

    $this->riderUser = User::forceCreate([
        'name' => 'Rider Test',
        'email' => 'rider.'.uniqid().'@example.test',
        'password' => bcrypt('password'),
    ]);

    $this->deliveryBoy = DeliveryBoy::forceCreate([
        'user_id' => $this->riderUser->id,
        'status' => 'active',
    ]);

    $this->store = Store::forceCreate(testStoreData($this->seller->id, [
        'name' => 'Store Test',
    ]));

    $this->product = Product::forceCreate(testProductData($this->seller->id, [
        'title' => 'Returnable Gadget',
        'is_returnable' => '1',
        'returnable_days' => 7,
    ]));

    $this->variant = ProductVariant::forceCreate(testVariantData($this->product->id, [
        'title' => 'Gadget Variant',
    ]));

    $this->order = Order::forceCreate([
        'uuid' => (string) Str::uuid(),
        'user_id' => $this->customer->id,
        'delivery_zone_id' => DeliveryZone::first()?->id ?? DeliveryZone::factory()->create()->id,
        'slug' => 'order-store-confirm-'.uniqid(),
        'email' => $this->customer->email,
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

    $this->orderItem = OrderItem::create([
        'order_id' => $this->order->id,
        'product_id' => $this->product->id,
        'product_variant_id' => $this->variant->id,
        'store_id' => $this->store->id,
        'title' => 'Returnable Gadget',
        'variant_title' => 'Gadget Variant',
        'sku' => 'GADGET-001',
        'quantity' => 1,
        'price' => 100,
        'subtotal' => 100,
        'status' => 'delivered',
    ]);

    $this->returnRequest = OrderItemReturn::forceCreate([
        'order_item_id' => $this->orderItem->id,
        'order_id' => $this->order->id,
        'user_id' => $this->customer->id,
        'seller_id' => $this->seller->id,
        'store_id' => $this->store->id,
        'delivery_boy_id' => $this->deliveryBoy->id,
        'reason' => 'Defective unit',
        'refund_amount' => 100.0,
        'snapshot_otp_required' => true,
        'snapshot_proof_required' => true,
        'pickup_status' => OrderItemReturnPickupStatusEnum::ASSIGNED(),
        'return_status' => OrderItemReturnStatusEnum::PICKUP_ASSIGNED(),
        'pickup_otp' => '654321',
    ]);
});

it('enforces OTP and proof images during rider pickup when required by product level configuration', function () {
    $pickupService = app(ReturnPickupService::class);

    // Attempt without OTP or images -> should fail
    $resMissingBoth = $pickupService->updatePickupStatus($this->returnRequest, OrderItemReturnPickupStatusEnum::PICKED_UP->value, $this->deliveryBoy, []);
    expect($resMissingBoth['success'])->toBeFalse()
        ->and($resMissingBoth['error'])->toBe('return_pickup_images_required');

    // Attempt with incorrect OTP -> should fail
    $resWrongOtp = $pickupService->updatePickupStatus($this->returnRequest, OrderItemReturnPickupStatusEnum::PICKED_UP->value, $this->deliveryBoy, [
        'otp' => '111111',
        'images' => ['Gadget_photo_1.jpg'],
    ]);
    expect($resWrongOtp['success'])->toBeFalse()
        ->and($resWrongOtp['error'])->toBe('invalid_otp');

    // Attempt with correct OTP but missing images -> should fail
    $resMissingProof = $pickupService->updatePickupStatus($this->returnRequest, OrderItemReturnPickupStatusEnum::PICKED_UP->value, $this->deliveryBoy, [
        'otp' => '654321',
        'images' => [],
    ]);
    expect($resMissingProof['success'])->toBeFalse()
        ->and($resMissingProof['error'])->toBe('return_pickup_images_required');

    // Attempt with correct OTP and valid proof images -> should succeed
    $resValid = $pickupService->updatePickupStatus($this->returnRequest, OrderItemReturnPickupStatusEnum::PICKED_UP->value, $this->deliveryBoy, [
        'otp' => '654321',
        'images' => ['Gadget_photo_1.jpg', 'Gadget_photo_2.jpg'],
    ]);
    expect($resValid['success'])->toBeTrue();

    $updatedReturn = $this->returnRequest->fresh();
    expect($updatedReturn->pickup_status)->toBe(OrderItemReturnPickupStatusEnum::PICKED_UP->value)
        ->and($updatedReturn->return_status)->toBe(OrderItemReturnStatusEnum::PICKED_UP->value);
});

it('approves return at store, credits customer wallet, and transitions status to completed', function () {
    $this->returnRequest->update([
        'pickup_status' => OrderItemReturnPickupStatusEnum::DELIVERED_TO_SELLER->value,
        'return_status' => OrderItemReturnStatusEnum::RECEIVED_BY_SELLER->value,
    ]);

    $this->withoutMiddleware(VerifyLicense::class);
    $this->actingAs($this->sellerUser, GuardNameEnum::SELLER->value);

    $response = $this->postJson(route('seller.returns.store-confirm', $this->returnRequest->id), [
        'action' => 'approve',
    ]);

    $response->assertStatus(200)
        ->assertJson(['success' => true]);

    $updatedReturn = $this->returnRequest->fresh();
    expect($updatedReturn->return_status)->toBe(OrderItemReturnStatusEnum::COMPLETED->value);

    $customerWallet = Wallet::where('user_id', $this->customer->id)
        ->where('type', WalletTypeEnum::CUSTOMER->value)
        ->first();
    expect($customerWallet)->not->toBeNull()
        ->and((float) $customerWallet->balance)->toBe(100.0);
});

it('does not refund customer automatically and transitions to received_by_seller when rider delivers return to seller', function () {
    // ==========================================================
    // Return Dispute System
    // Phase 2
    // Update Auto-Refund Delivered Return Test
    // ==========================================================
    $pickupService = app(ReturnPickupService::class);

    $this->returnRequest->update([
        'pickup_status' => OrderItemReturnPickupStatusEnum::PICKED_UP->value,
        'return_status' => OrderItemReturnStatusEnum::PICKED_UP->value,
        'picked_up_at' => now(),
    ]);

    $result = $pickupService->updatePickupStatus(
        $this->returnRequest->fresh(),
        OrderItemReturnPickupStatusEnum::DELIVERED_TO_SELLER->value,
        $this->deliveryBoy,
        [
            'pickup_condition' => 'good',
            'pickup_remarks' => 'Delivered to seller',
        ]
    );

    expect($result['success'])->toBeTrue();

    $updatedReturn = $this->returnRequest->fresh();
    expect($updatedReturn->pickup_status)->toBe(OrderItemReturnPickupStatusEnum::DELIVERED_TO_SELLER->value)
        ->and($updatedReturn->return_status)->toBe(OrderItemReturnStatusEnum::RECEIVED_BY_SELLER->value)
        ->and($updatedReturn->received_at)->not->toBeNull()
        ->and($updatedReturn->refund_processed_at)->toBeNull()
        ->and($this->orderItem->fresh()->status)->toBe('delivered');

    $customerWallet = Wallet::where('user_id', $this->customer->id)
        ->where('type', WalletTypeEnum::CUSTOMER->value)
        ->first();
    expect($customerWallet)->toBeNull();
});

it('allows seller to approve a return with optional inspection photos', function () {
    // ==========================================================
    // Return Dispute System
    // Phase 3
    // Seller Approve Return Test
    // ==========================================================
    $this->returnRequest->update([
        'pickup_status' => OrderItemReturnPickupStatusEnum::DELIVERED_TO_SELLER->value,
        'return_status' => OrderItemReturnStatusEnum::RECEIVED_BY_SELLER->value,
    ]);
    $this->withoutMiddleware(VerifyLicense::class);
    $this->actingAs($this->sellerUser, GuardNameEnum::SELLER->value);

    $fakePhoto = UploadedFile::fake()->image('inspection.jpg');

    $response = $this->postJson(route('seller.returns.store-confirm', $this->returnRequest->id), [
        'action' => 'approve',
        'remarks' => 'Item is in good condition, accept return.',
        'inspection_photos' => [$fakePhoto],
    ]);

    $response->assertStatus(200)
        ->assertJson(['success' => true]);

    $updatedReturn = $this->returnRequest->fresh();
    expect($updatedReturn->return_status)->toBe(OrderItemReturnStatusEnum::COMPLETED->value)
        ->and($updatedReturn->seller_comment)->toBe('Item is in good condition, accept return.');

    expect($updatedReturn->getMedia(SpatieMediaCollectionName::SELLER_INSPECTION_PHOTOS())->count())->toBe(1);
});

it('allows seller to escalate a return to admin and transitions status to disputed', function () {
    // ==========================================================
    // Return Dispute System
    // Phase 3
    // Seller Escalate to Admin Test
    // ==========================================================
    $this->returnRequest->update([
        'pickup_status' => OrderItemReturnPickupStatusEnum::DELIVERED_TO_SELLER->value,
        'return_status' => OrderItemReturnStatusEnum::RECEIVED_BY_SELLER->value,
    ]);
    $this->withoutMiddleware(VerifyLicense::class);
    $this->actingAs($this->sellerUser, GuardNameEnum::SELLER->value);

    $fakePhoto = UploadedFile::fake()->image('damaged.jpg');

    $response = $this->postJson(route('seller.returns.store-confirm', $this->returnRequest->id), [
        'action' => 'escalate',
        'dispute_reason' => 'Wrong Product Returned',
        'remarks' => 'Customer returned a brick instead of a phone',
        'inspection_photos' => [$fakePhoto],
    ]);

    $response->assertStatus(200)
        ->assertJson(['success' => true]);

    $updatedReturn = $this->returnRequest->fresh();
    expect($updatedReturn->return_status)->toBe(OrderItemReturnStatusEnum::DISPUTED->value)
        ->and($updatedReturn->dispute_reason)->toBe('Wrong Product Returned')
        ->and($updatedReturn->seller_comment)->toBe('Customer returned a brick instead of a phone')
        ->and($updatedReturn->disputed_at)->not->toBeNull();

    expect($updatedReturn->getMedia(SpatieMediaCollectionName::SELLER_INSPECTION_PHOTOS())->count())->toBe(1);
});

it('enforces mandatory evidence for seller escalation', function () {
    // ==========================================================
    // Return Dispute System
    // Phase 3
    // Seller Escalation Validation Constraints Test
    // ==========================================================
    $this->returnRequest->update([
        'pickup_status' => OrderItemReturnPickupStatusEnum::DELIVERED_TO_SELLER->value,
        'return_status' => OrderItemReturnStatusEnum::RECEIVED_BY_SELLER->value,
    ]);
    $this->withoutMiddleware(VerifyLicense::class);
    $this->actingAs($this->sellerUser, GuardNameEnum::SELLER->value);

    // Attempt escalation without any required fields
    $response = $this->postJson(route('seller.returns.store-confirm', $this->returnRequest->id), [
        'action' => 'escalate',
    ]);
    $response->assertStatus(422)
        ->assertJsonValidationErrors(['dispute_reason', 'remarks', 'inspection_photos']);

    // Attempt escalation without photos
    $response = $this->postJson(route('seller.returns.store-confirm', $this->returnRequest->id), [
        'action' => 'escalate',
        'dispute_reason' => 'Wrong Product Returned',
        'remarks' => 'Customer returned a brick instead of a phone',
    ]);
    $response->assertStatus(422)
        ->assertJsonValidationErrors(['inspection_photos']);
});

it('blocks seller actions on already resolved or escalated returns', function () {
    // ==========================================================
    // Return Dispute System
    // Phase 3
    // Block Secondary Actions Test
    // ==========================================================
    $this->returnRequest->update([
        'pickup_status' => OrderItemReturnPickupStatusEnum::DELIVERED_TO_SELLER->value,
        'return_status' => OrderItemReturnStatusEnum::DISPUTED->value,
        'disputed_at' => now(),
    ]);
    $this->withoutMiddleware(VerifyLicense::class);
    $this->actingAs($this->sellerUser, GuardNameEnum::SELLER->value);

    // Attempt to approve a return that is already in DISPUTED status
    $response = $this->postJson(route('seller.returns.store-confirm', $this->returnRequest->id), [
        'action' => 'approve',
    ]);
    $response->assertStatus(422)
        ->assertJsonFragment(['success' => false]);
});

it('updates return timeline correctly when return is disputed', function () {
    // ==========================================================
    // Return Dispute System
    // Phase 3
    // Timeline Updates Test
    // ==========================================================
    $this->returnRequest->update([
        'pickup_status' => OrderItemReturnPickupStatusEnum::DELIVERED_TO_SELLER->value,
        'return_status' => OrderItemReturnStatusEnum::DISPUTED->value,
        'disputed_at' => now(),
    ]);

    $timelineService = app(ReturnRequestService::class);
    $steps = $timelineService->timelineSteps($this->returnRequest);

    $keys = collect($steps)->pluck('key')->toArray();

    expect($keys)->toContain('disputed')
        ->and(collect($steps)->firstWhere('key', 'disputed')['label'])->toContain('Seller Escalated');
});

it('parses raw multipart form data when delivery app sends PUT request directly', function () {
    $this->withoutMiddleware(VerifyLicense::class);
    $this->actingAs($this->riderUser, 'sanctum');

    $boundary = '--dio-boundary-2193514319';
    $rawBody = "----dio-boundary-2193514319\r\n"
        ."content-disposition: form-data; name=\"status\"\r\n\r\n"
        ."picked_up\r\n"
        ."----dio-boundary-2193514319\r\n"
        ."content-disposition: form-data; name=\"otp\"\r\n\r\n"
        ."345634\r\n"
        ."----dio-boundary-2193514319--\r\n";

    $response = $this->call(
        'PUT',
        '/api/delivery-boy/return-pickups/'.$this->returnRequest->id.'/status',
        [],
        [],
        [],
        [
            'CONTENT_TYPE' => 'multipart/form-data; boundary='.$boundary,
            'HTTP_ACCEPT' => 'application/json',
        ],
        $rawBody
    );

    // It should pass validation and not fail with 'The status field is required'
    expect($response->json('message'))->not->toContain('status field is required');
});

it('accepts single image upload parameter when updating return pickup status', function () {
    $this->withoutMiddleware(VerifyLicense::class);
    $this->actingAs($this->riderUser, 'sanctum');

    $boundary = '--dio-boundary-single-image';
    $rawBody = "----dio-boundary-single-image\r\n"
        ."content-disposition: form-data; name=\"status\"\r\n\r\n"
        ."picked_up\r\n"
        ."----dio-boundary-single-image\r\n"
        ."content-disposition: form-data; name=\"otp\"\r\n\r\n"
        ."345634\r\n"
        ."----dio-boundary-single-image\r\n"
        ."content-disposition: form-data; name=\"image\"; filename=\"proof.jpg\"\r\n"
        ."Content-Type: image/jpeg\r\n\r\n"
        ."fakeimagebytes\r\n"
        ."----dio-boundary-single-image--\r\n";

    $response = $this->call(
        'PUT',
        '/api/delivery-boy/return-pickups/'.$this->returnRequest->id.'/status',
        [],
        [],
        [],
        [
            'CONTENT_TYPE' => 'multipart/form-data; boundary='.$boundary,
            'HTTP_ACCEPT' => 'application/json',
        ],
        $rawBody
    );

    expect($response->json('message'))->not->toContain('image')
        ->and($response->json('message'))->not->toContain('required');
});

it('accepts capitalized Image parameter case-insensitively', function () {
    $this->withoutMiddleware(VerifyLicense::class);
    $this->actingAs($this->riderUser, 'sanctum');

    $boundary = '--dio-boundary-capital-image';
    $rawBody = "----dio-boundary-capital-image\r\n"
        ."content-disposition: form-data; name=\"status\"\r\n\r\n"
        ."picked_up\r\n"
        ."----dio-boundary-capital-image\r\n"
        ."content-disposition: form-data; name=\"otp\"\r\n\r\n"
        ."345634\r\n"
        ."----dio-boundary-capital-image\r\n"
        ."content-disposition: form-data; name=\"Image\"; filename=\"proof.jpg\"\r\n"
        ."Content-Type: image/jpeg\r\n\r\n"
        ."fakeimagebytes\r\n"
        ."----dio-boundary-capital-image--\r\n";

    $response = $this->call(
        'PUT',
        '/api/delivery-boy/return-pickups/'.$this->returnRequest->id.'/status',
        [],
        [],
        [],
        [
            'CONTENT_TYPE' => 'multipart/form-data; boundary='.$boundary,
            'HTTP_ACCEPT' => 'application/json',
        ],
        $rawBody
    );

    expect($response->json('message'))->not->toContain('image')
        ->and($response->json('message'))->not->toContain('required');
});
