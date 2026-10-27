<?php

use App\Http\Requests\Product\StoreUpdateProductRequest;
use App\Models\Product;
use Illuminate\Support\Facades\Validator;

it('normalizes seller app multipart form data custom fields properly', function () {
    $input = [
        '0' => '{"key":"ROHAN","value":"DHRUV"}',
        '1' => '{"key":"weight","value":"2KG"}',
    ];

    $normalized = Product::normalizeCustomFields($input);

    expect($normalized)->toBe([
        'ROHAN' => 'DHRUV',
        'weight' => '2KG',
    ]);
});

it('normalizes seller app array of key-value objects', function () {
    $input = [
        ['key' => 'Color', 'value' => 'Blue'],
        ['title' => 'Size', 'value' => 'XL'],
        ['name' => 'Brand Custom', 'value' => 'Nike'],
    ];

    $normalized = Product::normalizeCustomFields($input);

    expect($normalized)->toBe([
        'Color' => 'Blue',
        'Size' => 'XL',
        'Brand Custom' => 'Nike',
    ]);
});

it('normalizes standard associative array custom fields from web panel', function () {
    $input = [
        'Material' => 'Cotton',
        'Fit' => 'Slim',
    ];

    $normalized = Product::normalizeCustomFields($input);

    expect($normalized)->toBe([
        'Material' => 'Cotton',
        'Fit' => 'Slim',
    ]);
});

it('ignores temporary web placeholders with blank or empty values', function () {
    $input = [
        '__custom_1720000000_1234__' => '',
        'Material' => 'Cotton',
    ];

    $normalized = Product::normalizeCustomFields($input);

    expect($normalized)->toBe([
        'Material' => 'Cotton',
    ]);
});

it('normalizes JSON string representation of custom fields', function () {
    $jsonArray = '[{"key":"ROHAN","value":"DHRUV"},{"key":"weight","value":"2KG"}]';
    $normalized1 = Product::normalizeCustomFields($jsonArray);

    $jsonObject = '{"ROHAN":"DHRUV","weight":"2KG"}';
    $normalized2 = Product::normalizeCustomFields($jsonObject);

    expect($normalized1)->toBe([
        'ROHAN' => 'DHRUV',
        'weight' => '2KG',
    ]);

    expect($normalized2)->toBe([
        'ROHAN' => 'DHRUV',
        'weight' => '2KG',
    ]);
});

it('returns null for empty or invalid custom fields', function () {
    expect(Product::normalizeCustomFields(null))->toBeNull();
    expect(Product::normalizeCustomFields([]))->toBeNull();
    expect(Product::normalizeCustomFields(''))->toBeNull();
    expect(Product::normalizeCustomFields('invalid-json'))->toBeNull();
});

it('model getter normalizes legacy malformed custom_fields data on the fly without DB migration', function () {
    $product = new Product();
    // Simulate raw legacy string from DB
    $legacyDbJson = json_encode([
        '0' => '{"key":"ROHAN","value":"DHRUV"}',
        '1' => '{"key":"weight","value":"2KG"}',
    ]);
    $product->setRawAttributes(['custom_fields' => $legacyDbJson]);

    expect($product->custom_fields)->toBe([
        'ROHAN' => 'DHRUV',
        'weight' => '2KG',
    ]);
});

it('model setter properly normalizes and JSON encodes custom_fields attribute for DB storage', function () {
    $product = new Product();
    $product->custom_fields = [
        '0' => '{"key":"ROHAN","value":"DHRUV"}',
        '1' => '{"key":"weight","value":"2KG"}',
    ];

    expect($product->getAttributes()['custom_fields'])->toBe(json_encode([
        'ROHAN' => 'DHRUV',
        'weight' => '2KG',
    ]));

    expect($product->custom_fields)->toBe([
        'ROHAN' => 'DHRUV',
        'weight' => '2KG',
    ]);
});
