<?php

use App\Enums\DefaultSystemRolesEnum;
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
use App\Models\Seller;
use App\Models\Store;
use App\Models\User;
use App\Models\Wallet;
use App\Models\OrderAuditLog;
use App\Services\ReturnRequestService;
use App\Services\SettingService;
use App\Notifications\OrderItemReturnUpdated;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

beforeEach(function () {
    // Only fake specific custom events to prevent blocking Eloquent Model observers
    Event::fake([
        \App\Events\Order\OrderStatusUpdated::class
    ]);
    Notification::fake();

    $this->withoutMiddleware([
        \App\Http\Middleware\VerifyLicense::class,
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
        'slug' => 'order-sla-'.uniqid(),
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
        'billing_address_1' => '123 St',
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
        'shipping_address_1' => '123 St',
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
});

it('auto processes return request on SLA expiry', function () {
    // 1. Create a return request in received_by_seller state, older than 48 hours (default SLA)
    $returnRequest = OrderItemReturn::forceCreate([
        'order_item_id' => $this->orderItem->id,
        'order_id' => $this->order->id,
        'user_id' => $this->customer->id,
        'seller_id' => $this->seller->id,
        'store_id' => $this->store->id,
        'reason' => 'Defective unit',
        'refund_amount' => 100.0,
        'pickup_status' => OrderItemReturnPickupStatusEnum::DELIVERED_TO_SELLER->value,
        'return_status' => OrderItemReturnStatusEnum::RECEIVED_BY_SELLER->value,
        'received_at' => now()->subHours(49),
    ]);

    // Set the SLA settings in DB (simulating default)
    $settings = app(SettingService::class)->getOrCreateDefaultSetting('seller');
    $val = $settings->value;
    $val['return_seller_inspection_sla_hours'] = 48;
    $settings->setAttribute('value', json_encode($val));
    $settings->save();
    app(SettingService::class)->clearSettingCache('seller');

    // Run the scheduler command
    $exitCode = Artisan::call('returns:process-inspection-sla');
    expect($exitCode)->toBe(0);

    // Verify status updated to completed (auto approved)
    $returnRequest->refresh();
    expect($returnRequest->return_status)->toBe((string) OrderItemReturnStatusEnum::COMPLETED())
        ->and($returnRequest->refund_processed_at)->not->toBeNull();

    // Verify wallet refund processed
    $customerWallet = Wallet::where('user_id', $this->customer->id)->first();
    expect($customerWallet)->not->toBeNull()
        ->and((float) $customerWallet->balance)->toBe(100.0);

    // Verify notifications were dispatched to customer and seller
    Notification::assertSentTo($this->customer, OrderItemReturnUpdated::class);
    Notification::assertSentTo($this->sellerUser, OrderItemReturnUpdated::class);

    // Verify audit logs were written
    $auditLogs = OrderAuditLog::where('order_item_id', $this->orderItem->id)->get();
    expect($auditLogs->where('action', 'SELLER_INSPECTION_SLA_AUTO_APPROVAL')->count())->toBe(1)
        ->and($auditLogs->where('action', 'RETURN_SETTLEMENT_COMPLETED')->count())->toBe(1);
});

it('respects dynamic custom configured SLA hours', function () {
    // 1. Create a return request received 25 hours ago
    $returnRequest = OrderItemReturn::forceCreate([
        'order_item_id' => $this->orderItem->id,
        'order_id' => $this->order->id,
        'user_id' => $this->customer->id,
        'seller_id' => $this->seller->id,
        'store_id' => $this->store->id,
        'reason' => 'Defective unit',
        'refund_amount' => 100.0,
        'pickup_status' => OrderItemReturnPickupStatusEnum::DELIVERED_TO_SELLER->value,
        'return_status' => OrderItemReturnStatusEnum::RECEIVED_BY_SELLER->value,
        'received_at' => now()->subHours(25),
    ]);

    // 2. Set SLA to 24 hours (so 25 hours is expired)
    $settings = app(SettingService::class)->getOrCreateDefaultSetting('seller');
    $val = $settings->value;
    $val['return_seller_inspection_sla_hours'] = 24;
    $settings->setAttribute('value', json_encode($val));
    $settings->save();
    app(SettingService::class)->clearSettingCache('seller');

    // Run the scheduler command
    $exitCode = Artisan::call('returns:process-inspection-sla');
    expect($exitCode)->toBe(0);

    $returnRequest->refresh();
    expect($returnRequest->return_status)->toBe((string) OrderItemReturnStatusEnum::COMPLETED());
});

it('ignores returns that are not expired yet', function () {
    // 1. Create a return request received 10 hours ago (SLA = 24 hours)
    $returnRequest = OrderItemReturn::forceCreate([
        'order_item_id' => $this->orderItem->id,
        'order_id' => $this->order->id,
        'user_id' => $this->customer->id,
        'seller_id' => $this->seller->id,
        'store_id' => $this->store->id,
        'reason' => 'Defective unit',
        'refund_amount' => 100.0,
        'pickup_status' => OrderItemReturnPickupStatusEnum::DELIVERED_TO_SELLER->value,
        'return_status' => OrderItemReturnStatusEnum::RECEIVED_BY_SELLER->value,
        'received_at' => now()->subHours(10),
    ]);

    $settings = app(SettingService::class)->getOrCreateDefaultSetting('seller');
    $val = $settings->value;
    $val['return_seller_inspection_sla_hours'] = 24;
    $settings->setAttribute('value', json_encode($val));
    $settings->save();
    app(SettingService::class)->clearSettingCache('seller');

    $exitCode = Artisan::call('returns:process-inspection-sla');
    expect($exitCode)->toBe(0);

    $returnRequest->refresh();
    expect($returnRequest->return_status)->toBe((string) OrderItemReturnStatusEnum::RECEIVED_BY_SELLER());
});

it('ignores returns that are not in received_by_seller state', function () {
    // 1. Create a return request in DISPUTED state but received 100 hours ago
    $returnRequest = OrderItemReturn::forceCreate([
        'order_item_id' => $this->orderItem->id,
        'order_id' => $this->order->id,
        'user_id' => $this->customer->id,
        'seller_id' => $this->seller->id,
        'store_id' => $this->store->id,
        'reason' => 'Defective unit',
        'refund_amount' => 100.0,
        'pickup_status' => OrderItemReturnPickupStatusEnum::DELIVERED_TO_SELLER->value,
        'return_status' => OrderItemReturnStatusEnum::DISPUTED->value,
        'received_at' => now()->subHours(100),
    ]);

    $settings = app(SettingService::class)->getOrCreateDefaultSetting('seller');
    $val = $settings->value;
    $val['return_seller_inspection_sla_hours'] = 24;
    $settings->setAttribute('value', json_encode($val));
    $settings->save();
    app(SettingService::class)->clearSettingCache('seller');

    $exitCode = Artisan::call('returns:process-inspection-sla');
    expect($exitCode)->toBe(0);

    $returnRequest->refresh();
    expect($returnRequest->return_status)->toBe((string) OrderItemReturnStatusEnum::DISPUTED());
});

it('rolls back database transaction if auto processing fails midway', function () {
    // 1. Create a return request received 50 hours ago
    $returnRequest = OrderItemReturn::forceCreate([
        'order_item_id' => $this->orderItem->id,
        'order_id' => $this->order->id,
        'user_id' => $this->customer->id,
        'seller_id' => $this->seller->id,
        'store_id' => $this->store->id,
        'reason' => 'Defective unit',
        'refund_amount' => 100.0,
        'pickup_status' => OrderItemReturnPickupStatusEnum::DELIVERED_TO_SELLER->value,
        'return_status' => OrderItemReturnStatusEnum::RECEIVED_BY_SELLER->value,
        'received_at' => now()->subHours(50),
    ]);

    $settings = app(SettingService::class)->getOrCreateDefaultSetting('seller');
    $val = $settings->value;
    $val['return_seller_inspection_sla_hours'] = 24;
    $settings->setAttribute('value', json_encode($val));
    $settings->save();
    app(SettingService::class)->clearSettingCache('seller');

    // Mock OrderService to throw exception on refund processing
    $mockOrderService = Mockery::mock(\App\Services\OrderService::class);
    $mockOrderService->shouldReceive('processOrderItemRefund')
        ->andThrow(new \RuntimeException('Transaction rollback mock error'));
    $this->app->instance(\App\Services\OrderService::class, $mockOrderService);

    // Execute SLA sweep via service directly
    $service = app(ReturnRequestService::class);
    $result = $service->processSlaTimeouts();

    expect($result['failed'])->toHaveCount(1)
        ->and($result['failed'][0]['id'])->toBe($returnRequest->id);

    // Verify DB was rolled back: status must remain received_by_seller, no wallet refund created
    $returnRequest->refresh();
    expect($returnRequest->return_status)->toBe((string) OrderItemReturnStatusEnum::RECEIVED_BY_SELLER());
});
