<?php

use App\Models\Order;
use App\Models\Store;
use App\Models\StoreSlotOverride;
use App\Models\DeliveryTimeSlot;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->withoutMiddleware(\App\Http\Middleware\VerifyLicense::class);
});

function phase1_createTestOrder(User $user, array $extra = []): Order {
    return Order::create(array_merge([
        'uuid' => fake()->uuid(),
        'user_id' => $user->id,
        'delivery_zone_id' => \App\Models\DeliveryZone::factory()->create()->id,
        'slug' => 'ord-' . uniqid(),
        'email' => $user->email ?? 'test@example.com',
        'ip_address' => '127.0.0.1',
        'currency_code' => 'USD',
        'currency_rate' => 1.0,
        'payment_method' => 'prepaid',
        'payment_status' => 'completed',
        'status' => 'pending',
        'fulfillment_type' => 'hyperlocal',
        'wallet_balance' => 0.0,
        'subtotal' => 500.0,
        'total_payable' => 500.0,
        'final_total' => 500.0,
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
        'total' => 500.0,
        'grand_total' => 500.0,
    ], $extra));
}

it('can save store configuration for dynamic slots', function () {
    $store = Store::factory()->create();

    $store->update([
        'scheduled_delivery_slot_duration' => 30,
        'scheduled_delivery_slot_capacity' => 15,
        'is_temporarily_closed' => true,
        'temporary_closure_reason' => 'Maintenance',
        'prep_concurrency' => 5,
        'reschedule_deadline_hours' => 12,
    ]);

    $store->refresh();

    expect($store->scheduled_delivery_slot_duration)->toBe(30)
        ->and($store->scheduled_delivery_slot_capacity)->toBe(15)
        ->and($store->is_temporarily_closed)->toBeTrue()
        ->and($store->temporary_closure_reason)->toBe('Maintenance')
        ->and($store->prep_concurrency)->toBe(5)
        ->and($store->reschedule_deadline_hours)->toBe(12);
});

it('can manage store slot overrides', function () {
    $store = Store::factory()->create();

    $override = StoreSlotOverride::create([
        'store_id' => $store->id,
        'override_date' => '2026-08-01',
        'start_time' => '10:00:00',
        'end_time' => '11:00:00',
        'is_blocked' => true,
        'capacity' => 5,
    ]);

    expect($override->store_id)->toBe($store->id)
        ->and($override->override_date->format('Y-m-d'))->toBe('2026-08-01')
        ->and($override->start_time)->toBe('10:00:00')
        ->and($override->end_time)->toBe('11:00:00')
        ->and($override->is_blocked)->toBeTrue()
        ->and($override->capacity)->toBe(5);

    expect($store->slotOverrides)->toHaveCount(1);
});

it('can save dynamic slot details on orders', function () {
    $user = User::factory()->create();
    $order = phase1_createTestOrder($user, [
        'scheduled_date' => '2026-08-01',
        'scheduled_slot_start_time' => '10:00:00',
        'scheduled_slot_end_time' => '11:00:00',
        'reschedule_status' => 'needs_reschedule',
        'reschedule_reason' => 'Store timing changed',
        'reschedule_deadline' => '2026-08-02 10:00:00',
        'preparation_scheduled_at' => '2026-08-01 09:40:00',
    ]);

    $order->refresh();

    expect($order->scheduled_slot_start_time)->toBe('10:00:00')
        ->and($order->scheduled_slot_end_time)->toBe('11:00:00')
        ->and($order->reschedule_status)->toBe('needs_reschedule')
        ->and($order->reschedule_reason)->toBe('Store timing changed')
        ->and($order->reschedule_deadline->toDateTimeString())->toBe('2026-08-02 10:00:00')
        ->and($order->preparation_scheduled_at->toDateTimeString())->toBe('2026-08-01 09:40:00')
        ->and($order->isScheduledDelivery())->toBeTrue();
});

it('can retrofit existing scheduled orders', function () {
    $user = User::factory()->create();
    $store = Store::factory()->create();

    $slot = DeliveryTimeSlot::create([
        'store_id' => $store->id,
        'label' => '10:00 AM - 11:00 AM',
        'start_time' => '10:00:00',
        'end_time' => '11:00:00',
        'max_orders' => 10,
        'is_active' => true,
    ]);

    $order = phase1_createTestOrder($user, [
        'delivery_time_slot_id' => $slot->id,
        'scheduled_date' => '2026-08-01',
    ]);

    expect($order->scheduled_slot_start_time)->toBeNull()
        ->and($order->scheduled_slot_end_time)->toBeNull();

    $exitCode = Artisan::call('orders:retrofit-scheduled');

    expect($exitCode)->toBe(0);

    $order->refresh();

    expect($order->scheduled_slot_start_time)->toBe('10:00:00')
        ->and($order->scheduled_slot_end_time)->toBe('11:00:00');
});
