<?php

use App\Enums\SplashSlotEnum;
use App\Enums\SpatieMediaCollectionName;
use App\Models\SplashScreen;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;

uses(RefreshDatabase::class);

it('rejects an invalid splash app type', function (): void {
    $this->getJson('/api/splash-config?app=invalid')
        ->assertStatus(422)
        ->assertJson([
            'success' => false,
        ]);
});

it('returns null slots when no splash is configured', function (): void {
    $this->getJson('/api/splash-config?app=customer')
        ->assertOk()
        ->assertJsonPath('data.current', null)
        ->assertJsonPath('data.staged_next', null);
});

it('returns current and staged splash for the requested app only', function (): void {
    SplashScreen::create([
        'app_type' => 'seller',
        'slot' => SplashSlotEnum::CURRENT(),
        'version' => 'seller-version',
        'media_type' => 'image',
        'bg_type' => 'color',
        'bg_color' => '#111111',
        'min_display_seconds' => 2,
    ]);

    SplashScreen::create([
        'app_type' => 'customer',
        'slot' => SplashSlotEnum::CURRENT(),
        'version' => 'current-version',
        'media_type' => 'image',
        'bg_type' => 'color',
        'bg_color' => '#1B5E20',
        'overlay_enabled' => true,
        'overlay_type' => 'image',
        'overlay_x_percent' => 50,
        'overlay_y_percent' => 48,
        'overlay_width_percent' => 60,
        'min_display_seconds' => 2,
    ]);

    SplashScreen::create([
        'app_type' => 'customer',
        'slot' => SplashSlotEnum::STAGED_NEXT(),
        'version' => 'staged-version',
        'media_type' => 'image',
        'bg_type' => 'image',
        'bg_color' => '#1A002C',
        'overlay_enabled' => true,
        'overlay_type' => 'lottie',
        'overlay_x_percent' => 50,
        'overlay_y_percent' => 45,
        'overlay_width_percent' => 70,
        'min_display_seconds' => 3,
    ]);

    $this->getJson('/api/splash-config?app=customer')
        ->assertOk()
        ->assertJsonPath('data.current.version', 'current-version')
        ->assertJsonPath('data.current.background.type', 'color')
        ->assertJsonPath('data.current.background.color', '#1B5E20')
        ->assertJsonPath('data.current.overlay.enabled', true)
        ->assertJsonPath('data.current.overlay.position.y_percent', 48)
        ->assertJsonPath('data.staged_next.version', 'staged-version')
        ->assertJsonPath('data.staged_next.background.type', 'image')
        ->assertJsonPath('data.staged_next.overlay.type', 'lottie')
        ->assertJsonPath('data.staged_next.overlay.position.width_percent', 70)
        ->assertJsonPath('data.staged_next.min_display_seconds', 3);
});

it('returns the updated version after cache is cleared', function (): void {
    $screen = SplashScreen::create([
        'app_type' => 'customer',
        'slot' => SplashSlotEnum::CURRENT(),
        'version' => 'first-version',
        'media_type' => 'image',
        'bg_type' => 'color',
        'bg_color' => '#1B5E20',
        'min_display_seconds' => 2,
    ]);

    $this->getJson('/api/splash-config?app=customer')
        ->assertOk()
        ->assertJsonPath('data.current.version', 'first-version');

    $screen->update(['version' => 'second-version']);
    Cache::forget('splash_config:customer');

    $this->getJson('/api/splash-config?app=customer')
        ->assertOk()
        ->assertJsonPath('data.current.version', 'second-version');
});

it('returns cache keys and supports etag revalidation', function (): void {
    SplashScreen::create([
        'app_type' => 'customer',
        'slot' => SplashSlotEnum::CURRENT(),
        'version' => 'etag-version',
        'media_type' => 'image',
        'bg_type' => 'color',
        'bg_color' => '#1B5E20',
        'min_display_seconds' => 2,
    ]);

    $response = $this->getJson('/api/splash-config?app=customer')
        ->assertOk()
        ->assertJsonPath('data.current.cache_key', 'splash:1:etag-version')
        ->assertHeader('ETag');

    $this->withHeader('If-None-Match', $response->headers->get('ETag'))
        ->getJson('/api/splash-config?app=customer')
        ->assertStatus(304);
});

it('does not return an asset manifest for mobile splash config', function (): void {
    $screen = SplashScreen::create([
        'app_type' => 'customer',
        'slot' => SplashSlotEnum::CURRENT(),
        'version' => 'asset-version',
        'media_type' => 'image',
        'bg_type' => 'image',
        'bg_color' => '#1B5E20',
        'overlay_enabled' => true,
        'overlay_type' => 'image',
        'overlay_x_percent' => 50,
        'overlay_y_percent' => 45,
        'overlay_width_percent' => 60,
        'min_display_seconds' => 2,
    ]);

    $screen->addMedia(UploadedFile::fake()->image('background.jpg', 1080, 1920))
        ->toMediaCollection(SpatieMediaCollectionName::SPLASH_BACKGROUND_IMAGE());
    $screen->addMedia(UploadedFile::fake()->image('overlay.png', 400, 400))
        ->toMediaCollection(SpatieMediaCollectionName::SPLASH_OVERLAY_IMAGE());

    $this->getJson('/api/splash-config?app=customer')
        ->assertOk()
        ->assertJsonPath('data.current.background.type', 'image')
        ->assertJsonPath('data.current.overlay.type', 'image')
        ->assertJsonMissingPath('data.current.assets');
});
