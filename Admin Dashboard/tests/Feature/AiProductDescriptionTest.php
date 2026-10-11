<?php

use App\Enums\DefaultSystemRolesEnum;
use App\Enums\GuardNameEnum;
use App\Enums\SettingTypeEnum;
use App\Models\Country;
use App\Models\Setting;
use App\Models\User;
use App\Services\GeminiService;
use App\Services\SettingService;
use App\Http\Middleware\VerifyLicense;
use App\Http\Middleware\ValidateAdmin;
use App\Http\Middleware\EnsureSystemVendorTypeSelected;
use App\Http\Middleware\EnsureSubscriptionFeatureSelected;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->withoutMiddleware([
        VerifyLicense::class,
        ValidateAdmin::class,
        EnsureSystemVendorTypeSelected::class,
        EnsureSubscriptionFeatureSelected::class,
    ]);
    $this->admin = User::factory()->create([
        'access_panel' => GuardNameEnum::ADMIN->value,
    ]);
    Role::findOrCreate(DefaultSystemRolesEnum::SUPER_ADMIN->value, GuardNameEnum::ADMIN->value);
    Permission::findOrCreate('setting.system.edit', GuardNameEnum::ADMIN->value);
    Permission::findOrCreate('setting.system.view', GuardNameEnum::ADMIN->value);
    $this->admin->assignRole(DefaultSystemRolesEnum::SUPER_ADMIN->value);
    $this->admin->givePermissionTo(['setting.system.edit', 'setting.system.view']);

    // Seed Country table for validation rule
    Country::create([
        'name' => 'United States',
        'currency' => 'USD',
        'currency_symbol' => '$',
        'iso2' => 'US',
        'iso3' => 'USA',
        'phonecode' => '1',
        'numeric_code' => '840',
    ]);

    // Pre-populate systemVendorType setting via SettingService
    $settingService = app(SettingService::class);
    $settingService->saveSetting('system', [
        'appName' => 'Hyperlocal',
        'systemVendorType' => 'multiple',
    ]);
});

test('system settings can enable AI description generator and save gemini api key', function () {
    $response = $this->actingAs($this->admin, GuardNameEnum::ADMIN->value)->postJson('/admin/settings/store', [
        'type' => 'system',
        'appName' => 'Hyperlocal Store',
        'systemTimezone' => 'UTC',
        'copyrightDetails' => '2026 Hyperlocal',
        'currency' => 'USD',
        'currencySymbol' => '$',
        'layoutType' => 'featured_section',
        'checkoutType' => 'multi_store',
        'minimumCartAmount' => 0,
        'maximumItemsAllowedInCart' => 50,
        'lowStockLimit' => 5,
        'enableAiDescription' => '1',
        'aiModel' => 'gemini-3.6-flash',
        'geminiApiKey' => 'test-gemini-key-12345',
    ]);

    $response->assertStatus(200);
    $response->assertJson(['success' => true]);

    $dbSetting = Setting::find('system');
    $rawValues = $dbSetting ? $dbSetting->value : [];

    expect((bool) ($rawValues['enableAiDescription'] ?? false))->toBeTrue();
    expect($rawValues['aiModel'] ?? '')->toBe('gemini-3.6-flash');
    expect($rawValues['geminiApiKey'] ?? '')->toBe('test-gemini-key-12345');
});

test('gemini service throws exception if AI is disabled', function () {
    $geminiService = app(GeminiService::class);

    expect(fn() => $geminiService->generateProductDescription(['title' => 'Test Product']))
        ->toThrow(Exception::class);
});

test('ai description controller returns generated short description and full description when gemini API succeeds', function () {
    $settingService = app(SettingService::class);
    $settingService->saveSetting('system', [
        'appName' => 'Hyperlocal',
        'enableAiDescription' => true,
        'aiModel' => 'gemini-3.6-flash',
        'geminiApiKey' => 'fake-api-key',
    ]);

    Http::fake([
        'https://generativelanguage.googleapis.com/*' => Http::response([
            'candidates' => [
                [
                    'content' => [
                        'parts' => [
                            [
                                'text' => json_encode([
                                    'short_description' => 'A premium wireless noise-canceling headphone.',
                                    'description' => "Enjoy crystal-clear sound with our flagship headphones.\n• Active Noise Cancellation\n• 30-hour battery life"
                                ])
                            ]
                        ]
                    ]
                ]
            ]
        ], 200)
    ]);

    $response = $this->actingAs($this->admin, GuardNameEnum::ADMIN->value)->postJson('/admin/products/generate-ai-description', [
        'title' => 'Wireless Headphones',
        'tone' => 'professional',
        'keywords' => 'Bluetooth 5.2, ANC, 30h battery',
    ]);

    $response->assertStatus(200);
    $response->assertJson([
        'success' => true,
        'data' => [
            'short_description' => 'A premium wireless noise-canceling headphone.',
            'description' => "Enjoy crystal-clear sound with our flagship headphones.\n• Active Noise Cancellation\n• 30-hour battery life"
        ]
    ]);
});

test('ai description handles truncated json responses without leaking json syntax', function () {
    $settingService = app(SettingService::class);
    $settingService->saveSetting('system', [
        'appName' => 'Hyperlocal',
        'enableAiDescription' => true,
        'aiModel' => 'gemini-3.6-flash',
        'geminiApiKey' => 'fake-api-key',
    ]);

    Http::fake([
        'https://generativelanguage.googleapis.com/*' => Http::response([
            'candidates' => [
                [
                    'content' => [
                        'parts' => [
                            [
                                'text' => '{ "short_description": "Elevate your culinary creations with our premium coriander, delivering a fresh, citrusy, and warm nutty flavor profile to every dish. Perfect for'
                            ]
                        ]
                    ]
                ]
            ]
        ], 200)
    ]);

    $response = $this->actingAs($this->admin, GuardNameEnum::ADMIN->value)->postJson('/admin/products/generate-ai-description', [
        'title' => 'Coriander Seeds',
    ]);

    $response->assertStatus(200);
    $response->assertJson([
        'success' => true,
    ]);

    $data = $response->json('data');
    expect($data['short_description'])->toBe('Elevate your culinary creations with our premium coriander, delivering a fresh, citrusy, and warm nutty flavor profile to every dish. Perfect for');
    expect($data['short_description'])->not->toContain('{');
    expect($data['short_description'])->not->toContain('"short_description"');
});
