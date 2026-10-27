<?php

use App\Enums\DefaultSystemRolesEnum;
use App\Enums\GuardNameEnum;
use App\Enums\LoyaltyPoint\LoyaltyPointTransactionTypeEnum;
use App\Enums\SettingTypeEnum;
use App\Models\Setting;
use App\Models\User;
use App\Services\LoyaltyPointService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->withoutMiddleware();
    $this->admin = User::factory()->create([
        'access_panel' => GuardNameEnum::ADMIN->value,
    ]);
    Role::findOrCreate(DefaultSystemRolesEnum::SUPER_ADMIN->value, GuardNameEnum::ADMIN->value);
    $this->admin->assignRole(DefaultSystemRolesEnum::SUPER_ADMIN->value);
});

it('allows admin to update loyalty point settings including redemption value and batch expiry', function () {
    $response = $this->actingAs($this->admin, GuardNameEnum::ADMIN->value)
        ->postJson(route('admin.settings.store'), [
            'type' => SettingTypeEnum::LOYALTY_POINT(),
            'is_enabled' => 1,
            'point_rate' => 100.00,
            'redemption_value' => 0.50,
            'max_redemption_percentage' => 15.00,
            'min_order_value_to_redeem' => 250.00,
            'is_expiry_enabled' => 1,
            'expiry_days' => 45,
        ]);

    $response->assertOk()
        ->assertJson([
            'success' => true,
        ]);

    $settings = Setting::getLoyaltyPointSettings();

    expect($settings['is_enabled'])->toBeTrue()
        ->and($settings['point_rate'])->toEqual(100.00)
        ->and($settings['redemption_value'])->toEqual(0.50)
        ->and($settings['max_redemption_percentage'])->toEqual(15.00)
        ->and($settings['min_order_value_to_redeem'])->toEqual(250.00)
        ->and($settings['is_expiry_enabled'])->toBeTrue()
        ->and($settings['expiry_days'])->toEqual(45);
});

it('validates loyalty point settings input', function () {
    $response = $this->actingAs($this->admin, GuardNameEnum::ADMIN->value)
        ->postJson(route('admin.settings.store'), [
            'type' => SettingTypeEnum::LOYALTY_POINT(),
            'is_enabled' => 1,
            'point_rate' => -10,
            'redemption_value' => 0,
            'max_redemption_percentage' => 150,
            'min_order_value_to_redeem' => -5,
            'expiry_days' => 0,
        ]);

    $response->assertOk()
        ->assertJson([
            'success' => false,
        ]);
});

it('validates min order value against redemption value and max redemption cap percentage', function () {
    $response = $this->actingAs($this->admin, GuardNameEnum::ADMIN->value)
        ->postJson(route('admin.settings.store'), [
            'type' => SettingTypeEnum::LOYALTY_POINT(),
            'is_enabled' => 1,
            'point_rate' => 100,
            'redemption_value' => 100,
            'max_redemption_percentage' => 10,
            'min_order_value_to_redeem' => 500,
            'expiry_days' => 30,
        ]);

    $response->assertOk()
        ->assertJson([
            'success' => false,
            'message' => 'Validation failed: Minimum Order Value of 1000 is required if you want to keep 100 Points value for 10%',
        ]);
});

it('initializes and manages user balance and handles batch expiry and FIFO deduction through LoyaltyPointService', function () {
    $user = User::factory()->create();
    $service = app(LoyaltyPointService::class);

    $balanceRecord = $service->getUserBalance($user->id);
    expect($balanceRecord->points_balance)->toBe(0);

    // Credit unexpired batch (100 points)
    $creditTx = $service->adjustBalance(
        userId: $user->id,
        points: 100,
        type: LoyaltyPointTransactionTypeEnum::EARNED(),
        description: 'Order earning'
    );

    expect($creditTx->points)->toBe(100)
        ->and($creditTx->type->value)->toBe('earned')
        ->and($user->fresh()->loyalty_points_balance)->toBe(100);

    // Manually create an expired batch of 50 points
    \App\Models\LoyaltyPointTransaction::create([
        'user_id' => $user->id,
        'points' => 50,
        'used_points' => 0,
        'expires_at' => now()->subDay(),
        'type' => LoyaltyPointTransactionTypeEnum::EARNED(),
        'status' => \App\Enums\LoyaltyPoint\LoyaltyPointTransactionStatusEnum::CREDITED(),
        'description' => 'Expired batch',
    ]);

    // Active balance should still ignore expired points and return 100
    expect($service->getActivePointsBalance($user->id))->toBe(100)
        ->and($service->getUserBalance($user->id)->points_balance)->toBe(100);

    // Redeem 30 points (FIFO deduction)
    $debitTx = $service->adjustBalance(
        userId: $user->id,
        points: 30,
        type: LoyaltyPointTransactionTypeEnum::REDEEMED(),
        description: 'Order redemption'
    );

    expect($debitTx->points)->toBe(30)
        ->and($debitTx->type->value)->toBe('redeemed')
        ->and($user->fresh()->loyalty_points_balance)->toBe(70)
        ->and($creditTx->fresh()->used_points)->toBe(30);
});

it('runs loyalty:expire-points console command to process expired batches and dispatch reminders', function () {
    Setting::updateOrCreate(
        ['variable' => SettingTypeEnum::LOYALTY_POINT()],
        [
            'value' => json_encode([
                'is_enabled' => true,
                'point_rate' => 100.0,
                'is_expiry_enabled' => true,
                'expiry_days' => 30,
            ]),
        ]
    );
    app(\App\Services\SettingService::class)->clearSettingCache(SettingTypeEnum::LOYALTY_POINT());

    $user = User::factory()->create();

    // Expired batch
    \App\Models\LoyaltyPointTransaction::create([
        'user_id' => $user->id,
        'points' => 40,
        'used_points' => 0,
        'expires_at' => now()->subHours(2),
        'type' => LoyaltyPointTransactionTypeEnum::EARNED(),
        'status' => \App\Enums\LoyaltyPoint\LoyaltyPointTransactionStatusEnum::CREDITED(),
        'description' => 'Past batch',
    ]);

    // Batch expiring in 2 days (trigger reminder)
    \App\Models\LoyaltyPointTransaction::create([
        'user_id' => $user->id,
        'points' => 60,
        'used_points' => 0,
        'expires_at' => now()->addDays(2),
        'type' => LoyaltyPointTransactionTypeEnum::EARNED(),
        'status' => \App\Enums\LoyaltyPoint\LoyaltyPointTransactionStatusEnum::CREDITED(),
        'description' => 'Expiring soon batch',
    ]);

    $this->artisan('loyalty:expire-points')->assertExitCode(0);

    // Verify expired transaction created
    $this->assertDatabaseHas('loyalty_point_transactions', [
        'user_id' => $user->id,
        'points' => -40,
        'type' => LoyaltyPointTransactionTypeEnum::EXPIRED(),
    ]);

    // Verify notification created
    $this->assertDatabaseHas('notifications', [
        'notifiable_id' => $user->id,
        'type' => \App\Enums\NotificationTypeEnum::LOYALTY_POINT(),
    ]);
});
