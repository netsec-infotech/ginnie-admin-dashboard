<?php

use App\Contracts\AiDriverInterface;
use App\Enums\DefaultSystemRolesEnum;
use App\Http\Middleware\CheckMaintenanceMode;
use App\Http\Middleware\VerifyLicense;
use App\Models\Language;
use App\Models\Product;
use App\Models\Seller;
use App\Models\User;
use App\Services\Ai\AiManager;
use App\Services\CatalogLanguageService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->withoutMiddleware([VerifyLicense::class, CheckMaintenanceMode::class]);
    Http::preventStrayRequests();
    $this->user = User::factory()->create(['access_panel' => 'seller']);
    $this->seller = Seller::factory()->create(['user_id' => $this->user->id]);
    Role::findOrCreate(DefaultSystemRolesEnum::SELLER->value, 'seller');
    $this->user->assignRole(DefaultSystemRolesEnum::SELLER->value);
    Sanctum::actingAs($this->user);
    Language::updateOrCreate(['surface' => 'web', 'code' => 'es'], [
        'name' => 'Spanish', 'native_name' => 'Español', 'direction' => 'ltr',
        'is_active' => true, 'is_default' => false, 'sort_order' => 2,
    ]);
    Language::where('code', 'fr')->update(['is_active' => false]);
    app(CatalogLanguageService::class)->clearCache();
    $this->driver = Mockery::mock(AiDriverInterface::class);
    $manager = Mockery::mock(AiManager::class);
    $manager->shouldReceive('getAiSettings')->andReturn(['enableAiDescription' => true]);
    $manager->shouldReceive('driver')->andReturn($this->driver);
    $this->app->instance(AiManager::class, $manager);
    $this->payload = ['fields' => ['title' => 'Shirt'], 'source_locale' => 'en', 'target_locales' => ['es']];
});

it('returns the shared translation contract with normalized active targets', function (): void {
    $this->driver->shouldReceive('translateStructured')->once()->with(
        ['title' => 'Shirt'], 'en', ['es'], ['es' => 'Spanish'],
        ['title' => 255, 'short_description' => 255, 'description' => 10000],
    )->andReturn(['es' => ['title' => 'Camisa']]);

    $this->postJson('/api/seller/products/translate', array_replace($this->payload, ['target_locales' => [' ES ']]))
        ->assertSuccessful()
        ->assertJsonPath('success', true)
        ->assertJsonPath('message', __('labels.translated_successfully'))
        ->assertJsonPath('data.translations.es.title', 'Camisa')
        ->assertJsonPath('data.source_locale', 'en')
        ->assertJsonPath('data.target_locales', ['es']);
});

it('derives all active targets when no targets are supplied', function (): void {
    $targets = app(CatalogLanguageService::class)->active()->reject(fn ($language) => $language->code === 'en');
    $this->driver->shouldReceive('translateStructured')->once()->with(
        ['title' => 'Shirt'], 'en', $targets->pluck('code')->all(), $targets->pluck('name', 'code')->all(),
        ['title' => 255, 'short_description' => 255, 'description' => 10000],
    )->andReturn(['es' => ['title' => 'Camisa']]);

    $this->postJson('/api/seller/products/translate', ['fields' => ['title' => 'Shirt']])->assertSuccessful();
});

it('rejects invalid translation payloads before calling the provider', function (array $payload): void {
    $this->driver->shouldNotReceive('translateStructured');
    $this->postJson('/api/seller/products/translate', array_replace($this->payload, $payload))->assertUnprocessable();
})->with([
    'non-English source' => [['source_locale' => 'hi']],
    'inactive target' => [['target_locales' => ['fr']]],
    'unknown target' => [['target_locales' => ['xx']]],
    'English target' => [['target_locales' => ['en']]],
    'duplicate target' => [['target_locales' => ['es', 'ES']]],
    'nested target' => [['target_locales' => [['es']]]],
    'unknown field' => [['fields' => ['other' => 'Text']]],
    'nested field' => [['fields' => ['title' => ['en' => 'Shirt']]]],
    'overlong title' => [['fields' => ['title' => str_repeat('a', 256)]]],
    'overlong description' => [['fields' => ['description' => str_repeat('a', 10001)]]],
    'different module' => [['module' => 'brand']],
]);

it('authorizes update translation against the owned product without saving it', function (): void {
    $product = Product::factory()->create(['seller_id' => $this->seller->id, 'title' => ['en' => 'Original']]);
    $this->driver->shouldReceive('translateStructured')->once()->andReturn(['es' => ['title' => 'Camisa']]);
    $this->postJson('/api/seller/products/translate', [...$this->payload, 'product_id' => $product->id])->assertSuccessful();
    expect($product->refresh()->getTranslations('title'))->toBe(['en' => 'Original']);
});

it('rejects translation of another sellers product', function (): void {
    $product = Product::factory()->create();
    $this->driver->shouldNotReceive('translateStructured');
    $this->postJson('/api/seller/products/translate', [...$this->payload, 'product_id' => $product->id])->assertForbidden();
});

it('requires product permission and a valid seller record', function (bool $hasSeller): void {
    $user = User::factory()->create(['access_panel' => 'seller']);
    if ($hasSeller) {
        Seller::factory()->create(['user_id' => $user->id]);
    }
    Sanctum::actingAs($user);
    $this->driver->shouldNotReceive('translateStructured');
    $this->postJson('/api/seller/products/translate', $this->payload)->assertForbidden();
})->with([true, false]);

it('does not call the driver when AI is disabled', function (): void {
    $manager = Mockery::mock(AiManager::class);
    $manager->shouldReceive('getAiSettings')->andReturn(['enableAiDescription' => false]);
    $manager->shouldNotReceive('driver');
    $this->app->instance(AiManager::class, $manager);
    $this->postJson('/api/seller/products/translate', $this->payload)
        ->assertUnprocessable()->assertJsonPath('success', false);
});

it('returns a controlled failure if the driver throws a type error', function (): void {
    $this->driver->shouldReceive('translateStructured')->once()->andThrow(new TypeError('Invalid provider output'));
    $this->postJson('/api/seller/products/translate', $this->payload)
        ->assertUnprocessable()->assertJsonPath('success', false);
});

it('limits translation requests per authenticated user', function (): void {
    $this->driver->shouldReceive('translateStructured')->times(10)->andReturn(['es' => ['title' => 'Camisa']]);
    for ($attempt = 0; $attempt < 10; $attempt++) {
        $this->postJson('/api/seller/products/translate', $this->payload)->assertSuccessful();
    }
    $this->postJson('/api/seller/products/translate', $this->payload)->assertStatus(429);
});
