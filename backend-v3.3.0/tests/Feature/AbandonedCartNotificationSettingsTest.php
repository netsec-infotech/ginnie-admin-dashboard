<?php

use App\Services\AbandonedCartSettings;
use App\Types\Settings\NotificationSettingType;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;

uses(RefreshDatabase::class);

it('uses safe abandoned cart notification defaults for legacy settings', function () {
    $configuration = app(AbandonedCartSettings::class)->configuration();

    expect($configuration)
        ->toMatchArray([
            'enabled' => true,
            'delay_minutes' => 60,
            'channels' => ['push'],
        ]);
});

it('accepts an empty optional channel selection', function () {
    $settings = NotificationSettingType::fromArray([
        'enableAbandonedCartNotification' => true,
        'abandonedCartNotificationDelayMinutes' => 60,
        'abandonedCartNotificationChannels' => [],
    ]);

    expect($settings->abandonedCartNotificationChannels)->toBe([]);
});

it('validates abandoned cart notification delay boundaries', function (int $minutes) {
    $settings = NotificationSettingType::fromArray([
        'enableAbandonedCartNotification' => true,
        'abandonedCartNotificationDelayMinutes' => $minutes,
        'abandonedCartNotificationChannels' => ['push'],
    ]);

    expect($settings->abandonedCartNotificationDelayMinutes)->toBe($minutes);
})->with([5, 1440]);

it('rejects an abandoned cart notification delay outside the supported range', function (int $minutes) {
    NotificationSettingType::fromArray([
        'enableAbandonedCartNotification' => true,
        'abandonedCartNotificationDelayMinutes' => $minutes,
        'abandonedCartNotificationChannels' => ['push'],
    ]);
})->with([4, 1441])->throws(ValidationException::class);

it('rejects unsupported abandoned cart notification channels', function () {
    NotificationSettingType::fromArray([
        'enableAbandonedCartNotification' => true,
        'abandonedCartNotificationDelayMinutes' => 60,
        'abandonedCartNotificationChannels' => ['push', 'whatsapp'],
    ]);
})->throws(ValidationException::class);
