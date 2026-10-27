<?php

use App\Contracts\AiDriverInterface;
use App\Enums\DefaultSystemRolesEnum;
use App\Http\Middleware\SetCatalogLocale;
use App\Http\Requests\Product\StoreUpdateProductRequest;
use App\Http\Resources\Product\ProductResource;
use App\Http\Resources\Product\SellerProductResource;
use App\Models\BulkUploadJob;
use App\Models\Language;
use App\Models\Product;
use App\Models\Seller;
use App\Models\User;
use App\Services\Ai\AiManager;
use App\Services\Ai\AiTranslationService;
use App\Services\BulkUpload\ProductBulkImportService;
use App\Services\CatalogLanguageService;
use App\Services\ProductService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Validator;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

it('provides unique active catalog languages in settings api and not in seller product detail', function (): void {
    $this->withoutMiddleware();
    $user = User::factory()->create(['access_panel' => 'seller']);
    $seller = Seller::factory()->create(['user_id' => $user->id]);
    Role::findOrCreate(DefaultSystemRolesEnum::SELLER->value, 'seller');
    $user->assignRole(DefaultSystemRolesEnum::SELLER->value);
    Sanctum::actingAs($user);
    $product = Product::factory()->create(['seller_id' => $seller->id, 'title' => ['en' => 'Shirt', 'es' => 'Camisa']]);
    Language::where('code', 'fr')->update(['is_active' => false]);
    app(CatalogLanguageService::class)->clearCache();

    $this->getJson('/api/seller/products/'.$product->id)
        ->assertSuccessful()
        ->assertJsonPath('data.id', $product->id)
        ->assertJsonPath('data.title', 'Shirt')
        ->assertJsonPath('data.translations.title.es', 'Camisa')
        ->assertJsonMissingPath('data.catalog_languages');

    $this->getJson('/api/seller/products')->assertSuccessful()->assertJsonMissingPath('data.catalog_languages');

    // Verify settings api includes catalog_languages
    $settingsResponse = $this->getJson('/api/settings')->assertSuccessful();
    $settingsData = collect($settingsResponse->json('data'));

    $catalogLangSetting = $settingsData->firstWhere('variable', 'catalog_languages');
    expect($catalogLangSetting)->not->toBeNull()
        ->and($catalogLangSetting['value']['default'])->toBe('en');

    $languages = collect($catalogLangSetting['value']['languages']);
    expect($languages->first()['code'])->toBe('en')
        ->and($languages->pluck('code')->all())->toContain('es', 'ar')->not->toContain('fr')
        ->and($languages->pluck('code')->unique()->count())->toBe($languages->count())
        ->and($languages->firstWhere('code', 'ar')['direction'])->toBe('rtl');

    // Also verify system setting value contains catalog_languages
    $systemSetting = $settingsData->firstWhere('variable', 'system');
    expect($systemSetting['value']['catalog_languages']['default'])->toBe('en');

    // Also verify GET /api/settings/catalog_languages
    $this->getJson('/api/settings/catalog_languages')
        ->assertSuccessful()
        ->assertJsonPath('data.variable', 'catalog_languages')
        ->assertJsonPath('data.value.default', 'en');
});

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

it('stores translated products with immutable English-derived slugs and English-only SEO fallbacks', function (): void {
    $product = Product::factory()->create([
        'title' => ['en' => 'Summer Fruit Box', 'es' => 'Caja de frutas'],
        'short_description' => ['en' => 'Fresh seasonal fruit', 'es' => 'Fruta fresca'],
        'description' => ['en' => 'An English description', 'es' => 'Una descripción'],
        'metadata' => [],
        'slug' => null,
    ]);

    $slug = $product->slug;
    app()->setLocale('es');

    expect($product->title)->toBe('Caja de frutas')
        ->and($product->seo_title)->toBe('Summer Fruit Box')
        ->and($product->seo_description)->toBe('Fresh seasonal fruit');

    $product->setTranslation('title', 'en', 'Renamed English Product')->save();

    expect($product->refresh()->slug)->toBe($slug);
});

it('normalizes scalar requests and rejects unknown or inactive locales', function (): void {
    Language::updateOrCreate(['surface' => 'web', 'code' => 'fr'], [
        'name' => 'French', 'native_name' => 'Français', 'country_code' => 'fr', 'direction' => 'ltr',
        'is_active' => false, 'is_default' => false, 'sort_order' => 4,
    ]);
    app(CatalogLanguageService::class)->clearCache();

    $request = StoreUpdateProductRequest::create('/admin/products', 'POST', [
        'title' => 'Legacy Product',
        'short_description' => 'Legacy short',
        'description' => 'Legacy description',
    ]);
    $request->setContainer(app());
    (new ReflectionMethod($request, 'prepareForValidation'))->invoke($request);

    expect($request->input('title'))->toBe(['en' => 'Legacy Product'])
        ->and($request->input('short_description'))->toBe(['en' => 'Legacy short'])
        ->and($request->input('description'))->toBe(['en' => 'Legacy description']);

    $rules = Arr::only($request->rules(), [
        'title', 'title.en', 'title.*',
        'short_description', 'short_description.en', 'short_description.*',
        'description', 'description.en', 'description.*',
    ]);
    $valid = [
        'title' => ['en' => 'Allowed', 'es' => 'Permitido'],
        'short_description' => ['en' => 'Short'],
        'description' => ['en' => str_repeat('x', 50000)],
    ];

    expect(Validator::make($valid, $rules)->passes())->toBeTrue()
        ->and(Validator::make(array_replace_recursive($valid, ['title' => ['xx' => 'Unknown']]), $rules)->fails())->toBeTrue()
        ->and(Validator::make(array_replace_recursive($valid, ['description' => ['en' => str_repeat('x', 50001)]]), $rules)->fails())->toBeTrue();
});

