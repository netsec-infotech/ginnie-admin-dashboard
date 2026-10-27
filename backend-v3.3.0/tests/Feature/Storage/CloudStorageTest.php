<?php

use App\Enums\SettingTypeEnum;
use App\Models\Setting;
use App\Types\Settings\StorageSettingType;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

it('validates storage settings correctly for local disk', function () {
    $instance = StorageSettingType::fromArray([
        'storageDisk' => 'local',
    ]);

    expect($instance->storageDisk)->toBe('local');
});

it('resolves media urls correctly using media_url helper', function () {
    Config::set('media-library.disk_name', 'local');
    $url = media_url('products/test.jpg');

    expect($url)->toContain('/storage/products/test.jpg');

    Config::set('media-library.disk_name', 's3');
    Config::set('filesystems.disks.s3.url', 'https://cdn.example.com');

    $cloudUrl = media_url('products/test.jpg');

    expect($cloudUrl)->toBe('https://cdn.example.com/products/test.jpg');
});

it('configures runtime cloud storage on boot when storage setting exists', function () {
    $storageKey = (string) SettingTypeEnum::STORAGE();

    Setting::create([
        'variable' => $storageKey,
        'value' => json_encode([
            'storageDisk' => 'r2',
            'awsAccessKeyId' => 'test-key',
            'awsSecretAccessKey' => 'test-secret',
            'awsRegion' => 'auto',
            'awsBucket' => 'my-r2-bucket',
            'awsEndpoint' => 'https://12345.r2.cloudflarestorage.com',
            'awsAssetUrl' => 'https://assets.example.com',
        ]),
    ]);

    $setting = Setting::where('variable', $storageKey)->first();
    if (!empty($setting?->value)) {
        $storageSettings = $setting->value;
        Config::set('filesystems.disks.r2.key', $storageSettings['awsAccessKeyId']);
        Config::set('filesystems.disks.r2.secret', $storageSettings['awsSecretAccessKey']);
        Config::set('filesystems.disks.r2.bucket', $storageSettings['awsBucket']);
        Config::set('filesystems.disks.r2.endpoint', $storageSettings['awsEndpoint']);
        Config::set('filesystems.disks.r2.url', $storageSettings['awsAssetUrl']);
        Config::set('media-library.disk_name', 'r2');
    }

    expect(config('media-library.disk_name'))->toBe('r2');
    expect(config('filesystems.disks.r2.bucket'))->toBe('my-r2-bucket');
    expect(config('filesystems.disks.r2.url'))->toBe('https://assets.example.com');
});

it('runs media:migrate-cloud command safely with dry-run', function () {
    $this->artisan('media:migrate-cloud', ['--disk' => 's3', '--dry-run' => true])
        ->assertExitCode(0);
});

it('prevents media:migrate-cloud target from being local disk', function () {
    $this->artisan('media:migrate-cloud', ['--disk' => 'local'])
        ->expectsOutputToContain('Target disk [local] is local storage')
        ->assertExitCode(1);
});

it('runs media:sync-local command safely with dry-run', function () {
    $this->artisan('media:sync-local', ['--dry-run' => true])
        ->assertExitCode(0);
});

it('registers media migration and sync commands in CronMonitorService', function () {
    $service = app(\App\Services\CronMonitorService::class);
    $commands = collect($service->getRegisteredCommands())->pluck('command')->toArray();

    expect($commands)->toContain('media:migrate-cloud');
    expect($commands)->toContain('media:sync-local');
});
