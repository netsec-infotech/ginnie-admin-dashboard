<?php

use App\Models\OnboardingScreen;
use App\Models\OnboardingScreenSetting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use App\Enums\DefaultSystemRolesEnum;

uses(RefreshDatabase::class);

it('rejects an invalid onboarding app type', function (): void {
    $this->getJson('/api/onboarding-screens?app=invalid')
        ->assertStatus(422)
        ->assertJson([
            'success' => false,
        ]);
});

it('returns empty screens when onboarding is disabled for the app', function (): void {
    OnboardingScreenSetting::create([
        'app_type' => 'customer',
        'is_enabled' => false,
    ]);

    OnboardingScreen::create([
        'app_type' => 'customer',
        'title' => 'Welcome',
        'subtitle' => 'Start ordering nearby.',
    ]);

    $this->getJson('/api/onboarding-screens?app=customer')
        ->assertOk()
        ->assertJsonPath('data.app_type', 'customer')
        ->assertJsonPath('data.is_enabled', false)
        ->assertJsonPath('data.screens', []);
});

it('returns enabled onboarding screens ordered for the requested app only', function (): void {
    OnboardingScreenSetting::create([
        'app_type' => 'customer',
        'is_enabled' => true,
    ]);

    OnboardingScreen::create([
        'app_type' => 'seller',
        'title' => 'Seller only',
        'subtitle' => 'Hidden from customer response.',
    ]);

    OnboardingScreen::create([
        'app_type' => 'customer',
        'title' => 'First',
        'subtitle' => 'Shown first.',
        'background_type' => 'gradient',
        'gradient_start_color' => '#111111',
        'gradient_end_color' => '#eeeeee',
        'overlay_enabled' => true,
        'overlay_type' => 'image',
        'overlay_opacity' => 0.65,
        'overlay_image_size' => 75,
        'button_background_color' => '#123456',
        'button_foreground_color' => '#ffffff',
        'indicator_active_color' => '#abcdef',
        'indicator_inactive_color' => '#fedcba',
    ]);

    OnboardingScreen::create([
        'app_type' => 'customer',
        'title' => 'Second',
        'subtitle' => 'Shown second.',
        'overlay_enabled' => false,
        'overlay_type' => 'image',
        'overlay_opacity' => 1,
    ]);

    $this->getJson('/api/onboarding-screens?app=customer')
        ->assertOk()
        ->assertJsonPath('data.app_type', 'customer')
        ->assertJsonPath('data.is_enabled', true)
        ->assertJsonPath('data.screens.0.title', 'First')
        ->assertJsonPath('data.screens.0.design.background.type', 'gradient')
        ->assertJsonPath('data.screens.0.design.background.gradient_start_color', '#111111')
        ->assertJsonPath('data.screens.0.design.overlay.type', 'image')
        ->assertJsonPath('data.screens.0.design.overlay.image', '')
        ->assertJsonPath('data.screens.0.design.overlay.image_size', 75)
        ->assertJsonPath('data.screens.0.design.overlay.opacity', 0.65)
        ->assertJsonPath('data.screens.0.design.overlay_opacity', 0.65)
        ->assertJsonPath('data.screens.0.design.button.background_color', '#123456')
        ->assertJsonPath('data.screens.0.design.button.foreground_color', '#ffffff')
        ->assertJsonPath('data.screens.0.design.indicator.active_color', '#abcdef')
        ->assertJsonPath('data.screens.0.design.indicator.inactive_color', '#fedcba')
        ->assertJsonPath('data.screens.1.title', 'Second')
        ->assertJsonPath('data.screens.1.media', '')
        ->assertJsonPath('data.screens.1.media_type', '')
        ->assertJsonPath('data.screens.1.media_mime_type', '')
        ->assertJsonPath('data.screens.1.design.background.image', '')
        ->assertJsonPath('data.screens.1.design.overlay', null)
        ->assertJsonPath('data.screens.1.design.overlay_opacity', 0)
        ->assertJsonCount(2, 'data.screens');
});

it('returns at most five onboarding screens for one app', function (): void {
    OnboardingScreenSetting::create([
        'app_type' => 'customer',
        'is_enabled' => true,
    ]);

    for ($screen = 1; $screen <= 6; $screen++) {
        OnboardingScreen::create([
            'app_type' => 'customer',
            'title' => "Screen {$screen}",
            'subtitle' => "Subtitle {$screen}",
        ]);
    }

    $this->getJson('/api/onboarding-screens?app=customer')
        ->assertOk()
        ->assertJsonCount(5, 'data.screens')
        ->assertJsonPath('data.screens.4.title', 'Screen 5');
});

it('returns valid admin datatable json for onboarding screens', function (): void {
    $this->withoutMiddleware([
        \App\Http\Middleware\VerifyLicense::class,
        \App\Http\Middleware\EnsureSystemVendorTypeSelected::class,
        \App\Http\Middleware\EnsureSubscriptionFeatureSelected::class,
    ]);

    $admin = User::forceCreate([
        'name' => 'Admin Test',
        'email' => 'admin.'.uniqid().'@example.test',
        'password' => bcrypt('password'),
        'access_panel' => 'admin',
    ]);
    $admin->assignRole(Role::findOrCreate(DefaultSystemRolesEnum::SUPER_ADMIN->value, 'web'));

    OnboardingScreen::create([
        'app_type' => 'customer',
        'title' => 'Customer slide',
        'subtitle' => 'Customer subtitle',
    ]);

    $this->actingAs($admin, 'web')
        ->getJson(route('admin.onboarding-screens.datatable', [
            'app_type' => 'customer',
            'draw' => 1,
            'start' => 0,
            'length' => 10,
        ]))
        ->assertOk()
        ->assertJsonPath('draw', 1)
        ->assertJsonPath('recordsTotal', 1)
        ->assertJsonPath('data.0.title_raw', 'Customer slide')
        ->assertJsonStructure([
            'preview',
            'data' => [
                '*' => ['id', 'image', 'details', 'created_at', 'title_raw', 'app_type', 'design', 'action'],
            ],
        ]);
});
