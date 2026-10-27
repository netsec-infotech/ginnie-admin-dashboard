<?php

use App\Enums\DefaultSystemRolesEnum;
use App\Enums\GuardNameEnum;
use App\Enums\SettingTypeEnum;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
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

it('allows admin to save google analytics and microsoft clarity in web settings', function () {
    Storage::fake('public');

    $response = $this->actingAs($this->admin, GuardNameEnum::ADMIN->value)
        ->postJson(route('admin.settings.store'), [
            'type' => SettingTypeEnum::WEB(),
            'siteName' => 'Hyperlocal Store',
            'siteCopyright' => '© 2026 Hyperlocal',
            'supportNumber' => '1234567890',
            'supportEmail' => 'support@example.com',
            'address' => '123 Street',
            'shortDescription' => 'Best hyperlocal shopping app',
            'siteHeaderLogo' => UploadedFile::fake()->image('header.png'),
            'siteHeaderDarkLogo' => UploadedFile::fake()->image('header-dark.png'),
            'siteFooterLogo' => UploadedFile::fake()->image('footer.png'),
            'siteFavicon' => UploadedFile::fake()->image('favicon.ico'),
            'pwaName' => 'Hyperlocal PWA',
            'pwaDescription' => 'Hyperlocal PWA Description',
            'googleAnalyticsId' => 'G-WEBTEST12345',
            'microsoftClarityId' => 'clarityweb999',
            'headerScript' => '<script>console.log("custom header");</script>',
            'footerScript' => '<script>console.log("custom footer");</script>',
        ]);

    $response->assertOk()
        ->assertJson([
            'success' => true,
        ]);

    $setting = Setting::where('variable', SettingTypeEnum::WEB())->first();
    expect($setting)->not->toBeNull();
    $value = $setting->value;
    expect($value['googleAnalyticsId'])->toBe('G-WEBTEST12345')
        ->and($value['microsoftClarityId'])->toBe('clarityweb999')
        ->and($value['headerScript'])->toBe('<script>console.log("custom header");</script>')
        ->and($value['footerScript'])->toBe('<script>console.log("custom footer");</script>');
});

it('allows admin to save google analytics and microsoft clarity in app settings', function () {
    $response = $this->actingAs($this->admin, GuardNameEnum::ADMIN->value)
        ->postJson(route('admin.settings.store'), [
            'type' => SettingTypeEnum::APP(),
            'customerAppstoreLink' => 'https://apps.apple.com/app/customer',
            'customerPlaystoreLink' => 'https://play.google.com/store/apps/customer',
            'customerGoogleAnalyticsId' => 'G-CUSTAPP123',
            'customerMicrosoftClarityId' => 'claritycust456',
            'sellerGoogleAnalyticsId' => 'G-SELLAPP789',
            'sellerMicrosoftClarityId' => 'claritysell012',
            'riderGoogleAnalyticsId' => 'G-RIDEAPP345',
            'riderMicrosoftClarityId' => 'clarityride678',
        ]);

    $response->assertOk()
        ->assertJson([
            'success' => true,
        ]);

    $setting = Setting::where('variable', SettingTypeEnum::APP())->first();
    expect($setting)->not->toBeNull();
    $value = $setting->value;
    expect($value['customerGoogleAnalyticsId'])->toBe('G-CUSTAPP123')
        ->and($value['customerMicrosoftClarityId'])->toBe('claritycust456')
        ->and($value['sellerGoogleAnalyticsId'])->toBe('G-SELLAPP789')
        ->and($value['sellerMicrosoftClarityId'])->toBe('claritysell012')
        ->and($value['riderGoogleAnalyticsId'])->toBe('G-RIDEAPP345')
        ->and($value['riderMicrosoftClarityId'])->toBe('clarityride678');
});

