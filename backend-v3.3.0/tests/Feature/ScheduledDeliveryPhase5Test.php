<?php

use App\Models\Order;
use App\Models\Store;
use App\Models\User;
use App\Services\OrderService;
use App\Enums\Payment\PaymentTypeEnum;
use Carbon\Carbon;
use Laravel\Sanctum\Sanctum;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;

use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->withoutMiddleware(\App\Http\Middleware\VerifyLicense::class);
    phase5_seedSettings();
});

function phase5_seedSettings(): void {
    \App\Models\Setting::updateOrCreate(
        ['variable' => \App\Enums\SettingTypeEnum::PAYMENT()],
        ['value' => json_encode([
            'cod' => true,
            'wallet' => true,
            'stripe' => false,
            'razorpay' => false,
            'paystack' => false,
            'flutterwave' => false,
        ])]
    );

    \App\Models\Setting::updateOrCreate(
        ['variable' => \App\Enums\SettingTypeEnum::SYSTEM()],
        ['value' => json_encode([
            'currency' => 'USD',
            'minimum_cart_amount' => 0.0,
            'systemVendorType' => 'multiple',
            'checkoutType' => 'single_store',
        ])]
    );

    \App\Models\Setting::updateOrCreate(
        ['variable' => \App\Enums\SettingTypeEnum::SCHEDULED_DELIVERY()],
        ['value' => json_encode([
            'enableScheduledDelivery' => true,
            'maxAdvanceBookingDaysLimit' => 7,
        ])]
    );

    app(\App\Services\SettingService::class)->clearSettingCache(\App\Enums\SettingTypeEnum::PAYMENT());
    app(\App\Services\SettingService::class)->clearSettingCache(\App\Enums\SettingTypeEnum::SYSTEM());
    app(\App\Services\SettingService::class)->clearSettingCache(\App\Enums\SettingTypeEnum::SCHEDULED_DELIVERY());
}

function phase5_createStore(): Store {
    $store = Store::factory()->create([
        'status' => \App\Enums\Store\StoreStatusEnum::ONLINE(),
        'is_scheduled_delivery_enabled' => true,
        'scheduled_delivery_buffer_minutes' => 60,
        'max_advance_booking_days' => 7,
        'scheduled_delivery_slot_duration' => 60,
        'scheduled_delivery_slot_capacity' => 10,
        'reschedule_deadline_hours' => 2,
    ]);

    $store->update([
        'metadata' => [
            'operating_hours' => [
                'monday' => ['is_open' => true, 'open_time' => '09:00:00', 'close_time' => '12:00:00'],
                'tuesday' => ['is_open' => true, 'open_time' => '09:00:00', 'close_time' => '12:00:00'],
                'wednesday' => ['is_open' => true, 'open_time' => '09:00:00', 'close_time' => '12:00:00'],
                'thursday' => ['is_open' => true, 'open_time' => '09:00:00', 'close_time' => '12:00:00'],
                'friday' => ['is_open' => true, 'open_time' => '09:00:00', 'close_time' => '12:00:00'],
                'saturday' => ['is_open' => true, 'open_time' => '09:00:00', 'close_time' => '12:00:00'],
                'sunday' => ['is_open' => false, 'open_time' => '09:00:00', 'close_time' => '12:00:00'],
            ]
        ]
    ]);

    $sellerUser = $store->seller->user;
    $sellerUser->update(['access_panel' => \App\Enums\GuardNameEnum::SELLER->value]);
    Role::findOrCreate(\App\Enums\DefaultSystemRolesEnum::SELLER(), \App\Enums\GuardNameEnum::SELLER());
    $sellerUser->assignRole(\App\Enums\DefaultSystemRolesEnum::SELLER());
    $store->seller->update(['verification_status' => \App\Enums\Seller\SellerVerificationStatusEnum::Approved()]);

    return $store;
}

