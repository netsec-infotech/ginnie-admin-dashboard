<?php

use App\Enums\DefaultSystemRolesEnum;
use App\Enums\GuardNameEnum;
use App\Models\Category;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

test('unauthenticated user cannot access global search', function () {
    $response = $this->get(route('admin.global-search', ['q' => 'test']));
    $response->assertRedirect();
});

test('admin can perform global search across menu and entities', function () {
    $this->withoutMiddleware();

    $admin = User::factory()->create([
        'access_panel' => GuardNameEnum::ADMIN->value,
    ]);

    Role::findOrCreate(DefaultSystemRolesEnum::SUPER_ADMIN->value, GuardNameEnum::ADMIN->value);
    $admin->assignRole(DefaultSystemRolesEnum::SUPER_ADMIN->value);

    $response = $this->actingAs($admin)
        ->getJson(route('admin.global-search', ['q' => 'Order']));

    $response->assertOk()
        ->assertJsonStructure([
            'status',
            'data' => [
                'query',
                'total',
                'results',
            ],
        ]);
});

test('category search provider returns matching categories', function () {
    $this->withoutMiddleware();

    $admin = User::factory()->create([
        'access_panel' => GuardNameEnum::ADMIN->value,
    ]);

    Category::create([
        'title' => 'Electronics Gadgets',
        'slug' => 'electronics-gadgets',
        'description' => 'Electronic items category',
        'status' => 'active',
    ]);

    $response = $this->actingAs($admin)
        ->getJson(route('admin.global-search', ['q' => 'Electronics']));

    $response->assertOk()
        ->assertJsonFragment(['title' => 'Electronics Gadgets']);
});

test('seller global search returns formatted json response', function () {
    $this->withoutMiddleware();

    $sellerUser = User::factory()->create([
        'access_panel' => GuardNameEnum::SELLER->value,
    ]);

    Role::findOrCreate(DefaultSystemRolesEnum::SELLER->value, GuardNameEnum::SELLER->value);
    $sellerUser->assignRole(DefaultSystemRolesEnum::SELLER->value);

    $response = $this->actingAs($sellerUser)
        ->getJson(route('seller.global-search', ['q' => 'Product']));

    $response->assertOk()
        ->assertJsonStructure([
            'status',
            'data' => [
                'query',
                'total',
                'results',
            ],
        ]);
});