it('returns google analytics and microsoft clarity in web and app api endpoints', function () {
    $service = app(\App\Services\SettingService::class);
    $service->saveSetting(SettingTypeEnum::WEB(), [
        'siteName' => 'Hyperlocal Store',
        'googleAnalyticsId' => 'G-WEBAPI123',
        'microsoftClarityId' => 'claritywebapi456',
    ]);

    $service->saveSetting(SettingTypeEnum::APP(), [
        'customerGoogleAnalyticsId' => 'G-CUSTAPI123',
        'customerMicrosoftClarityId' => 'claritycustapi456',
        'sellerGoogleAnalyticsId' => 'G-SELLAPI789',
        'sellerMicrosoftClarityId' => 'claritysellapi012',
        'riderGoogleAnalyticsId' => 'G-RIDEAPI345',
        'riderMicrosoftClarityId' => 'clarityrideapi678',
    ]);

    // Test GET /api/settings/web (show)
    $webResponse = $this->getJson(route('api.settings.show', ['setting' => 'web']));
    $webResponse->assertOk()
        ->assertJsonPath('data.value.googleAnalyticsId', 'G-WEBAPI123')
        ->assertJsonPath('data.value.microsoftClarityId', 'claritywebapi456');

    // Test GET /api/settings/app (show)
    $appResponse = $this->getJson(route('api.settings.show', ['setting' => 'app']));
    $appResponse->assertOk()
        ->assertJsonPath('data.value.customerGoogleAnalyticsId', 'G-CUSTAPI123')
        ->assertJsonPath('data.value.customerMicrosoftClarityId', 'claritycustapi456')
        ->assertJsonPath('data.value.sellerGoogleAnalyticsId', 'G-SELLAPI789')
        ->assertJsonPath('data.value.sellerMicrosoftClarityId', 'claritysellapi012')
        ->assertJsonPath('data.value.riderGoogleAnalyticsId', 'G-RIDEAPI345')
        ->assertJsonPath('data.value.riderMicrosoftClarityId', 'clarityrideapi678');

    // Test GET /api/settings (all)
    $allResponse = $this->getJson(route('api.settings.index'));
    $allResponse->assertOk();

    $data = collect($allResponse->json('data'));
    $webItem = $data->firstWhere('variable', 'web');
    $appItem = $data->firstWhere('variable', 'app');

    expect($webItem['value']['googleAnalyticsId'])->toBe('G-WEBAPI123')
        ->and($webItem['value']['microsoftClarityId'])->toBe('claritywebapi456')
        ->and($appItem['value']['customerGoogleAnalyticsId'])->toBe('G-CUSTAPI123')
        ->and($appItem['value']['customerMicrosoftClarityId'])->toBe('claritycustapi456')
        ->and($appItem['value']['sellerGoogleAnalyticsId'])->toBe('G-SELLAPI789')
        ->and($appItem['value']['sellerMicrosoftClarityId'])->toBe('claritysellapi012')
        ->and($appItem['value']['riderGoogleAnalyticsId'])->toBe('G-RIDEAPI345')
        ->and($appItem['value']['riderMicrosoftClarityId'])->toBe('clarityrideapi678');
});

it('renders analytics fields on the admin web settings page', function () {
    $service = app(\App\Services\SettingService::class);
    $service->saveSetting(SettingTypeEnum::WEB(), [
        'googleAnalyticsId' => 'G-WEBVIEW123',
        'microsoftClarityId' => 'claritywebview456',
    ]);

    $response = $this->actingAs($this->admin, GuardNameEnum::ADMIN->value)
        ->get(route('admin.settings.show', ['setting' => 'web']));

    $response->assertOk()
        ->assertSee('pills-analytics')
        ->assertSee('name="googleAnalyticsId"', false)
        ->assertSee('name="microsoftClarityId"', false)
        ->assertSee('G-WEBVIEW123')
        ->assertSee('claritywebview456');
});

