<?php

use App\Enums\LanguageSurfaceEnum;
use App\Http\Middleware\EnsureSubscriptionFeatureSelected;
use App\Http\Middleware\EnsureSystemVendorTypeSelected;
use App\Http\Middleware\VerifyLicense;
use App\Models\Language;
use App\Models\User;
use App\Services\ClientLanguageService;
use App\Services\LanguagePackSyncService;
use App\Services\LanguageService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Permission;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->originalResources = resource_path();
    $this->syncResources = sys_get_temp_dir().'/hyperlocal-language-sync-'.bin2hex(random_bytes(8));
    File::makeDirectory($this->syncResources);
    config(['languages.packs_path' => $this->syncResources.'/language-packs']);
    $source = ['title' => 'Title', 'Title' => 'Case distinct', 'welcome' => 'Hello :name', 'nested.message' => 'Message', '@title.description' => 'Metadata'];
    $panel = Mockery::mock(LanguageService::class)->makePartial();
    $panel->shouldReceive('source')->andReturn($source);
    $this->app->instance(LanguageService::class, $panel);
    $clients = Mockery::mock(ClientLanguageService::class)->makePartial();
    $clients->shouldReceive('source')->andReturn($source);
    $this->app->instance(ClientLanguageService::class, $clients);
    $this->withoutMiddleware([VerifyLicense::class, EnsureSystemVendorTypeSelected::class, EnsureSubscriptionFeatureSelected::class]);
});

afterEach(function (): void {
    File::deleteDirectory($this->syncResources);
});

function syncPackFixture(string $surface, string $code, mixed $pack): Language
{
    File::ensureDirectoryExists(config('languages.packs_path').'/'.$surface);
    File::put(config('languages.packs_path')."/{$surface}/{$code}.json", is_string($pack) ? $pack : json_encode($pack, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR));

    return Language::updateOrCreate(['surface' => $surface, 'code' => $code], ['name' => $code, 'native_name' => $code, 'direction' => 'ltr', 'is_active' => false]);
}

function syncPackAdmin(bool $editable = true): User
{
    $user = User::factory()->create(['access_panel' => 'admin']);
    Permission::findOrCreate('language.edit', 'admin');
    foreach (['language.view', ...($editable ? ['language.edit'] : [])] as $permission) {
        $user->givePermissionTo(Permission::findOrCreate($permission, 'admin'));
    }

    return $user;
}

it('syncs every surface including inactive languages without overwriting rows or settings', function (): void {
    $languages = [];
    foreach (LanguageSurfaceEnum::cases() as $surface) {
        $language = syncPackFixture($surface->value, 'xx', ['labels' => ['title' => 'नमस्ते', 'Title' => 'Different', 'nested' => ['message' => 'Nested'], '@title' => ['description' => 'Metadata'], 'unknown' => 'Ignore', 'welcome' => '']]);
        $language->translations()->create(['key' => 'title', 'value' => 'Custom']);
        $languages[] = [$language, $language->fresh()->getAttributes(), $language->translations()->where('key', 'title')->first()->updated_at->toISOString()];
    }
    $service = app(LanguagePackSyncService::class);
    $report = $service->sync();
    expect($report['totals']['inserted'])->toBe(15)->and($report['totals']['failed'])->toBe(0);
    foreach ($languages as [$language, $settings, $timestamp]) {
        expect($language->fresh()->getAttributes())->toBe($settings)
            ->and($language->translations()->where('key', 'title')->first()->value)->toBe('Custom')
            ->and($language->translations()->where('key', 'title')->first()->updated_at->toISOString())->toBe($timestamp)
            ->and($language->translations()->where('key', 'Title')->value('value'))->toBe('Different')
            ->and($language->translations()->where('key', 'welcome')->exists())->toBeFalse();
    }
    expect($service->sync()['totals']['inserted'])->toBe(0);
});

it('filters surfaces and skips English missing packs and empty packs', function (): void {
    $web = syncPackFixture('web', 'xx', ['title' => 'Web']);
    $panel = syncPackFixture('panel', 'xx', ['title' => 'Panel']);
    syncPackFixture('web', 'en', ['title' => 'Never import English']);
    syncPackFixture('web', 'yy', (object) []);
    $report = app(LanguagePackSyncService::class)->sync('web');
    expect($report['totals']['inserted'])->toBe(1)->and($web->translations()->value('value'))->toBe('Web')
        ->and($panel->translations()->count())->toBe(0)
        ->and(Language::where('code', 'en')->get()->sum(fn ($language) => $language->translations()->count()))->toBe(0);
});

