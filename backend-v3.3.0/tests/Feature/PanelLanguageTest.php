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
use App\Services\LanguageService;
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

function panelLanguage(string $code): Language
{
    return Language::query()
        ->where('surface', LanguageSurfaceEnum::PANEL->value)
        ->where('code', $code)
        ->firstOrFail();
}

function languageAdmin(array $permissions = []): User
{
    $admin = User::factory()->create(['access_panel' => GuardNameEnum::ADMIN->value]);
    $role = Role::findOrCreate('Language Test Admin', GuardNameEnum::ADMIN->value);

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

it('defines the reserved language surfaces with rider app terminology', function () {
    expect(LanguageSurfaceEnum::cases())->toHaveCount(5)
        ->and(LanguageSurfaceEnum::PANEL->value)->toBe('panel')
        ->and(LanguageSurfaceEnum::RIDER_APP->value)->toBe('rider_app')
        ->and(LanguageSurfaceEnum::clientValues())->toBe([
            'web',
            'customer_app',
            'seller_app',
            'rider_app',
        ]);
});

it('seeds the five active panel languages and only English as default', function () {
    $languages = Language::query()
        ->where('surface', LanguageSurfaceEnum::PANEL->value)
        ->orderBy('sort_order')
        ->get();

    expect($languages->pluck('code')->all())->toBe(['en', 'es', 'fr', 'de', 'zh'])
        ->and($languages->every(fn (Language $language): bool => $language->is_active))->toBeTrue()
        ->and($languages->where('is_default', true)->pluck('code')->all())->toBe(['en']);
});

it('imports every committed non-English panel pack exactly', function (string $code) {
    $payload = json_decode(
        (string) file_get_contents(resource_path("language-packs/panel/{$code}.json")),
        true,
        512,
        JSON_THROW_ON_ERROR,
    );
    $expected = collect(Arr::dot($payload['labels']))
        ->filter(fn (mixed $value): bool => is_string($value) && trim($value) !== '')
        ->all();

    $actual = panelLanguage($code)->translations()->pluck('value', 'key')->all();
    ksort($expected);
    ksort($actual);

    expect($actual)->toBe($expected);
})->with(['es', 'fr', 'de', 'zh']);

it('normalizes locale codes consistently', function () {
    $languages = app(LanguageService::class);

    expect($languages->normalizeCode(' PT_br '))->toBe('pt-BR')
        ->and($languages->normalizeCode('zh_hant_tw'))->toBe('zh-Hant-TW')
        ->and($languages->normalizeCode(''))->toBe('');
});

it('does not reactivate a configured inactive language through hardcoded fallbacks', function () {
    panelLanguage('es')->update(['is_active' => false]);
    app(LanguageService::class)->forgetLanguages();

    expect(app(LanguageService::class)->findActive('es'))->toBeNull();

    $this->withSession(['locale' => 'fr'])
        ->get(route('set.language', 'es'))
        ->assertRedirect()
        ->assertSessionHas('locale', 'fr');
});

it('does not expose hardcoded languages when configured panel rows are all inactive', function () {
    Language::query()->where('surface', LanguageSurfaceEnum::PANEL->value)->update(['is_active' => false]);
    app(LanguageService::class)->forgetLanguages();

    expect(app(LanguageService::class)->active())->toBeEmpty()
        ->and(app(LanguageService::class)->findActive('es'))->toBeNull();
});

it('uses bootstrap languages only when the language table is empty', function () {
    Language::query()->delete();
    app(LanguageService::class)->forgetLanguages();

    expect(app(LanguageService::class)->active()->pluck('code')->all())->toBe(['en', 'es', 'fr', 'de', 'zh']);
});

it('uses database overrides before bundled locale files', function () {
    $spanish = panelLanguage('es');
    $spanish->translations()->updateOrCreate(['key' => 'cancel'], ['value' => 'Database Cancel']);
    app(LanguageService::class)->touch($spanish);
    config()->set('languages.legacy_panel_file_fallback', true);

    app(LanguageService::class)->apply($spanish);

    expect(__('labels.cancel'))->toBe('Database Cancel');
});

it('keeps nested Laravel translation groups compatible through English aliases', function () {
    expect(__('auth.failed'))->toBe('These credentials do not match our records.')
        ->and(__('messages.cart_is_empty'))->toBe('Your cart is empty')
        ->and(__('pagination.next'))->toBe('Next &raquo;')
        ->and(__('passwords.sent'))->toBe('We have emailed your password reset link.')
        ->and(__('validation.required', ['attribute' => 'email']))->toBe('The email field is required.');
});

it('uses bundled locale files when development compatibility is enabled', function () {
    $spanish = panelLanguage('es');
    $spanish->translations()->where('key', 'cancel')->delete();
    $spanish->translations()->where('key', 'messages.cart_is_empty')->delete();
    app(LanguageService::class)->touch($spanish);
    config()->set('languages.legacy_panel_file_fallback', true);

    app(LanguageService::class)->apply($spanish);

    expect(__('labels.cancel'))->toBe('Cancelar')
        ->and(__('messages.cart_is_empty'))->toBe('Su carrito está vacío');
});

it('falls back to canonical English in database-only mode', function () {
    $spanish = panelLanguage('es');
    $spanish->translations()->where('key', 'cancel')->delete();
    $spanish->translations()->where('key', 'messages.cart_is_empty')->delete();
    app(LanguageService::class)->touch($spanish);
    config()->set('languages.legacy_panel_file_fallback', false);

    app(LanguageService::class)->apply($spanish);

    expect(__('labels.cancel'))->toBe('Cancel')
        ->and(__('messages.cart_is_empty'))->toBe('Your cart is empty');
});

it('saves applies and exports seller walkthrough translations through dynamic panel languages', function () {
    $spanish = panelLanguage('es');
    $languages = app(LanguageService::class);
    $languages->saveTranslations($spanish, [
        ['key' => 'seller_tours.seller_dashboard.title', 'value' => 'Panel e informes'],
        ['key' => 'seller_tours.seller_dashboard.stage_0_step_0.description', 'value' => 'Revisa las ventas de tu cuenta.'],
        ['key' => 'seller_tours.close', 'value' => 'Cerrar recorrido'],
        ['key' => 'seller_tours.progress', 'value' => ':current de :total'],
    ]);

    expect($spanish->translations()->where('key', 'seller_tours.close')->value('value'))->toBe('Cerrar recorrido');
    $languages->apply($spanish);
    expect(__('labels.seller_tours.seller_dashboard.title'))->toBe('Panel e informes')
        ->and(__('labels.seller_tours.seller_dashboard.stage_0_step_0.description'))->toBe('Revisa las ventas de tu cuenta.')
        ->and(__('labels.seller_tours.close'))->toBe('Cerrar recorrido')
        ->and(__('labels.seller_tours.progress', ['current' => 1, 'total' => 5]))->toBe('1 de 5')
        ->and(__('labels.seller_tours.seller_dashboard.description'))->toBe('Learn dashboard & reports using the controls available in your seller panel.');

    expect($languages->exportTree($spanish)['seller_tours']['seller_dashboard']['title'])->toBe('Panel e informes');
    expect(fn () => $languages->saveTranslations($spanish, [
        ['key' => 'seller_tours.progress', 'value' => 'Sin marcadores'],
    ]))->toThrow(InvalidArgumentException::class);
});

it('validates placeholders and preserves translations outside the edited page', function () {
    $spanish = panelLanguage('es');
    $languages = app(LanguageService::class);
    $languages->saveTranslations($spanish, [
        ['key' => 'language_pack_imported', 'value' => ':count traducciones importadas.'],
        ['key' => 'cancel', 'value' => 'Cancelar ahora'],
    ]);

    expect(fn () => $languages->saveTranslations($spanish, [
        ['key' => 'language_pack_imported', 'value' => 'Importado.'],
    ]))->toThrow(InvalidArgumentException::class)
        ->and($spanish->translations()->where('key', 'cancel')->value('value'))->toBe('Cancelar ahora');
});

it('invalidates cached translations after mutations', function () {
    $spanish = panelLanguage('es');
    $languages = app(LanguageService::class);
    $spanish->translations()->updateOrCreate(['key' => 'cancel'], ['value' => 'Primero']);
    $languages->touch($spanish);

    expect($languages->translations($spanish)->get('cancel'))->toBe('Primero');

    $spanish->translations()->where('key', 'cancel')->update(['value' => 'Segundo']);
    expect($languages->translations($spanish)->get('cancel'))->toBe('Primero');

    $languages->touch($spanish);
    expect($languages->translations($spanish)->get('cancel'))->toBe('Segundo');
});

it('ignores unknown imports and treats blank values as removals', function () {
    $spanish = panelLanguage('es');
    $languages = app(LanguageService::class);
    $spanish->translations()->updateOrCreate(['key' => 'cancel'], ['value' => 'Cancelar']);

    $count = $languages->import($spanish, [
        'cancel' => '   ',
        'save' => 'Guardar ahora',
        'unknown_key' => 'Ignored',
    ]);

    expect($count)->toBe(1)
        ->and($spanish->translations()->where('key', 'cancel')->exists())->toBeFalse()
        ->and($spanish->translations()->where('key', 'save')->value('value'))->toBe('Guardar ahora')
        ->and($spanish->translations()->where('key', 'unknown_key')->exists())->toBeFalse();
});

it('imports nested Unicode values without flattening loss', function () {
    $chinese = panelLanguage('zh');
    $count = app(LanguageService::class)->import($chinese, [
        'validation' => [
            'required' => ':attribute 字段是必填项。',
        ],
    ]);

    expect($count)->toBe(1)
        ->and($chinese->translations()->where('key', 'validation.required')->value('value'))
        ->toBe(':attribute 字段是必填项。');
});

it('cascades translations when a language is deleted', function () {
    $language = Language::query()->create([
        'surface' => LanguageSurfaceEnum::PANEL->value,
        'code' => 'it',
        'name' => 'Italian',
        'native_name' => 'Italiano',
        'direction' => 'ltr',
        'is_active' => false,
        'is_default' => false,
        'sort_order' => 60,
    ]);
    $translation = $language->translations()->create(['key' => 'cancel', 'value' => 'Annulla']);

    $language->delete();

    expect(LanguageTranslation::query()->whereKey($translation->id)->exists())->toBeFalse();
});

it('enforces language management permissions', function () {
    $viewer = languageAdmin([AdminPermissionEnum::LANGUAGE_VIEW->value]);

    $this->actingAs($viewer, GuardNameEnum::ADMIN->value)
        ->get(route('admin.languages.panel'))
        ->assertOk();

    $this->actingAs($viewer, GuardNameEnum::ADMIN->value)
        ->get(route('admin.languages.create'))
        ->assertForbidden();
});

it('creates non-default languages as inactive drafts and default languages as active', function () {
    $admin = languageAdmin([
        AdminPermissionEnum::LANGUAGE_VIEW->value,
        AdminPermissionEnum::LANGUAGE_CREATE->value,
    ]);

    $this->actingAs($admin, GuardNameEnum::ADMIN->value)
        ->postJson(route('admin.languages.store'), [
            'code' => 'it',
            'name' => 'Italian',
            'native_name' => 'Italiano',
            'country_code' => 'it',
            'direction' => 'ltr',
            'sort_order' => 60,
            'is_default' => false,
        ])
        ->assertCreated();

    expect(panelLanguage('it')->is_active)->toBeFalse()
        ->and(panelLanguage('it')->is_default)->toBeFalse()
        ->and(panelLanguage('it')->country_code)->toBe('it');

    $this->actingAs($admin, GuardNameEnum::ADMIN->value)
        ->postJson(route('admin.languages.store'), [
            'code' => 'pt-BR',
            'name' => 'Brazilian Portuguese',
            'native_name' => 'Português (Brasil)',
            'country_code' => 'br',
            'direction' => 'ltr',
            'sort_order' => 70,
            'is_default' => true,
        ])
        ->assertCreated();

    expect(panelLanguage('pt-BR')->is_active)->toBeTrue()
        ->and(panelLanguage('pt-BR')->is_default)->toBeTrue()
        ->and(panelLanguage('pt-BR')->country_code)->toBe('br')
        ->and(panelLanguage('en')->is_default)->toBeFalse();
});

it('protects English and the current default from deactivation or deletion', function () {
    $admin = languageAdmin([
        AdminPermissionEnum::LANGUAGE_EDIT->value,
        AdminPermissionEnum::LANGUAGE_DELETE->value,
    ]);
    $english = panelLanguage('en');

    $this->actingAs($admin, GuardNameEnum::ADMIN->value)
        ->postJson(route('admin.languages.toggle', $english))
        ->assertUnprocessable();

    $this->actingAs($admin, GuardNameEnum::ADMIN->value)
        ->deleteJson(route('admin.languages.delete', $english))
        ->assertUnprocessable();

    expect($english->fresh()->is_active)->toBeTrue()
        ->and($english->fresh()->exists)->toBeTrue();
});

it('protects a non-English current default from deactivation or deletion', function () {
    $admin = languageAdmin([
        AdminPermissionEnum::LANGUAGE_EDIT->value,
        AdminPermissionEnum::LANGUAGE_DELETE->value,
    ]);
    Language::query()->where('surface', LanguageSurfaceEnum::PANEL->value)->update(['is_default' => false]);
    $german = panelLanguage('de');
    $german->update(['is_default' => true, 'is_active' => true]);

    $this->actingAs($admin, GuardNameEnum::ADMIN->value)
        ->postJson(route('admin.languages.toggle', $german))
        ->assertUnprocessable();

    $this->actingAs($admin, GuardNameEnum::ADMIN->value)
        ->deleteJson(route('admin.languages.delete', $german))
        ->assertUnprocessable();

    expect($german->fresh()->is_active)->toBeTrue()
        ->and($german->fresh()->is_default)->toBeTrue();
});

it('validates JSON imports and placeholder parity through the management endpoint', function () {
    $admin = languageAdmin([AdminPermissionEnum::LANGUAGE_EDIT->value]);
    $spanish = panelLanguage('es');
    $file = UploadedFile::fake()->createWithContent('es.json', json_encode([
        'labels' => [
            'language_pack_imported' => 'Importado sin marcador.',
        ],
    ], JSON_THROW_ON_ERROR));

    $this->actingAs($admin, GuardNameEnum::ADMIN->value)
        ->postJson(route('admin.languages.import', $spanish), ['labels_file' => $file])
        ->assertUnprocessable();
});

it('exports every canonical source key', function () {
    $admin = languageAdmin([AdminPermissionEnum::LANGUAGE_VIEW->value]);
    $spanish = panelLanguage('es');

    $response = $this->actingAs($admin, GuardNameEnum::ADMIN->value)
        ->get(route('admin.languages.export', $spanish))
        ->assertOk();
    $payload = json_decode($response->streamedContent(), true, 512, JSON_THROW_ON_ERROR);

    expect(Arr::dot($payload['labels']))->toHaveCount(count(app(LanguageService::class)->source()));
});

it('renders RTL direction and styles for an active panel language', function () {
    $admin = languageAdmin([AdminPermissionEnum::LANGUAGE_VIEW->value]);
    Language::query()->create([
        'surface' => LanguageSurfaceEnum::PANEL->value,
        'code' => 'ar',
        'name' => 'Arabic',
        'native_name' => 'العربية',
        'direction' => 'rtl',
        'is_active' => true,
        'is_default' => false,
        'sort_order' => 60,
    ]);
    app(LanguageService::class)->forgetLanguages();

    $this->actingAs($admin, GuardNameEnum::ADMIN->value)
        ->withSession(['locale' => 'ar'])
        ->get(route('admin.languages.index'))
        ->assertOk()
        ->assertSee('<html lang="ar" dir="rtl">', false)
        ->assertSee('tabler.rtl', false);
});

it('renders the database language selector on admin and seller guest layouts', function () {
    $this->get(route('admin.login'))
        ->assertOk()
        ->assertSee('Español')
        ->assertSee(route('set.language', 'es'));

    $this->get(route('seller.login'))
        ->assertOk()
        ->assertSee('Français')
        ->assertSee(route('set.language', 'fr'));
});

it('operates before language migrations have run', function () {
    Cache::flush();
    Schema::dropIfExists('language_translations');
    Schema::dropIfExists('languages');
    app(LanguageService::class)->forgetLanguages();

    $language = app(LanguageService::class)->resolveForRequest(request());

    expect($language->code)->toBe('en')
        ->and($language->exists)->toBeFalse();
});
