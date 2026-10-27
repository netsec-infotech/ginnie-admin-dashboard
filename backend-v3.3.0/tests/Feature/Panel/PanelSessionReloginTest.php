<?php

use App\Enums\DefaultSystemRolesEnum;
use App\Enums\GuardNameEnum;
use App\Models\Seller;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

function panelAdminUser(): User
{
    $user = User::factory()->create([
        'access_panel' => GuardNameEnum::ADMIN->value,
    ]);

    Role::findOrCreate(DefaultSystemRolesEnum::SUPER_ADMIN->value, GuardNameEnum::ADMIN->value);
    $user->assignRole(DefaultSystemRolesEnum::SUPER_ADMIN->value);

    return $user;
}

function panelSellerUser(): User
{
    $user = User::factory()->create([
        'access_panel' => GuardNameEnum::SELLER->value,
    ]);

    Seller::factory()->create([
        'user_id' => $user->id,
        'verification_status' => 'approved',
    ]);

    Role::findOrCreate(DefaultSystemRolesEnum::SELLER->value, GuardNameEnum::SELLER->value);
    $user->assignRole(DefaultSystemRolesEnum::SELLER->value);

    return $user;
}

it('returns admins to the intended protected page after login', function () {
    $user = panelAdminUser();

    $this->get(route('admin.auth.check'))
        ->assertRedirect(route('admin.login'));

    $this->postJson(route('admin.login.post'), [
        'email' => $user->email,
        'password' => 'password',
    ])->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonPath('redirect_url', route('admin.auth.check'))
        ->assertJsonStructure(['csrf_token']);
});

it('returns sellers to the intended protected page after login', function () {
    $user = panelSellerUser();

    $this->get(route('seller.auth.check'))
        ->assertRedirect(route('seller.login'));

    $this->postJson(route('seller.login.post'), [
        'email' => $user->email,
        'password' => 'password',
    ])->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonPath('redirect_url', route('seller.auth.check'))
        ->assertJsonStructure(['csrf_token']);
});

it('falls back to panel dashboards when no intended page exists', function (string $panel, Closure $userFactory) {
    $user = $userFactory();

    $this->postJson(route($panel.'.login.post'), [
        'email' => $user->email,
        'password' => 'password',
    ])->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonPath('redirect_url', route($panel.'.dashboard'))
        ->assertJsonStructure(['csrf_token']);
})->with([
    'admin' => ['admin', fn () => panelAdminUser()],
    'seller' => ['seller', fn () => panelSellerUser()],
]);

it('reports authenticated panel auth checks', function (string $panel, string $guard, Closure $userFactory) {
    $user = $userFactory();

    $this->actingAs($user, $guard)
        ->getJson(route($panel.'.auth.check'))
        ->assertOk()
        ->assertJsonPath('authenticated', true);
})->with([
    'admin' => ['admin', GuardNameEnum::ADMIN->value, fn () => panelAdminUser()],
    'seller' => ['seller', GuardNameEnum::SELLER->value, fn () => panelSellerUser()],
]);

it('returns json unauthenticated responses for expired panel ajax requests', function (string $panel) {
    $this->getJson(route($panel.'.auth.check'))
        ->assertUnauthorized();
})->with([
    'admin' => ['admin'],
    'seller' => ['seller'],
]);

it('provides fresh relogin metadata without requiring authentication', function (string $panel) {
    $this->getJson(route($panel.'.auth.relogin'))
        ->assertOk()
        ->assertJsonPath('authenticated', false)
        ->assertJsonPath('login_url', route($panel.'.login.post'))
        ->assertJsonStructure(['csrf_token']);
})->with([
    'admin' => ['admin'],
    'seller' => ['seller'],
]);

it('renders the shared panel login form inside the relogin modal', function () {
    $html = view('components.panel-relogin-modal', [
        'panel' => 'admin',
    ])->render();

    expect($html)
        ->toContain('id="panel-relogin-form"')
        ->toContain('name="fcm_token"')
        ->toContain('name="device_type"')
        ->toContain('name="email"')
        ->toContain('name="password"')
        ->toContain('data-password-toggle="#panel-relogin-password"');
});

it('renders the shared panel login form inside the relogin modal even when attributes variable is a string in view data', function () {
    $html = view('components.panel-relogin-modal', [
        'panel' => 'admin',
        'attributes' => '{"some":"json"}',
    ])->render();

    expect($html)
        ->toContain('id="panel-relogin-form"')
        ->toContain('name="email"')
        ->toContain('name="password"');
});

it('resolves seller panel routes from the request path for the shared layout relogin modal', function () {
    $user = panelSellerUser();

    Route::get('/seller/test-panel-layout', function () {
        return view('layouts.main', [
            'user' => auth()->user(),
            'systemSettings' => [],
        ]);
    })->name('seller.test-panel-layout');

    $this->actingAs($user, GuardNameEnum::SELLER->value)
        ->get(route('seller.test-panel-layout'))
        ->assertOk()
        ->assertSee('data-panel="seller"', false)
        ->assertSee('data-login-url="'.route('seller.login.post').'"', false)
        ->assertSee('action="'.route('seller.login.post').'"', false);
});
