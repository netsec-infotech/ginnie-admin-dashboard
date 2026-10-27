<?php

use App\Enums\DeviceTypeEnum;
use App\Enums\Notification\NotificationRoleTypeEnum;
use App\Models\User;
use App\Models\UserFcmToken;
use App\Services\DeviceTokenService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->service = app(DeviceTokenService::class);
    $this->user = User::create([
        'name' => 'Panel User',
        'email' => 'panel-'.uniqid().'@example.test',
        'mobile' => '9000000010',
        'password' => bcrypt('password'),
    ]);
});

it('inserts a new fcm token row when none exists', function () {
    $row = $this->service->sync($this->user, 'token-aaa', DeviceTypeEnum::WEB);

    expect($row)->toBeInstanceOf(UserFcmToken::class)
        ->and($row->fcm_token)->toBe('token-aaa')
        ->and($row->user_id)->toBe($this->user->id)
        ->and($row->device_type)->toBe(DeviceTypeEnum::WEB);

    expect(UserFcmToken::where('fcm_token', 'token-aaa')->count())->toBe(1);
});

it('replaces the previous token row when the client supplies it', function () {
    UserFcmToken::create([
        'user_id' => $this->user->id,
        'fcm_token' => 'token-old',
        'device_type' => DeviceTypeEnum::WEB->value,
    ]);

    $this->service->sync(
        $this->user,
        'token-new',
        DeviceTypeEnum::WEB,
        null,
        'token-old'
    );

    expect(UserFcmToken::where('fcm_token', 'token-old')->exists())->toBeFalse()
        ->and(UserFcmToken::where('fcm_token', 'token-new')->where('user_id', $this->user->id)->exists())
        ->toBeTrue();
});

it('only deletes the previous token if it belongs to the same user', function () {
    $other = User::create([
        'name' => 'Other',
        'email' => 'other-'.uniqid().'@example.test',
        'mobile' => '9000000011',
        'password' => bcrypt('password'),
    ]);

    UserFcmToken::create([
        'user_id' => $other->id,
        'fcm_token' => 'token-other',
        'device_type' => DeviceTypeEnum::WEB->value,
    ]);

    $this->service->sync(
        $this->user,
        'token-new',
        DeviceTypeEnum::WEB,
        null,
        'token-other'
    );

    // The other user's token must NOT be wiped just because someone supplied it.
    expect(UserFcmToken::where('fcm_token', 'token-other')->where('user_id', $other->id)->exists())
        ->toBeTrue();
});

it('repoints an existing token to the new user via updateOrCreate', function () {
    $other = User::create([
        'name' => 'Original Owner',
        'email' => 'orig-'.uniqid().'@example.test',
        'mobile' => '9000000012',
        'password' => bcrypt('password'),
    ]);

    UserFcmToken::create([
        'user_id' => $other->id,
        'fcm_token' => 'shared-browser-token',
        'device_type' => DeviceTypeEnum::WEB->value,
    ]);

    $this->service->sync($this->user, 'shared-browser-token', DeviceTypeEnum::WEB);

    expect(UserFcmToken::where('fcm_token', 'shared-browser-token')->count())->toBe(1)
        ->and(UserFcmToken::where('fcm_token', 'shared-browser-token')->first()->user_id)
        ->toBe($this->user->id);
});

it('forgets only the matching token for the given user', function () {
    UserFcmToken::create([
        'user_id' => $this->user->id,
        'fcm_token' => 'token-keep',
        'device_type' => DeviceTypeEnum::WEB->value,
    ]);
    UserFcmToken::create([
        'user_id' => $this->user->id,
        'fcm_token' => 'token-drop',
        'device_type' => DeviceTypeEnum::WEB->value,
    ]);

    $deleted = $this->service->forget($this->user, 'token-drop');

    expect($deleted)->toBe(1)
        ->and(UserFcmToken::where('fcm_token', 'token-keep')->exists())->toBeTrue()
        ->and(UserFcmToken::where('fcm_token', 'token-drop')->exists())->toBeFalse();
});

it('stores the notification role type as an enum-backed value', function () {
    $row = $this->service->sync(
        $this->user,
        'token-role',
        DeviceTypeEnum::WEB,
        NotificationRoleTypeEnum::ADMIN
    );

    expect($row->role_type)->toBe(NotificationRoleTypeEnum::ADMIN)
        ->and(UserFcmToken::query()->where('fcm_token', 'token-role')->value('role_type'))
        ->toBe(NotificationRoleTypeEnum::ADMIN->value);
});
