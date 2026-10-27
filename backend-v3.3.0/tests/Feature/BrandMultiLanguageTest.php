<?php

use App\Enums\BrandStatusEnum;
use App\Helpers\TranslationHelper;
use App\Http\Resources\Admin\BrandManagementResource;
use App\Http\Resources\BrandResource;
use App\Http\Middleware\SetCatalogLocale;
use App\Http\Requests\Brand\StoreBrandRequest;
use App\Models\Brand;
use App\Models\BulkUploadJob;
use App\Models\Language;
use App\Contracts\AiDriverInterface;
use App\Services\Ai\AiManager;
use App\Services\Ai\AiTranslationService;
use App\Services\BrandService;
use App\Services\BulkUpload\BrandBulkImportService;
use App\Services\CatalogLanguageService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Validator;

uses(RefreshDatabase::class);

beforeEach(function () {
    // Seed test languages for client surfaces
    Language::updateOrCreate(['surface' => 'web', 'code' => 'en'], [
        'name' => 'English',
        'native_name' => 'English',
        'country_code' => 'gb',
        'direction' => 'ltr',
        'is_active' => true,
        'is_default' => true,
        'sort_order' => 1,
    ]);

    Language::updateOrCreate(['surface' => 'customer_app', 'code' => 'ar'], [
        'name' => 'Arabic',
        'native_name' => 'العربية',
        'country_code' => 'sa',
        'direction' => 'rtl',
        'is_active' => true,
        'is_default' => false,
        'sort_order' => 2,
    ]);

    Language::updateOrCreate(['surface' => 'web', 'code' => 'es'], [
        'name' => 'Spanish',
        'native_name' => 'Español',
        'country_code' => 'es',
        'direction' => 'ltr',
        'is_active' => true,
        'is_default' => false,
        'sort_order' => 3,
    ]);

    app(\App\Services\CatalogLanguageService::class)->clearCache();
});

it('creates a brand with multi-language title and description and generates canonical slug', function () {
    $brand = Brand::create([
        'title' => [
            'en' => 'Nike Global',
            'ar' => 'نايكي العالمية',
        ],
        'description' => [
            'en' => 'Official Nike sportswear',
            'ar' => 'ملابس نايكي الرياضية الرسمية',
        ],
        'status' => BrandStatusEnum::ACTIVE(),
    ]);

    expect($brand->exists)->toBeTrue()
        ->and($brand->uuid)->not->toBeEmpty()
        ->and($brand->slug)->toBe('nike-global')
        ->and($brand->getTranslation('title', 'en'))->toBe('Nike Global')
        ->and($brand->getTranslation('title', 'ar'))->toBe('نايكي العالمية')
        ->and($brand->getTranslation('description', 'en'))->toBe('Official Nike sportswear')
        ->and($brand->getTranslation('description', 'ar'))->toBe('ملابس نايكي الرياضية الرسمية');
});

it('preserves stable slug when updating brand translations', function () {
    $brand = app(BrandService::class)->createBrand([
        'title' => [
            'en' => 'Apple Inc',
            'es' => 'Manzana Inc',
        ],
        'description' => ['en' => 'Technology company'],
        'status' => BrandStatusEnum::ACTIVE(),
    ]);

    $originalSlug = $brand->slug;
    expect($originalSlug)->toBe('apple-inc');

    // Update translations
    app(BrandService::class)->updateBrand($brand, [
        'title' => [
            'en' => 'Apple Worldwide',
            'es' => 'Apple España',
            'ar' => 'أبل العالمية',
        ],
    ]);

    $brand->refresh();
    expect($brand->slug)->toBe($originalSlug)
        ->and($brand->getTranslation('title', 'en'))->toBe('Apple Worldwide')
        ->and($brand->getTranslation('title', 'ar'))->toBe('أبل العالمية');
});

