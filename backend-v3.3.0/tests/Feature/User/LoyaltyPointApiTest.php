<?php

use App\Enums\DefaultSystemRolesEnum;
use App\Enums\GuardNameEnum;
use App\Enums\LoyaltyPoint\LoyaltyPointTransactionStatusEnum;
use App\Enums\LoyaltyPoint\LoyaltyPointTransactionTypeEnum;
use App\Enums\SettingTypeEnum;
use App\Http\Middleware\VerifyLicense;
use App\Models\Setting;
use App\Models\User;
use App\Services\LoyaltyPointService;
use App\Services\SettingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->withoutMiddleware();

    Setting::updateOrCreate(
        ['variable' => SettingTypeEnum::LOYALTY_POINT()],
        [
            'value' => json_encode([
                'is_enabled' => true,
                'point_rate' => 100.0,
                'redemption_value' => 0.5,
                'max_redemption_percentage' => 10.0,
                'min_order_value_to_redeem' => 0.0,
                'expiry_days' => 30,
            ]),
        ]
    );

    Cache::flush();
    $settingService = app(SettingService::class);
    foreach (SettingTypeEnum::values() as $v) {
        $settingService->clearSettingCache($v);
    }

    $this->user = User::factory()->create();

    $this->admin = User::factory()->create([
        'access_panel' => GuardNameEnum::ADMIN->value,
    ]);
    Role::findOrCreate(DefaultSystemRolesEnum::SUPER_ADMIN->value, GuardNameEnum::ADMIN->value);
    $this->admin->assignRole(DefaultSystemRolesEnum::SUPER_ADMIN->value);
});

it('fetches paginated user loyalty point transaction history with summary statistics via API', function () {
    app(LoyaltyPointService::class)->adjustBalance(
        userId: $this->user->id,
        points: 50,
        type: LoyaltyPointTransactionTypeEnum::EARNED(),
        description: 'First earning',
        status: LoyaltyPointTransactionStatusEnum::CREDITED()
    );

    app(LoyaltyPointService::class)->adjustBalance(
        userId: $this->user->id,
        points: -10,
        type: LoyaltyPointTransactionTypeEnum::REDEEMED(),
        description: 'Order redemption',
        status: LoyaltyPointTransactionStatusEnum::CREDITED()
    );

    $response = $this->actingAs($this->user, 'sanctum')
        ->getJson('/api/user/loyalty-points/transactions');

    $response->assertStatus(200)
        ->assertJsonStructure([
            'success',
            'data' => [
                'is_enabled',
                'points_balance',
                'redemption_value',
                'equivalent_monetary_value',
                'expiring_points_in_30_days',
                'transactions' => [
                    '*' => ['id', 'points', 'type', 'status', 'description', 'created_at'],
                ],
                'pagination' => ['current_page', 'last_page', 'per_page', 'total'],
            ],
        ]);

    expect($response->json('data.transactions'))->toHaveCount(2)
        ->and($response->json('data.points_balance'))->toBe(40);
});

it('supports filtering loyalty point transactions by expiry date parameters', function () {
    \App\Models\LoyaltyPointTransaction::create([
        'user_id' => $this->user->id,
        'points' => 100,
        'used_points' => 0,
        'type' => 'earned',
        'status' => 'credited',
        'description' => 'Expiring soon',
        'expires_at' => now()->addDays(5),
    ]);

    \App\Models\LoyaltyPointTransaction::create([
        'user_id' => $this->user->id,
        'points' => 50,
        'used_points' => 0,
        'type' => 'earned',
        'status' => 'credited',
        'description' => 'Never expires',
        'expires_at' => null,
    ]);

    $expiringResponse = $this->actingAs($this->user, 'sanctum')
        ->getJson('/api/user/loyalty-points/transactions?expiry_filter=expiring_soon');

    $expiringResponse->assertStatus(200);
    expect($expiringResponse->json('data.transactions'))->toHaveCount(1)
        ->and($expiringResponse->json('data.transactions.0.description'))->toBe('Expiring soon');

    $noExpiryResponse = $this->actingAs($this->user, 'sanctum')
        ->getJson('/api/user/loyalty-points/transactions?expiry_filter=never_expires');

    $noExpiryResponse->assertStatus(200);
    expect($noExpiryResponse->json('data.transactions'))->toHaveCount(1)
        ->and($noExpiryResponse->json('data.transactions.0.description'))->toBe('Never expires');
});

it('allows admin to credit and debit customer loyalty points manually', function () {
    // Admin Credit
    $creditResponse = $this->actingAs($this->admin, GuardNameEnum::ADMIN->value)
        ->postJson("/admin/customers/{$this->user->id}/adjust-loyalty-points", [
            'action' => 'credit',
            'points' => 75,
            'note' => 'Bonus points from admin',
        ]);

    $creditResponse->assertStatus(200)
        ->assertJson([
            'success' => true,
            'data' => [
                'points_balance' => 75,
            ],
        ]);

    expect($this->user->fresh()->loyalty_points_balance)->toBe(75);

    // Admin Debit
    $debitResponse = $this->actingAs($this->admin, GuardNameEnum::ADMIN->value)
        ->postJson("/admin/customers/{$this->user->id}/adjust-loyalty-points", [
            'action' => 'debit',
            'points' => 25,
            'note' => 'Correction debit',
        ]);

    $debitResponse->assertStatus(200)
        ->assertJson([
            'success' => true,
            'data' => [
                'points_balance' => 50,
            ],
        ]);

    expect($this->user->fresh()->loyalty_points_balance)->toBe(50);
});

it('returns server-side datatable json for customer loyalty transactions', function () {
    app(LoyaltyPointService::class)->adjustBalance(
        userId: $this->user->id,
        points: 40,
        type: LoyaltyPointTransactionTypeEnum::EARNED(),
        description: 'Points for purchase',
        status: LoyaltyPointTransactionStatusEnum::CREDITED()
    );

    $response = $this->actingAs($this->admin, GuardNameEnum::ADMIN->value)
        ->getJson("/admin/customers/{$this->user->id}/loyalty-datatable");

    $response->assertStatus(200)
        ->assertJsonStructure([
            'draw',
            'recordsTotal',
            'recordsFiltered',
            'data' => [
                '*' => ['id', 'created_at', 'type', 'points', 'status', 'expires_at', 'description'],
            ],
        ]);

    expect($response->json('recordsTotal'))->toBe(1);
});
