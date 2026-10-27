<?php

use App\Enums\Blog\BlogStatusEnum;
use App\Enums\WatchBuy\WatchBuyProfileTypeEnum;
use App\Http\Middleware\CheckInstallation;
use App\Http\Middleware\VerifyLicense;
use App\Models\Blog;
use App\Models\BlogCategory;
use App\Models\Product;
use App\Models\User;
use App\Models\WatchBuyProfile;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;

use App\Enums\DefaultSystemRolesEnum;
use App\Enums\GuardNameEnum;
use Spatie\Permission\Models\Role;

use App\Http\Middleware\EnsureSubscriptionFeatureSelected;
use App\Http\Middleware\EnsureSystemVendorTypeSelected;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->withoutMiddleware([
        VerifyLicense::class,
        CheckInstallation::class,
        EnsureSystemVendorTypeSelected::class,
        EnsureSubscriptionFeatureSelected::class,
    ]);
    Gate::before(fn () => true);

    Role::findOrCreate(DefaultSystemRolesEnum::SUPER_ADMIN->value, GuardNameEnum::ADMIN->value);
    $this->admin = User::factory()->create([
        'access_panel' => GuardNameEnum::ADMIN->value,
    ]);
    $this->admin->assignRole(DefaultSystemRolesEnum::SUPER_ADMIN->value);
    $this->actingAs($this->admin, GuardNameEnum::ADMIN->value);

    $this->author = WatchBuyProfile::factory()->create([
        'profile_type' => WatchBuyProfileTypeEnum::PLATFORM,
        'username' => 'chef_mario',
        'bio' => 'Master of organic ingredients',
    ]);

    $this->product = Product::forceCreate(testProductData($this->admin->id, [
        'title' => 'Fresh Basil Leaves',
        'status' => 'active',
        'verification_status' => 'approved',
    ]));
});

test('admin can view blogs list page', function () {
    Blog::factory()->count(3)->create();

    $this->get(route('admin.blogs.index'))
        ->assertSuccessful()
        ->assertSee(__('labels.blogs'));
});

test('admin can fetch blogs datatable', function () {
    Blog::factory()->create([
        'title' => 'Top 5 Summer Salads',
        'author_profile_id' => $this->author->id,
    ]);

    $response = $this->getJson(route('admin.blogs.index'), ['X-Requested-With' => 'XMLHttpRequest'])
        ->assertSuccessful()
        ->assertJsonStructure(['data', 'recordsTotal', 'recordsFiltered']);

    expect($response->json('recordsTotal'))->toBe(1)
        ->and($response->json('data.0.title'))->toBe('Top 5 Summer Salads');
});

test('admin can create a new blog with image, author, and linked products', function () {
    Storage::fake('public');
    $image = UploadedFile::fake()->image('blog-cover.jpg', 800, 600);

    $payload = [
        'title' => '10 Secrets of Organic Farming',
        'short_description' => 'Learn how local farmers grow chemical-free crops.',
        'content' => '<h2>Introduction</h2><p>Organic farming starts with nutrient-rich soil...</p>',
        'author_profile_id' => $this->author->id,
        'product_ids' => [$this->product->id],
        'status' => BlogStatusEnum::PUBLISHED->value,
        'published_at' => now()->toDateTimeString(),
        'image' => $image,
        'metadata' => [
            'seo_title' => '10 Secrets of Organic Farming | Groceries Guide',
            'seo_description' => 'Discover the essential secrets of organic farming and healthy living.',
            'seo_keywords' => ['organic', 'farming', 'fresh'],
        ],
    ];

    $this->post(route('admin.blogs.store'), $payload)
        ->assertRedirect(route('admin.blogs.index'))
        ->assertSessionHas('success');

    $this->assertDatabaseHas('blogs', [
        'title' => '10 Secrets of Organic Farming',
        'slug' => '10-secrets-of-organic-farming',
        'author_profile_id' => $this->author->id,
        'status' => BlogStatusEnum::PUBLISHED->value,
    ]);

    $blog = Blog::query()->first();
    expect($blog->product_ids)->toBe([$this->product->id])
        ->and($blog->image)->not->toBeNull();
});

test('admin can update an existing blog', function () {
    $blog = Blog::factory()->create([
        'title' => 'Old Blog Title',
        'author_profile_id' => $this->author->id,
    ]);

    $payload = [
        'title' => 'Updated Blog Title',
        'short_description' => 'Updated short description.',
        'content' => '<p>Updated content body.</p>',
        'author_profile_id' => $this->author->id,
        'status' => BlogStatusEnum::PUBLISHED->value,
        'published_at' => now()->toDateTimeString(),
    ];

    $this->put(route('admin.blogs.update', $blog->id), $payload)
        ->assertRedirect(route('admin.blogs.index'))
        ->assertSessionHas('success');

    $blog->refresh();
    expect($blog->title)->toBe('Updated Blog Title')
        ->and($blog->slug)->toBe('updated-blog-title');
});

test('admin can toggle blog publish status', function () {
    $blog = Blog::factory()->create(['status' => BlogStatusEnum::PUBLISHED]);

    $this->postJson(route('admin.blogs.toggle-status', $blog->id))
        ->assertSuccessful()
        ->assertJsonPath('success', true)
        ->assertJsonPath('data.status', 'draft');

    expect($blog->fresh()->status)->toBe(BlogStatusEnum::DRAFT);

    $this->postJson(route('admin.blogs.toggle-status', $blog->id))
        ->assertSuccessful()
        ->assertJsonPath('data.status', 'published');

    expect($blog->fresh()->status)->toBe(BlogStatusEnum::PUBLISHED);
});

test('admin can delete a blog', function () {
    $blog = Blog::factory()->create();

    $this->deleteJson(route('admin.blogs.destroy', $blog->id))
        ->assertSuccessful()
        ->assertJsonPath('success', true);

    $this->assertSoftDeleted('blogs', ['id' => $blog->id]);
});

test('blog creation requires author_profile_id', function () {
    $payload = [
        'title' => 'Blog without author',
        'content' => '<p>Content here...</p>',
        'status' => BlogStatusEnum::PUBLISHED->value,
    ];

    $response = $this->post(route('admin.blogs.store'), $payload);

    $response->assertSessionHasErrors(['author_profile_id']);
});

test('blog seeder executes successfully and seeds blogs with categories and authors', function () {
    $this->seed(\Database\Seeders\BlogSeeder::class);

    expect(Blog::count())->toBeGreaterThanOrEqual(18)
        ->and(BlogCategory::count())->toBeGreaterThanOrEqual(6);

    $editorsPicks = Blog::editorsPick()->get();
    expect($editorsPicks->count())->toBeGreaterThanOrEqual(5);

    $sampleBlog = Blog::where('slug', '10-quick-nutritious-15-minute-dinner-recipes')->first();
    expect($sampleBlog)->not->toBeNull()
        ->and($sampleBlog->categories->count())->toBe(2)
        ->and($sampleBlog->is_editors_pick)->toBeTrue()
        ->and($sampleBlog->authorProfile)->not->toBeNull()
        ->and($sampleBlog->authorProfile->profile_type->value)->toBe('platform');
});
