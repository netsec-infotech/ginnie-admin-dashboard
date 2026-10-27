<?php

use App\Enums\BrandStatusEnum;
use App\Enums\HomePageScopeEnum;
use App\Models\Brand;
use App\Services\Visibility\BrandVisibilityInspector;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function inspector(): BrandVisibilityInspector
{
    return app(BrandVisibilityInspector::class);
}

function makeBrand(array $overrides = []): Brand
{
    return Brand::create(array_merge([
        'title' => 'Test Brand ' . uniqid(),
        'status' => BrandStatusEnum::ACTIVE(),
        'scope_type' => HomePageScopeEnum::GLOBAL(),
    ], $overrides));
}

it('marks an inactive brand as hidden', function () {
    $brand = makeBrand(['status' => BrandStatusEnum::INACTIVE()]);

    $result = inspector()->inspect($brand);

    expect($result['status'])->toBe('hidden');
    expect(collect($result['checks'])->firstWhere('key', 'status')['state'])->toBe('fail');
});

it('marks a category-scoped brand without scope_id as hidden', function () {
    $brand = makeBrand([
        'scope_type' => HomePageScopeEnum::CATEGORY(),
        'scope_id' => null,
    ]);

    $result = inspector()->inspect($brand);

    expect($result['status'])->toBe('hidden');
    expect(collect($result['checks'])->firstWhere('key', 'scope')['state'])->toBe('fail');
});

it('reports no products when the brand has none', function () {
    $brand = makeBrand();

    $result = inspector()->inspect($brand);

    expect(collect($result['checks'])->firstWhere('key', 'has_products')['state'])->toBe('fail');
});