it('preserves omitted translations and removes only submitted blank non-English translations', function (): void {
    Event::fake();
    $product = Product::factory()->create([
        'title' => ['en' => 'Coffee', 'es' => 'Café', 'fr' => 'Café français'],
        'short_description' => ['en' => 'Roasted', 'es' => 'Tostado', 'fr' => 'Torréfié'],
        'description' => ['en' => 'Coffee beans', 'es' => 'Granos', 'fr' => 'Grains'],
    ]);

    app(ProductService::class)->updateTranslations($product, [
        'title' => ['es' => ''],
        'short_description' => ['es' => 'Nuevo tostado'],
    ]);

    $product->refresh();
    expect($product->getTranslations('title'))->not->toHaveKey('es')
        ->and($product->getTranslation('title', 'fr', false))->toBe('Café français')
        ->and($product->getTranslation('short_description', 'es', false))->toBe('Nuevo tostado')
        ->and($product->getTranslation('description', 'fr', false))->toBe('Grains');
});

it('localizes public resources but keeps seller management canonical with complete maps', function (): void {
    $product = Product::factory()->create([
        'title' => ['en' => 'Shoes', 'es' => 'Zapatos'],
        'short_description' => ['en' => 'Walking shoes', 'es' => 'Zapatos para caminar'],
        'description' => ['en' => 'Comfortable shoes'],
    ]);
    app()->setLocale('es');

    $public = (new ProductResource($product))->toArray(Request::create('/api/products/shoes'));
    $management = (new SellerProductResource($product))->toArray(Request::create('/api/seller/products/1'));

    expect($public['title'])->toBe('Zapatos')
        ->and($public['short_description'])->toBe('Zapatos para caminar')
        ->and($public['description'])->toBe('Comfortable shoes')
        ->and($public)->not->toHaveKey('translations')
        ->and($management['title'])->toBe('Shoes')
        ->and($management['translations']['title']['es'])->toBe('Zapatos');
});

it('searches and orders publicly by requested locale and management by English', function (): void {
    Product::factory()->create([
        'title' => ['en' => 'Zebra', 'es' => 'Arbol'],
        'short_description' => ['en' => 'First short'],
        'description' => ['en' => 'First description'],
    ]);
    Product::factory()->create([
        'title' => ['en' => 'Apple', 'es' => 'Zapato'],
        'short_description' => ['en' => 'Second short'],
        'description' => ['en' => 'Second description'],
    ]);
    app()->setLocale('es');

    expect(Product::whereLocalizedTextLike('Arbol')->count())->toBe(1)
        ->and(Product::whereLocalizedTextLike('Apple')->count())->toBe(1)
        ->and(Product::orderByTranslatedTitle()->first()->getTranslation('title', 'en', false))->toBe('Zebra')
        ->and(Product::orderByEnglishTitle()->first()->getTranslation('title', 'en', false))->toBe('Apple');
});

it('uses the locale header before the query and safely falls back to English', function (): void {
    $middleware = app(SetCatalogLocale::class);
    $request = Request::create('/api/products?locale=es');
    $request->headers->set('X-Locale', 'ar');

    $middleware->handle($request, fn (): Response => new Response);
    expect(app()->getLocale())->toBe('ar');

    $invalidRequest = Request::create('/api/products?locale=es');
    $invalidRequest->headers->set('X-Locale', 'not-a-locale');
    $middleware->handle($invalidRequest, fn (): Response => new Response);

    expect(app()->getLocale())->toBe('en');
});

it('rejects conflicting product-level values repeated across regular bulk variant rows', function (): void {
    $importer = app(ProductBulkImportService::class);
    $method = new ReflectionMethod($importer, 'translationPayload');
    $result = $method->invoke($importer, [
        ['title' => 'First title', 'short_description' => 'Short', 'description' => 'Description'],
        ['title' => 'Second title', 'short_description' => 'Short', 'description' => 'Description'],
    ], [
        'title' => 'First title',
        'short_description' => 'Short',
        'description' => 'Description',
    ], null);

    expect($result)->toHaveKey('error');
});

