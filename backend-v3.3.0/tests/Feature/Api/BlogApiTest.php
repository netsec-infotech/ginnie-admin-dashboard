<?php

use App\Enums\Blog\BlogStatusEnum;
use App\Enums\WatchBuy\WatchBuyProfileTypeEnum;
use App\Http\Middleware\CheckInstallation;
use App\Http\Middleware\VerifyLicense;
use App\Models\Blog;
use App\Models\DeliveryZone;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Seller;
use App\Models\Store;
use App\Models\StoreProductVariant;
use App\Models\User;
use App\Models\WatchBuyProfile;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->withoutMiddleware([VerifyLicense::class, CheckInstallation::class]);

    $this->author = WatchBuyProfile::factory()->create([
        'profile_type' => WatchBuyProfileTypeEnum::PLATFORM,
        'username' => 'green_kitchen',
        'bio' => 'Fresh recipes from farm to table',
    ]);

    $this->seller = Seller::factory()->create();
    $this->store = Store::forceCreate(testStoreData($this->seller->id, [
        'name' => 'Organic Valley Store',
        'status' => 'online',
        'visibility_status' => 'visible',
    ]));

    $this->product = Product::forceCreate(testProductData($this->seller->id, [
        'title' => 'Organic Ripe Tomatoes',
        'status' => 'active',
        'verification_status' => 'approved',
    ]));
    $this->variant = ProductVariant::forceCreate(testVariantData($this->product->id, [
        'title' => '500g',
        'availability' => true,
    ]));
    $this->storeVariant = StoreProductVariant::forceCreate([
        'product_variant_id' => $this->variant->id,
        'store_id' => $this->store->id,
        'sku' => 'ORG-TOM-500',
        'price' => 40,
        'special_price' => 35,
        'cost' => 20,
        'stock' => 100,
    ]);
});

test('public blogs listing returns only published blogs', function () {
    // 2 Published blogs
    Blog::factory()->create([
        'title' => 'Published Blog 1',
        'status' => BlogStatusEnum::PUBLISHED,
        'published_at' => now()->subDay(),
    ]);
    Blog::factory()->create([
        'title' => 'Published Blog 2',
        'status' => BlogStatusEnum::PUBLISHED,
        'published_at' => now()->subHours(2),
    ]);

    // 1 Draft blog
    Blog::factory()->draft()->create(['title' => 'Draft Secret Post']);

    // 1 Future Scheduled blog
    Blog::factory()->create([
        'title' => 'Future Scheduled Post',
        'status' => BlogStatusEnum::PUBLISHED,
        'published_at' => now()->addDays(2),
    ]);

    $response = $this->getJson('/api/blogs')
        ->assertSuccessful()
        ->assertJsonPath('success', true)
        ->assertJsonPath('data.total', 2);

    $titles = collect($response->json('data.data'))->pluck('title')->all();
    expect($titles)->toContain('Published Blog 1', 'Published Blog 2')
        ->and($titles)->not->toContain('Draft Secret Post', 'Future Scheduled Post');
});

test('public blogs listing supports search filtering', function () {
    Blog::factory()->create([
        'title' => 'How to Make Delicious Guacamole',
        'status' => BlogStatusEnum::PUBLISHED,
        'published_at' => now()->subDay(),
    ]);
    Blog::factory()->create([
        'title' => 'Best Spices for Indian Curries',
        'status' => BlogStatusEnum::PUBLISHED,
        'published_at' => now()->subDay(),
    ]);

    $this->getJson('/api/blogs?search=Guacamole')
        ->assertSuccessful()
        ->assertJsonPath('data.total', 1)
        ->assertJsonPath('data.data.0.title', 'How to Make Delicious Guacamole');
});

