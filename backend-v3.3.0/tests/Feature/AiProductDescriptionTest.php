<?php

use App\Contracts\AiDriverInterface;
use App\Enums\AiProviderEnum;
use App\Enums\DefaultSystemRolesEnum;
use App\Enums\GuardNameEnum;
use App\Enums\SettingTypeEnum;
use App\Http\Middleware\EnsureSubscriptionFeatureSelected;
use App\Http\Middleware\EnsureSystemVendorTypeSelected;
use App\Http\Middleware\ValidateAdmin;
use App\Http\Middleware\VerifyLicense;
use App\Models\Country;
use App\Models\Setting;
use App\Models\User;
use App\Services\Ai\AiManager;
use App\Services\Ai\Drivers\AnthropicDriver;
use App\Services\Ai\Drivers\DeepSeekDriver;
use App\Services\Ai\Drivers\GeminiDriver;
use App\Services\Ai\Drivers\GroqDriver;
use App\Services\Ai\Drivers\OpenAiDriver;
use App\Services\GeminiService;
use App\Services\SettingService;
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
    Permission::findOrCreate('setting.ai.edit', GuardNameEnum::ADMIN->value);
    Permission::findOrCreate('setting.ai.view', GuardNameEnum::ADMIN->value);
    $this->admin->assignRole(DefaultSystemRolesEnum::SUPER_ADMIN->value);
    $this->admin->givePermissionTo([
        'setting.system.edit',
        'setting.system.view',
        'setting.ai.edit',
        'setting.ai.view',
    ]);

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

test('admin can view AI settings page', function () {
    $response = $this->actingAs($this->admin, GuardNameEnum::ADMIN->value)->get('/admin/settings/ai');

    $response->assertStatus(200);
    $response->assertSee('AI Feature Settings');
});

test('admin can save AI settings with multi-provider credentials', function () {
    $response = $this->actingAs($this->admin, GuardNameEnum::ADMIN->value)->postJson('/admin/settings/store', [
        'type' => 'ai',
        'enableAiDescription' => '1',
        'aiProvider' => 'openai',
        'aiModel' => 'gpt-4o-mini',
        'aiCustomModel' => '',
        'aiCustomBaseUrl' => '',
        'aiTemperature' => '0.6',
        'geminiApiKey' => 'gemini-key-123',
        'openaiApiKey' => 'sk-openai-key-456',
        'claudeApiKey' => 'sk-claude-key-789',
        'deepseekApiKey' => 'sk-deepseek-key-101',
        'groqApiKey' => 'gsk-groq-key-202',
    ]);

    $response->assertStatus(200);
    $response->assertJson(['success' => true]);

    $dbSetting = Setting::find('ai');
    $rawValues = $dbSetting ? $dbSetting->value : [];

    expect((bool) ($rawValues['enableAiDescription'] ?? false))->toBeTrue()
        ->and($rawValues['aiProvider'])->toBe('openai')
        ->and($rawValues['aiModel'])->toBe('gpt-4o-mini')
        ->and($rawValues['openaiApiKey'])->toBe('sk-openai-key-456')
        ->and($rawValues['geminiApiKey'])->toBe('gemini-key-123')
        ->and($rawValues['groqApiKey'])->toBe('gsk-groq-key-202');
});

test('ai manager throws exception if AI is disabled', function () {
    $aiManager = app(AiManager::class);

    expect(fn () => $aiManager->generateProductDescription(['title' => 'Test Product']))
        ->toThrow(Exception::class);
});

test('ai description generation works with Google Gemini driver', function () {
    $settingService = app(SettingService::class);
    $settingService->saveSetting('ai', [
        'enableAiDescription' => true,
        'aiProvider' => 'gemini',
        'aiModel' => 'gemini-3.8-flash',
        'geminiApiKey' => 'fake-gemini-key',
    ]);

    Http::fake([
        'https://generativelanguage.googleapis.com/*' => Http::response([
            'candidates' => [
                [
                    'content' => [
                        'parts' => [
                            [
                                'text' => json_encode([
                                    'short_description' => 'A premium organic strawberry pack.',
                                    'description' => "Freshly picked farm strawberries.\n• 100% Organic\n• Sweet & Juicy",
                                ]),
                            ],
                        ],
                    ],
                ],
            ],
        ], 200),
    ]);

    $response = $this->actingAs($this->admin, GuardNameEnum::ADMIN->value)->postJson('/admin/products/generate-ai-description', [
        'title' => 'Fresh Strawberries',
        'tone' => 'professional',
        'keywords' => 'Organic, 500g',
    ]);

    $response->assertStatus(200)
        ->assertJson([
            'success' => true,
            'data' => [
                'short_description' => 'A premium organic strawberry pack.',
                'description' => "Freshly picked farm strawberries.\n• 100% Organic\n• Sweet & Juicy",
            ],
        ]);
});

