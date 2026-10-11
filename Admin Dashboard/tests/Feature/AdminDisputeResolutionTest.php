<?php

use App\Enums\DefaultSystemRolesEnum;
use App\Enums\GuardNameEnum;
use App\Enums\Order\OrderItemReturnPickupStatusEnum;
use App\Enums\Order\OrderItemReturnStatusEnum;
use App\Enums\Order\OrderItemStatusEnum;
use App\Enums\Order\AdminLossBearerEnum;
use App\Enums\Wallet\WalletTypeEnum;
use App\Http\Middleware\VerifyLicense;
use App\Models\DeliveryBoy;
use App\Models\DeliveryZone;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\OrderItemReturn;
use App\Models\PlatformExpenseRecord;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Seller;
use App\Models\Store;
use App\Models\User;
use App\Enums\SpatieMediaCollectionName;
use App\Services\ReturnRequestService;
use App\Models\Wallet;
use App\Notifications\ReturnDisputeResolvedNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

beforeEach(function () {
    Event::fake();
    Notification::fake();

    $this->withoutMiddleware([
        VerifyLicense::class,
        \App\Http\Middleware\EnsureSystemVendorTypeSelected::class,
        \App\Http\Middleware\EnsureSubscriptionFeatureSelected::class,
    ]);

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

    $this->adminUser = User::forceCreate([
        'name' => 'Admin Test',
        'email' => 'admin.'.uniqid().'@example.test',
        'password' => bcrypt('password'),
        'access_panel' => 'admin',
    ]);
    $roleAdminWeb = Role::findOrCreate(DefaultSystemRolesEnum::SUPER_ADMIN->value, 'web');
    $roleAdmin = Role::findOrCreate(DefaultSystemRolesEnum::SUPER_ADMIN->value, 'admin');
    $this->adminUser->assignRole($roleAdminWeb, $roleAdmin);

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
        'slug' => 'order-admin-dispute-'.uniqid(),
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
        'delivery_boy_id' => $this->deliveryBoy->id,
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
        'pickup_status' => OrderItemReturnPickupStatusEnum::DELIVERED_TO_SELLER->value,
        'return_status' => OrderItemReturnStatusEnum::DISPUTED->value,
        'dispute_reason' => 'Damaged customer package',
        'seller_comment' => 'Customer sent back trash',
        'disputed_at' => now()->subDay(),
    ]);
});

it('enforces admin permission to resolve return disputes', function () {
    // Non-admin (customer or seller) trying to resolve dispute
    $this->actingAs($this->customer, 'web');
    $response = $this->putJson(route('admin.returns.resolve.alias', $this->returnRequest->id), [
        'action' => 'customer',
        'remarks' => 'Denying return',
    ]);
    
    // Redirected by ValidateAdmin middleware because access_panel != 'admin'
    $response->assertStatus(302)
        ->assertRedirect(route('admin.login'));

    // Regular admin without viewAny or update permissions trying to resolve dispute
    $nonAuthorizedAdmin = User::forceCreate([
        'name' => 'Regular Admin',
        'email' => 'regular-admin.'.uniqid().'@example.test',
        'password' => bcrypt('password'),
        'access_panel' => 'admin',
    ]);
    $this->actingAs($nonAuthorizedAdmin, 'web');
    $response2 = $this->putJson(route('admin.returns.resolve.alias', $this->returnRequest->id), [
        'action' => 'customer',
        'remarks' => 'Denying return',
    ]);
    $response2->assertStatus(403);
});

it('only allows resolving disputed returns', function () {
    $this->actingAs($this->adminUser, 'web');

    // Change status from DISPUTED to COMPLETED
    $this->returnRequest->update([
        'return_status' => OrderItemReturnStatusEnum::COMPLETED->value
    ]);

    $response = $this->putJson(route('admin.returns.resolve.alias', $this->returnRequest->id), [
        'action' => 'customer',
        'remarks' => 'Already done',
    ]);

    $response->assertStatus(400)
        ->assertJsonFragment(['success' => false]);
});

