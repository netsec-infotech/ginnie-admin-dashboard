<?php

use App\Enums\Notification\NotificationRoleTypeEnum;
use App\Enums\NotificationTypeEnum;
use App\Models\User;
use App\Services\NotificationService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('filters header notifications by the requested panel role', function () {
    $user = User::factory()->create();
    $service = app(NotificationService::class);

    $adminNotification = $service->createNotification([
        'user_id' => $user->id,
        'type' => NotificationTypeEnum::SYSTEM(),
        'sent_to' => 'admin',
        'title' => 'Admin title',
        'message' => 'Admin message',
    ]);

    $service->createNotification([
        'user_id' => $user->id,
        'type' => NotificationTypeEnum::SYSTEM(),
        'sent_to' => 'customer',
        'title' => 'Customer title',
        'message' => 'Customer message',
    ]);

    $headerData = $service->getHeaderNotifications($user->id, 'admin');

    expect($headerData['unread_count'])->toBe(1)
        ->and($headerData['notifications'])->toHaveCount(1)
        ->and($headerData['notifications']->first()->is($adminNotification))->toBeTrue();
});

it('stores null role type when the sent_to value cannot be mapped', function () {
    $user = User::factory()->create();

    $notification = app(NotificationService::class)->createNotification([
        'user_id' => $user->id,
        'type' => NotificationTypeEnum::SYSTEM(),
        'sent_to' => 'broadcast',
        'title' => 'Unknown audience',
        'message' => 'Unknown role should be nullable',
    ]);

    expect($notification->role_type)->toBeNull()
        ->and($notification->fresh()->getRawOriginal('role_type'))->toBeNull()
        ->and($notification->resolved_role_type)->toBeNull();
});

it('maps known sent_to values to the enum-backed role type', function () {
    $user = User::factory()->create();

    $notification = app(NotificationService::class)->createNotification([
        'user_id' => $user->id,
        'type' => NotificationTypeEnum::SYSTEM(),
        'sent_to' => 'seller',
        'title' => 'Seller audience',
        'message' => 'Seller role type should be stored',
    ]);

    expect($notification->role_type)->toBe(NotificationRoleTypeEnum::SELLER)
        ->and($notification->fresh()->getRawOriginal('role_type'))->toBe(NotificationRoleTypeEnum::SELLER->value);
});
