<?php

use App\Enums\Blog\BlogStatusEnum;
use App\Enums\DefaultSystemRolesEnum;
use App\Enums\GuardNameEnum;
use App\Models\Blog;
use App\Models\BlogCategory;
use App\Models\User;
use App\Models\WatchBuyProfile;
use App\Http\Middleware\CheckInstallation;
use App\Http\Middleware\EnsureSubscriptionFeatureSelected;
use App\Http\Middleware\EnsureSystemVendorTypeSelected;
use App\Http\Middleware\VerifyLicense;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->withoutMiddleware([
        VerifyLicense::class,
        CheckInstallation::class,
        EnsureSystemVendorTypeSelected::class,
        EnsureSubscriptionFeatureSelected::class,
    ]);
    Gate::before(fn () => true);
});

test('admin can create, update, toggle status and delete blog categories', function () {
    $admin = User::factory()->create([
        'access_panel' => GuardNameEnum::ADMIN->value,
    ]);
    Role::findOrCreate(DefaultSystemRolesEnum::SUPER_ADMIN->value, GuardNameEnum::ADMIN->value);
    $admin->assignRole(DefaultSystemRolesEnum::SUPER_ADMIN->value);

    // 1. Create Blog Category
    $createResponse = $this->actingAs($admin)->postJson(route('admin.blog-categories.store'), [
        'name' => 'Healthy Recipes',
        'description' => 'Nutritious meal ideas',
        'sort_order' => 1,
        'status' => true,
        'metadata' => [
            'seo_title' => 'Healthy Recipes SEO',
            'seo_keywords' => ['healthy', 'organic', 'meals'],
            'seo_description' => 'Best healthy recipes',
        ],
    ]);

    $createResponse->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonPath('data.name', 'Healthy Recipes');

    $category = BlogCategory::where('name', 'Healthy Recipes')->first();
    expect($category)->not->toBeNull()
        ->and($category->slug)->toBe('healthy-recipes')
        ->and($category->status)->toBeTrue()
        ->and($category->metadata['seo_title'])->toBe('Healthy Recipes SEO');

    // 2. Fetch Edit Data
    $editResponse = $this->actingAs($admin)->getJson(route('admin.blog-categories.edit', $category->id));
    $editResponse->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonPath('data.name', 'Healthy Recipes');

    // 3. Update Category
    $updateResponse = $this->actingAs($admin)->putJson(route('admin.blog-categories.update', $category->id), [
        'name' => 'Delicious Healthy Recipes',
        'sort_order' => 5,
        'status' => true,
    ]);

    $updateResponse->assertOk()
        ->assertJsonPath('success', true);

    expect($category->fresh()->name)->toBe('Delicious Healthy Recipes')
        ->and($category->fresh()->sort_order)->toBe(5);

    // 4. Toggle Status
    $toggleResponse = $this->actingAs($admin)->postJson(route('admin.blog-categories.toggle-status', $category->id));
    $toggleResponse->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonPath('data.status', false);

    expect($category->fresh()->status)->toBeFalse();

    // 5. Delete Category
    $deleteResponse = $this->actingAs($admin)->deleteJson(route('admin.blog-categories.destroy', $category->id));
    $deleteResponse->assertOk()
        ->assertJsonPath('success', true);

    expect(BlogCategory::find($category->id))->toBeNull()
        ->and(BlogCategory::withTrashed()->find($category->id))->not->toBeNull();
});

