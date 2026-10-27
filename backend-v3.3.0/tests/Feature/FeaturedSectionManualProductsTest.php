<?php

use App\Models\Category;
use App\Models\FeaturedSection;
use App\Models\Product;
use App\Models\ProductCondition;
use App\Models\Seller;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

it('returns only manually selected products in the saved pivot order', function () {
    $seller = Seller::factory()->create();
    $category = Category::create([
        'title' => 'Featured Category',
        'description' => 'Category for featured products',
        'requires_approval' => false,
        'metadata' => [],
        'status' => 'active',
    ]);

    $condition = ProductCondition::create([
        'uuid' => (string) Str::uuid(),
        'category_id' => $category->id,
        'title' => 'New',
        'alignment' => 'strip',
    ]);

    $firstProduct = makeFeaturedProduct($seller->id, $category->id, $condition->id, 'First Product');
    $secondProduct = makeFeaturedProduct($seller->id, $category->id, $condition->id, 'Second Product');
    $unselectedProduct = makeFeaturedProduct($seller->id, $category->id, $condition->id, 'Unselected Product');

    $section = FeaturedSection::create([
        'title' => 'Manual Picks',
        'style' => 'grid',
        'section_type' => 'featured',
        'status' => 'active',
        'scope_type' => 'global',
    ]);

    $section->products()->sync([
        $firstProduct->id => ['sort_order' => 2],
        $secondProduct->id => ['sort_order' => 1],
    ]);

    $results = $section->getProductsQuery()->pluck('products.title')->all();

    expect($results)->toBe([
        'Second Product',
        'First Product',
    ])->and($results)->not->toContain($unselectedProduct->title);
});

it('returns only manually selected products from the scoped category for custom product sections', function () {
    $seller = Seller::factory()->create();
    $scopedCategory = Category::create([
        'title' => 'Scoped Category',
        'description' => 'Scoped category for featured products',
        'requires_approval' => false,
        'metadata' => [],
        'status' => 'active',
    ]);
    $childCategory = Category::create([
        'parent_id' => $scopedCategory->id,
        'title' => 'Scoped Child Category',
        'description' => 'Scoped child category for featured products',
        'requires_approval' => false,
        'metadata' => [],
        'status' => 'active',
    ]);
    $otherCategory = Category::create([
        'title' => 'Other Category',
        'description' => 'Other category for featured products',
        'requires_approval' => false,
        'metadata' => [],
        'status' => 'active',
    ]);

    $scopedCondition = ProductCondition::create([
        'uuid' => (string) Str::uuid(),
        'category_id' => $scopedCategory->id,
        'title' => 'Scoped Condition',
        'alignment' => 'strip',
    ]);
    $childCondition = ProductCondition::create([
        'uuid' => (string) Str::uuid(),
        'category_id' => $childCategory->id,
        'title' => 'Child Condition',
        'alignment' => 'strip',
    ]);
    $otherCondition = ProductCondition::create([
        'uuid' => (string) Str::uuid(),
        'category_id' => $otherCategory->id,
        'title' => 'Other Condition',
        'alignment' => 'strip',
    ]);

    $matchingProduct = makeFeaturedProduct($seller->id, $scopedCategory->id, $scopedCondition->id, 'Matching Product');
    $childProduct = makeFeaturedProduct($seller->id, $childCategory->id, $childCondition->id, 'Child Product');
    $outsideScopedCategory = makeFeaturedProduct($seller->id, $otherCategory->id, $otherCondition->id, 'Outside Product');

    $section = FeaturedSection::create([
        'title' => 'Scoped Manual Picks',
        'style' => 'grid',
        'section_type' => 'custom_products',
        'status' => 'active',
        'scope_type' => 'category',
        'scope_id' => $scopedCategory->id,
    ]);

    $section->products()->sync([
        $matchingProduct->id => ['sort_order' => 1],
        $childProduct->id => ['sort_order' => 2],
        $outsideScopedCategory->id => ['sort_order' => 3],
    ]);

    $results = $section->getProductsQuery()->pluck('products.title')->all();

    expect($results)->toBe(['Matching Product', 'Child Product'])
        ->and($results)->not->toContain($outsideScopedCategory->title);
});

function makeFeaturedProduct(int $sellerId, int $categoryId, int $conditionId, string $title): Product
{
    return Product::create([
        'seller_id' => $sellerId,
        'category_id' => $categoryId,
        'product_condition_id' => $conditionId,
        'title' => $title,
        'type' => 'simple',
        'short_description' => 'Short description',
        'description' => 'Long description',
        'tags' => 'featured',
        'metadata' => [],
        'status' => 'active',
        'verification_status' => 'approved',
        'featured' => '0',
        'minimum_order_quantity' => 1,
        'quantity_step_size' => 1,
        'total_allowed_quantity' => 1,
        'is_returnable' => '0',
        'is_cancelable' => '0',
        'is_attachment_required' => '0',
    ]);
}