it('rejects invalid packs atomically while processing other languages', function (mixed $pack): void {
    $invalid = syncPackFixture('web', 'xx', $pack);
    syncPackFixture('web', 'yy', ['title' => 'Valid']);
    $report = app(LanguagePackSyncService::class)->sync('web');
    expect($report['totals']['failed'])->toBe(1)->and($report['totals']['inserted'])->toBe(1)->and($invalid->translations()->count())->toBe(0);
})->with([
    'malformed JSON' => ['{broken'], 'list root' => ['["invalid"]'],
    'invalid value' => [['title' => 'Valid first', 'Title' => ['invalid']]],
    'invalid placeholder' => [['title' => 'Valid first', 'welcome' => 'Missing name']],
]);

it('refreshes panel and client caches even when rerun inserts nothing', function (): void {
    foreach (['panel', 'web'] as $surface) {
        $language = syncPackFixture($surface, 'xx', ['title' => 'Fresh']);
        $key = $surface === 'panel' ? "language.labels.{$language->id}" : "client-language-labels.web.{$language->id}";
        $service = app(LanguagePackSyncService::class);
        for ($attempt = 0; $attempt < 2; $attempt++) {
            Cache::forever($key, collect(['title' => 'Stale']));
            $service->syncLanguage(LanguageSurfaceEnum::from($surface), $language);
            expect(Cache::has($key))->toBeFalse();
        }
    }
});

it('rejects concurrent CLI and UI runs', function (): void {
    $lock = Cache::lock(LanguagePackSyncService::LOCK_KEY, 300);
    $lock->get();
    try {
        $this->artisan('languages:sync-packs')->expectsOutputToContain(__('labels.language_sync_busy'))->assertFailed();
        $this->actingAs(syncPackAdmin(), 'admin')->postJson(route('admin.languages.sync-packs'), ['surface' => 'all'])->assertUnprocessable();
    } finally {
        $lock->release();
    }
});

it('reports command success and invalid surface failures', function (): void {
    syncPackFixture('web', 'xx', ['title' => 'Translation']);
    $this->artisan('languages:sync-packs', ['--surface' => 'web'])->expectsOutputToContain('xx')->assertSuccessful();
    $this->artisan('languages:sync-packs', ['--surface' => 'invalid'])->assertFailed();
});

it('validates UI permission and surface and preserves partial results', function (): void {
    $this->actingAs(syncPackAdmin(false), 'admin')->postJson(route('admin.languages.sync-packs'), ['surface' => 'all'])->assertForbidden();
    $this->actingAs(syncPackAdmin(), 'admin')->postJson(route('admin.languages.sync-packs'), ['surface' => 'invalid'])->assertUnprocessable();
    syncPackFixture('web', 'xx', ['title' => 'Valid']);
    syncPackFixture('web', 'yy', '{invalid');
    $this->postJson(route('admin.languages.sync-packs'), ['surface' => 'web'])->assertUnprocessable()
        ->assertJsonPath('data.totals.inserted', 1)->assertJsonPath('data.totals.failed', 1);
    $this->post(route('admin.languages.sync-packs'), ['surface' => 'web'])->assertRedirect(route('admin.languages.index'))
        ->assertSessionHas('language_pack_sync.report.results');
});

it('renders the sync interface only for editors', function (): void {
    $this->actingAs(syncPackAdmin(false), 'admin')->get(route('admin.languages.index'))->assertSuccessful()->assertDontSee('id="language-pack-sync"', false);
    $this->actingAs(syncPackAdmin(), 'admin')->get(route('admin.languages.index'))->assertSuccessful()->assertSee('id="language-pack-sync"', false);
});

it('rejects missing tables and mismatched single-language surfaces', function (): void {
    $language = syncPackFixture('web', 'xx', ['title' => 'Translation']);
    expect(fn () => app(LanguagePackSyncService::class)->syncLanguage(LanguageSurfaceEnum::PANEL, $language))->toThrow(InvalidArgumentException::class);
    Schema::drop('language_translations');
    expect(fn () => app(LanguagePackSyncService::class)->sync())->toThrow(RuntimeException::class, __('labels.language_sync_tables_missing'));
});

it('audits shipped packs without database writes and validates the corrected website packs', function (): void {
    config(['languages.packs_path' => $this->originalResources.'/language-packs']);
    $service = new LanguagePackSyncService(new LanguageService, new ClientLanguageService);
    $before = DB::table('language_translations')->count();
    $audit = collect($service->auditPacks());
    expect($audit)->toHaveCount(40)
        ->and($audit->where('status', 'invalid')->values()->all())->toBe([])
        ->and($audit->where('surface', 'web')->whereIn('locale', ['hi', 'ar'])->pluck('status')->unique()->all())->toBe(['valid'])
        ->and(DB::table('language_translations')->count())->toBe($before);
});