test('admin can create and update blog with category_ids and is_editors_pick flag', function () {
    $admin = User::factory()->create([
        'access_panel' => GuardNameEnum::ADMIN->value,
    ]);
    Role::findOrCreate(DefaultSystemRolesEnum::SUPER_ADMIN->value, GuardNameEnum::ADMIN->value);
    $admin->assignRole(DefaultSystemRolesEnum::SUPER_ADMIN->value);

    $profile = WatchBuyProfile::factory()->create();
    $category1 = BlogCategory::factory()->create(['name' => 'Vegetarian Delights']);
    $category2 = BlogCategory::factory()->create(['name' => 'Healthy Drinks']);

    // Create Blog
    $response = $this->actingAs($admin)->post(route('admin.blogs.store'), [
        'title' => 'Top 5 Vegan Smoothies',
        'author_profile_id' => $profile->id,
        'category_ids' => [$category1->id, $category2->id],
        'short_description' => 'Quick and energizing morning smoothies.',
        'content' => '<p>Recipe details here...</p>',
        'tags' => ['vegan', 'smoothies', 'breakfast'],
        'status' => BlogStatusEnum::PUBLISHED->value,
        'is_editors_pick' => true,
    ]);

    $response->assertRedirect(route('admin.blogs.index'));

    $blog = Blog::where('title', 'Top 5 Vegan Smoothies')->first();
    expect($blog)->not->toBeNull()
        ->and($blog->category_ids)->toEqual([$category1->id, $category2->id])
        ->and($blog->is_editors_pick)->toBeTrue()
        ->and($blog->tags)->toEqual(['vegan', 'smoothies', 'breakfast']);

    // Toggle Editor's Pick
    $toggleResponse = $this->actingAs($admin)->postJson(route('admin.blogs.toggle-editors-pick', $blog->id));
    $toggleResponse->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonPath('data.is_editors_pick', false);
    expect($blog->fresh()->is_editors_pick)->toBeFalse();

    // Update Blog
    $updateResponse = $this->actingAs($admin)->put(route('admin.blogs.update', $blog->id), [
        'title' => 'Top 5 Vegan Smoothies Updated',
        'author_profile_id' => $profile->id,
        'category_ids' => [$category1->id],
        'content' => '<p>Updated recipe details...</p>',
        'tags' => ['vegan', 'healthy-drinks'],
        'status' => BlogStatusEnum::PUBLISHED->value,
        'is_editors_pick' => true,
    ]);

    $updateResponse->assertRedirect(route('admin.blogs.index'));
    expect($blog->fresh()->category_ids)->toEqual([$category1->id])
        ->and($blog->fresh()->is_editors_pick)->toBeTrue()
        ->and($blog->fresh()->tags)->toEqual(['vegan', 'healthy-drinks']);
});

test('public api returns list of active blog categories with post count', function () {
    $activeCat1 = BlogCategory::factory()->create(['name' => 'Category Active 1', 'status' => true, 'sort_order' => 1]);
    $activeCat2 = BlogCategory::factory()->create(['name' => 'Category Active 2', 'status' => true, 'sort_order' => 2]);
    $inactiveCat = BlogCategory::factory()->create(['name' => 'Category Inactive', 'status' => false]);

    Blog::factory()->create([
        'category_ids' => [$activeCat1->id],
        'status' => BlogStatusEnum::PUBLISHED,
        'published_at' => now()->subDay(),
    ]);

    $response = $this->getJson(route('blog-categories.index'));

    $response->assertOk()
        ->assertJsonPath('success', true);

    $data = collect($response->json('data'));
    expect($data->pluck('name'))->toContain('Category Active 1', 'Category Active 2')
        ->and($data->pluck('name'))->not->toContain('Category Inactive');

    $cat1Data = $data->firstWhere('name', 'Category Active 1');
    expect($cat1Data['blogs_count'])->toBe(1);
});

test('public api filters blogs by category_id, category_slug, and editors_pick', function () {
    $catA = BlogCategory::factory()->create(['name' => 'Category Alpha', 'slug' => 'category-alpha']);
    $catB = BlogCategory::factory()->create(['name' => 'Category Beta', 'slug' => 'category-beta']);

    $blogA = Blog::factory()->create([
        'title' => 'Article in Alpha',
        'category_ids' => [$catA->id],
        'is_editors_pick' => true,
        'status' => BlogStatusEnum::PUBLISHED,
        'published_at' => now()->subHour(),
    ]);

    $blogB = Blog::factory()->create([
        'title' => 'Article in Beta',
        'category_ids' => [$catB->id],
        'is_editors_pick' => false,
        'status' => BlogStatusEnum::PUBLISHED,
        'published_at' => now()->subHour(),
    ]);

    // Filter by category_slug
    $responseSlug = $this->getJson(route('blogs.index', ['category_slug' => 'category-alpha']));
    $responseSlug->assertOk()
        ->assertJsonPath('success', true);

    $itemsSlug = collect($responseSlug->json('data.data'));
    expect($itemsSlug->pluck('id'))->toContain($blogA->id)
        ->and($itemsSlug->pluck('id'))->not->toContain($blogB->id);

    // Filter by category_id
    $responseId = $this->getJson(route('blogs.index', ['category_id' => $catB->id]));
    $responseId->assertOk()
        ->assertJsonPath('success', true);

    $itemsId = collect($responseId->json('data.data'));
    expect($itemsId->pluck('id'))->toContain($blogB->id)
        ->and($itemsId->pluck('id'))->not->toContain($blogA->id);

    // Filter by editors_pick
    $responseEditorsPick = $this->getJson(route('blogs.index', ['editors_pick' => 1]));
    $responseEditorsPick->assertOk()
        ->assertJsonPath('success', true);

    $itemsEp = collect($responseEditorsPick->json('data.data'));
    expect($itemsEp->pluck('id'))->toContain($blogA->id)
        ->and($itemsEp->pluck('id'))->not->toContain($blogB->id);
});

