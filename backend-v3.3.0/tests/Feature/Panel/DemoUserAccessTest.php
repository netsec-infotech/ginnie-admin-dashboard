<?php

use App\Enums\GuardNameEnum;
use App\Http\Middleware\RestrictDemoUserWrites;
use App\Http\Resources\Setting\PaymentSettingResource;
use App\Models\Setting;
use App\Models\User;
use App\Support\DemoMode;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Route;

uses(RefreshDatabase::class);

beforeEach(function () {
    Cache::flush();
    config()->set('demo.user_emails', ['demoadmin@gmail.com']);

    Route::get('/__tests/demo-write', fn () => response()->json(['allowed' => true]))
        ->middleware(RestrictDemoUserWrites::class);
    Route::post('/__tests/demo-write', function () {
        Setting::query()->where('variable', 'system')->update([
            'value' => json_encode(['demoMode' => false]),
        ]);

        return response()->json(['allowed' => true]);
    })->middleware(RestrictDemoUserWrites::class);
});

function enablePanelDemoMode(): void
{
    Setting::query()->updateOrCreate(
        ['variable' => 'system'],
        ['value' => json_encode(['demoMode' => true])],
    );
    Cache::flush();
}

it('identifies only the configured user while demo mode is enabled', function () {
    enablePanelDemoMode();

    $demoUser = User::factory()->create(['email' => 'DemoAdmin@Gmail.com']);
    $otherUser = User::factory()->create(['email' => 'admin@example.test']);

    expect(DemoMode::isDemoUser($demoUser))->toBeTrue()
        ->and(DemoMode::isDemoUser($otherUser))->toBeFalse();
});

it('does not identify the configured user when demo mode is disabled', function () {
    Setting::query()->create([
        'variable' => 'system',
        'value' => json_encode(['demoMode' => false]),
    ]);
    Cache::flush();

    $demoUser = User::factory()->create(['email' => 'demoadmin@gmail.com']);

    expect(DemoMode::isDemoUser($demoUser))->toBeFalse();
});

it('grants the demo user view authorization without stored permissions', function () {
    enablePanelDemoMode();

    $demoUser = User::factory()->create([
        'email' => 'demoadmin@gmail.com',
        'access_panel' => GuardNameEnum::ADMIN->value,
    ]);

    $this->actingAs($demoUser);

    expect($demoUser->hasPermissionTo('permission-that-is-not-stored'))->toBeTrue()
        ->and($demoUser->hasAnyPermission(['another-missing-permission']))->toBeTrue()
        ->and(Gate::allows('an-unregistered-demo-ability'))->toBeTrue();
});

it('allows safe requests and blocks writes before data changes', function () {
    enablePanelDemoMode();

    $demoUser = User::factory()->create(['email' => 'demoadmin@gmail.com']);
    $this->actingAs($demoUser);

    $this->getJson('/__tests/demo-write')->assertOk();
    $this->postJson('/__tests/demo-write')
        ->assertForbidden()
        ->assertJsonPath('success', false)
        ->assertJsonPath('message', __('labels.demo_action_restricted'));

    expect(Setting::query()->where('variable', 'system')->first()->value['demoMode'])->toBeTrue();
});

it('allows non-demo users to write while system demo mode remains enabled', function () {
    enablePanelDemoMode();

    $user = User::factory()->create(['email' => 'admin@example.test']);
    $this->actingAs($user);

    $this->postJson('/__tests/demo-write')->assertOk();

    expect(Setting::query()->where('variable', 'system')->first()->value['demoMode'])->toBeFalse();
});

it('protects both authenticated panel route groups', function () {
    expect(Route::getRoutes()->getByName('admin.dashboard')->gatherMiddleware())
        ->toContain(RestrictDemoUserWrites::class)
        ->and(Route::getRoutes()->getByName('seller.dashboard')->gatherMiddleware())
        ->toContain(RestrictDemoUserWrites::class);
});

it('masks payment secrets only for the demo user', function () {
    enablePanelDemoMode();

    $demoUser = User::factory()->create(['email' => 'demoadmin@gmail.com']);
    $admin = User::factory()->create(['email' => 'admin@example.test']);
    $setting = new Setting;
    $setting->variable = 'payment';
    $setting->setAttribute('value', json_encode([
        'stripeSecretKey' => 'stripe-secret-value',
        'stripeWebhookSecretKey' => 'stripe-webhook-value',
    ]));

    $demoRequest = Request::create('/admin/settings/payment');
    $demoRequest->setUserResolver(fn () => $demoUser);
    app()->instance('request', $demoRequest);
    $demoValues = (new PaymentSettingResource($setting))->toArray($demoRequest)['value'];

    $adminRequest = Request::create('/admin/settings/payment');
    $adminRequest->setUserResolver(fn () => $admin);
    app()->instance('request', $adminRequest);
    $adminValues = (new PaymentSettingResource($setting))->toArray($adminRequest)['value'];

    expect($demoValues['stripeSecretKey'])->toBe('****************')
        ->and($demoValues['stripeWebhookSecretKey'])->toBe('****************')
        ->and($adminValues['stripeSecretKey'])->toBe('stripe-secret-value')
        ->and($adminValues['stripeWebhookSecretKey'])->toBe('stripe-webhook-value');
});
