<?php

use App\Enums\BrandStatusEnum;
use App\Enums\DefaultSystemRolesEnum;
use App\Enums\GuardNameEnum;
use App\Enums\HomePageScopeEnum;
use App\Models\Brand;
use App\Models\Category;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->withoutMiddleware();

    $user = User::factory()->create(['access_panel' => GuardNameEnum::ADMIN->value]);
    Role::findOrCreate(DefaultSystemRolesEnum::SUPER_ADMIN->value, GuardNameEnum::ADMIN->value);
    $user->assignRole(DefaultSystemRolesEnum::SUPER_ADMIN->value);
    $this->actingAs($user, GuardNameEnum::ADMIN->value);
});

it('streams a CSV with the export column order leading with id', function () {
    Brand::create([
        'title' => 'ExportA',
        'description' => 'A description',
        'status' => BrandStatusEnum::ACTIVE(),
        'scope_type' => HomePageScopeEnum::GLOBAL(),
        'metadata' => ['seo_title' => 'A SEO', 'seo_keywords' => ['a', 'b'], 'seo_description' => 'A desc'],
    ]);

    $resp = $this->get(route('admin.brands.bulk-upload.export'));

    $resp->assertOk();
    $resp->assertHeader('Content-Type', 'text/csv; charset=UTF-8');
    $body = $resp->streamedContent();
    expect($body)->not->toStartWith("\xEF\xBB\xBF");
    $lines = preg_split("/\r?\n/", trim($body));
    expect($lines[0])->toStartWith('id,title,');
    expect($lines)->toHaveCount(2);
    // The body row should contain the brand title
    expect($lines[1])->toContain('ExportA');
});

it('filters by status', function () {
    Brand::create(['title' => 'ActiveOne', 'description' => 'Active brand', 'status' => BrandStatusEnum::ACTIVE(), 'scope_type' => HomePageScopeEnum::GLOBAL()]);
    Brand::create(['title' => 'InactiveOne', 'description' => 'Inactive brand', 'status' => BrandStatusEnum::INACTIVE(), 'scope_type' => HomePageScopeEnum::GLOBAL()]);

    $resp = $this->get(route('admin.brands.bulk-upload.export', ['filters' => ['status' => 'active']]));

    $body = $resp->streamedContent();
    expect($body)->toContain('ActiveOne')->not->toContain('InactiveOne');
});

it('filters by scope_type=category and scope_id', function () {
    $cat = Category::create(['title' => 'Fashion', 'status' => 'active']);
    Brand::create(['title' => 'FashionBrand', 'description' => 'Fashion brand', 'status' => BrandStatusEnum::ACTIVE(), 'scope_type' => HomePageScopeEnum::CATEGORY(), 'scope_id' => $cat->id]);
    Brand::create(['title' => 'GlobalBrand', 'description' => 'Global brand', 'status' => BrandStatusEnum::ACTIVE(), 'scope_type' => HomePageScopeEnum::GLOBAL()]);

    $resp = $this->get(route('admin.brands.bulk-upload.export', [
        'filters' => ['scope_type' => 'category', 'scope_id' => $cat->id],
    ]));

    $body = $resp->streamedContent();
    expect($body)->toContain('FashionBrand')->not->toContain('GlobalBrand');
});

it('leaves logo_url empty in the export so re-import preserves the existing logo', function () {
    $brand = Brand::create(['title' => 'NoLogoCol', 'description' => 'No logo brand', 'status' => BrandStatusEnum::ACTIVE(), 'scope_type' => HomePageScopeEnum::GLOBAL()]);

    $resp = $this->get(route('admin.brands.bulk-upload.export'));

    $body = $resp->streamedContent();
    $rows = array_map('str_getcsv', preg_split("/\r?\n/", trim($body)));
    expect(array_slice($rows[0], 0, 4))->toBe(['id', 'title', 'description', 'uuid']);
    $logoColIndex = array_search('logo_url', $rows[0]);
    expect($rows[1][$logoColIndex])->toBe('');
});

it('flattens metadata keywords to a comma-separated string', function () {
    Brand::create([
        'title' => 'SeoBrand',
        'description' => 'SEO brand',
        'status' => BrandStatusEnum::ACTIVE(),
        'scope_type' => HomePageScopeEnum::GLOBAL(),
        'metadata' => ['seo_keywords' => ['x', 'y', 'z']],
    ]);

    $resp = $this->get(route('admin.brands.bulk-upload.export'));

    $body = $resp->streamedContent();
    expect($body)->toContain('"x,y,z"');
});

it('streams category exports without a UTF-8 BOM before id', function () {
    Category::create(['title' => 'ExportCategory', 'status' => 'active']);

    $resp = $this->get(route('admin.categories.bulk-upload.export'));

    $resp->assertOk();
    $body = $resp->streamedContent();
    expect($body)->not->toStartWith("\xEF\xBB\xBF");
    expect($body)->toStartWith('id,title,');
    expect($body)->toContain('ExportCategory');
});
