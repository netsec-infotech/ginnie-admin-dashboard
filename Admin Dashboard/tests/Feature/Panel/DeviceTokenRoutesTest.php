<?php

use App\Enums\DefaultSystemRolesEnum;
use App\Enums\GuardNameEnum;
use App\Enums\Notification\NotificationRoleTypeEnum;
use App\Models\Seller;
use App\Models\User;
use App\Models\UserFcmToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

it('seller panel sync stores the fcm token', function () {
    $user = User::factory()->create(['access_panel' => GuardNameEnum::SELLER->value]);
    Seller::factory()->create(['user_id' => $user->id]);
    Role::findOrCreate(DefaultSystemRolesEnum::SELLER->value, GuardNameEnum::SELLER->value);
    $user->assignRole(DefaultSystemRolesEnum::SELLER->value);

    $this->actingAs($user, GuardNameEnum::SELLER->value);

    $this->postJson(route('seller.devices.sync'), [
        'fcm_token' => 'browser-token-123',
        'device_type' => 'web',
    ])->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonPath('message', 'labels.device_synced');

    expect(
        UserFcmToken::where('fcm_token', 'browser-token-123')
            ->where('user_id', $user->id)
            ->exists()
    )->toBeTrue()
        ->and(UserFcmToken::query()->where('fcm_token', 'browser-token-123')->value('role_type'))
        ->toBe(NotificationRoleTypeEnum::SELLER->value);
});

it('seller panel sync replaces the previous token when supplied', function () {
    $user = User::factory()->create(['access_panel' => GuardNameEnum::SELLER->value]);
    Seller::factory()->create(['user_id' => $user->id]);
    Role::findOrCreate(DefaultSystemRolesEnum::SELLER->value, GuardNameEnum::SELLER->value);
    $user->assignRole(DefaultSystemRolesEnum::SELLER->value);

    UserFcmToken::create([
        'user_id' => $user->id,
        'fcm_token' => 'token-old',
        'device_type' => 'web',
    ]);

    $this->actingAs($user, GuardNameEnum::SELLER->value);

    $this->postJson(route('seller.devices.sync'), [
        'fcm_token' => 'token-new',
        'device_type' => 'web',
        'previous_token' => 'token-old',
    ])->assertOk()
        ->assertJsonPath('success', true);

    expect(UserFcmToken::where('fcm_token', 'token-old')->exists())->toBeFalse()
        ->and(UserFcmToken::where('fcm_token', 'token-new')->where('user_id', $user->id)->exists())
        ->toBeTrue();
});

it('seller panel forget removes the matching token', function () {
    $user = User::factory()->create(['access_panel' => GuardNameEnum::SELLER->value]);
    Seller::factory()->create(['user_id' => $user->id]);
    Role::findOrCreate(DefaultSystemRolesEnum::SELLER->value, GuardNameEnum::SELLER->value);
    $user->assignRole(DefaultSystemRolesEnum::SELLER->value);

    UserFcmToken::create([
        'user_id' => $user->id,
        'fcm_token' => 'token-bye',
        'device_type' => 'web',
    ]);

    $this->actingAs($user, GuardNameEnum::SELLER->value);

    $this->call('DELETE', route('seller.devices.forget'), [
        'fcm_token' => 'token-bye',
    ])->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonPath('message', 'labels.device_forgotten');

    expect(UserFcmToken::where('fcm_token', 'token-bye')->exists())->toBeFalse();
});

it('seller panel sync rejects invalid device_type', function () {
    $user = User::factory()->create(['access_panel' => GuardNameEnum::SELLER->value]);
    Seller::factory()->create(['user_id' => $user->id]);
    Role::findOrCreate(DefaultSystemRolesEnum::SELLER->value, GuardNameEnum::SELLER->value);
    $user->assignRole(DefaultSystemRolesEnum::SELLER->value);

    $this->actingAs($user, GuardNameEnum::SELLER->value);

    $this->postJson(route('seller.devices.sync'), [
        'fcm_token' => 'browser-token-456',
        'device_type' => 'tv',
    ])->assertStatus(422);

    expect(UserFcmToken::where('fcm_token', 'browser-token-456')->exists())->toBeFalse();
});

it('seller panel sync is gated by the seller middleware when unauthenticated', function () {
    $response = $this->post(route('seller.devices.sync'), [
        'fcm_token' => 'no-auth-token',
        'device_type' => 'web',
    ]);

    // ValidateSeller middleware redirects unauthenticated visitors to login.
    $response->assertRedirect(route('seller.login'));

    expect(UserFcmToken::where('fcm_token', 'no-auth-token')->exists())->toBeFalse();
});