it('resolves dispute with customer bears the loss outcome', function () {
    $this->actingAs($this->adminUser, 'web');

    $response = $this->putJson(route('admin.returns.resolve.alias', $this->returnRequest->id), [
        'action' => 'customer',
        'remarks' => 'Invalid return contents verified by admin review.',
    ]);

    $response->assertStatus(200)
        ->assertJson(['success' => true]);

    $updatedReturn = $this->returnRequest->fresh();
    expect($updatedReturn->return_status)->toBe(OrderItemReturnStatusEnum::REJECTED->value)
        ->and($updatedReturn->admin_resolution)->toBe(AdminLossBearerEnum::CUSTOMER)
        ->and($updatedReturn->admin_comment)->toBe('Invalid return contents verified by admin review.')
        ->and($updatedReturn->resolved_by)->toBe($this->adminUser->id)
        ->and($updatedReturn->resolved_by_admin_at)->not->toBeNull();

    // Verify customer wallet was NOT refunded
    $customerWallet = Wallet::where('user_id', $this->customer->id)->first();
    expect($customerWallet)->toBeNull();

    // Verify notifications were dispatched after successful transaction
    Notification::assertSentTo($this->customer, ReturnDisputeResolvedNotification::class);
    Notification::assertSentTo($this->sellerUser, ReturnDisputeResolvedNotification::class);
});

it('resolves dispute with seller bears the loss outcome and processes wallet refund', function () {
    $this->actingAs($this->adminUser, 'web');

    $response = $this->putJson(route('admin.returns.resolve.alias', $this->returnRequest->id), [
        'action' => 'seller',
        'remarks' => 'Seller claim is invalid. Product is defective. Refunding customer.',
    ]);

    $response->assertStatus(200)
        ->assertJson(['success' => true]);

    $updatedReturn = $this->returnRequest->fresh();
    expect($updatedReturn->return_status)->toBe(OrderItemReturnStatusEnum::COMPLETED->value)
        ->and($updatedReturn->admin_resolution)->toBe(AdminLossBearerEnum::SELLER)
        ->and($updatedReturn->admin_comment)->toBe('Seller claim is invalid. Product is defective. Refunding customer.');

    // Verify customer wallet WAS refunded
    $customerWallet = Wallet::where('user_id', $this->customer->id)->first();
    expect($customerWallet)->not->toBeNull()
        ->and((float) $customerWallet->balance)->toBe(100.0);

    Notification::assertSentTo($this->customer, ReturnDisputeResolvedNotification::class);
    Notification::assertSentTo($this->sellerUser, ReturnDisputeResolvedNotification::class);
});

it('resolves dispute with delivery rider bears the loss outcome, refunds customer, and logs platform expense', function () {
    $this->actingAs($this->adminUser, 'web');

    $response = $this->putJson(route('admin.returns.resolve.alias', $this->returnRequest->id), [
        'action' => 'rider',
        'remarks' => 'Rider mishandled package. Platform absorbs loss.',
    ]);

    $response->assertStatus(200)
        ->assertJson(['success' => true]);

    $updatedReturn = $this->returnRequest->fresh();
    expect($updatedReturn->return_status)->toBe(OrderItemReturnStatusEnum::COMPLETED->value)
        ->and($updatedReturn->admin_resolution)->toBe(AdminLossBearerEnum::RIDER);

    // Verify customer wallet WAS refunded
    $customerWallet = Wallet::where('user_id', $this->customer->id)->first();
    expect($customerWallet)->not->toBeNull()
        ->and((float) $customerWallet->balance)->toBe(100.0);

    // Verify platform expense was logged
    $expense = PlatformExpenseRecord::where('order_item_return_id', $this->returnRequest->id)->first();
    expect($expense)->not->toBeNull()
        ->and((float) $expense->amount)->toBe(100.0)
        ->and($expense->delivery_boy_id)->toBe($this->deliveryBoy->id)
        ->and($expense->classification)->toBe('RETURN_DISPUTE')
        ->and($expense->remarks)->toBe('Rider mishandled package. Platform absorbs loss.');

    Notification::assertSentTo($this->customer, ReturnDisputeResolvedNotification::class);
    Notification::assertSentTo($this->sellerUser, ReturnDisputeResolvedNotification::class);
    Notification::assertSentTo($this->riderUser, ReturnDisputeResolvedNotification::class);
});