test('public api filters blogs by tag', function () {
    $blog1 = Blog::factory()->create([
        'title' => 'Vegan Feast',
        'tags' => ['vegan', 'organic', 'dinner'],
        'status' => BlogStatusEnum::PUBLISHED,
        'published_at' => now()->subHour(),
    ]);

    $blog2 = Blog::factory()->create([
        'title' => 'Keto Meal Prep',
        'tags' => ['keto', 'high-protein'],
        'status' => BlogStatusEnum::PUBLISHED,
        'published_at' => now()->subHour(),
    ]);

    $response = $this->getJson(route('blogs.index', ['tag' => 'vegan']));
    $response->assertOk()
        ->assertJsonPath('success', true);

    $items = collect($response->json('data.data'));
    expect($items->pluck('id'))->toContain($blog1->id)
        ->and($items->pluck('id'))->not->toContain($blog2->id);
});

test('public api returns blog detail with categories list, category, tags, and related articles', function () {
    $category1 = BlogCategory::factory()->create(['name' => 'Health & Wellness', 'slug' => 'health-wellness']);
    $category2 = BlogCategory::factory()->create(['name' => 'Fitness', 'slug' => 'fitness']);

    $mainBlog = Blog::factory()->create([
        'title' => 'Main Health Guide',
        'slug' => 'main-health-guide',
        'category_ids' => [$category1->id, $category2->id],
        'is_editors_pick' => true,
        'tags' => ['wellness', 'exercise'],
        'status' => BlogStatusEnum::PUBLISHED,
        'published_at' => now()->subDay(),
    ]);

    $relatedBlog1 = Blog::factory()->create([
        'title' => 'Related by Category',
        'category_ids' => [$category1->id],
        'tags' => ['nutrition'],
        'status' => BlogStatusEnum::PUBLISHED,
        'published_at' => now()->subDays(2),
    ]);

    $relatedBlog2 = Blog::factory()->create([
        'title' => 'Related by Tag',
        'category_ids' => [],
        'tags' => ['wellness', 'sleep'],
        'status' => BlogStatusEnum::PUBLISHED,
        'published_at' => now()->subDays(3),
    ]);

    $unrelatedBlog = Blog::factory()->create([
        'title' => 'Unrelated Post',
        'category_ids' => [],
        'tags' => ['automotive'],
        'status' => BlogStatusEnum::PUBLISHED,
        'published_at' => now()->subDays(4),
    ]);

    $response = $this->getJson(route('blogs.show', 'main-health-guide'));

    $response->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonPath('data.title', 'Main Health Guide')
        ->assertJsonPath('data.is_editors_pick', true)
        ->assertJsonPath('data.category.name', 'Health & Wellness')
        ->assertJsonPath('data.tags', ['wellness', 'exercise']);

    $categoriesList = collect($response->json('data.categories'));
    expect($categoriesList->pluck('name'))->toContain('Health & Wellness', 'Fitness');

    $relatedList = collect($response->json('data.related_blogs'));
    expect($relatedList->pluck('id'))->toContain($relatedBlog1->id)
        ->and($relatedList->pluck('id'))->toContain($relatedBlog2->id)
        ->and($relatedList->pluck('id'))->not->toContain($unrelatedBlog->id)
        ->and($relatedList->pluck('id'))->not->toContain($mainBlog->id);
});
