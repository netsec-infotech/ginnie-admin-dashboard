<?php

use App\Models\DeliveryBoy;
use App\Models\User;
use App\Services\DeliveryBoyService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->service = new DeliveryBoyService();

    $user = User::create([
        'name'     => 'Rider Drop ' . uniqid(),
        'email'    => 'rider-drop-' . uniqid() . '@example.test',
        'mobile'   => '9333333333',
        'password' => bcrypt('password'),
    ]);

    $this->deliveryBoy = DeliveryBoy::factory()->create([
        'user_id' => $user->id,
        'status'  => 'active',
    ]);
});

it('increments drop_count on each call', function () {
    expect($this->deliveryBoy->drop_count)->toBe(0);

    $this->service->incrementDropCount($this->deliveryBoy);
    expect($this->deliveryBoy->fresh()->drop_count)->toBe(1);

    $this->service->incrementDropCount($this->deliveryBoy);
    expect($this->deliveryBoy->fresh()->drop_count)->toBe(2);
});

it('does not auto-flag a rider below the drop threshold', function () {
    // Threshold is 3 — first three drops should NOT flag.
    $this->service->incrementDropCount($this->deliveryBoy);
    $this->service->incrementDropCount($this->deliveryBoy);
    $this->service->incrementDropCount($this->deliveryBoy);

    $fresh = $this->deliveryBoy->fresh();
    expect($fresh->drop_count)->toBe(3)
        ->and($fresh->is_flagged)->toBeFalse();
});

it('auto-flags a rider once they cross the drop threshold', function () {
    for ($i = 0; $i < 4; $i++) {
        $this->service->incrementDropCount($this->deliveryBoy);
    }

    $fresh = $this->deliveryBoy->fresh();
    expect($fresh->drop_count)->toBe(4)
        ->and($fresh->is_flagged)->toBeTrue();
});

it('does not unflag a rider whose drop_count is later reset', function () {
    // Push past the threshold
    for ($i = 0; $i < 4; $i++) {
        $this->service->incrementDropCount($this->deliveryBoy);
    }
    expect($this->deliveryBoy->fresh()->is_flagged)->toBeTrue();

    // Admin resets the count manually (e.g. after review). The flag should
    // stay set until admin explicitly clears it — we never auto-unflag.
    $this->deliveryBoy->update(['drop_count' => 0]);

    $this->service->incrementDropCount($this->deliveryBoy);
    expect($this->deliveryBoy->fresh()->is_flagged)->toBeTrue();
});
