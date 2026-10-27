<?php

use App\Contracts\AiDriverInterface;
use App\Enums\CategoryStatusEnum;
use App\Http\Requests\Category\StoreCategoryRequest;
use App\Http\Resources\Admin\CategoryManagementResource;
use App\Http\Resources\CategoryResource;
use App\Models\BulkUploadJob;
use App\Models\Category;
use App\Models\Language;
use App\Services\Ai\AiManager;
use App\Services\Ai\AiTranslationService;
use App\Services\BulkUpload\CategoryBulkImportService;
use App\Services\CatalogLanguageService;
use App\Services\CategoryService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    Language::updateOrCreate(['surface' => 'web', 'code' => 'en'], [
        'name' => 'English', 'native_name' => 'English', 'country_code' => 'gb', 'direction' => 'ltr',
        'is_active' => true, 'is_default' => true, 'sort_order' => 1,
    ]);
    Language::updateOrCreate(['surface' => 'web', 'code' => 'es'], [
        'name' => 'Spanish', 'native_name' => 'Español', 'country_code' => 'es', 'direction' => 'ltr',
        'is_active' => true, 'is_default' => false, 'sort_order' => 2,
    ]);
    Language::updateOrCreate(['surface' => 'customer_app', 'code' => 'ar'], [
        'name' => 'Arabic', 'native_name' => 'العربية', 'country_code' => 'sa', 'direction' => 'rtl',
        'is_active' => true, 'is_default' => false, 'sort_order' => 3,
    ]);

    app(CatalogLanguageService::class)->clearCache();
});

it('creates translated categories with canonical immutable slugs and nullable descriptions', function (): void {
    $category = app(CategoryService::class)->createCategory([
        'title' => ['en' => 'Fresh Produce', 'es' => 'Productos frescos'],
        'description' => null,
        'status' => CategoryStatusEnum::ACTIVE(),
    ]);

    expect($category->uuid)->not->toBeEmpty()
        ->and($category->slug)->toBe('fresh-produce')
        ->and($category->getTranslation('title', 'es'))->toBe('Productos frescos')
        ->and($category->getRawOriginal('description'))->toBeNull();

    $originalSlug = $category->slug;
    $category = app(CategoryService::class)->updateCategory($category, [
        'title' => ['en' => 'Produce Market', 'ar' => 'سوق المنتجات'],
    ]);

    expect($category->slug)->toBe($originalSlug)
        ->and($category->getTranslation('title', 'en'))->toBe('Produce Market')
        ->and($category->getTranslation('title', 'ar'))->toBe('سوق المنتجات');
});

it('preserves omitted and inactive translations while removing submitted blanks', function (): void {
    $category = app(CategoryService::class)->createCategory([
        'title' => ['en' => 'Bakery', 'es' => 'Panadería', 'fr' => 'Boulangerie'],
        'description' => ['en' => 'Fresh bread', 'es' => 'Pan fresco', 'fr' => 'Pain frais'],
        'status' => CategoryStatusEnum::ACTIVE(),
    ]);

    $category = app(CategoryService::class)->updateCategory($category, [
        'title' => ['en' => 'Bakery', 'es' => ''],
        'description' => ['en' => '', 'es' => ''],
    ]);

    expect($category->getTranslations('title'))->not->toHaveKey('es')
        ->and($category->getTranslations('title')['fr'])->toBe('Boulangerie')
        ->and($category->getTranslations('description'))->not->toHaveKeys(['en', 'es'])
        ->and($category->getTranslations('description')['fr'])->toBe('Pain frais');
});

it('normalizes legacy scalar category request values to English', function (): void {
    $request = StoreCategoryRequest::create('/admin/categories', 'POST', [
        'title' => 'Legacy Scalar Category',
        'description' => 'Legacy scalar description',
        'status' => CategoryStatusEnum::ACTIVE(),
    ]);
    $request->setContainer(app());
    $prepare = new ReflectionMethod($request, 'prepareForValidation');
    $prepare->invoke($request);

    expect($request->input('title'))->toBe(['en' => 'Legacy Scalar Category'])
        ->and($request->input('description'))->toBe(['en' => 'Legacy scalar description']);
});