it('renders analytics fields on the admin app settings page', function () {
    $service = app(\App\Services\SettingService::class);
    $service->saveSetting(SettingTypeEnum::APP(), [
        'customerGoogleAnalyticsId' => 'G-CUSTVIEW123',
        'customerMicrosoftClarityId' => 'claritycustview456',
        'sellerGoogleAnalyticsId' => 'G-SELLVIEW789',
        'sellerMicrosoftClarityId' => 'claritysellview012',
        'riderGoogleAnalyticsId' => 'G-RIDEVIEW345',
        'riderMicrosoftClarityId' => 'clarityrideview678',
    ]);

    $response = $this->actingAs($this->admin, GuardNameEnum::ADMIN->value)
        ->get(route('admin.settings.show', ['setting' => 'app']));

    $response->assertOk()
        ->assertSee('name="customerGoogleAnalyticsId"', false)
        ->assertSee('name="customerMicrosoftClarityId"', false)
        ->assertSee('name="sellerGoogleAnalyticsId"', false)
        ->assertSee('name="sellerMicrosoftClarityId"', false)
        ->assertSee('name="riderGoogleAnalyticsId"', false)
        ->assertSee('name="riderMicrosoftClarityId"', false)
        ->assertSee('G-CUSTVIEW123')
        ->assertSee('claritycustview456')
        ->assertSee('G-SELLVIEW789')
        ->assertSee('claritysellview012')
        ->assertSee('G-RIDEVIEW345')
        ->assertSee('clarityrideview678');
});

it('does not expose geminiApiKey or customSmsUrl in public settings API endpoints', function () {
    $service = app(\App\Services\SettingService::class);
    $service->saveSetting(SettingTypeEnum::SYSTEM(), [
        'appName' => 'Hyperlocal',
        'enableAiDescription' => true,
        'aiModel' => 'gemini-3.6-flash',
        'geminiApiKey' => 'super-secret-gemini-key-999',
    ]);

    $service->saveSetting(SettingTypeEnum::AUTHENTICATION(), [
        'customSms' => true,
        'customSmsUrl' => 'https://sms-gateway.example.com/api/send?token=secret123',
        'customSmsMethod' => 'POST',
    ]);

    // Public / Customer API GET /api/settings/system
    $systemResponse = $this->getJson(route('api.settings.show', ['setting' => 'system']));
    $systemResponse->assertOk()
        ->assertJsonMissingPath('data.value.geminiApiKey');

    // Public / Customer API GET /api/settings/authentication
    $authResponse = $this->getJson(route('api.settings.show', ['setting' => 'authentication']));
    $authResponse->assertOk()
        ->assertJsonMissingPath('data.value.customSmsUrl')
        ->assertJsonMissingPath('data.value.customSmsMethod');

    // Public / Customer API GET /api/settings (all)
    $allResponse = $this->getJson(route('api.settings.index'));
    $allResponse->assertOk();

    $data = collect($allResponse->json('data'));
    $systemItem = $data->firstWhere('variable', 'system');
    $authItem = $data->firstWhere('variable', 'authentication');

    expect($systemItem['value'])->not->toHaveKey('geminiApiKey')
        ->and($authItem['value'])->not->toHaveKey('customSmsUrl')
        ->and($authItem['value'])->not->toHaveKey('customSmsMethod');

    // Admin Panel GET /admin/settings/system still receives geminiApiKey
    $adminSystemResponse = $this->actingAs($this->admin, GuardNameEnum::ADMIN->value)
        ->get(route('admin.settings.show', ['setting' => 'system']));
    $adminSystemResponse->assertOk()
        ->assertSee('super-secret-gemini-key-999');

    // Admin Panel GET /admin/settings/authentication still receives customSmsUrl
    $adminAuthResponse = $this->actingAs($this->admin, GuardNameEnum::ADMIN->value)
        ->get(route('admin.settings.show', ['setting' => 'authentication']));
    $adminAuthResponse->assertOk()
        ->assertSee('https://sms-gateway.example.com/api/send?token=secret123');
});
