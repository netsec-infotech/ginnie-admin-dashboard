<?php

use App\Enums\AdminPermissionEnum;
use App\Enums\GuardNameEnum;
use App\Enums\LanguageSurfaceEnum;
use App\Http\Middleware\EnsureSubscriptionFeatureSelected;
use App\Http\Middleware\EnsureSystemVendorTypeSelected;
use App\Http\Middleware\VerifyLicense;
use App\Models\Language;
use App\Models\LanguageTranslation;
use App\Models\User;
use App\Services\ClientLanguageService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->withoutMiddleware([
        VerifyLicense::class,
        EnsureSystemVendorTypeSelected::class,
        EnsureSubscriptionFeatureSelected::class,
    ]);
});

function websiteLanguage(string $code): Language
{
    return Language::query()
        ->where('surface', LanguageSurfaceEnum::WEB->value)
        ->where('code', $code)
        ->firstOrFail();
}

function websiteLanguageAdmin(array $permissions = []): User
{
    $admin = User::factory()->create(['access_panel' => GuardNameEnum::ADMIN->value]);
    $role = Role::findOrCreate('Website Language Test Admin', GuardNameEnum::ADMIN->value);

    foreach ([
        AdminPermissionEnum::LANGUAGE_VIEW->value,
        AdminPermissionEnum::LANGUAGE_CREATE->value,
        AdminPermissionEnum::LANGUAGE_EDIT->value,
        AdminPermissionEnum::LANGUAGE_DELETE->value,
    ] as $permission) {
        Permission::findOrCreate($permission, GuardNameEnum::ADMIN->value);
    }

    $role->syncPermissions($permissions);
    $admin->assignRole($role);

    return $admin;
}

it('seeds active English Hindi and Arabic website languages with English as default', function () {
    $languages = Language::query()
        ->where('surface', LanguageSurfaceEnum::WEB->value)
        ->orderBy('sort_order')
        ->get();

    expect($languages->pluck('code')->all())->toBe(['en', 'hi', 'ar'])
        ->and($languages->every(fn (Language $language): bool => $language->is_active))->toBeTrue()
        ->and($languages->where('is_default', true)->pluck('code')->all())->toBe(['en'])
        ->and($languages->firstWhere('code', 'ar')?->direction)->toBe('rtl')
        ->and($languages->firstWhere('code', 'hi')?->direction)->toBe('ltr');
});

it('imports supplied website packs against the canonical English catalog', function () {
    $service = app(ClientLanguageService::class);
    $source = $service->source(LanguageSurfaceEnum::WEB);
    $hindi = websiteLanguage('hi');
    $arabic = websiteLanguage('ar');

    expect($source)->toHaveCount(1848)
        ->and($hindi->translations()->count())->toBe(1847)
        ->and($arabic->translations()->count())->toBe(1847)
        ->and($hindi->translations()->where('key', 'saveForLater.start_typing_to_search')->exists())->toBeFalse()
        ->and($arabic->translations()->where('key', 'reels.likesCount_zero')->exists())->toBeFalse()
        ->and($arabic->translations()->where('key', 'stripe_already_paid')->value('value'))->toContain('الدفع');
});

it('does not overwrite existing website records or translations when the seed migration is reapplied', function () {
    $hindi = websiteLanguage('hi');
    $hindi->update(['name' => 'Custom Hindi']);
    $hindi->translations()->where('key', 'stripe_payment_failed')->update(['value' => 'Custom translation']);

    $migration = require database_path('migrations/2026_10_01_000001_seed_website_languages.php');
    $migration->up();

    expect($hindi->refresh()->name)->toBe('Custom Hindi')
        ->and($hindi->translations()->where('key', 'stripe_payment_failed')->value('value'))->toBe('Custom translation')
        ->and(Language::query()->where('surface', 'web')->where('code', 'hi')->count())->toBe(1);
});

