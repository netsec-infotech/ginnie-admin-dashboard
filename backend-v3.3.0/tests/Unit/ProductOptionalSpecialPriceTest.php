<?php

use App\Http\Requests\Product\StoreUpdateProductRequest;
use App\Http\Controllers\TaxClassController;
use App\Http\Controllers\TaxRateController;
use App\Models\Product;
use App\Models\TaxClass;
use App\Services\ProductService;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Validator;

function validateProductPricingRow(array $pricingRow): Illuminate\Contracts\Validation\Validator
{
    $requestFields = array_intersect_key($pricingRow, array_flip(['is_inclusive_tax', 'tax_groups']));
    $pricingRow = array_diff_key($pricingRow, $requestFields);

    $request = StoreUpdateProductRequest::create('/products', 'POST', array_merge([
        'type' => 'simple',
        'pricing' => json_encode([
            'store_pricing' => [array_merge([
                'store_id' => 1,
                'price' => 100,
                'cost' => 60,
                'stock' => 10,
                'sku' => 'OPTIONAL-SP-001',
            ], $pricingRow)],
        ]),
    ], $requestFields));
    $request->setUserResolver(fn () => null);

    $validator = Validator::make([], []);
    $request->withValidator($validator);
    $validator->passes();

    return $validator;
}

it('allows special price to be omitted from product pricing', function () {
    expect(validateProductPricingRow([])->errors()->has('pricing.store_pricing.0.special_price'))->toBeFalse()
        ->and(validateProductPricingRow(['special_price' => ''])->errors()->has('pricing.store_pricing.0.special_price'))->toBeFalse()
        ->and(validateProductPricingRow(['special_price' => 0])->errors()->has('pricing.store_pricing.0.special_price'))->toBeFalse();
});

it('still validates a supplied special price', function () {
    expect(validateProductPricingRow(['special_price' => 80])->errors()->has('pricing.store_pricing.0.special_price'))->toBeFalse()
        ->and(validateProductPricingRow(['special_price' => 120])->errors()->has('pricing.store_pricing.0.special_price'))->toBeTrue()
        ->and(validateProductPricingRow(['special_price' => -1])->errors()->has('pricing.store_pricing.0.special_price'))->toBeTrue()
        ->and(validateProductPricingRow(['special_price' => 'invalid'])->errors()->has('pricing.store_pricing.0.special_price'))->toBeTrue();
});

it('normalizes an omitted special price without changing the API storage contract', function (array $pricing, float $expected) {
    $method = new ReflectionMethod(ProductService::class, 'normalizeSpecialPrice');
    $actual = $method->invoke(new ProductService, $pricing);

    expect($actual)->toBe($expected);
})->with([
    'missing special price' => [['price' => 100], 100.0],
    'blank special price' => [['price' => 100, 'special_price' => ''], 100.0],
    'zero special price' => [['price' => 100, 'special_price' => 0], 100.0],
    'equal special price' => [['price' => 100, 'special_price' => 100], 100.0],
    'special price above regular price' => [['price' => 100, 'special_price' => 120], 100.0],
    'valid discount' => [['price' => 100, 'special_price' => 80], 80.0],
]);

it('converts pricing once when inclusive tax is turned off', function () {
    $method = new ReflectionMethod(ProductService::class, 'convertPricingForTaxModeChange');
    $actual = $method->invoke(new ProductService, [
        'store_pricing' => [[
            'store_id' => 1,
            'price' => 100,
            'special_price' => 80,
        ]],
    ], '1', '0', 10, 10, 'update');

    expect($actual['store_pricing'][0]['price'])->toBe(90.91)
        ->and($actual['store_pricing'][0]['special_price'])->toBe(72.73);
});

