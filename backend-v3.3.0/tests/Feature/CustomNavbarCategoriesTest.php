<?php

use App\Enums\DefaultSystemRolesEnum;
use App\Enums\GuardNameEnum;
use App\Http\Controllers\Admin\HomeBuilderController;
use App\Models\Category;
use App\Models\HomeNavbarItem;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

test('admin can create and list custom navbar items', function () {
    $this->withoutMiddleware();

    $admin = User::factory()->create([
        'access_panel' => GuardNameEnum::ADMIN->value,
    ]);
    Role::findOrCreate(DefaultSystemRolesEnum::SUPER_ADMIN->value, GuardNameEnum::ADMIN->value);
    $admin->assignRole(DefaultSystemRolesEnum::SUPER_ADMIN->value);

    $response = $this->actingAs($admin)->postJson(route('admin.home-navbar-items.store'), [
        'title' => 'Summer Festive',
        'show_on_app' => true,
        'show_on_web' => true,
    ]);

    $response->assertOk()
        ->assertJsonPath('data.title', 'Summer Festive')
        ->assertJsonPath('data.slug', 'summer-festive');

    expect(HomeNavbarItem::where('slug', 'summer-festive')->exists())->toBeTrue();
});

test('public api returns active custom home navbar items', function () {
    $this->withoutMiddleware();

    HomeNavbarItem::create([
        'title' => 'Monsoon Deals',
        'slug' => 'monsoon-deals',
        'sort_order' => 1,
        'app_sort_order' => 1,
        'web_sort_order' => 1,
        'is_active' => true,
        'show_on_app' => true,
        'show_on_web' => true,
    ]);

    $response = $this->getJson(route('home-navbar-items.index', ['platform' => 'app']));

    $response->assertOk()
        ->assertJsonPath('success', true);

    $items = collect($response->json('data'));
    expect($items->contains('title', 'Monsoon Deals'))->toBeTrue();
});

test('unified category api returns custom navbar categories when home is true', function () {
    $this->withoutMiddleware();

    // 1. Create a custom category with 0 catalog products
    HomeNavbarItem::create([
        'title' => 'Trending Deals',
        'slug' => 'trending-deals',
        'sort_order' => 1,
        'app_sort_order' => 1,
        'web_sort_order' => 1,
        'is_active' => true,
        'show_on_app' => true,
        'show_on_web' => true,
    ]);

    // 2. Call GET /api/categories?home=true
    $response = $this->getJson('/api/categories?home=true');

    $response->assertOk()
        ->assertJsonPath('success', true);

    $items = collect($response->json('data.data'));
    expect($items->contains('title', 'Trending Deals'))->toBeTrue();
});

test('admin can update order of custom navbar items', function () {
    $this->withoutMiddleware();

    $admin = User::factory()->create([
        'access_panel' => GuardNameEnum::ADMIN->value,
    ]);
    Role::findOrCreate(DefaultSystemRolesEnum::SUPER_ADMIN->value, GuardNameEnum::ADMIN->value);
    $admin->assignRole(DefaultSystemRolesEnum::SUPER_ADMIN->value);

    $item1 = HomeNavbarItem::create([
        'title' => 'Tab One',
        'slug' => 'tab-one',
        'sort_order' => 1,
        'app_sort_order' => 1,
        'web_sort_order' => 1,
        'is_active' => true,
        'show_on_app' => true,
        'show_on_web' => true,
    ]);

    $item2 = HomeNavbarItem::create([
        'title' => 'Tab Two',
        'slug' => 'tab-two',
        'sort_order' => 2,
        'app_sort_order' => 2,
        'web_sort_order' => 2,
        'is_active' => true,
        'show_on_app' => true,
        'show_on_web' => true,
    ]);

    $response = $this->actingAs($admin)->postJson(route('admin.home-navbar-items.order'), [
        'platform' => 'app',
        'item_ids' => [$item2->id, $item1->id],
    ]);

    $response->assertOk();

    expect($item2->fresh()->app_sort_order)->toBe(1);
    expect($item1->fresh()->app_sort_order)->toBe(2);
});

test('admin can update appearance and delete custom navbar item', function () {
    $this->withoutMiddleware();

    $admin = User::factory()->create([
        'access_panel' => GuardNameEnum::ADMIN->value,
    ]);
    Role::findOrCreate(DefaultSystemRolesEnum::SUPER_ADMIN->value, GuardNameEnum::ADMIN->value);
    $admin->assignRole(DefaultSystemRolesEnum::SUPER_ADMIN->value);

    $item = HomeNavbarItem::create([
        'title' => 'Festive Offers',
        'slug' => 'festive-offers',
        'sort_order' => 1,
        'app_sort_order' => 1,
        'web_sort_order' => 1,
        'is_active' => true,
        'show_on_app' => true,
        'show_on_web' => true,
        'home_appearance' => [],
    ]);

    // Update appearance via category appearance route
    $response = $this->actingAs($admin)->postJson("/admin/categories/{$item->id}/appearance", [
        'background_type' => 'color',
        'background_color_web' => '#ff5500',
        'background_color_app' => '#0055ff',
        'font_color' => '#ffffff',
    ]);

    $response->assertOk()
        ->assertJsonPath('data.background_type', 'color')
        ->assertJsonPath('data.background_color_web', '#ff5500');

    $apiResponse = $this->getJson('/api/categories?home=true');
    $apiResponse->assertOk();

    $apiItem = collect($apiResponse->json('data.data'))
        ->firstWhere('slug', 'festive-offers');

    expect($apiItem['background_type'])->toBe('color')
        ->and($apiItem['background_color_web'])->toBe('#ff5500')
        ->and($apiItem['background_color_app'])->toBe('#0055ff');

    // Delete custom item
    $deleteResponse = $this->actingAs($admin)->deleteJson("/admin/home-navbar-items/{$item->id}");
    $deleteResponse->assertOk();

    expect(HomeNavbarItem::find($item->id))->toBeNull();
});

test('admin can view homepage builder index without undefined variables', function () {
    $this->withoutMiddleware();

    $admin = User::factory()->create([
        'access_panel' => GuardNameEnum::ADMIN->value,
    ]);
    Role::findOrCreate(DefaultSystemRolesEnum::SUPER_ADMIN->value, GuardNameEnum::ADMIN->value);
    $admin->assignRole(DefaultSystemRolesEnum::SUPER_ADMIN->value);

    $this->actingAs($admin);

    $view = app(HomeBuilderController::class)->index();

    expect($view->getData())->toHaveKeys([
        'scopeTypes',
        'appOptions',
        'webOptions',
        'appTemplates',
        'webTemplates',
        'appThemes',
        'webThemes',
        'categories',
        'parentCategories',
        'customNavbarItems',
    ]);
});

test('home layout api returns category scope with empty sections for custom category without layout', function () {
    $this->withoutMiddleware();

    $custom = HomeNavbarItem::create([
        'title' => 'Festive Deals',
        'slug' => 'festive-deals',
        'is_active' => true,
        'show_on_app' => true,
        'show_on_web' => true,
    ]);

    $response = $this->getJson('/api/home-layout?category_slug=festive-deals');

    $response->assertSuccessful();
    $response->assertJson([
        'success' => true,
        'data' => [
            'scope_type' => 'category',
            'scope_id' => $custom->id,
            'sections' => [],
        ],
    ]);
});
