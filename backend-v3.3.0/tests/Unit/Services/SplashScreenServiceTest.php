<?php

use App\Enums\SplashSlotEnum;
use App\Models\SplashScreen;
use App\Services\SplashScreenService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('promotes staged splash to current and removes the staged slot', function (): void {
    SplashScreen::create([
        'app_type' => 'customer',
        'slot' => SplashSlotEnum::CURRENT(),
        'version' => 'old-current',
        'media_type' => 'image',
        'bg_type' => 'color',
        'bg_color' => '#111111',
        'min_display_seconds' => 2,
    ]);

    $staged = SplashScreen::create([
        'app_type' => 'customer',
        'slot' => SplashSlotEnum::STAGED_NEXT(),
        'version' => 'new-staged',
        'media_type' => 'gif',
        'bg_type' => 'color',
        'bg_color' => '#222222',
        'min_display_seconds' => 3,
    ]);

    app(SplashScreenService::class)->promote($staged);

    expect(SplashScreen::query()->forApp('customer')->forSlot(SplashSlotEnum::CURRENT())->value('media_type'))->toBe('gif')
        ->and(SplashScreen::query()->forApp('customer')->forSlot(SplashSlotEnum::STAGED_NEXT())->exists())->toBeFalse()
        ->and(SplashScreen::query()->forApp('customer')->forSlot(SplashSlotEnum::ARCHIVED())->exists())->toBeTrue();
});

it('only promotes staged splash when activation time is due', function (): void {
    $futureStaged = SplashScreen::create([
        'app_type' => 'customer',
        'slot' => SplashSlotEnum::STAGED_NEXT(),
        'version' => 'future-staged',
        'media_type' => 'image',
        'bg_type' => 'color',
        'bg_color' => '#333333',
        'min_display_seconds' => 2,
        'activate_at' => now()->addHour(),
    ]);

    $service = app(SplashScreenService::class);

    expect($service->promoteDueForApp('customer'))->toBeNull()
        ->and($futureStaged->fresh()->slot)->toBe(SplashSlotEnum::STAGED_NEXT());

    $futureStaged->update(['activate_at' => now()->subMinute()]);

    expect($service->promoteDueForApp('customer'))->not->toBeNull()
        ->and(SplashScreen::query()->forApp('customer')->forSlot(SplashSlotEnum::CURRENT())->value('version'))->not->toBe('future-staged')
        ->and(SplashScreen::query()->forApp('customer')->forSlot(SplashSlotEnum::STAGED_NEXT())->exists())->toBeFalse();
});

it('promotes due staged splash screens through the scheduled command', function (): void {
    SplashScreen::create([
        'app_type' => 'customer',
        'slot' => SplashSlotEnum::STAGED_NEXT(),
        'version' => 'customer-due',
        'media_type' => 'image',
        'bg_type' => 'color',
        'bg_color' => '#444444',
        'min_display_seconds' => 2,
        'activate_at' => now()->subMinute(),
    ]);

    SplashScreen::create([
        'app_type' => 'seller',
        'slot' => SplashSlotEnum::STAGED_NEXT(),
        'version' => 'seller-future',
        'media_type' => 'image',
        'bg_type' => 'color',
        'bg_color' => '#555555',
        'min_display_seconds' => 2,
        'activate_at' => now()->addHour(),
    ]);

    $this->artisan('splash-screens:promote-due')
        ->expectsOutput('Promoted due splash screen for customer.')
        ->expectsOutput('Promoted 1 due splash screen(s).')
        ->assertSuccessful();

    expect(SplashScreen::query()->forApp('customer')->forSlot(SplashSlotEnum::CURRENT())->exists())->toBeTrue()
        ->and(SplashScreen::query()->forApp('seller')->forSlot(SplashSlotEnum::STAGED_NEXT())->exists())->toBeTrue();
});