it('protects the sync endpoint from unauthenticated requests', function (): void {
    $this->postJson(route('admin.languages.sync-packs'), ['surface' => 'all'])->assertUnauthorized();
});

it('enforces CSRF for the admin sync form', function (): void {
    $this->actingAs(syncPackAdmin(), 'admin');
    $this->app['env'] = 'production';
    try {
        $this->postJson(route('admin.languages.sync-packs'), ['surface' => 'all'])->assertStatus(419);
    } finally {
        $this->app['env'] = 'testing';
    }
});

it('keeps frontend interpolation checks scoped to web with narrow Arabic exceptions', function (): void {
    $source = ['reels.likesCount_one' => '{{count}} like', 'title' => '{{name}}', 'Title' => 'Other'];
    $clients = Mockery::mock(ClientLanguageService::class)->makePartial();
    $clients->shouldReceive('source')->andReturn($source);
    $this->app->instance(ClientLanguageService::class, $clients);
    $arabic = syncPackFixture('web', 'ar', ['reels' => ['likesCount_one' => 'إعجاب واحد'], 'title' => '{{name}}']);
    $invalid = syncPackFixture('web', 'xx', ['title' => '{{Name}}']);
    $mobile = syncPackFixture('customer_app', 'xx', ['title' => 'No interpolation', 'empty' => (object) []]);
    $service = app(LanguagePackSyncService::class);
    expect($service->syncLanguage(LanguageSurfaceEnum::WEB, $arabic)['failed'])->toBe(0)
        ->and($service->syncLanguage(LanguageSurfaceEnum::WEB, $invalid)['failed'])->toBe(1)
        ->and($service->syncLanguage(LanguageSurfaceEnum::CUSTOMER_APP, $mobile)['inserted'])->toBe(1);
});

it('fails clearly for an unavailable canonical source', function (): void {
    syncPackFixture('web', 'xx', ['title' => 'Translation']);
    $clients = Mockery::mock(ClientLanguageService::class)->makePartial();
    $clients->shouldReceive('source')->andReturn([]);
    $service = new LanguagePackSyncService(app(LanguageService::class), $clients);
    expect($service->sync('web')['totals']['failed'])->toBe(1);
});

it('rejects unsafe locale paths without reading outside the pack directory', function (): void {
    $language = Language::create(['surface' => 'web', 'code' => '../xx', 'name' => 'Unsafe', 'native_name' => 'Unsafe', 'direction' => 'ltr']);
    expect(app(LanguagePackSyncService::class)->syncLanguage(LanguageSurfaceEnum::WEB, $language)['failed'])->toBe(1);
});

it('exposes newly synchronized labels immediately through the existing public API', function (): void {
    $sourcePath = $this->syncResources.'/en.json';
    File::put($sourcePath, '{"title":"English title"}');
    config(['languages.sources.web' => $sourcePath]);
    $language = syncPackFixture('web', 'hi', ['title' => 'नमस्ते']);
    $language->update(['is_active' => true]);
    $clients = new ClientLanguageService;
    $this->app->instance(ClientLanguageService::class, $clients);
    $this->getJson('/api/language-labels?surface=web&locale=hi')->assertSuccessful()->assertJsonPath('data.labels.title', 'English title');
    app(LanguagePackSyncService::class)->sync('web');
    $this->getJson('/api/language-labels?surface=web&locale=hi')->assertSuccessful()->assertJsonPath('data.labels.title', 'नमस्ते');
});

it('seeds the corrected bundled website translations on fresh installation', function (): void {
    foreach (['hi', 'ar'] as $code) {
        $language = Language::where('surface', 'web')->where('code', $code)->firstOrFail();
        expect($language->translations()->where('key', 'pages.categoryProducts.title')->value('value'))->toContain('{{category}}');
    }
    $arabic = Language::where('surface', 'web')->where('code', 'ar')->firstOrFail();
    expect($arabic->translations()->where('key', 'checkout.validation.maxItemsExceeded')->value('value'))->toContain('{{currentItems}}');
});

it('includes the sync feature and bundled catalogs in the release manifest', function (): void {
    $manifest = json_decode(File::get(base_path('extra/update.json')), true, 512, JSON_THROW_ON_ERROR);
    $targets = array_column($manifest['actions'], 'target');
    expect($targets)->toContain('app', 'config/languages.php', 'routes/admin-route.php',
        'resources/lang/en/labels.php', 'resources/language-packs', 'resources/language-sources',
        'resources/views/admin/languages', 'public/assets/js/language-pack-sync.js');
});