it('exports and processes UUID-only product translations with blank preservation and explicit deletion', function (): void {
    Event::fake();
    $product = Product::factory()->create([
        'title' => ['en' => 'Tea', 'es' => 'Té', 'ar' => 'شاي'],
        'short_description' => ['en' => 'Green tea', 'es' => 'Té verde'],
        'description' => ['en' => 'Tea description', 'es' => 'Descripción'],
    ]);
    $importer = app(ProductBulkImportService::class);
    $job = BulkUploadJob::create([
        'uploadable_type' => Product::class,
        'status' => 'processing',
        'file_path' => 'product-es.csv',
        'original_filename' => 'product-es.csv',
        'operation' => 'translation',
        'target_locale' => 'es',
        'match_field' => 'uuid',
        'column_mapping' => [
            'uuid' => 'uuid',
            'title_es' => 'title_es',
            'short_description_es' => 'short_description_es',
            'description_es' => 'description_es',
            'title_ar' => 'title_ar',
        ],
        'default_values' => ['seller_id' => $product->seller_id],
    ]);

    expect($importer->translationExportColumns('es'))->toContain('uuid', 'title_en', 'short_description_es', 'description_es')
        ->and($importer->translationExportRow($product, 'es')['title_es'])->toBe('Té');

    [$outcome] = $importer->processRow([
        'uuid' => $product->uuid,
        'title_es' => 'Té nuevo',
        'short_description_es' => '',
        'description_es' => '__DELETE__',
        'title_ar' => 'لا تكتب',
    ], $job);

    $product->refresh();
    expect($outcome)->toBe('updated')
        ->and($product->getTranslation('title', 'es', false))->toBe('Té nuevo')
        ->and($product->getTranslation('short_description', 'es', false))->toBe('Té verde')
        ->and($product->getTranslations('description'))->not->toHaveKey('es')
        ->and($product->getTranslation('title', 'ar', false))->toBe('شاي');
});

it('passes product-specific field limits to the provider-agnostic AI translation driver', function (): void {
    $driver = Mockery::mock(AiDriverInterface::class);
    $limits = ['title' => 255, 'short_description' => 255, 'description' => 10000];
    $driver->shouldReceive('translateStructured')->once()->with(
        ['title' => 'Product', 'short_description' => 'Short', 'description' => 'Description'],
        'en',
        Mockery::on(fn (array $locales): bool => in_array('es', $locales, true) && in_array('ar', $locales, true)),
        Mockery::type('array'),
        $limits,
    )->andReturn(['es' => ['title' => 'Producto']]);
    $manager = Mockery::mock(AiManager::class);
    $manager->shouldReceive('getAiSettings')->once()->andReturn(['enableAiDescription' => true]);
    $manager->shouldReceive('driver')->once()->andReturn($driver);

    $service = new AiTranslationService($manager, app(CatalogLanguageService::class));
    expect($service->translateEnglishFields([
        'title' => 'Product', 'short_description' => 'Short', 'description' => 'Description',
    ], $limits))->toBe(['es' => ['title' => 'Producto']]);
});

it('limits product AI translation to the requested active target languages', function (): void {
    $driver = Mockery::mock(AiDriverInterface::class);
    $limits = ['title' => 255, 'short_description' => 255, 'description' => 10000];
    $driver->shouldReceive('translateStructured')->once()->with(
        ['title' => 'Product', 'short_description' => 'Short', 'description' => 'Description'],
        'en',
        ['es'],
        ['es' => 'Spanish'],
        $limits,
    )->andReturn(['es' => ['title' => 'Producto']]);
    $manager = Mockery::mock(AiManager::class);
    $manager->shouldReceive('getAiSettings')->once()->andReturn(['enableAiDescription' => true]);
    $manager->shouldReceive('driver')->once()->andReturn($driver);

    $service = new AiTranslationService($manager, app(CatalogLanguageService::class));
    expect($service->translateEnglishFields([
        'title' => 'Product', 'short_description' => 'Short', 'description' => 'Description',
    ], $limits, ['es']))->toBe(['es' => ['title' => 'Producto']]);
});

it('round-trips literal legacy product text through the restart-safe migration', function (): void {
    $product = Product::factory()->create();
    $migration = require database_path('migrations/2026_10_07_084529_convert_product_text_columns_to_json.php');
    $migration->down();

    DB::table('products')->where('id', $product->id)->update([
        'title' => '{"looks":"json"}',
        'short_description' => 'Quoted "short" café',
        'description' => "Unicode وصف\nsecond line",
    ]);

    $migration->up();
    $row = DB::table('products')->where('id', $product->id)->first();

    expect(json_decode($row->title, true, 512, JSON_THROW_ON_ERROR))->toBe(['en' => '{"looks":"json"}'])
        ->and(json_decode($row->short_description, true, 512, JSON_THROW_ON_ERROR))->toBe(['en' => 'Quoted "short" café'])
        ->and(json_decode($row->description, true, 512, JSON_THROW_ON_ERROR))->toBe(['en' => "Unicode وصف\nsecond line"]);
});