test('public blog detail returns content, author, SEO metadata, and increments views', function () {
    $blog = Blog::factory()->create([
        'title' => 'Complete Smoothie Guide',
        'slug' => 'complete-smoothie-guide',
        'content' => '<h1>Smoothie 101</h1><p>Blend berries with almond milk.</p>',
        'author_profile_id' => $this->author->id,
        'product_ids' => [$this->product->id],
        'status' => BlogStatusEnum::PUBLISHED,
        'published_at' => now()->subDay(),
        'views_count' => 10,
        'metadata' => [
            'seo_title' => 'The Complete Smoothie Guide 2026',
            'seo_description' => 'Best smoothie recipes with organic groceries.',
            'seo_keywords' => ['smoothie', 'healthy'],
        ],
    ]);

    $this->getJson('/api/blogs/complete-smoothie-guide')
        ->assertSuccessful()
        ->assertJsonPath('success', true)
        ->assertJsonPath('data.slug', 'complete-smoothie-guide')
        ->assertJsonPath('data.author.username', 'green_kitchen')
        ->assertJsonPath('data.seo.title', 'The Complete Smoothie Guide 2026')
        ->assertJsonPath('data.linked_products.0.id', $this->product->id)
        ->assertJsonPath('data.linked_products.0.special_price', 35);

    expect($blog->fresh()->views_count)->toBe(11);
});

test('public blog detail returns 404 for drafts or non-existent blogs', function () {
    $draft = Blog::factory()->draft()->create(['slug' => 'draft-article']);

    $this->getJson('/api/blogs/draft-article')->assertNotFound();
    $this->getJson('/api/blogs/non-existent-slug')->assertNotFound();
});

test('public blogs listing returns editors_pick as an array on page 1 and empty on page 2 or during search', function () {
    // Create an editor's pick blog that has an older published_at (would be on page 2 if sorted)
    $olderEditorsPick = Blog::factory()->create([
        'title' => 'Ancient Wisdom Editor Pick',
        'status' => BlogStatusEnum::PUBLISHED,
        'is_editors_pick' => true,
        'published_at' => now()->subDays(10),
    ]);

    // Create 3 regular blogs published recently
    for ($i = 1; $i <= 3; $i++) {
        Blog::factory()->create([
            'title' => "Recent Blog {$i}",
            'status' => BlogStatusEnum::PUBLISHED,
            'is_editors_pick' => false,
            'published_at' => now()->subDays($i),
        ]);
    }

    // Page 1 with per_page=2: olderEditorsPick is NOT in the main 2 items of page 1, but MUST be in editors_pick section
    $page1Response = $this->getJson('/api/blogs?per_page=2&page=1')
        ->assertSuccessful()
        ->assertJsonStructure([
            'data' => [
                'current_page',
                'editors_pick',
                'data',
                'total',
                'last_page',
                'per_page',
            ],
        ]);

    $editorsPickItems = $page1Response->json('data.editors_pick');
    expect(is_array($editorsPickItems))->toBeTrue()
        ->and(array_is_list($editorsPickItems))->toBeTrue() // Verifies it's a JSON array, not an associative object!
        ->and(collect($editorsPickItems)->pluck('title'))->toContain('Ancient Wisdom Editor Pick');

    // Ensure the blog in editors_pick is NOT repeated in the main data feed
    $page1DataTitles = collect($page1Response->json('data.data'))->pluck('title');
    expect($page1DataTitles)->not->toContain('Ancient Wisdom Editor Pick');

    // Page 2: editors_pick should be empty array to save bandwidth
    $page2Response = $this->getJson('/api/blogs?per_page=2&page=2')
        ->assertSuccessful();

    expect($page2Response->json('data.editors_pick'))->toBeEmpty();

    // Ensure the blog in editors_pick is NOT repeated in page 2 data feed either
    $page2DataTitles = collect($page2Response->json('data.data'))->pluck('title');
    expect($page2DataTitles)->not->toContain('Ancient Wisdom Editor Pick');

    // Search query: editors_pick should be empty array
    $searchResponse = $this->getJson('/api/blogs?search=NonExistentTerm')
        ->assertSuccessful();

    expect($searchResponse->json('data.editors_pick'))->toBeEmpty();
});