test('product AI generation returns the provider error message', function () {
    app(SettingService::class)->saveSetting('ai', [
        'enableAiDescription' => true,
        'aiProvider' => 'gemini',
        'aiModel' => 'gemini-3.8-flash',
        'geminiApiKey' => 'fake-gemini-key',
    ]);

    Http::fake([
        'https://generativelanguage.googleapis.com/*' => Http::response([
            'error' => [
                'message' => 'The AI provider is temporarily unavailable. Please try again later.',
                'status' => 'UNAVAILABLE',
            ],
        ], 503),
    ]);

    $response = $this->actingAs($this->admin, GuardNameEnum::ADMIN->value)
        ->postJson('/admin/products/generate-ai-description', ['title' => 'Unavailable Product']);

    $response->assertStatus(400)
        ->assertJsonPath('success', false)
        ->assertJsonPath('message', 'The AI provider is temporarily unavailable. Please try again later.');
});

test('product AI translation returns the provider error message', function () {
    app(SettingService::class)->saveSetting('ai', [
        'enableAiDescription' => true,
        'aiProvider' => 'gemini',
        'aiModel' => 'gemini-3.8-flash',
        'geminiApiKey' => 'fake-gemini-key',
    ]);

    Http::fake([
        'https://generativelanguage.googleapis.com/*' => Http::response([
            'error' => [
                'message' => 'The AI provider quota has been exceeded. Please try again later.',
                'status' => 'RESOURCE_EXHAUSTED',
            ],
        ], 429),
    ]);

    $response = $this->actingAs($this->admin, GuardNameEnum::ADMIN->value)
        ->postJson('/admin/translations/ai-translate', [
            'module' => 'product',
            'fields' => [
                'title' => 'English product',
                'short_description' => 'English short description',
                'description' => 'English description',
            ],
        ]);

    $response->assertStatus(422)
        ->assertJsonPath('success', false)
        ->assertJsonPath('message', 'The AI provider quota has been exceeded. Please try again later.');
});

test('product AI translation rejects inactive or unknown target locales', function () {
    $response = $this->actingAs($this->admin, GuardNameEnum::ADMIN->value)
        ->postJson('/admin/translations/ai-translate', [
            'module' => 'product',
            'target_locales' => ['not-configured'],
            'fields' => ['title' => 'English product'],
        ]);

    $response->assertStatus(422)
        ->assertJsonValidationErrors('target_locales.0');
});

test('ai description generation works with OpenAI driver', function () {
    $settingService = app(SettingService::class);
    $settingService->saveSetting('ai', [
        'enableAiDescription' => true,
        'aiProvider' => 'openai',
        'aiModel' => 'gpt-4o-mini',
        'openaiApiKey' => 'sk-fake-openai-key',
    ]);

    Http::fake([
        'https://api.openai.com/v1/chat/completions' => Http::response([
            'choices' => [
                [
                    'message' => [
                        'content' => json_encode([
                            'short_description' => 'Wireless noise-canceling headphones with rich acoustics.',
                            'description' => "Immerse yourself in crystal sound.\n• Active Noise Cancellation\n• 40h Battery",
                        ]),
                    ],
                ],
            ],
        ], 200),
    ]);

    $response = $this->actingAs($this->admin, GuardNameEnum::ADMIN->value)->postJson('/admin/products/generate-ai-description', [
        'title' => 'Wireless Headphones',
    ]);

    $response->assertStatus(200)
        ->assertJson([
            'success' => true,
            'data' => [
                'short_description' => 'Wireless noise-canceling headphones with rich acoustics.',
                'description' => "Immerse yourself in crystal sound.\n• Active Noise Cancellation\n• 40h Battery",
            ],
        ]);
});