it('resolves database translations over English website fallback', function () {
    $service = app(ClientLanguageService::class);
    $hindi = websiteLanguage('hi');
    $labels = Arr::dot($service->resolvedLabels(LanguageSurfaceEnum::WEB, $hindi));

    expect($labels['stripe_already_paid'])->toContain('भुगतान')
        ->and($labels)->toHaveKey('pages.categoryProducts.title');

    $hindi->translations()->where('key', 'stripe_already_paid')->delete();
    $service->forget($hindi);

    expect(data_get($service->resolvedLabels(LanguageSurfaceEnum::WEB, $hindi), 'stripe_already_paid'))
        ->toBe($service->source(LanguageSurfaceEnum::WEB)['stripe_already_paid']);
});

it('lists active website languages through the public API', function () {
    $this->getJson(route('api.languages.index', ['surface' => 'web']))
        ->assertOk()
        ->assertJsonPath('data.default', 'en')
        ->assertJsonCount(3, 'data.languages')
        ->assertJsonFragment([
            'code' => 'ar',
            'name' => 'Arabic',
            'native_name' => 'العربية',
            'direction' => 'rtl',
            'flag_url' => asset('assets/theme/img/flags/sa.svg'),
        ]);
});

it('returns resolved labels and defaults invalid or inactive locales to English', function () {
    $this->getJson(route('api.language-labels.index', ['surface' => 'web', 'locale' => 'AR']))
        ->assertOk()
        ->assertJsonPath('data.locale', 'ar')
        ->assertJsonPath('data.direction', 'rtl')
        ->assertJsonPath('data.labels.stripe_already_paid', websiteLanguage('ar')->translations()->where('key', 'stripe_already_paid')->value('value'));

    websiteLanguage('hi')->update(['is_active' => false]);
    app(ClientLanguageService::class)->forgetLanguages(LanguageSurfaceEnum::WEB);

    $this->getJson(route('api.language-labels.index', ['surface' => 'web', 'locale' => 'hi']))
        ->assertOk()
        ->assertJsonPath('data.locale', 'en')
        ->assertJsonPath('data.direction', 'ltr');

    $this->getJson(route('api.language-labels.index', ['surface' => 'web', 'locale' => 'invalid']))
        ->assertOk()
        ->assertJsonPath('data.locale', 'en');
});

it('keeps the shared language APIs available for enabled mobile surfaces', function (string $surface) {
    $this->getJson(route('api.languages.index', ['surface' => $surface]))
        ->assertOk()
        ->assertJsonPath('data.default', 'en');

    $this->getJson(route('api.language-labels.index', ['surface' => $surface]))
        ->assertOk()
        ->assertJsonPath('data.locale', 'en');
})->with(['customer_app', 'seller_app', 'rider_app']);

it('returns an unavailable response when no active website language exists', function () {
    Language::query()->where('surface', 'web')->update(['is_active' => false]);
    app(ClientLanguageService::class)->forgetLanguages(LanguageSurfaceEnum::WEB);

    $this->getJson(route('api.language-labels.index', ['surface' => 'web', 'locale' => 'en']))
        ->assertNotFound()
        ->assertJsonPath('success', false);
});

it('is bootstrap safe before language tables exist', function () {
    Schema::drop('language_translations');
    Schema::drop('languages');

    expect(app(ClientLanguageService::class)->languages(LanguageSurfaceEnum::WEB))->toBeEmpty();

    $this->getJson(route('api.language-labels.index', ['surface' => 'web']))
        ->assertNotFound();
});

it('renders the website language management area for permitted admins', function () {
    $admin = websiteLanguageAdmin([AdminPermissionEnum::LANGUAGE_VIEW->value]);

    $this->actingAs($admin)
        ->get(route('admin.languages.index'))
        ->assertOk()
        ->assertSee(route('admin.languages.clients.show', 'web'));

    $this->actingAs($admin)
        ->get(route('admin.languages.clients.show', 'web'))
        ->assertOk()
        ->assertSee('languages-table')
        ->assertSee('resources/language-sources/web/en.json')
        ->assertSee('1848');
});

