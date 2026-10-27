<?php

use App\Enums\Product\ProductStatusEnum;
use App\Enums\Product\ProductVarificationStatusEnum;
use App\Models\Product;
use App\Services\Visibility\ProductVisibilityInspector;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function productInspector(): ProductVisibilityInspector
{
    return app(ProductVisibilityInspector::class);
}

function makeVisibilityInspectorProduct(array $overrides = []): Product
{
    return Product::create(array_merge([
        'title' => 'Test Product ' . uniqid(),
        'status' => ProductStatusEnum::ACTIVE(),
        'verification_status' => ProductVarificationStatusEnum::APPROVED(),
    ], $overrides));
}

it('marks an inactive product as hidden', function () {
    $product = makeVisibilityInspectorProduct(['status' => ProductStatusEnum::INACTIVE()]);

    $result = productInspector()->inspect($product);

    expect($result['status'])->toBe('hidden');
    expect(collect($result['checks'])->firstWhere('key', 'status')['state'])->toBe('fail');
});

it('marks an unapproved product as hidden', function () {
    $product = makeVisibilityInspectorProduct(['verification_status' => ProductVarificationStatusEnum::PENDING()]);

    $result = productInspector()->inspect($product);

    expect(collect($result['checks'])->firstWhere('key', 'verification')['state'])->toBe('fail');
});

it('reports no variants when the product has none', function () {
    $product = makeVisibilityInspectorProduct();

    $result = productInspector()->inspect($product);

    expect(collect($result['checks'])->firstWhere('key', 'variants')['state'])->toBe('fail');
});