it('unsets empty non-English translations while guarding canonical English', function () {
    $brand = app(BrandService::class)->createBrand([
        'title' => [
            'en' => 'Sony Corporation',
            'es' => 'Sony España',
            'ar' => 'سوني',
        ],
        'description' => ['en' => 'Electronics company'],
        'status' => BrandStatusEnum::ACTIVE(),
    ]);

    expect($brand->getTranslations('title'))->toHaveKeys(['en', 'es', 'ar']);

    // Admin form submits with 'es' cleared (empty string) and 'ar' preserved
    app(BrandService::class)->updateBrand($brand, [
        'title' => [
            'en' => 'Sony Corporation',
            'es' => '',
            'ar' => 'سوني',
        ],
    ]);

    $brand->refresh();
    $translations = $brand->getTranslations('title');
    expect($translations)->toHaveKey('en')
        ->and($translations)->toHaveKey('ar')
        ->and($translations)->not->toHaveKey('es')
        ->and($brand->getTranslation('title', 'es'))->toBe('Sony Corporation'); // Fallback to EN
});

it('resolves localized string without leaking translations dictionary in public BrandResource', function () {
    $brand = Brand::create([
        'title' => [
            'en' => 'Adidas Originals',
            'ar' => 'أديداس أوريجينالز',
        ],
        'description' => [
            'en' => 'Classic sports apparel',
        ],
        'status' => BrandStatusEnum::ACTIVE(),
    ]);

    app()->setLocale('ar');
    $request = Request::create('/api/v1/brands', 'GET');
    $resource = (new BrandResource($brand))->toArray($request);

    expect($resource['title'])->toBe('أديداس أوريجينالز')
        ->and($resource['description'])->toBe('Classic sports apparel') // Fallback to EN
        ->and(array_key_exists('translations', $resource))->toBeFalse();
});

it('provides translations map in BrandManagementResource for admin and seller edit', function () {
    $brand = Brand::create([
        'title' => [
            'en' => 'Puma Sport',
            'ar' => 'بوما سبورت',
        ],
        'description' => [
            'en' => 'Sporting goods',
        ],
        'status' => BrandStatusEnum::ACTIVE(),
    ]);

    $request = Request::create('/admin/brands/1/edit', 'GET');
    $management = (new BrandManagementResource($brand))->toArray($request);

    expect($management['translations']['title']['en'])->toBe('Puma Sport')
        ->and($management['translations']['title']['ar'])->toBe('بوما سبورت')
        ->and($management['translations']['description']['en'])->toBe('Sporting goods');
});

it('performs multi-locale search on brands', function () {
    Brand::create([
        'title' => [
            'en' => 'Puma Sport',
            'ar' => 'بوما سبورت',
        ],
        'description' => ['en' => 'Sporting goods'],
        'status' => BrandStatusEnum::ACTIVE(),
    ]);

    app()->setLocale('ar');

    // Searching English
    expect(Brand::whereTitleLike('Puma')->exists())->toBeTrue();
    // Searching the requested Arabic locale
    expect(Brand::whereTitleLike('بوما')->exists())->toBeTrue();
});

it('imports multi-language columns via BrandBulkImportService in create mode', function () {
    $importer = app(BrandBulkImportService::class);
    $job = BulkUploadJob::create([
        'uploadable_type' => Brand::class,
        'status' => 'processing',
        'file_path' => 'dummy.csv',
        'original_filename' => 'brands.csv',
        'operation' => 'create',
        'column_mapping' => [],
    ]);

    [$outcome, $msg] = $importer->processRow([
        'title' => 'Under Armour',
        'title_ar' => 'أندر آرمور',
        'description' => 'Athletic performance gear',
        'description_ar' => 'معدات الأداء الرياضي',
    ], $job);

    expect($outcome)->toBe('created');

    $brand = Brand::where('slug', 'under-armour')->first();
    expect($brand)->not->toBeNull()
        ->and($brand->getTranslation('title', 'en'))->toBe('Under Armour')
        ->and($brand->getTranslation('title', 'ar'))->toBe('أندر آرمور')
        ->and($brand->getTranslation('description', 'ar'))->toBe('معدات الأداء الرياضي');
});

