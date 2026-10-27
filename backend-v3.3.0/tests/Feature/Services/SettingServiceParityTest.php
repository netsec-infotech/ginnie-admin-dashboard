<?php

use App\Enums\SettingTypeEnum;
use App\Models\Setting;
use App\Services\SettingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

uses(RefreshDatabase::class);

beforeEach(function () {
    Cache::flush();
});

it('returns the same web payload from getAllSettings() and getSettingByVariable() after a save', function () {
    $service = app(SettingService::class);

    $service->saveSetting(SettingTypeEnum::WEB(), [
        'siteName' => 'Hyperlocal Test',
        'supportEmail' => 'support@example.test',
        'supportNumber' => '9999999999',
    ]);

    $all = $service->getAllSettings();
    $webFromIndex = $all->first(fn ($resource) => $resource->resource->variable === SettingTypeEnum::WEB());
    $webFromShow = $service->getSettingByVariable(SettingTypeEnum::WEB());

    expect($webFromIndex)->not->toBeNull()
        ->and($webFromIndex->toArray(new Request()))->toEqual($webFromShow->toArray(new Request()));
});

it('keeps index() and show() in sync when a stale cache exists before the save', function () {
    $service = app(SettingService::class);

    // Prime the cache with defaults (simulates an early read before any save).
    $service->getSettingByVariable(SettingTypeEnum::WEB());
    expect(Cache::has('settings:web'))->toBeTrue();

    // Now save real values — the cache must be invalidated so both endpoints see them.
    $service->saveSetting(SettingTypeEnum::WEB(), [
        'siteName' => 'Brand After Save',
        'supportEmail' => 'after@example.test',
        'supportNumber' => '1111111111',
    ]);

    $webFromShow = $service->getSettingByVariable(SettingTypeEnum::WEB())->toArray(new Request());
    $webFromIndex = $service->getAllSettings()
        ->first(fn ($resource) => $resource->resource->variable === SettingTypeEnum::WEB())
        ->toArray(new Request());

    expect($webFromShow['value']['siteName'])->toBe('Brand After Save')
        ->and($webFromIndex['value']['siteName'])->toBe('Brand After Save')
        ->and($webFromIndex)->toEqual($webFromShow);
});

it('persists the web setting row to the DB on save (not just the cache)', function () {
    $service = app(SettingService::class);

    $service->saveSetting(SettingTypeEnum::WEB(), [
        'siteName' => 'DB Persistence Check',
    ]);

    // The smoking-gun assertion from the original bug report: after a save the
    // settings table itself must hold the row, not only the cache. If this
    // ever fails, optimize:clear will "remove" the data again.
    $row = Setting::query()->where('variable', SettingTypeEnum::WEB())->first();

    expect($row)->not->toBeNull()
        ->and($row->value['siteName'])->toBe('DB Persistence Check');
});

it('forgets the cache key on save so the next read repopulates from the DB', function () {
    $service = app(SettingService::class);

    // Pre-populate the cache.
    $service->getSettingByVariable(SettingTypeEnum::WEB());
    expect(Cache::has('settings:web'))->toBeTrue();

    $service->saveSetting(SettingTypeEnum::WEB(), [
        'siteName' => 'Forget Check',
    ]);

    // After a save the cache must be empty until the next read repopulates it.
    expect(Cache::has('settings:web'))->toBeFalse();
});
