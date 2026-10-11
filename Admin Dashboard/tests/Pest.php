<?php

use App\Models\Category;
use App\Models\ProductCondition;
use App\Models\StoreAddonItem;
use App\Models\StoreProductVariantAddon;
use Illuminate\Support\Str;

StoreProductVariantAddon::creating(function (StoreProductVariantAddon $storeProductVariantAddon): void {
    $storeProductVariantAddon->uuid ??= (string) Str::uuid();
    $storeProductVariantAddon->metadata ??= [];
});

StoreAddonItem::creating(function (StoreAddonItem $storeAddonItem): void {
    $storeAddonItem->uuid ??= (string) Str::uuid();
    $storeAddonItem->metadata ??= [];
});

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
|
| The closure you provide to your test functions is always bound to a specific PHPUnit test
| case class. By default, that class is "PHPUnit\Framework\TestCase". Of course, you may
| need to change it using the "pest()" function to bind a different classes or traits.
|
*/

pest()->extend(Tests\TestCase::class)
 // ->use(Illuminate\Foundation\Testing\RefreshDatabase::class)
    ->in('Feature', 'Unit');

/*
|--------------------------------------------------------------------------
| Expectations
|--------------------------------------------------------------------------
|
| When you're writing tests, you often need to check that values meet certain conditions. The
| "expect()" function gives you access to a set of "expectations" methods that you can use
| to assert different things. Of course, you may extend the Expectation API at any time.
|
*/

expect()->extend('toBeOne', function () {
    return $this->toBe(1);
});

/*
|--------------------------------------------------------------------------
| Functions
|--------------------------------------------------------------------------
|
| While Pest is very powerful out-of-the-box, you may have some testing code specific to your
| project that you don't want to repeat in every file. Here you can also expose helpers as
| global functions to help you to reduce the number of lines of code in your test files.
|
*/

function something()
{
    // ..
}

function testStoreData(int $sellerId, array $overrides = []): array
{
    return array_merge([
        'seller_id' => $sellerId,
        'name' => 'Test Store '.Str::random(6),
        'address' => 'Test street',
        'city' => 'Test City',
        'landmark' => 'Test landmark',
        'state' => 'Test State',
        'zipcode' => '12345',
        'country' => 'India',
        'country_code' => 'IN',
        'latitude' => 12.97159888,
        'longitude' => 77.59456666,
        'contact_email' => 'store+'.Str::uuid().'@example.test',
        'contact_number' => '9999999999',
        'tax_name' => 'GST',
        'tax_number' => 'GST-12345',
        'bank_name' => 'Test Bank',
        'bank_branch_code' => 'TEST0001',
        'account_holder_name' => 'Test Seller',
        'account_number' => '1234567890',
        'routing_number' => '987654321',
        'bank_account_type' => 'checking',
        'currency_code' => 'INR',
        'about_us' => 'Test store about section.',
        'metadata' => [],
        'verification_status' => 'approved',
    ], $overrides);
}

function testProductData(int $sellerId, array $overrides = []): array
{
    $category = Category::query()->create([
        'uuid' => (string) Str::uuid(),
        'title' => 'Test Category '.Str::random(6),
        'image' => 'category.png',
        'description' => 'Test category description.',
        'requires_approval' => false,
        'metadata' => [],
    ]);

    $condition = ProductCondition::query()->create([
        'uuid' => (string) Str::uuid(),
        'category_id' => $category->id,
        'title' => 'Test Condition '.Str::random(6),
        'alignment' => 'strip',
    ]);

    return array_merge([
        'uuid' => (string) Str::uuid(),
        'seller_id' => $sellerId,
        'category_id' => $category->id,
        'product_condition_id' => $condition->id,
        'title' => 'Test Product '.Str::random(6),
        'type' => 'variant',
        'short_description' => 'Test short description',
        'description' => 'Test product description',
        'tags' => 'test',
        'base_prep_time' => 15,
        'metadata' => [],
        'total_allowed_quantity' => 0,
        'minimum_order_quantity' => 1,
        'quantity_step_size' => 1,
    ], $overrides);
}

function testVariantData(int $productId, array $overrides = []): array
{
    return array_merge([
        'uuid' => (string) Str::uuid(),
        'product_id' => $productId,
        'title' => 'Test Variant '.Str::random(6),
        'slug' => 'test-variant-'.Str::lower(Str::random(10)),
        'weight' => 1,
        'height' => 1,
        'breadth' => 1,
        'length' => 1,
        'availability' => true,
        'barcode' => 'BARCODE-'.Str::upper(Str::random(8)),
        'visibility' => 'published',
        'is_default' => true,
    ], $overrides);
}

function testStoreProductVariantAddonData(array $overrides = []): array
{
    return array_merge([
        'uuid' => (string) Str::uuid(),
        'metadata' => [],
    ], $overrides);
}

function testStoreAddonItemData(array $overrides = []): array
{
    return array_merge([
        'uuid' => (string) Str::uuid(),
        'metadata' => [],
    ], $overrides);
}
