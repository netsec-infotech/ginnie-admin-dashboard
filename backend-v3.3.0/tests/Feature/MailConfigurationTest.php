<?php

use App\Enums\SettingTypeEnum;
use App\Models\Setting;
use App\Providers\MailConfigServiceProvider;
use App\Types\Settings\EmailSettingType;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;

uses(RefreshDatabase::class);

function writeMailConfigurationSetting(array $value): void
{
    Setting::query()->updateOrCreate(
        ['variable' => SettingTypeEnum::EMAIL()],
        ['value' => json_encode($value)]
    );
}

it('uses the dedicated from address instead of the smtp username', function () {
    $originalEmail = Setting::query()->find(SettingTypeEnum::EMAIL())?->getRawOriginal('value');
    $originalSystem = Setting::query()->find(SettingTypeEnum::SYSTEM())?->getRawOriginal('value');

    try {
        writeMailConfigurationSetting([
            'smtpHost' => 'smtp-relay.example.test',
            'smtpPort' => '587',
            'smtpEmail' => 'smtp-login@example.test',
            'smtpPassword' => 'secret',
            'smtpEncryption' => 'tls',
            'smtpContentType' => 'html',
            'mailFromAddress' => 'orders@example.test',
            'mailFromName' => 'Example Orders',
        ]);

        Setting::query()->updateOrCreate(
            ['variable' => SettingTypeEnum::SYSTEM()],
            ['value' => json_encode(['appName' => 'System Brand'])]
        );

        (new MailConfigServiceProvider(app()))->boot();

        expect(Config::get('mail.mailers.smtp.username'))->toBe('smtp-login@example.test')
            ->and(Config::get('mail.from.address'))->toBe('orders@example.test')
            ->and(Config::get('mail.from.name'))->toBe('Example Orders');
    } finally {
        restoreMailConfigurationSetting(SettingTypeEnum::EMAIL(), $originalEmail);
        restoreMailConfigurationSetting(SettingTypeEnum::SYSTEM(), $originalSystem);
    }
});

it('falls back to smtp username for legacy email settings without a from address', function () {
    $originalEmail = Setting::query()->find(SettingTypeEnum::EMAIL())?->getRawOriginal('value');

    try {
        writeMailConfigurationSetting([
            'smtpHost' => 'smtp-relay.example.test',
            'smtpPort' => '587',
            'smtpEmail' => 'legacy-login@example.test',
            'smtpPassword' => 'secret',
            'smtpEncryption' => 'tls',
            'smtpContentType' => 'text',
        ]);

        (new MailConfigServiceProvider(app()))->boot();

        expect(Config::get('mail.mailers.smtp.username'))->toBe('legacy-login@example.test')
            ->and(Config::get('mail.from.address'))->toBe('legacy-login@example.test');
    } finally {
        restoreMailConfigurationSetting(SettingTypeEnum::EMAIL(), $originalEmail);
    }
});

it('persists smtp authentication and visible sender fields separately', function () {
    $settings = EmailSettingType::fromArray([
        'smtpHost' => 'smtp-relay.example.test',
        'smtpPort' => '587',
        'smtpEmail' => 'api-key-login@example.test',
        'smtpPassword' => 'secret',
        'smtpEncryption' => 'tls',
        'smtpContentType' => 'html',
        'mailFromAddress' => 'billing@example.test',
        'mailFromName' => 'Billing Team',
    ]);

    expect($settings->smtpEmail)->toBe('api-key-login@example.test')
        ->and($settings->mailFromAddress)->toBe('billing@example.test')
        ->and($settings->mailFromName)->toBe('Billing Team');
});

function restoreMailConfigurationSetting(string $variable, ?string $rawValue): void
{
    if ($rawValue === null) {
        Setting::query()->where('variable', $variable)->delete();

        return;
    }

    Setting::query()->updateOrCreate(
        ['variable' => $variable],
        ['value' => $rawValue]
    );
}