it('filters website languages and paginates translation rows', function () {
    $admin = websiteLanguageAdmin([
        AdminPermissionEnum::LANGUAGE_VIEW->value,
        AdminPermissionEnum::LANGUAGE_EDIT->value,
    ]);
    websiteLanguage('hi')->update(['is_active' => false]);

    $this->actingAs($admin)
        ->getJson(route('admin.languages.clients.datatable', [
            'surface' => 'web',
            'draw' => 1,
            'start' => 0,
            'length' => 10,
            'status' => 'inactive',
        ]))
        ->assertOk()
        ->assertJsonPath('recordsFiltered', 1)
        ->assertJsonCount(1, 'data');

    $this->actingAs($admin)
        ->getJson(route('admin.languages.clients.translations.datatable', [
            'surface' => 'web',
            'language' => websiteLanguage('ar'),
            'draw' => 2,
            'start' => 0,
            'length' => 25,
            'status' => 'translated',
        ]))
        ->assertOk()
        ->assertJsonPath('recordsTotal', 1848)
        ->assertJsonPath('translatedCount', 1847)
        ->assertJsonCount(25, 'data');
});

it('exposes enabled mobile language management surfaces', function (string $surface) {
    $admin = websiteLanguageAdmin([AdminPermissionEnum::LANGUAGE_VIEW->value]);

    $this->actingAs($admin)
        ->get(route('admin.languages.clients.show', $surface))
        ->assertOk()
        ->assertSee("resources/language-sources/{$surface}/en.json");
})->with(['customer_app', 'seller_app', 'rider_app']);

it('enforces website language permissions', function () {
    $admin = websiteLanguageAdmin();

    $this->actingAs($admin)
        ->get(route('admin.languages.clients.show', 'web'))
        ->assertForbidden();

    $this->actingAs($admin)
        ->postJson(route('admin.languages.clients.store', 'web'), [])
        ->assertForbidden();
});

it('creates inactive drafts and can make a website language default', function () {
    $admin = websiteLanguageAdmin([
        AdminPermissionEnum::LANGUAGE_VIEW->value,
        AdminPermissionEnum::LANGUAGE_CREATE->value,
        AdminPermissionEnum::LANGUAGE_EDIT->value,
    ]);

    $this->actingAs($admin)
        ->postJson(route('admin.languages.clients.store', 'web'), [
            'code' => 'fr',
            'name' => 'French',
            'native_name' => 'Français',
            'country_code' => 'fr',
            'direction' => 'ltr',
            'sort_order' => 80,
        ])
        ->assertCreated();

    $french = websiteLanguage('fr');
    expect($french->is_active)->toBeFalse()
        ->and($french->is_default)->toBeFalse()
        ->and($french->country_code)->toBe('fr');

    $this->actingAs($admin)
        ->postJson(route('admin.languages.clients.default', ['web', $french]))
        ->assertOk();

    expect($french->refresh()->is_active)->toBeTrue()
        ->and($french->is_default)->toBeTrue()
        ->and(websiteLanguage('en')->is_default)->toBeFalse();
});

it('protects the default and English website languages', function () {
    $admin = websiteLanguageAdmin([
        AdminPermissionEnum::LANGUAGE_EDIT->value,
        AdminPermissionEnum::LANGUAGE_DELETE->value,
    ]);
    $english = websiteLanguage('en');

    $this->actingAs($admin)
        ->postJson(route('admin.languages.clients.toggle', ['web', $english]))
        ->assertUnprocessable();

    $this->actingAs($admin)
        ->deleteJson(route('admin.languages.clients.delete', ['web', $english]))
        ->assertUnprocessable();

    $this->actingAs($admin)
        ->postJson(route('admin.languages.clients.import', ['web', $english]), [
            'labels_file' => UploadedFile::fake()->createWithContent('labels.json', '{}'),
        ])
        ->assertUnprocessable();
});

