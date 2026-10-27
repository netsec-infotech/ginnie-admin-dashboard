<?php

use App\Events\WebVersionMismatchDetected;
use App\Listeners\SendWebVersionMismatchAdminNotification;
use App\Models\Setting;
use App\Models\SystemUpdate;
use App\Notifications\WebVersionMismatchNotification;
use App\Services\SettingService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Notification;

it('dispatches WebVersionMismatchDetected event when web version is outdated', function () {
    Event::fake();

    // Mock SettingService to avoid DB table queries in unmigrated test environment
    $settingServiceMock = Mockery::mock(SettingService::class);
    $settingServiceMock->shouldReceive('getSettingByVariable')->andReturn(null);
    $this->app->instance(SettingService::class, $settingServiceMock);

    // Mock SystemUpdate & Setting model queries
    $systemUpdateMock = Mockery::mock('alias:' . SystemUpdate::class);
    $builderMock = Mockery::mock(Builder::class);
    $builderMock->shouldReceive('first')->andReturn(null);
    $systemUpdateMock->shouldReceive('active')->andReturn($builderMock);

    $settingMock = Mockery::mock('alias:' . Setting::class);
    $settingMock->shouldReceive('getCurrentVersion')->andReturn('1.0.0');

    $response = $this->withoutMiddleware()
        ->getJson(route('api.settings.check-version', [
            'current_version' => '0.0.1',
            'platform' => 'android',
            'app' => 'web',
        ]));

    $response->assertStatus(200);
    $response->assertJsonPath('success', true);
    $response->assertJsonPath('data.update_available', true);

    Event::assertDispatched(WebVersionMismatchDetected::class, function ($event) {
        return $event->clientVersion === '0.0.1' && $event->latestVersion === '1.0.0';
    });
});

it('sends WebVersionMismatchNotification and applies Option B throttling for unique version mismatches', function () {
    Notification::fake();
    Cache::flush();

    $event = new WebVersionMismatchDetected(
        clientVersion: '0.0.1',
        latestVersion: '1.0.0',
        ipAddress: '127.0.0.1',
        userAgent: 'PestTestBrowser'
    );

    $listener = new SendWebVersionMismatchAdminNotification();

    // Verify cache key before execution
    $cacheKey = "web_version_mismatch_notified_{$event->clientVersion}_{$event->latestVersion}";
    expect(Cache::has($cacheKey))->toBeFalse();

    // First handle call
    $listener->handle($event);

    // Verify Option B cache lock is set
    expect(Cache::has($cacheKey))->toBeTrue();

    // Second handle call with same version pair should exit early due to Option B lock
    $listener->handle($event);
});