it('resolves dispute with platform bears the loss outcome, refunds customer, and logs platform expense without rider linkage', function () {
    $this->actingAs($this->adminUser, 'web');

    $response = $this->putJson(route('admin.returns.resolve.alias', $this->returnRequest->id), [
        'action' => 'platform',
        'remarks' => 'Internal warehouse mistake. Platform goodwill refund.',
    ]);

    $response->assertStatus(200)
        ->assertJson(['success' => true]);

    $updatedReturn = $this->returnRequest->fresh();
    expect($updatedReturn->return_status)->toBe(OrderItemReturnStatusEnum::COMPLETED->value)
        ->and($updatedReturn->admin_resolution)->toBe(AdminLossBearerEnum::PLATFORM);

    // Verify customer wallet WAS refunded
    $customerWallet = Wallet::where('user_id', $this->customer->id)->first();
    expect($customerWallet)->not->toBeNull()
        ->and((float) $customerWallet->balance)->toBe(100.0);

    // Verify platform expense was logged
    $expense = PlatformExpenseRecord::where('order_item_return_id', $this->returnRequest->id)->first();
    expect($expense)->not->toBeNull()
        ->and((float) $expense->amount)->toBe(100.0)
        ->and($expense->delivery_boy_id)->toBeNull()
        ->and($expense->classification)->toBe('PLATFORM_GOODWILL')
        ->and($expense->remarks)->toBe('Internal warehouse mistake. Platform goodwill refund.');

    Notification::assertSentTo($this->customer, ReturnDisputeResolvedNotification::class);
    Notification::assertSentTo($this->sellerUser, ReturnDisputeResolvedNotification::class);
    // Verify rider is not notified for platform loss
    Notification::assertNotSentTo($this->riderUser, ReturnDisputeResolvedNotification::class);
});

it('uploads admin resolution evidence photos to the dedicated collection', function () {
    $this->actingAs($this->adminUser, 'web');

    $fakePhoto = UploadedFile::fake()->image('evidence.jpg');

    $response = $this->putJson(route('admin.returns.resolve.alias', $this->returnRequest->id), [
        'action' => 'customer',
        'remarks' => 'Evidence attached.',
        'resolution_photos' => [$fakePhoto],
    ]);

    $response->assertStatus(200);

    $updatedReturn = $this->returnRequest->fresh();
    expect($updatedReturn->getMedia(SpatieMediaCollectionName::ADMIN_RESOLUTION_PHOTOS())->count())->toBe(1)
        ->and($updatedReturn->getMedia(SpatieMediaCollectionName::SELLER_INSPECTION_PHOTOS())->count())->toBe(0);
});

it('attaches metadata to timeline steps when resolved', function () {
    $this->actingAs($this->adminUser, 'web');

    $this->putJson(route('admin.returns.resolve.alias', $this->returnRequest->id), [
        'action' => 'rider',
        'remarks' => 'Rider damage.',
    ]);

    $timelineService = app(ReturnRequestService::class);
    $steps = $timelineService->timelineSteps($this->returnRequest->fresh());

    $adminResolvedStep = collect($steps)->firstWhere('key', 'admin_resolved');
    expect($adminResolvedStep)->not->toBeNull()
        ->and($adminResolvedStep['label'])->toBe('Delivery Rider Bears the Loss')
        ->and($adminResolvedStep['metadata']['admin_name'])->toBe($this->adminUser->name)
        ->and($adminResolvedStep['metadata']['timestamp'])->not->toBeNull();
});

it('blocks resolution edits once a return request dispute is resolved', function () {
    $this->actingAs($this->adminUser, 'web');

    // First resolution
    $this->putJson(route('admin.returns.resolve.alias', $this->returnRequest->id), [
        'action' => 'customer',
        'remarks' => 'First resolve',
    ]);

    // Second resolution attempt should fail
    $response = $this->putJson(route('admin.returns.resolve.alias', $this->returnRequest->id), [
        'action' => 'seller',
        'remarks' => 'Second resolve',
    ]);

    $response->assertStatus(400);
});

it('rolls back the database transaction if dispute resolution fails midway', function () {
    $this->actingAs($this->adminUser, 'web');

    // Swap instance of orderService to force exception throwing
    $mockOrderService = Mockery::mock(\App\Services\OrderService::class);
    $mockOrderService->shouldReceive('processOrderItemRefund')
        ->andThrow(new \RuntimeException('Mock refund service error'));

    $this->app->instance(\App\Services\OrderService::class, $mockOrderService);

    $response = $this->putJson(route('admin.returns.resolve.alias', $this->returnRequest->id), [
        'action' => 'seller',
        'remarks' => 'Failing seller resolution',
    ]);

    $response->assertStatus(400);

    // Verify database was rolled back: status is still DISPUTED, resolved_by_admin_at remains null
    $updatedReturn = $this->returnRequest->fresh();
    expect($updatedReturn->return_status)->toBe(OrderItemReturnStatusEnum::DISPUTED->value)
        ->and($updatedReturn->resolved_by_admin_at)->toBeNull();
});