it('validates active locale keys, limits, and soft-deleted English uniqueness', function (): void {
    Category::create(['title' => ['en' => 'Reserved Category'], 'status' => CategoryStatusEnum::ACTIVE()])->delete();

    $rules = StoreCategoryRequest::baseRules();
    foreach (['image', 'icon', 'active_icon'] as $field) {
        unset($rules[$field]);
    }
    $valid = [
        'title' => ['en' => 'Allowed Category', 'es' => 'Categoría permitida'],
        'description' => ['en' => str_repeat('d', 10000)],
    ];

    expect(Validator::make($valid, $rules)->passes())->toBeTrue()
        ->and(Validator::make(array_replace_recursive($valid, ['title' => ['xx' => 'Unknown']]), $rules)->fails())->toBeTrue()
        ->and(Validator::make(array_replace_recursive($valid, ['title' => ['en' => 'reserved category']]), $rules)->fails())->toBeTrue()
        ->and(Validator::make(array_replace_recursive($valid, ['description' => ['en' => str_repeat('x', 10001)]]), $rules)->fails())->toBeTrue();
});

it('returns localized public values and English management values without leaking maps publicly', function (): void {
    $parent = Category::create([
        'title' => ['en' => 'Food', 'es' => 'Alimentos'],
        'description' => ['en' => 'Food description'],
        'status' => CategoryStatusEnum::ACTIVE(),
    ]);
    $category = Category::create([
        'parent_id' => $parent->id,
        'title' => ['en' => 'Fruit', 'es' => 'Fruta'],
        'description' => ['en' => 'Fresh fruit'],
        'status' => CategoryStatusEnum::ACTIVE(),
    ])->load('parent');

    app()->setLocale('es');
    $public = (new CategoryResource($category))->toArray(Request::create('/api/categories', 'GET'));
    $management = (new CategoryManagementResource($category))->toArray(Request::create('/admin/categories/1', 'GET'));

    expect($public['title'])->toBe('Fruta')
        ->and($public['description'])->toBe('Fresh fruit')
        ->and($public)->not->toHaveKey('translations')
        ->and($management['title'])->toBe('Fruit')
        ->and($management['parent_title'])->toBe('Food')
        ->and($management['translations']['title']['es'])->toBe('Fruta');
});

it('applies header then query locale precedence on the public category API', function (): void {
    $this->withoutMiddleware(\App\Http\Middleware\VerifyLicense::class);

    $parent = Category::create([
        'title' => ['en' => 'Food', 'es' => 'Alimentos', 'ar' => 'طعام'],
        'status' => CategoryStatusEnum::ACTIVE(),
    ]);
    Category::create([
        'parent_id' => $parent->id,
        'title' => ['en' => 'Fruit', 'es' => 'Fruta', 'ar' => 'فاكهة'],
        'status' => CategoryStatusEnum::ACTIVE(),
    ]);

    $this->withHeader('X-Locale', 'ES')
        ->getJson('/api/categories?slug='.$parent->slug.'&locale=ar&include_no_product=true')
        ->assertOk()
        ->assertJsonPath('data.main_category_data.title', 'Alimentos')
        ->assertJsonPath('data.data.0.title', 'Fruta');

    $this->withHeader('X-Locale', 'unknown')
        ->getJson('/api/categories?slug='.$parent->slug.'&locale=es&include_no_product=true')
        ->assertOk()
        ->assertJsonPath('data.main_category_data.title', 'Food')
        ->assertJsonPath('data.data.0.title', 'Fruit');
});

it('searches and orders by localized values publicly and English values for management', function (): void {
    Category::create(['title' => ['en' => 'Zebra', 'es' => 'Arbol'], 'status' => CategoryStatusEnum::ACTIVE()]);
    Category::create(['title' => ['en' => 'Apple', 'es' => 'Zapato'], 'status' => CategoryStatusEnum::ACTIVE()]);
    app()->setLocale('es');

    expect(Category::whereLocalizedTextLike('Arbol')->count())->toBe(1)
        ->and(Category::whereLocalizedTextLike('Apple')->count())->toBe(1)
        ->and(Category::orderByTitle()->first()->getTranslation('title', 'en', false))->toBe('Zebra')
        ->and(Category::orderByEnglishTitle()->first()->getTranslation('title', 'en', false))->toBe('Apple');
});