it('prevents cross-surface language access', function () {
    $admin = websiteLanguageAdmin([AdminPermissionEnum::LANGUAGE_EDIT->value]);
    $panelEnglish = Language::query()->where('surface', 'panel')->where('code', 'en')->firstOrFail();

    $this->actingAs($admin)
        ->get(route('admin.languages.clients.edit', ['web', $panelEnglish]))
        ->assertNotFound();
});

it('imports only canonical website keys and removes blank translations', function () {
    $admin = websiteLanguageAdmin([AdminPermissionEnum::LANGUAGE_EDIT->value]);
    $hindi = websiteLanguage('hi');
    $sourceValue = app(ClientLanguageService::class)->source(LanguageSurfaceEnum::WEB)['stripe_payment_pending'];

    $this->actingAs($admin)
        ->postJson(route('admin.languages.clients.import', ['web', $hindi]), [
            'labels_file' => UploadedFile::fake()->createWithContent('labels.json', json_encode([
                'labels' => [
                    'stripe_payment_pending' => '',
                    'stripe_payment_failed' => 'भुगतान दोबारा विफल हुआ।',
                    'unknown_key' => 'Ignored',
                ],
            ], JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR)),
        ])
        ->assertOk()
        ->assertJsonPath('data.imported', 1);

    expect($hindi->translations()->where('key', 'stripe_payment_pending')->exists())->toBeFalse()
        ->and($hindi->translations()->where('key', 'stripe_payment_failed')->value('value'))->toBe('भुगतान दोबारा विफल हुआ।')
        ->and($hindi->translations()->where('key', 'unknown_key')->exists())->toBeFalse()
        ->and(data_get(app(ClientLanguageService::class)->resolvedLabels(LanguageSurfaceEnum::WEB, $hindi), 'stripe_payment_pending'))->toBe($sourceValue);
});

it('exports wrapped labels and downloads supplied quick add packs', function () {
    $admin = websiteLanguageAdmin([AdminPermissionEnum::LANGUAGE_VIEW->value]);

    $export = $this->actingAs($admin)
        ->get(route('admin.languages.clients.export', ['web', websiteLanguage('hi')]))
        ->assertOk()
        ->assertHeader('content-type', 'application/json; charset=UTF-8');
    $exported = json_decode($export->streamedContent(), true, 512, JSON_THROW_ON_ERROR);

    expect($exported)->toHaveKey('labels')
        ->and(data_get($exported, 'labels.stripe_already_paid'))->toContain('भुगतान');

    $download = $this->actingAs($admin)
        ->get(route('admin.languages.clients.quick-add.download', ['web', 'ar']))
        ->assertOk();
    $downloaded = json_decode($download->streamedContent(), true, 512, JSON_THROW_ON_ERROR);

    expect(data_get($downloaded, 'labels.stripe_already_paid'))->toContain('الدفع');
});

it('invalidates cached website languages and labels after mutations', function () {
    $service = app(ClientLanguageService::class);
    $hindi = websiteLanguage('hi');
    $service->languages(LanguageSurfaceEnum::WEB);
    $service->translations(LanguageSurfaceEnum::WEB, $hindi);

    expect(Cache::has('client-languages.web'))->toBeTrue()
        ->and(Cache::has("client-language-labels.web.{$hindi->id}"))->toBeTrue();

    $service->saveTranslations(LanguageSurfaceEnum::WEB, $hindi, [[
        'key' => 'stripe_payment_failed',
        'value' => 'भुगतान विफल रहा।',
    ]]);

    expect(Cache::has('client-languages.web'))->toBeFalse()
        ->and(Cache::has("client-language-labels.web.{$hindi->id}"))->toBeFalse();
});

it('cascades website translations when a language is deleted', function () {
    $hindi = websiteLanguage('hi');
    $translationId = $hindi->translations()->value('id');

    $hindi->delete();

    expect(LanguageTranslation::find($translationId))->toBeNull();
});