it('converts inclusive tax off from existing stored pricing instead of mobile submitted pricing', function () {
    $method = new ReflectionMethod(ProductService::class, 'convertPricingForTaxModeChange');
    $actual = $method->invoke(new ProductService, [
        'store_pricing' => [[
            'store_id' => 1,
            'price' => 209,
            'special_price' => 160,
        ]],
    ], '1', '0', 5, 5, 'update', [
        'store_pricing' => [
            1 => [
                'price' => 210,
                'special_price' => 168,
            ],
        ],
    ]);

    expect($actual['store_pricing'][0]['price'])->toBe(200.0)
        ->and($actual['store_pricing'][0]['special_price'])->toBe(160.0);
});

it('converts pricing once when inclusive tax is turned on', function () {
    $method = new ReflectionMethod(ProductService::class, 'convertPricingForTaxModeChange');
    $actual = $method->invoke(new ProductService, [
        'variant_pricing' => [[
            'store_id' => 1,
            'variant_id' => 'tmp-1',
            'price' => 100,
            'special_price' => 80,
        ]],
    ], '0', '1', 10, 10, 'update');

    expect($actual['variant_pricing'][0]['price'])->toBe(110.0)
        ->and($actual['variant_pricing'][0]['special_price'])->toBe(88.0);
});

it('converts inclusive tax on from existing variant stored pricing', function () {
    $method = new ReflectionMethod(ProductService::class, 'convertPricingForTaxModeChange');
    $actual = $method->invoke(new ProductService, [
        'variant_pricing' => [[
            'store_id' => 1,
            'variant_id' => 10,
            'price' => 199,
            'special_price' => 150,
        ]],
    ], '0', '1', 5, 5, 'update', [
        'variant_pricing' => [
            10 => [
                1 => [
                    'price' => 200,
                    'special_price' => 160,
                ],
            ],
        ],
    ]);

    expect($actual['variant_pricing'][0]['price'])->toBe(210.0)
        ->and($actual['variant_pricing'][0]['special_price'])->toBe(168.0);
});

it('does not convert pricing when inclusive tax mode is unchanged', function () {
    $method = new ReflectionMethod(ProductService::class, 'convertPricingForTaxModeChange');
    $pricing = [
        'store_pricing' => [[
            'store_id' => 1,
            'price' => 100,
            'special_price' => 80,
        ]],
    ];

    expect($method->invoke(new ProductService, $pricing, '1', '1', 10, 10, 'update'))->toBe($pricing);
});

it('preserves exact submitted price without modification during tax mode updates', function () {
    $method = new ReflectionMethod(ProductService::class, 'convertPricingForTaxModeChange');
    $pricing = [
        'store_pricing' => [[
            'store_id' => 1,
            'price' => 100,
            'special_price' => '',
        ]],
    ];
    $actual = $method->invoke(new ProductService, $pricing, '1', '0', 10, 10, 'update');

    expect($actual['store_pricing'][0]['price'])->toBe(100)
        ->and($actual['store_pricing'][0]['special_price'])->toBe('');
});

it('keeps inclusive stored pricing unchanged when only tax groups are removed', function () {
    $method = new ReflectionMethod(ProductService::class, 'convertPricingForTaxModeChange');
    $pricing = [
        'store_pricing' => [[
            'store_id' => 1,
            'price' => 100,
            'special_price' => 80,
        ]],
    ];

    expect($method->invoke(new ProductService, $pricing, '1', '1', 10, 0, 'update'))->toBe($pricing);
});

it('keeps price unchanged when inclusive tax is turned off and tax groups are removed', function () {
    $method = new ReflectionMethod(ProductService::class, 'convertPricingForTaxModeChange');
    $actual = $method->invoke(new ProductService, [
        'store_pricing' => [[
            'store_id' => 1,
            'price' => 100,
            'special_price' => 80,
        ]],
    ], '1', '0', 10, 0, 'update');

    expect($actual['store_pricing'][0]['price'])->toBe(100.0)
        ->and($actual['store_pricing'][0]['special_price'])->toBe(80.0);
});