it('processes UUID-only category translation rows with preservation and explicit deletion', function (): void {
    $category = Category::create([
        'title' => ['en' => 'Drinks', 'es' => 'Bebidas', 'ar' => 'مشروبات'],
        'description' => ['en' => 'All drinks', 'es' => 'Todas las bebidas'],
        'status' => CategoryStatusEnum::ACTIVE(),
    ]);
    $job = BulkUploadJob::create([
        'uploadable_type' => Category::class,
        'status' => 'processing',
        'file_path' => 'dummy.csv',
        'original_filename' => 'categories_es.csv',
        'operation' => 'translation',
        'target_locale' => 'es',
        'match_field' => 'uuid',
        'column_mapping' => ['uuid' => 'uuid', 'title_es' => 'title_es', 'description_es' => 'description_es'],
    ]);

    [$preserved] = app(CategoryBulkImportService::class)->processRow([
        'uuid' => $category->uuid, 'title_es' => '', 'description_es' => '',
    ], $job);
    expect($preserved)->toBe('skipped');

    [$updated] = app(CategoryBulkImportService::class)->processRow([
        'uuid' => $category->uuid, 'title_es' => 'Bebidas nuevas', 'description_es' => '__DELETE__',
    ], $job);

    $category->refresh();
    expect($updated)->toBe('updated')
        ->and($category->getTranslation('title', 'es', false))->toBe('Bebidas nuevas')
        ->and($category->getTranslations('description'))->not->toHaveKey('es')
        ->and($category->getTranslation('title', 'ar', false))->toBe('مشروبات')
        ->and($category->getTranslation('title', 'en', false))->toBe('Drinks');
});

it('keeps regular category bulk create and update English-canonical', function (): void {
    $parent = Category::create([
        'title' => ['en' => 'Bulk Parent'],
        'status' => CategoryStatusEnum::ACTIVE(),
    ]);
    $importer = app(CategoryBulkImportService::class);
    $createJob = BulkUploadJob::create([
        'uploadable_type' => Category::class,
        'status' => 'processing',
        'file_path' => 'create.csv',
        'original_filename' => 'create.csv',
        'operation' => 'create',
        'column_mapping' => [
            'title' => 'title',
            'title_es' => 'title_es',
            'description' => 'description',
            'parent_title' => 'parent_title',
        ],
    ]);

    [$created] = $importer->processRow([
        'title' => 'Bulk Child',
        'title_es' => 'Hijo masivo',
        'description' => 'English description',
        'parent_title' => 'bulk parent',
    ], $createJob);

    $category = Category::query()->whereEnglishTitle('bulk child')->firstOrFail();
    expect($created)->toBe('created')
        ->and($category->parent_id)->toBe($parent->id)
        ->and($category->getTranslation('title', 'en', false))->toBe('Bulk Child')
        ->and($category->getTranslation('title', 'es', false))->toBe('Hijo masivo');

    $updateJob = BulkUploadJob::create([
        'uploadable_type' => Category::class,
        'status' => 'processing',
        'file_path' => 'update.csv',
        'original_filename' => 'update.csv',
        'operation' => 'update',
        'match_field' => 'title',
        'column_mapping' => [
            'title' => 'match_title',
            'description' => 'description',
        ],
    ]);

    [$updated] = $importer->processRow([
        'match_title' => 'BULK CHILD',
        'description' => 'Updated English description',
    ], $updateJob);

    $category->refresh();
    expect($updated)->toBe('updated')
        ->and($category->getTranslation('description', 'en', false))->toBe('Updated English description')
        ->and($category->getTranslation('title', 'es', false))->toBe('Hijo masivo');
});

it('rejects invalid translation targets and ignores cross-locale translation columns', function (): void {
    $category = Category::create([
        'title' => ['en' => 'Isolated', 'es' => 'Aislado', 'ar' => 'معزول'],
        'status' => CategoryStatusEnum::ACTIVE(),
    ]);
    $importer = app(CategoryBulkImportService::class);
    $invalidJob = BulkUploadJob::create([
        'uploadable_type' => Category::class,
        'status' => 'processing',
        'file_path' => 'invalid.csv',
        'original_filename' => 'invalid.csv',
        'operation' => 'translation',
        'target_locale' => 'xx',
        'match_field' => 'uuid',
        'column_mapping' => ['uuid' => 'uuid', 'title_xx' => 'title_xx'],
    ]);

    [$invalid] = $importer->processRow([
        'uuid' => $category->uuid,
        'title_xx' => 'Unknown',
    ], $invalidJob);
    expect($invalid)->toBe('failed');

    $spanishJob = BulkUploadJob::create([
        'uploadable_type' => Category::class,
        'status' => 'processing',
        'file_path' => 'spanish.csv',
        'original_filename' => 'spanish.csv',
        'operation' => 'translation',
        'target_locale' => 'es',
        'match_field' => 'uuid',
        'column_mapping' => [
            'uuid' => 'uuid',
            'title_es' => 'title_es',
            'title_ar' => 'title_ar',
        ],
    ]);

    [$updated] = $importer->processRow([
        'uuid' => $category->uuid,
        'title_es' => 'Nuevo aislado',
        'title_ar' => 'يجب تجاهله',
    ], $spanishJob);

    $category->refresh();
    expect($updated)->toBe('updated')
        ->and($category->getTranslation('title', 'es', false))->toBe('Nuevo aislado')
        ->and($category->getTranslation('title', 'ar', false))->toBe('معزول');
});