it('processes dedicated translation-only bulk upload with uuid matching and blank cell preservation', function () {
    $brand1 = app(BrandService::class)->createBrand([
        'title' => ['en' => 'Zara Global'],
        'description' => ['en' => 'Fashion retailer'],
        'status' => BrandStatusEnum::ACTIVE(),
    ]);

    $brand2 = app(BrandService::class)->createBrand([
        'title' => ['en' => 'H&M Global', 'es' => 'H&M España Existente'],
        'description' => ['en' => 'Clothing store'],
        'status' => BrandStatusEnum::ACTIVE(),
    ]);

    $importer = app(BrandBulkImportService::class);
    $job = BulkUploadJob::create([
        'uploadable_type' => Brand::class,
        'status' => 'processing',
        'file_path' => 'dummy.csv',
        'original_filename' => 'translations_es.csv',
        'operation' => 'translation',
        'target_locale' => 'es',
        'match_field' => 'uuid',
        'column_mapping' => [],
    ]);

    // Row 1: update Zara Spanish translation
    [$outcome1, $msg1] = $importer->processRow([
        'uuid' => $brand1->uuid,
        'slug' => $brand1->slug,
        'title_en' => 'Zara Global',
        'description_en' => 'Fashion retailer',
        'title_es' => 'Zara España',
        'description_es' => 'Tienda de moda',
    ], $job);

    expect($outcome1)->toBe('updated');

    $brand1->refresh();
    expect($brand1->getTranslation('title', 'es'))->toBe('Zara España')
        ->and($brand1->getTranslation('description', 'es'))->toBe('Tienda de moda')
        ->and($brand1->getTranslation('title', 'en'))->toBe('Zara Global');

    // Row 2: H&M has empty target cells in CSV -> blank cell preservation
    [$outcome2, $msg2] = $importer->processRow([
        'uuid' => $brand2->uuid,
        'slug' => $brand2->slug,
        'title_en' => 'H&M Global',
        'description_en' => 'Clothing store',
        'title_es' => '',
        'description_es' => '',
    ], $job);

    expect($outcome2)->toBe('skipped');

    $brand2->refresh();
    expect($brand2->getTranslation('title', 'es'))->toBe('H&M España Existente');

    // Row 3: H&M explicit delete using __DELETE__ syntax
    [$outcome3, $msg3] = $importer->processRow([
        'uuid' => $brand2->uuid,
        'slug' => $brand2->slug,
        'title_en' => 'H&M Global',
        'description_en' => 'Clothing store',
        'title_es' => '__DELETE__',
        'description_es' => '',
    ], $job);

    expect($outcome3)->toBe('updated');

    $brand2->refresh();
    $translations = $brand2->getTranslations('title');
    expect($translations)->not->toHaveKey('es');
});

it('round-trips legacy scalar brand data through the guarded migration', function () {
    $migration = require database_path('migrations/2026_10_05_163000_convert_brands_columns_to_json.php');
    $migration->down();

    DB::table('brands')->insert([
        [
            'uuid' => (string) \Illuminate\Support\Str::uuid(),
            'title' => '{"looks":"json"}',
            'description' => 'Quoted "text" and हिन्दी',
            'slug' => 'literal-json-brand',
            'status' => BrandStatusEnum::ACTIVE(),
            'created_at' => now(),
            'updated_at' => now(),
        ],
        [
            'uuid' => (string) \Illuminate\Support\Str::uuid(),
            'title' => str_repeat('x', 255),
            'description' => str_repeat('y', 255),
            'slug' => 'maximum-brand',
            'status' => BrandStatusEnum::ACTIVE(),
            'created_at' => now(),
            'updated_at' => now(),
        ],
        [
            'uuid' => (string) \Illuminate\Support\Str::uuid(),
            'title' => 'Legacy Empty Description',
            'description' => '',
            'slug' => 'legacy-empty-description',
            'status' => BrandStatusEnum::ACTIVE(),
            'created_at' => now(),
            'updated_at' => now(),
        ],
    ]);

    $migration->up();

    $literal = DB::table('brands')->where('slug', 'literal-json-brand')->first();
    $maximum = DB::table('brands')->where('slug', 'maximum-brand')->first();
    $emptyDescription = DB::table('brands')->where('slug', 'legacy-empty-description')->first();

    expect(json_decode($literal->title, true, 512, JSON_THROW_ON_ERROR)['en'])->toBe('{"looks":"json"}')
        ->and(json_decode($literal->description, true, 512, JSON_THROW_ON_ERROR)['en'])->toBe('Quoted "text" and हिन्दी')
        ->and(json_decode($maximum->title, true, 512, JSON_THROW_ON_ERROR)['en'])->toHaveLength(255)
        ->and(json_decode($maximum->description, true, 512, JSON_THROW_ON_ERROR)['en'])->toHaveLength(255)
        ->and(json_decode($emptyDescription->title, true, 512, JSON_THROW_ON_ERROR)['en'])->toBe('Legacy Empty Description')
        ->and(json_decode($emptyDescription->description, true, 512, JSON_THROW_ON_ERROR)['en'])->toBe('')
        ->and(Schema::hasColumn('brands', 'title_legacy'))->toBeFalse()
        ->and(Schema::hasColumn('brands', 'description_legacy'))->toBeFalse();
});