test('ai description generation works with Anthropic Claude driver', function () {
    $settingService = app(SettingService::class);
    $settingService->saveSetting('ai', [
        'enableAiDescription' => true,
        'aiProvider' => 'claude',
        'aiModel' => 'claude-3-5-haiku-latest',
        'claudeApiKey' => 'sk-ant-fake-claude-key',
    ]);

    Http::fake([
        'https://api.anthropic.com/v1/messages' => Http::response([
            'content' => [
                [
                    'text' => json_encode([
                        'short_description' => 'Artisanal roasted arabica coffee beans.',
                        'description' => "Full-bodied single-origin roast.\n• Notes of dark cocoa\n• Medium dark roast",
                    ]),
                ],
            ],
        ], 200),
    ]);

    $response = $this->actingAs($this->admin, GuardNameEnum::ADMIN->value)->postJson('/admin/products/generate-ai-description', [
        'title' => 'Artisanal Coffee',
    ]);

    $response->assertStatus(200)
        ->assertJson([
            'success' => true,
            'data' => [
                'short_description' => 'Artisanal roasted arabica coffee beans.',
                'description' => "Full-bodied single-origin roast.\n• Notes of dark cocoa\n• Medium dark roast",
            ],
        ]);
});

test('ai description generation works with Groq and DeepSeek drivers', function () {
    $settingService = app(SettingService::class);
    $settingService->saveSetting('ai', [
        'enableAiDescription' => true,
        'aiProvider' => 'groq',
        'aiModel' => 'llama-3.3-70b-versatile',
        'groqApiKey' => 'gsk-fake-groq-key',
    ]);

    Http::fake([
        'https://api.groq.com/openai/v1/chat/completions' => Http::response([
            'choices' => [
                [
                    'message' => [
                        'content' => json_encode([
                            'short_description' => 'Ultra high-speed espresso machine.',
                            'description' => "Brew cafe-quality drinks in seconds.\n• 15 Bar Pressure\n• Rapid heating",
                        ]),
                    ],
                ],
            ],
        ], 200),
    ]);

    $response = $this->actingAs($this->admin, GuardNameEnum::ADMIN->value)->postJson('/admin/products/generate-ai-description', [
        'title' => 'Espresso Machine',
    ]);

    $response->assertStatus(200)
        ->assertJson([
            'success' => true,
            'data' => [
                'short_description' => 'Ultra high-speed espresso machine.',
            ],
        ]);
});

test('test connection endpoint returns 200 on valid API response', function () {
    Http::fake([
        'https://api.openai.com/v1/chat/completions' => Http::response([
            'choices' => [
                [
                    'message' => [
                        'content' => '{"status":"connected"}',
                    ],
                ],
            ],
        ], 200),
    ]);

    $response = $this->actingAs($this->admin, GuardNameEnum::ADMIN->value)->postJson('/admin/settings/ai/test-connection', [
        'provider' => 'openai',
        'api_key' => 'sk-test-key',
        'model' => 'gpt-4o-mini',
    ]);

    $response->assertStatus(200)
        ->assertJsonPath('success', true);
});

test('test connection endpoint returns 400 when API call fails', function () {
    Http::fake([
        'https://api.openai.com/v1/chat/completions' => Http::response([
            'error' => [
                'message' => 'Incorrect API key provided.',
            ],
        ], 401),
    ]);

    $response = $this->actingAs($this->admin, GuardNameEnum::ADMIN->value)->postJson('/admin/settings/ai/test-connection', [
        'provider' => 'openai',
        'api_key' => 'invalid-key',
        'model' => 'gpt-4o-mini',
    ]);

    $response->assertStatus(400)
        ->assertJsonPath('success', false)
        ->assertJsonPath('message', 'Incorrect API key provided.');
});

test('ai description handles truncated or raw text without leaking JSON brackets', function () {
    $settingService = app(SettingService::class);
    $settingService->saveSetting('ai', [
        'enableAiDescription' => true,
        'aiProvider' => 'gemini',
        'aiModel' => 'gemini-3.8-flash',
        'geminiApiKey' => 'fake-api-key',
    ]);

    Http::fake([
        'https://generativelanguage.googleapis.com/*' => Http::response([
            'candidates' => [
                [
                    'content' => [
                        'parts' => [
                            [
                                'text' => '{ "short_description": "Elevate your culinary creations with our premium coriander seeds',
                            ],
                        ],
                    ],
                ],
            ],
        ], 200),
    ]);

    $response = $this->actingAs($this->admin, GuardNameEnum::ADMIN->value)->postJson('/admin/products/generate-ai-description', [
        'title' => 'Coriander Seeds',
    ]);

    $response->assertStatus(200)
        ->assertJson(['success' => true]);

    $data = $response->json('data');
    expect($data['short_description'])->toBe('Elevate your culinary creations with our premium coriander seeds');
    expect($data['short_description'])->not->toContain('{');
});