it('exports the exact category translation template contract', function (): void {
    $parent = Category::create(['title' => ['en' => 'Parent'], 'status' => CategoryStatusEnum::ACTIVE()]);
    $category = Category::create([
        'parent_id' => $parent->id,
        'title' => ['en' => 'Child', 'es' => 'Hijo'],
        'description' => ['en' => 'English', 'es' => 'Español'],
        'status' => CategoryStatusEnum::ACTIVE(),
    ])->load('parent');
    $importer = app(CategoryBulkImportService::class);

    expect($importer->translationExportColumns('es'))->toBe([
        'uuid', 'id', 'slug', 'parent_id', 'parent_title_en', 'title_en', 'description_en', 'title_es', 'description_es',
    ])->and($importer->translationExportRow($category, 'es'))->toMatchArray([
        'uuid' => $category->uuid, 'parent_title_en' => 'Parent', 'title_en' => 'Child',
        'description_en' => 'English', 'title_es' => 'Hijo', 'description_es' => 'Español',
    ]);
});

it('passes category-specific field limits to the shared AI driver', function (): void {
    $driver = Mockery::mock(AiDriverInterface::class);
    $driver->shouldReceive('translateStructured')->once()->with(
        ['title' => 'Category', 'description' => str_repeat('x', 1000)],
        'en',
        Mockery::on(fn (array $locales): bool => in_array('es', $locales, true) && in_array('ar', $locales, true)),
        Mockery::type('array'),
        ['title' => 255, 'description' => 10000],
    )->andReturn(['es' => ['title' => 'Categoría']]);
    $manager = Mockery::mock(AiManager::class);
    $manager->shouldReceive('getAiSettings')->once()->andReturn(['enableAiDescription' => true]);
    $manager->shouldReceive('driver')->once()->andReturn($driver);

    $service = new AiTranslationService($manager, app(CatalogLanguageService::class));
    expect($service->translateEnglishFields([
        'title' => 'Category', 'description' => str_repeat('x', 1000),
    ], ['title' => 255, 'description' => 10000]))->toBe(['es' => ['title' => 'Categoría']]);
});

it('round-trips legacy scalar category values while preserving null and empty descriptions', function (): void {
    $migration = require database_path('migrations/2026_10_06_145314_convert_categories_columns_to_json.php');
    $migration->down();

    DB::table('categories')->insert([
        [
            'uuid' => (string) Str::uuid(), 'title' => '{"looks":"json"}',
            'description' => 'Quoted "text" and हिन्दी', 'slug' => 'literal-json-category',
            'status' => CategoryStatusEnum::ACTIVE(), 'created_at' => now(), 'updated_at' => now(),
        ],
        [
            'uuid' => (string) Str::uuid(), 'title' => str_repeat('x', 255),
            'description' => str_repeat('y', 12000), 'slug' => 'long-description-category',
            'status' => CategoryStatusEnum::ACTIVE(), 'created_at' => now(), 'updated_at' => now(),
        ],
        [
            'uuid' => (string) Str::uuid(), 'title' => 'Null Description', 'description' => null,
            'slug' => 'null-description-category', 'status' => CategoryStatusEnum::ACTIVE(),
            'created_at' => now(), 'updated_at' => now(),
        ],
        [
            'uuid' => (string) Str::uuid(), 'title' => 'Empty Description', 'description' => '',
            'slug' => 'empty-description-category', 'status' => CategoryStatusEnum::ACTIVE(),
            'created_at' => now(), 'updated_at' => now(),
        ],
    ]);

    $migration->up();
    $migration->up();

    $literal = DB::table('categories')->where('slug', 'literal-json-category')->first();
    $long = DB::table('categories')->where('slug', 'long-description-category')->first();
    $null = DB::table('categories')->where('slug', 'null-description-category')->first();
    $empty = DB::table('categories')->where('slug', 'empty-description-category')->first();

    expect(json_decode($literal->title, true, 512, JSON_THROW_ON_ERROR)['en'])->toBe('{"looks":"json"}')
        ->and(json_decode($literal->description, true, 512, JSON_THROW_ON_ERROR)['en'])->toBe('Quoted "text" and हिन्दी')
        ->and(json_decode($long->title, true, 512, JSON_THROW_ON_ERROR)['en'])->toHaveLength(255)
        ->and(json_decode($long->description, true, 512, JSON_THROW_ON_ERROR)['en'])->toHaveLength(12000)
        ->and($null->description)->toBeNull()
        ->and(json_decode($empty->description, true, 512, JSON_THROW_ON_ERROR)['en'])->toBe('')
        ->and(Schema::hasColumn('categories', 'title_legacy'))->toBeFalse()
        ->and(Schema::hasColumn('categories', 'description_legacy'))->toBeFalse();
});

