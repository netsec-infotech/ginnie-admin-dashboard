<?php

use App\Models\DeliveryBoy;
use App\Models\User;
use App\Services\DeliveryBoyService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->service = new DeliveryBoyService();

    $this->riderUser = User::create([
        'name'     => 'Rider Block ' . uniqid(),
        'email'    => 'rider-' . uniqid() . '@example.test',
        'mobile'   => '9111111111',
        'password' => bcrypt('password'),
    ]);

    $this->deliveryBoy = DeliveryBoy::factory()->create([
        'user_id' => $this->riderUser->id,
        'status'  => 'active',
    ]);

    $this->adminUser = User::create([
        'name'     => 'Admin Block ' . uniqid(),
        'email'    => 'admin-' . uniqid() . '@example.test',
        'mobile'   => '9222222222',
        'password' => bcrypt('password'),
    ]);
});

it('blocks a delivery boy and records audit attribution', function () {
    $result = $this->service->block($this->deliveryBoy, 'Persistent customer complaints', $this->adminUser->id);

    expect($result['success'])->toBeTrue();

    $fresh = $this->deliveryBoy->fresh();
    expect($fresh->is_blocked)->toBeTrue()
        ->and($fresh->blocked_reason)->toBe('Persistent customer complaints')
        ->and($fresh->blocked_by_admin_id)->toBe($this->adminUser->id)
        ->and($fresh->blocked_at)->not->toBeNull()
        ->and($fresh->status)->toBe('inactive');
});

it('refuses to block a delivery boy who is already blocked', function () {
    $this->service->block($this->deliveryBoy, 'first reason', $this->adminUser->id);

    $second = $this->service->block($this->deliveryBoy, 'second reason', $this->adminUser->id);

    expect($second['success'])->toBeFalse()
        ->and($second['message'])->toBe(__('labels.delivery_boy_already_blocked'));

    // First block's reason must remain — second call must not overwrite.
    expect($this->deliveryBoy->fresh()->blocked_reason)->toBe('first reason');
});

it('revokes the rider\'s active sanctum tokens when blocking', function () {
    // Issue a token like the login flow does.
    $this->riderUser->createToken('mobile');
    expect($this->riderUser->tokens()->count())->toBe(1);

    $this->service->block($this->deliveryBoy, 'safety review', $this->adminUser->id);

    expect($this->riderUser->fresh()->tokens()->count())->toBe(0);
});

it('unblocks a delivery boy and clears audit columns', function () {
    $this->service->block($this->deliveryBoy, 'temporary block', $this->adminUser->id);

    $result = $this->service->unblock($this->deliveryBoy);

    expect($result['success'])->toBeTrue();

    $fresh = $this->deliveryBoy->fresh();
    expect($fresh->is_blocked)->toBeFalse()
        ->and($fresh->blocked_reason)->toBeNull()
        ->and($fresh->blocked_by_admin_id)->toBeNull()
        ->and($fresh->blocked_at)->toBeNull();
});

it('refuses to unblock a delivery boy who is not blocked', function () {
    $result = $this->service->unblock($this->deliveryBoy);

    expect($result['success'])->toBeFalse()
        ->and($result['message'])->toBe(__('labels.delivery_boy_not_blocked'));
});