it('derives AI translation targets from active catalog languages', function () {
    $driver = Mockery::mock(AiDriverInterface::class);
    $driver->shouldReceive('translateStructured')
        ->once()
        ->with(
            ['title' => 'English title', 'description' => 'English description'],
            'en',
            Mockery::on(fn (array $locales): bool => ! in_array('en', $locales, true)
                && in_array('ar', $locales, true)
                && in_array('es', $locales, true)),
            Mockery::on(fn (array $names): bool => ($names['es'] ?? null) === 'Spanish'
                && ($names['ar'] ?? null) === 'Arabic'
                && ! array_key_exists('en', $names)),
        )
        ->andReturn(['es' => ['title' => 'Título'], 'ar' => ['title' => 'عنوان']]);

    $manager = Mockery::mock(AiManager::class);
    $manager->shouldReceive('getAiSettings')->once()->andReturn(['enableAiDescription' => true]);
    $manager->shouldReceive('driver')->once()->andReturn($driver);

    $service = new AiTranslationService($manager, app(CatalogLanguageService::class));

    expect($service->translateEnglishFields([
        'title' => 'English title',
        'description' => 'English description',
    ]))->toBe(['es' => ['title' => 'Título'], 'ar' => ['title' => 'عنوان']]);
});

it('accepts only active catalog locale keys and requires both English fields', function () {
    $rules = StoreBrandRequest::baseRules();
    unset($rules['logo']);

    $valid = [
        'scope_type' => 'global',
        'title' => ['en' => 'Allowed Brand', 'ar' => 'علامة'],
        'description' => ['en' => 'Allowed description', 'ar' => 'وصف'],
    ];

    expect(Validator::make($valid, $rules)->passes())->toBeTrue()
        ->and(Validator::make(array_replace_recursive($valid, [
            'title' => ['xx' => 'Unknown'],
        ]), $rules)->fails())->toBeTrue()
        ->and(Validator::make(array_replace_recursive($valid, [
            'description' => ['en' => ''],
        ]), $rules)->fails())->toBeTrue();

    Language::query()->where('code', 'es')->update(['is_active' => false]);
    app(CatalogLanguageService::class)->clearCache();

    $rules = StoreBrandRequest::baseRules();
    unset($rules['logo']);

    expect(Validator::make(array_replace_recursive($valid, [
        'title' => ['es' => 'Inactiva'],
    ]), $rules)->fails())->toBeTrue();
});

it('resolves catalog locale from the header then query and ignores unrelated locale inputs', function () {
    $middleware = app(SetCatalogLocale::class);
    $next = fn (): Response => new Response('ok');

    $headerRequest = Request::create('/api/brands?locale=es', 'GET', server: ['HTTP_X_LOCALE' => 'AR']);
    $middleware->handle($headerRequest, $next);
    expect(app()->getLocale())->toBe('ar');

    $invalidHeaderRequest = Request::create('/api/brands?locale=es', 'GET', server: ['HTTP_X_LOCALE' => 'unknown']);
    $middleware->handle($invalidHeaderRequest, $next);
    expect(app()->getLocale())->toBe('en');

    $ignoredRequest = Request::create('/api/brands?lang=ar', 'GET', server: ['HTTP_ACCEPT_LANGUAGE' => 'ar']);
    $middleware->handle($ignoredRequest, $next);
    expect(app()->getLocale())->toBe('en');
});