it('resumes the category migration after a partial column swap', function (): void {
    $migration = require database_path('migrations/2026_10_06_145314_convert_categories_columns_to_json.php');
    $migration->down();

    $uuid = (string) Str::uuid();
    DB::table('categories')->insert([
        'uuid' => $uuid,
        'title' => 'Interrupted Category',
        'description' => null,
        'slug' => 'interrupted-category',
        'status' => CategoryStatusEnum::ACTIVE(),
        'created_at' => now(),
        'updated_at' => now(),
    ]);
    Schema::table('categories', function ($table): void {
        $table->json('title_i18n')->nullable();
        $table->json('description_i18n')->nullable();
    });
    DB::table('categories')->where('uuid', $uuid)->update([
        'title_i18n' => json_encode(['en' => 'Interrupted Category'], JSON_THROW_ON_ERROR),
    ]);
    Schema::table('categories', fn ($table) => $table->renameColumn('title', 'title_legacy'));
    Schema::table('categories', fn ($table) => $table->renameColumn('title_i18n', 'title'));

    $migration->up();

    $category = DB::table('categories')->where('uuid', $uuid)->first();
    expect(json_decode($category->title, true, 512, JSON_THROW_ON_ERROR)['en'])->toBe('Interrupted Category')
        ->and($category->description)->toBeNull()
        ->and(Schema::hasColumn('categories', 'title_legacy'))->toBeFalse()
        ->and(Schema::hasColumn('categories', 'description_legacy'))->toBeFalse();
});

it('rejects blank legacy titles before swapping category columns', function (): void {
    $migration = require database_path('migrations/2026_10_06_145314_convert_categories_columns_to_json.php');
    $migration->down();

    DB::table('categories')->insert([
        'uuid' => (string) Str::uuid(),
        'title' => '   ',
        'description' => null,
        'slug' => 'blank-title-category',
        'status' => CategoryStatusEnum::ACTIVE(),
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    expect(fn () => $migration->up())
        ->toThrow(RuntimeException::class, 'has an empty canonical title');

    expect(Schema::hasColumn('categories', 'title'))->toBeTrue()
        ->and(Schema::hasColumn('categories', 'title_legacy'))->toBeFalse()
        ->and(DB::table('categories')->where('slug', 'blank-title-category')->value('title'))->toBe('   ');
});

it('rejects an unsafe rollback title instead of truncating it', function (): void {
    $migration = require database_path('migrations/2026_10_06_145314_convert_categories_columns_to_json.php');
    $category = Category::create([
        'title' => ['en' => 'Safe title'],
        'status' => CategoryStatusEnum::ACTIVE(),
    ]);
    DB::table('categories')->where('id', $category->id)->update([
        'title' => json_encode(['en' => str_repeat('x', 256)], JSON_THROW_ON_ERROR),
    ]);

    expect(fn () => $migration->down())
        ->toThrow(RuntimeException::class, 'title failed rollback verification');

    $stored = DB::table('categories')->where('id', $category->id)->value('title');
    expect(json_decode($stored, true, 512, JSON_THROW_ON_ERROR)['en'])->toHaveLength(256);
});
