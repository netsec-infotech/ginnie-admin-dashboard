<?php

use App\Enums\DefaultSystemRolesEnum;
use App\Enums\GuardNameEnum;
use App\Models\Brand;
use App\Models\Category;
use App\Models\Product;
use App\Models\Seller;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->withoutMiddleware();
});

function catalogExportAdmin(): User
{
    $admin = User::factory()->create(['access_panel' => GuardNameEnum::ADMIN->value]);
    Role::findOrCreate(DefaultSystemRolesEnum::SUPER_ADMIN->value, GuardNameEnum::ADMIN->value);
    $admin->assignRole(DefaultSystemRolesEnum::SUPER_ADMIN->value);

    return $admin;
}

function catalogExportSeller(): Seller
{
    $seller = Seller::factory()->create();
    $seller->user->update(['access_panel' => GuardNameEnum::SELLER->value]);
    Role::findOrCreate(DefaultSystemRolesEnum::SELLER->value, GuardNameEnum::SELLER->value);
    $seller->user->assignRole(DefaultSystemRolesEnum::SELLER->value);

    return $seller;
}

function catalogExportProduct(int $sellerId, array $overrides = []): Product
{
    return Product::forceCreate(testProductData($sellerId, array_merge([
        'title' => 'Catalog Export Product '.Str::random(8),
    ], $overrides)));
}

it('exports products for admin', function () {
    $admin = catalogExportAdmin();
    $seller = Seller::factory()->create();
    $first = catalogExportProduct($seller->id, ['title' => 'Admin Export One']);
    $second = catalogExportProduct($seller->id, ['title' => 'Admin Export Two']);

    $response = $this->actingAs($admin)->get(route('admin.products.export'));

    $response->assertOk();
    $body = $response->streamedContent();
    expect($body)
        ->toContain('handle,title,category_id')
        ->toContain($first->title)
        ->toContain($second->title);
});

it('exports only selected admin products', function () {
    $admin = catalogExportAdmin();
    $seller = Seller::factory()->create();
    $selected = catalogExportProduct($seller->id, ['title' => 'Selected Product Export']);
    $other = catalogExportProduct($seller->id, ['title' => 'Unselected Product Export']);

    $response = $this->actingAs($admin)->get(route('admin.products.export', [
        'ids' => [$selected->id],
        'columns' => ['id', 'product_details'],
    ]));

    $body = $response->streamedContent();
    expect($body)
        ->toStartWith('ID,"Product Details"')
        ->toContain($selected->title)
        ->not->toContain($other->title);
});

it('seller product export is seller scoped', function () {
    $seller = catalogExportSeller();
    $otherSeller = Seller::factory()->create();
    $ownProduct = catalogExportProduct($seller->id, ['title' => 'Own Seller Export']);
    $otherProduct = catalogExportProduct($otherSeller->id, ['title' => 'Other Seller Export']);

    $response = $this->actingAs($seller->user)->get(route('seller.products.export', [
        'ids' => [$ownProduct->id, $otherProduct->id],
    ]));

    $body = $response->streamedContent();
    expect($body)
        ->toContain($ownProduct->title)
        ->not->toContain($otherProduct->title);
});

it('exports only selected categories', function () {
    $admin = catalogExportAdmin();
    $selected = Category::query()->create(['title' => 'Selected Category Export', 'status' => 'active']);
    $other = Category::query()->create(['title' => 'Unselected Category Export', 'status' => 'active']);

    $response = $this->actingAs($admin)->get(route('admin.categories.bulk-upload.export', [
        'ids' => [$selected->id],
        'columns' => ['id', 'title'],
    ]));

    $body = $response->streamedContent();
    expect($body)
        ->toStartWith('ID,Title')
        ->toContain($selected->title)
        ->not->toContain($other->title);
});

it('exports only selected brands', function () {
    $admin = catalogExportAdmin();
    $selected = Brand::query()->create([
        'title' => 'Selected Brand Export',
        'description' => 'Selected brand',
        'status' => 'active',
        'scope_type' => 'global',
    ]);
    $other = Brand::query()->create([
        'title' => 'Unselected Brand Export',
        'description' => 'Unselected brand',
        'status' => 'active',
        'scope_type' => 'global',
    ]);

    $response = $this->actingAs($admin)->get(route('admin.brands.bulk-upload.export', [
        'ids' => [$selected->id],
        'columns' => ['id', 'title'],
    ]));

    $body = $response->streamedContent();
    expect($body)
        ->toStartWith('ID,Title')
        ->toContain($selected->title)
        ->not->toContain($other->title);
});

it('rejects unauthorized product export users', function () {
    $user = User::factory()->create(['access_panel' => GuardNameEnum::ADMIN->value]);

    $this->actingAs($user)->get(route('admin.products.export'))->assertForbidden();
});

it('puts selected products first in datatable responses', function () {
    $admin = catalogExportAdmin();
    $seller = Seller::factory()->create();
    $selected = catalogExportProduct($seller->id, ['title' => 'Hidden Selected Product']);
    $matched = catalogExportProduct($seller->id, ['title' => 'Visible Search Product']);

    $response = $this->actingAs($admin)->getJson(route('admin.products.datatable', [
        'draw' => 1,
        'start' => 0,
        'length' => 10,
        'search' => ['value' => 'Visible'],
        'order' => [['column' => 2, 'dir' => 'asc']],
        'selected_ids' => [$selected->id],
    ]));

    $response->assertOk();
    expect($response->json('data.0.id'))->toBe($selected->id)
        ->and(collect($response->json('data'))->pluck('id')->all())->toContain($matched->id);
});