function phase5_createTestProductVariant(Store $store, int $stock = 10): array {
    $product = \App\Models\Product::factory()->create(['seller_id' => $store->seller_id]);
    $variantId = DB::table('product_variants')->insertGetId([
        'uuid' => fake()->uuid(),
        'product_id' => $product->id,
        'title' => 'Default Variant',
        'slug' => 'var-' . uniqid(),
        'weight' => 1.0,
        'height' => 1.0,
        'breadth' => 1.0,
        'length' => 1.0,
        'availability' => true,
        'provider' => 'self',
        'barcode' => '1234567890',
        'visibility' => 'published',
        'is_default' => true,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    DB::table('store_product_variants')->insert([
        'product_variant_id' => $variantId,
        'store_id' => $store->id,
        'sku' => 'SKU-' . uniqid(),
        'price' => 100.00,
        'special_price' => 90.00,
        'cost' => 50.00,
        'stock' => $stock,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    return [$product->id, $variantId];
}

function phase5_createTestOrder(User $user, Store $store, array $orderExtra = [], int $stock = 10): Order {
    $order = Order::create(array_merge([
        'uuid' => fake()->uuid(),
        'user_id' => $user->id,
        'slug' => 'ord-' . uniqid(),
        'email' => $user->email ?? 'test@example.com',
        'ip_address' => '127.0.0.1',
        'currency_code' => 'USD',
        'currency_rate' => 1.0,
        'payment_method' => 'prepaid',
        'payment_status' => 'completed',
        'status' => 'pending',
        'fulfillment_type' => 'hyperlocal',
        'delivery_zone_id' => \App\Models\DeliveryZone::factory()->create()->id,
        'wallet_balance' => 0.0,
        'subtotal' => 100.0,
        'total_payable' => 100.0,
        'final_total' => 100.0,
        'billing_name' => 'John Doe',
        'billing_address_1' => '123 Test St',
        'billing_zip' => '10001',
        'billing_phone' => '1234567890',
        'billing_address_type' => 'home',
        'billing_latitude' => 10.0,
        'billing_longitude' => 20.0,
        'billing_city' => 'Test City',
        'billing_state' => 'Test State',
        'billing_country' => 'Test Country',
        'billing_country_code' => 'USA',
        'shipping_name' => 'John Doe',
        'shipping_address_1' => '123 Test St',
        'shipping_zip' => '10001',
        'shipping_phone' => '1234567890',
        'shipping_address_type' => 'home',
        'shipping_latitude' => 10.0,
        'shipping_longitude' => 20.0,
        'shipping_city' => 'Test City',
        'shipping_state' => 'Test State',
        'shipping_country' => 'Test Country',
        'shipping_country_code' => 'USA',
        'total' => 100.0,
        'grand_total' => 100.0,
    ], $orderExtra));

    [$productId, $variantId] = phase5_createTestProductVariant($store, $stock);

    \App\Models\OrderItem::create([
        'order_id' => $order->id,
        'product_id' => $productId,
        'product_variant_id' => $variantId,
        'store_id' => $store->id,
        'title' => 'Test Product Item',
        'variant_title' => 'Default Variant',
        'sku' => 'SKU-' . uniqid(),
        'quantity' => 1,
        'price' => 100.0,
        'subtotal' => 100.0,
        'status' => 'pending',
        'is_refunded' => false,
    ]);

    return $order;
}

it('flags orders when store goes offline', function () {
    $store = phase5_createStore();
    $user = User::factory()->create();
    $monday = Carbon::now()->next(Carbon::MONDAY)->format('Y-m-d');

    $order = phase5_createTestOrder($user, $store, [
        'scheduled_date' => $monday,
        'scheduled_slot_start_time' => '10:00:00',
        'scheduled_slot_end_time' => '11:00:00',
    ]);

    // Force store offline
    $store->update(['status' => \App\Enums\Store\StoreStatusEnum::OFFLINE()]);

    Artisan::call('orders:revalidate-scheduled');

    $order->refresh();
    expect($order->reschedule_status)->toBe('needs_reschedule')
        ->and($order->reschedule_reason)->toBe('store_offline')
        ->and($order->reschedule_deadline)->not->toBeNull();
});

it('flags orders when store is temporarily closed', function () {
    $store = phase5_createStore();
    $user = User::factory()->create();
    $monday = Carbon::now()->next(Carbon::MONDAY)->format('Y-m-d');

    $order = phase5_createTestOrder($user, $store, [
        'scheduled_date' => $monday,
        'scheduled_slot_start_time' => '10:00:00',
        'scheduled_slot_end_time' => '11:00:00',
    ]);

    // Force store closed
    $store->update(['is_temporarily_closed' => true, 'temporary_closure_reason' => 'Renovation']);

    Artisan::call('orders:revalidate-scheduled');

    $order->refresh();
    expect($order->reschedule_status)->toBe('needs_reschedule')
        ->and($order->reschedule_reason)->toBe('store_temporarily_closed');
});

it('flags orders when operating hours exclude the slot', function () {
    $store = phase5_createStore();
    $user = User::factory()->create();
    $monday = Carbon::now()->next(Carbon::MONDAY)->format('Y-m-d');

    $order = phase5_createTestOrder($user, $store, [
        'scheduled_date' => $monday,
        'scheduled_slot_start_time' => '10:00:00',
        'scheduled_slot_end_time' => '11:00:00',
    ]);

    // Change operating hours for Monday to exclude 10:00 - 11:00
    $metadata = $store->metadata;
    $metadata['operating_hours']['monday'] = ['is_open' => true, 'open_time' => '11:30:00', 'close_time' => '13:00:00'];
    $store->update(['metadata' => $metadata]);

    Artisan::call('orders:revalidate-scheduled');

    $order->refresh();
    expect($order->reschedule_status)->toBe('needs_reschedule')
        ->and($order->reschedule_reason)->toBe('timing_changed');
});

it('flags orders when item goes out of stock', function () {
    $store = phase5_createStore();
    $user = User::factory()->create();
    $monday = Carbon::now()->next(Carbon::MONDAY)->format('Y-m-d');

    // Create order with 0 stock on product variant
    $order = phase5_createTestOrder($user, $store, [
        'scheduled_date' => $monday,
        'scheduled_slot_start_time' => '10:00:00',
        'scheduled_slot_end_time' => '11:00:00',
    ], 0);

    Artisan::call('orders:revalidate-scheduled');

    $order->refresh();
    expect($order->reschedule_status)->toBe('needs_reschedule')
        ->and($order->reschedule_reason)->toBe('item_out_of_stock');
});

it('auto-cancels and refunds expired reschedule deadlines for non-COD prepaid orders', function () {
    $store = phase5_createStore();
    $user = User::factory()->create();
    $monday = Carbon::now()->next(Carbon::MONDAY)->format('Y-m-d');

    $order = phase5_createTestOrder($user, $store, [
        'payment_method' => 'prepaid',
        'payment_status' => 'completed',
        'scheduled_date' => $monday,
        'scheduled_slot_start_time' => '10:00:00',
        'scheduled_slot_end_time' => '11:00:00',
        'reschedule_status' => 'needs_reschedule',
        'reschedule_reason' => 'store_offline',
        'reschedule_deadline' => Carbon::now()->subMinute(),
    ]);

    // Check initial user wallet balance
    $walletResult = app(\App\Services\WalletService::class)->getWallet($user->id);
    $initialBalance = (float) ($walletResult['data']->balance ?? 0.0);

    Artisan::call('orders:revalidate-scheduled');

    $order->refresh();

    $walletResult = app(\App\Services\WalletService::class)->getWallet($user->id);
    $newBalance = (float) ($walletResult['data']->balance ?? 0.0);

    expect($order->status)->toBe('cancelled')
        ->and($order->reschedule_status)->toBe('cancelled')
        ->and($newBalance)->toBe($initialBalance + 100.0);
});

it('does not refund to wallet for expired COD orders', function () {
    $store = phase5_createStore();
    $user = User::factory()->create();
    $monday = Carbon::now()->next(Carbon::MONDAY)->format('Y-m-d');

    $order = phase5_createTestOrder($user, $store, [
        'payment_method' => PaymentTypeEnum::COD->value,
        'payment_status' => 'pending',
        'scheduled_date' => $monday,
        'scheduled_slot_start_time' => '10:00:00',
        'scheduled_slot_end_time' => '11:00:00',
        'reschedule_status' => 'needs_reschedule',
        'reschedule_reason' => 'store_offline',
        'reschedule_deadline' => Carbon::now()->subMinute(),
    ]);

    // Check initial user wallet balance
    $walletResult = app(\App\Services\WalletService::class)->getWallet($user->id);
    $initialBalance = (float) ($walletResult['data']->balance ?? 0.0);

    Artisan::call('orders:revalidate-scheduled');

    $order->refresh();

    $walletResult = app(\App\Services\WalletService::class)->getWallet($user->id);
    $newBalance = (float) ($walletResult['data']->balance ?? 0.0);

    expect($order->status)->toBe('cancelled')
        ->and($order->reschedule_status)->toBe('cancelled')
        ->and($newBalance)->toBe($initialBalance); // No refund
});

it('allows customer to reschedule to a new valid slot', function () {
    $store = phase5_createStore();
    $user = User::factory()->create();
    $monday = Carbon::now()->next(Carbon::MONDAY)->format('Y-m-d');

    $order = phase5_createTestOrder($user, $store, [
        'scheduled_date' => $monday,
        'scheduled_slot_start_time' => '10:00:00',
        'scheduled_slot_end_time' => '11:00:00',
        'reschedule_status' => 'needs_reschedule',
        'reschedule_reason' => 'store_offline',
        'reschedule_deadline' => Carbon::now()->addHour(),
    ]);

    Sanctum::actingAs($user);

    $response = $this->postJson("/api/user/orders/{$order->id}/recovery/reschedule", [
        'scheduled_date' => $monday,
        'scheduled_slot_start_time' => '11:00:00',
        'scheduled_slot_end_time' => '12:00:00',
    ]);

    $response->assertStatus(200);

    $order->refresh();
    expect($order->scheduled_slot_start_time)->toBe('11:00:00')
        ->and($order->reschedule_status)->toBeNull()
        ->and($order->reschedule_reason)->toBeNull();
});

it('allows customer to cancel order for full wallet refund', function () {
    $store = phase5_createStore();
    $user = User::factory()->create();
    $monday = Carbon::now()->next(Carbon::MONDAY)->format('Y-m-d');

    $order = phase5_createTestOrder($user, $store, [
        'payment_method' => 'prepaid',
        'payment_status' => 'completed',
        'scheduled_date' => $monday,
        'scheduled_slot_start_time' => '10:00:00',
        'scheduled_slot_end_time' => '11:00:00',
        'reschedule_status' => 'needs_reschedule',
        'reschedule_reason' => 'store_offline',
        'reschedule_deadline' => Carbon::now()->addHour(),
    ]);

    // Check initial user wallet balance
    $walletResult = app(\App\Services\WalletService::class)->getWallet($user->id);
    $initialBalance = (float) ($walletResult['data']->balance ?? 0.0);

    Sanctum::actingAs($user);

    $response = $this->postJson("/api/user/orders/{$order->id}/recovery/cancel");

    $response->assertStatus(200);

    $order->refresh();

    $walletResult = app(\App\Services\WalletService::class)->getWallet($user->id);
    $newBalance = (float) ($walletResult['data']->balance ?? 0.0);

    expect($order->status)->toBe('cancelled')
        ->and($newBalance)->toBe($initialBalance + 100.0);
});

it('rejects reschedule after deadline expires', function () {
    $store = phase5_createStore();
    $user = User::factory()->create();
    $monday = Carbon::now()->next(Carbon::MONDAY)->format('Y-m-d');

    $order = phase5_createTestOrder($user, $store, [
        'scheduled_date' => $monday,
        'scheduled_slot_start_time' => '10:00:00',
        'scheduled_slot_end_time' => '11:00:00',
        'reschedule_status' => 'needs_reschedule',
        'reschedule_reason' => 'store_offline',
        'reschedule_deadline' => Carbon::now()->subMinute(),
    ]);

    Sanctum::actingAs($user);

    $response = $this->postJson("/api/user/orders/{$order->id}/recovery/reschedule", [
        'scheduled_date' => $monday,
        'scheduled_slot_start_time' => '11:00:00',
        'scheduled_slot_end_time' => '12:00:00',
    ]);

    $response->assertStatus(422)
        ->assertJsonPath('message', 'The reschedule deadline has expired. Please cancel the order instead.');
});

it('allows seller to manage slot overrides', function () {
    $store = phase5_createStore();
    $sellerUser = $store->seller->user;

    Sanctum::actingAs($sellerUser);

    $monday = Carbon::now()->next(Carbon::MONDAY)->format('Y-m-d');

    // 1. Create a blocked slot override
    $response = $this->postJson("/api/seller/stores/{$store->id}/slot-overrides", [
        'override_date' => $monday,
        'start_time' => '10:00:00',
        'end_time' => '11:00:00',
        'is_blocked' => true,
    ]);

    $response->assertStatus(200);
    $overrideId = $response->json('data.id');
    expect($overrideId)->not->toBeNull();

    // 2. Fetch overrides list
    $response = $this->getJson("/api/seller/stores/{$store->id}/slot-overrides");
    $response->assertStatus(200)
        ->assertJsonCount(1, 'data');

    // 3. Delete override
    $response = $this->deleteJson("/api/seller/stores/{$store->id}/slot-overrides/{$overrideId}");
    $response->assertStatus(200);

    // Verify deleted
    $response = $this->getJson("/api/seller/stores/{$store->id}/slot-overrides");
    $response->assertStatus(200)
        ->assertJsonCount(0, 'data');
});

it('warns seller when creating override that affects existing bookings', function () {
    $store = phase5_createStore();
    $sellerUser = $store->seller->user;

    $user = User::factory()->create();
    $monday = Carbon::now()->next(Carbon::MONDAY)->format('Y-m-d');

    // Create an order in that slot
    $order = phase5_createTestOrder($user, $store, [
        'scheduled_date' => $monday,
        'scheduled_slot_start_time' => '10:00:00',
        'scheduled_slot_end_time' => '11:00:00',
    ]);

    Sanctum::actingAs($sellerUser);

    // Try to create override without confirmation - should return 202 warning
    $response = $this->postJson("/api/seller/stores/{$store->id}/slot-overrides", [
        'override_date' => $monday,
        'start_time' => '10:00:00',
        'end_time' => '11:00:00',
        'is_blocked' => true,
    ]);

    $response->assertStatus(202)
        ->assertJsonPath('data.warning', true)
        ->assertJsonPath('data.affected_orders_count', 1);

    // Try to create override WITH confirmation - should succeed and flag order
    $response = $this->postJson("/api/seller/stores/{$store->id}/slot-overrides", [
        'override_date' => $monday,
        'start_time' => '10:00:00',
        'end_time' => '11:00:00',
        'is_blocked' => true,
        'confirmed' => true,
    ]);

    $response->assertStatus(200);

    $order->refresh();
    expect($order->reschedule_status)->toBe('needs_reschedule')
        ->and($order->reschedule_reason)->toBe('slot_unavailable');
});

it('flags affected orders when timings are updated via store configuration web endpoint', function () {
    $store = phase5_createStore();
    $sellerUser = $store->seller->user;

    $user = User::factory()->create();
    $monday = Carbon::now()->next(Carbon::MONDAY)->format('Y-m-d');

    // Create order scheduled on Monday 10-11
    $order = phase5_createTestOrder($user, $store, [
        'scheduled_date' => $monday,
        'scheduled_slot_start_time' => '10:00:00',
        'scheduled_slot_end_time' => '11:00:00',
    ]);

    Sanctum::actingAs($sellerUser);

    // Update operating hours so that Monday is CLOSED via configuration route
    $newOperatingHours = $store->metadata['operating_hours'];
    $newOperatingHours['monday']['is_open'] = false;

    $response = $this->postJson("/seller/stores/{$store->id}/configuration", [
        'status' => 'online',
        'return_replacement_policy' => 'Return policy',
        'refund_policy' => 'Refund policy',
        'terms_and_conditions' => 'Terms',
        'delivery_policy' => 'Delivery',
        'metadata' => [
            'operating_hours' => $newOperatingHours,
        ]
    ]);

    $response->assertStatus(200);

    $order->refresh();
    expect($order->reschedule_status)->toBe('needs_reschedule')
        ->and($order->reschedule_reason)->toBe('Store timing changed');
});

it('retrieves resolved slots for seller overrides calendar', function () {
    $store = phase5_createStore();
    $sellerUser = $store->seller->user;

    Sanctum::actingAs($sellerUser);

    $monday = Carbon::now()->next(Carbon::MONDAY)->format('Y-m-d');

    $response = $this->getJson("/api/seller/stores/{$store->id}/resolved-slots?date={$monday}");

    $response->assertStatus(200)
        ->assertJsonPath('data.is_open', true)
        ->assertJsonStructure([
            'data' => [
                'slots' => [
                    '*' => [
                        'start_time',
                        'end_time',
                        'is_blocked',
                        'override_capacity',
                        'default_capacity',
                        'booked_count',
                        'override_id',
                    ]
                ]
            ]
        ]);
});

it('notifies seller when background revalidation flags scheduled orders', function () {
    \Illuminate\Support\Facades\Notification::fake();

    $store = phase5_createStore();
    $sellerUser = $store->seller->user;

    $user = User::factory()->create();
    $monday = Carbon::now()->next(Carbon::MONDAY)->format('Y-m-d');

    // Create an order scheduled on Monday 10-11
    $order = phase5_createTestOrder($user, $store, [
        'scheduled_date' => $monday,
        'scheduled_slot_start_time' => '10:00:00',
        'scheduled_slot_end_time' => '11:00:00',
    ]);

    // Force store offline to trigger revalidation failure
    $store->update(['status' => \App\Enums\Store\StoreStatusEnum::OFFLINE()]);

    Artisan::call('orders:revalidate-scheduled');

    \Illuminate\Support\Facades\Notification::assertSentTo(
        $sellerUser,
        \App\Notifications\ScheduledOrdersAffectedNotification::class,
        function ($notification) use ($store) {
            $data = $notification->toDatabase($store->seller->user);
            return $data['store_id'] === $store->id && $data['metadata']['affected_count'] === 1;
        }
    );
});

it('disables customer slots when enableScheduledDelivery is globally false', function () {
    $store = phase5_createStore();

    // Verify slots are retrieved successfully when globally enabled (default)
    $response = $this->getJson("/api/scheduled-delivery/stores/{$store->id}/dates");
    $response->assertStatus(200);

    // Disable globally
    $setting = \App\Models\Setting::where('variable', 'scheduled_delivery')->first();
    $val = $setting->value;
    $val['enableScheduledDelivery'] = false;
    $setting->update(['value' => json_encode($val)]);
    app(\App\Services\SettingService::class)->clearSettingCache('scheduled_delivery');

    // Verify slots request returns 422 with disabled message
    $response2 = $this->getJson("/api/scheduled-delivery/stores/{$store->id}/dates");
    $response2->assertStatus(422)
        ->assertJsonPath('success', false)
        ->assertJsonPath('message', 'Scheduled delivery is temporarily unavailable.');
});

it('preserves existing orders in revalidation when enableScheduledDelivery is globally false', function () {
    $store = phase5_createStore();
    $user = User::factory()->create();
    $monday = Carbon::now()->next(Carbon::MONDAY)->format('Y-m-d');

    // Create an order scheduled on Monday 10-11
    $order = phase5_createTestOrder($user, $store, [
        'scheduled_date' => $monday,
        'scheduled_slot_start_time' => '10:00:00',
        'scheduled_slot_end_time' => '11:00:00',
    ]);

    // Disable globally
    $setting = \App\Models\Setting::where('variable', 'scheduled_delivery')->first();
    $val = $setting->value;
    $val['enableScheduledDelivery'] = false;
    $setting->update(['value' => json_encode($val)]);
    app(\App\Services\SettingService::class)->clearSettingCache('scheduled_delivery');

    // Run revalidation
    Artisan::call('orders:revalidate-scheduled');

    // The existing order should NOT be flagged as needs_reschedule
    $order->refresh();
    expect($order->reschedule_status)->toBeNull();
});

it('rejects store configuration if max_advance_booking_days exceeds global admin ceiling', function () {
    $store = phase5_createStore();
    $sellerUser = $store->seller->user;

    // Set global ceiling to 5 days
    $setting = \App\Models\Setting::where('variable', 'scheduled_delivery')->first();
    $val = $setting->value;
    $val['maxAdvanceBookingDaysLimit'] = 5;
    $setting->update(['value' => json_encode($val)]);
    app(\App\Services\SettingService::class)->clearSettingCache('scheduled_delivery');

    Sanctum::actingAs($sellerUser);

    // Attempt to save 6 days
    $response = $this->postJson("/seller/stores/{$store->id}/configuration", [
        'status' => 'online',
        'return_replacement_policy' => 'Return policy',
        'refund_policy' => 'Refund policy',
        'terms_and_conditions' => 'Terms',
        'delivery_policy' => 'Delivery',
        'max_advance_booking_days' => 6,
    ]);

    // Should return validation error for max_advance_booking_days
    $response->assertStatus(422)
        ->assertJsonValidationErrors(['max_advance_booking_days']);

    // Attempt to save 5 days
    $response2 = $this->postJson("/seller/stores/{$store->id}/configuration", [
        'status' => 'online',
        'return_replacement_policy' => 'Return policy',
        'refund_policy' => 'Refund policy',
        'terms_and_conditions' => 'Terms',
        'delivery_policy' => 'Delivery',
        'max_advance_booking_days' => 5,
    ]);

    $response2->assertStatus(200);
});

it('validates break hours configuration input bounds', function () {
    $store = phase5_createStore();
    $sellerUser = $store->seller->user;

    Sanctum::actingAs($sellerUser);

    // 1. Fails when break_end_time is before or equal to break_start_time
    $response = $this->postJson("/seller/stores/{$store->id}/configuration", [
        'status' => 'online',
        'return_replacement_policy' => 'Return policy',
        'refund_policy' => 'Refund policy',
        'terms_and_conditions' => 'Terms',
        'delivery_policy' => 'Delivery',
        'metadata' => [
            'operating_hours' => [
                'monday' => [
                    'day' => 'monday',
                    'is_open' => '1',
                    'open_time' => '09:00',
                    'close_time' => '21:00',
                    'has_break' => '1',
                    'break_start_time' => '14:00',
                    'break_end_time' => '13:00',
                ]
            ]
        ]
    ]);
    $response->assertStatus(422)
        ->assertJsonValidationErrors(['metadata.operating_hours.monday.break_end_time']);

    // 2. Succeeds when break times are valid
    $response2 = $this->postJson("/seller/stores/{$store->id}/configuration", [
        'status' => 'online',
        'return_replacement_policy' => 'Return policy',
        'refund_policy' => 'Refund policy',
        'terms_and_conditions' => 'Terms',
        'delivery_policy' => 'Delivery',
        'metadata' => [
            'operating_hours' => [
                'monday' => [
                    'day' => 'monday',
                    'is_open' => '1',
                    'open_time' => '09:00',
                    'close_time' => '21:00',
                    'has_break' => '1',
                    'break_start_time' => '13:00',
                    'break_end_time' => '14:00',
                ]
            ]
        ]
    ]);
    $response2->assertStatus(200);
});

it('excludes dynamic slot segments that overlap store break hours', function () {
    $store = phase5_createStore();
    
    // Set 60 min slots and configured lunch break: Monday 13:00 - 14:00
    $store->update([
        'scheduled_delivery_slot_duration' => 60,
        'metadata' => [
            'operating_hours' => [
                'monday' => [
                    'day' => 'monday',
                    'is_open' => true,
                    'open_time' => '09:00:00',
                    'close_time' => '16:00:00',
                    'has_break' => true,
                    'break_start_time' => '13:00:00',
                    'break_end_time' => '14:00:00',
                ]
            ]
        ]
    ]);

    $monday = Carbon::now()->next(Carbon::MONDAY)->format('Y-m-d');
    
    $response = $this->getJson("/api/scheduled-delivery/stores/{$store->id}/slots?date={$monday}");
    $response->assertStatus(200);
    
    $slots = $response->json('data.slots');
    
    // Find the slots
    $slot12to13 = collect($slots)->firstWhere('start_time', '12:00:00');
    $slot13to14 = collect($slots)->firstWhere('start_time', '13:00:00');
    $slot14to15 = collect($slots)->firstWhere('start_time', '14:00:00');
    
    expect($slot12to13)->not->toBeNull()
        ->and($slot13to14)->toBeNull()  // Excluded due to overlap with break
        ->and($slot14to15)->not->toBeNull();
});

it('flags scheduled orders when break hours are updated to overlap', function () {
    $store = phase5_createStore();
    $user = User::factory()->create();
    $monday = Carbon::now()->next(Carbon::MONDAY)->format('Y-m-d');
    
    // Initial: open 09:00 - 21:00, no break
    $store->update([
        'metadata' => [
            'operating_hours' => [
                'monday' => [
                    'day' => 'monday',
                    'is_open' => true,
                    'open_time' => '09:00:00',
                    'close_time' => '21:00:00',
                    'has_break' => false,
                ]
            ]
        ]
    ]);

    // Create order scheduled in slot Monday 13:00 - 14:00
    $order = phase5_createTestOrder($user, $store, [
        'scheduled_date' => $monday,
        'scheduled_slot_start_time' => '13:00:00',
        'scheduled_slot_end_time' => '14:00:00',
    ]);

    // Update timings to add break overlapping this slot
    $storeService = app(\App\Services\StoreService::class);
    $storeService->updateTimings($store, [
        'monday' => [
            'day' => 'monday',
            'is_open' => true,
            'open_time' => '09:00:00',
            'close_time' => '21:00:00',
            'has_break' => true,
            'break_start_time' => '13:00:00',
            'break_end_time' => '14:00:00',
        ]
    ]);

    // Run revalidation
    Artisan::call('orders:revalidate-scheduled');

    // The order should now be flagged
    $order->refresh();
    expect($order->reschedule_status)->toBe('needs_reschedule')
        ->and($order->reschedule_reason)->toBe('Store timing changed');
});