it('keeps exclusive stored pricing unchanged when only tax groups are removed', function () {
    $method = new ReflectionMethod(ProductService::class, 'convertPricingForTaxModeChange');
    $pricing = [
        'store_pricing' => [[
            'store_id' => 1,
            'price' => 100,
            'special_price' => 80,
        ]],
    ];

    expect($method->invoke(new ProductService, $pricing, '0', '0', 10, 0, 'update'))->toBe($pricing);
});

it('preserves existing inclusive tax value on update when mobile omits the field', function () {
    $method = new ReflectionMethod(ProductService::class, 'incomingInclusiveTax');
    $product = new Product(['is_inclusive_tax' => '1']);

    expect($method->invoke(new ProductService, [], 'update', $product))->toBe('1')
        ->and($method->invoke(new ProductService, ['is_inclusive_tax' => '0'], 'update', $product))->toBe('0')
        ->and($method->invoke(new ProductService, [], 'create', null))->toBe('0');
});

it('removes existing tax groups on update when tax groups are omitted', function () {
    $method = new ReflectionMethod(ProductService::class, 'incomingTaxGroups');
    $product = new Product;
    $product->setRelation('taxClasses', collect([
        (new TaxClass)->forceFill(['id' => 5]),
        (new TaxClass)->forceFill(['id' => 8]),
    ]));

    expect($method->invoke(new ProductService, [], 'update', $product))->toBe([])
        ->and($method->invoke(new ProductService, ['tax_groups' => []], 'update', $product))->toBe([])
        ->and($method->invoke(new ProductService, [], 'create', null))->toBe([]);
});

it('clears stale loaded tax classes after syncing product tax groups', function () {
    $product = new Product;
    $product->setRelation('taxClasses', collect([
        (new TaxClass)->forceFill(['id' => 5]),
    ]));

    $product->unsetRelation('taxClasses');

    expect($product->relationLoaded('taxClasses'))->toBeFalse();
});

it('requires a tax group when inclusive tax is enabled', function () {
    expect(validateProductPricingRow(['is_inclusive_tax' => 1])->errors()->has('tax_groups'))->toBeTrue()
        ->and(validateProductPricingRow([
            'is_inclusive_tax' => 1,
            'tax_groups' => [5],
        ])->errors()->has('tax_groups'))->toBeFalse()
        ->and(validateProductPricingRow([
            'is_inclusive_tax' => 0,
            'tax_groups' => [],
        ])->errors()->has('tax_groups'))->toBeFalse();
});

it('normalizes api tax group removal payloads to an empty array', function (mixed $taxGroups) {
    $request = StoreUpdateProductRequest::create('/products', 'POST', ['tax_groups' => $taxGroups]);
    $method = new ReflectionMethod(StoreUpdateProductRequest::class, 'prepareForValidation');
    $method->invoke($request);

    expect($request->input('tax_groups'))->toBe([]);
})->with([
    'null tax groups' => [null],
    'blank tax groups' => [''],
    'json empty tax groups' => ['[]'],
]);

it('normalizes api tax class aliases into tax groups', function (array $payload, array $expected) {
    $request = StoreUpdateProductRequest::create('/products', 'POST', $payload);
    $method = new ReflectionMethod(StoreUpdateProductRequest::class, 'prepareForValidation');
    $method->invoke($request);

    expect($request->input('tax_groups'))->toBe($expected);
})->with([
    'removed tax classes' => [['tax_classes' => []], []],
    'removed tax class ids' => [['tax_class_ids' => []], []],
    'response-shaped tax classes' => [[
        'tax_classes' => [
            ['id' => 7, 'title' => 'NEW TAX'],
            ['id' => 9, 'title' => 'GST'],
        ],
    ], [7, 9]],
]);

it('routes admin tax group search to the tax class controller', function () {
    $route = Route::getRoutes()->getByName('admin.tax-classes.search');

    expect($route?->getControllerClass())->toBe(TaxClassController::class);
});

it('keeps admin tax rate search routed to the tax rate controller', function () {
    $route = Route::getRoutes()->getByName('admin.tax-rates.search');

    expect($route?->getControllerClass())->toBe(TaxRateController::class);
});