test('admin can view ai settings page and see saved api keys', function () {
    $settingService = app(SettingService::class);
    $settingService->saveSetting('ai', [
        'enableAiDescription' => true,
        'aiProvider' => 'gemini',
        'aiModel' => 'gemini-3.8-flash',
        'geminiApiKey' => 'AIzaSyTestApiKey1234567890',
        'openaiApiKey' => 'sk-test-openai-key-abc',
    ]);

    $response = $this->actingAs($this->admin, GuardNameEnum::ADMIN->value)->get('/admin/settings/ai');

    $response->assertStatus(200);
    $response->assertViewHas('settings', function ($settings) {
        return ($settings['geminiApiKey'] ?? '') === 'AIzaSyTestApiKey1234567890'
            && ($settings['openaiApiKey'] ?? '') === 'sk-test-openai-key-abc'
            && ($settings['aiProvider'] ?? '') === 'gemini';
    });
});

test('product create form shows Generate with AI button when enabled in ai settings', function () {
    $settingService = app(SettingService::class);
    $settingService->saveSetting('ai', [
        'enableAiDescription' => true,
        'aiProvider' => 'gemini',
        'aiModel' => 'gemini-3.8-flash',
        'geminiApiKey' => 'fake-key',
    ]);

    expect(Setting::isAiDescriptionEnabled())->toBeTrue();

    $response = $this->actingAs($this->admin, GuardNameEnum::ADMIN->value)->get(route('admin.products.create'));
    $response->assertStatus(200);
    $response->assertSee('Generate with AI');
});

test('ai product generator returns full suite with title, HTML description, tags, and SEO metadata', function () {
    $settingService = app(SettingService::class);
    $settingService->saveSetting('ai', [
        'enableAiDescription' => true,
        'aiProvider' => 'openai',
        'aiModel' => 'gpt-4o-mini',
        'openaiApiKey' => 'sk-fake-openai-key',
    ]);

    Http::fake([
        'https://api.openai.com/v1/chat/completions' => Http::response([
            'choices' => [
                [
                    'message' => [
                        'content' => json_encode([
                            'title' => 'Ergonomic Wireless Gaming Mouse RGB',
                            'short_description' => 'Precision wireless gaming mouse with ultra-low latency and customizable RGB lighting.',
                            'description' => '<p>Dominate your games with pro-grade precision.</p><h3>Key Features</h3><ul><li><strong>Ultra-low latency:</strong> 1ms response rate.</li><li><strong>Custom RGB:</strong> 16.8M colors.</li></ul><p><strong>Why Choose Us:</strong> Built with high durability switches.</p>',
                            'tags' => ['Gaming Mouse', 'Wireless Mouse', 'RGB Accessories', 'PC Gaming'],
                            'seo_title' => 'Ergonomic Wireless Gaming Mouse RGB | Pro Gear',
                            'seo_keywords' => ['gaming mouse', 'wireless mouse', 'rgb gaming', 'ergonomic mouse'],
                            'seo_description' => 'Upgrade your setup with the ultra-fast Ergonomic Wireless Gaming Mouse featuring 1ms latency and custom RGB lighting. Free fast shipping.',
                        ]),
                    ],
                ],
            ],
        ], 200),
    ]);

    $response = $this->actingAs($this->admin, GuardNameEnum::ADMIN->value)->postJson('/admin/products/generate-ai-description', [
        'title' => 'Wireless Gaming Mouse',
        'tone' => 'persuasive',
        'length' => 'standard',
        'keywords' => 'RGB, 1ms, Ergonomic',
    ]);

    $response->assertStatus(200)
        ->assertJson([
            'success' => true,
            'data' => [
                'title' => 'Ergonomic Wireless Gaming Mouse RGB',
                'short_description' => 'Precision wireless gaming mouse with ultra-low latency and customizable RGB lighting.',
                'tags' => ['Gaming Mouse', 'Wireless Mouse', 'RGB Accessories', 'PC Gaming'],
                'seo_title' => 'Ergonomic Wireless Gaming Mouse RGB | Pro Gear',
            ],
        ]);

    $data = $response->json('data');
    expect($data['description'])->toContain('<h3>Key Features</h3>');
    expect($data['seo_keywords'])->toBeArray();
    expect($data['seo_description'])->toBeString();
});


