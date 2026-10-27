<?php

use App\Enums\AdminPermissionEnum;
use App\Enums\GuardNameEnum;
use App\Enums\LanguageSurfaceEnum;
use App\Http\Middleware\EnsureSubscriptionFeatureSelected;
use App\Http\Middleware\EnsureSystemVendorTypeSelected;
use App\Http\Middleware\VerifyLicense;
use App\Models\Language;
use App\Models\User;
use App\Services\ClientLanguageService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
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

function mobileLanguage(string $surface, string $code): Language
{
    return Language::query()
        ->where('surface', $surface)
        ->where('code', $code)
        ->firstOrFail();
}

function mobileLanguageAdmin(array $permissions = []): User
{
    $admin = User::factory()->create(['access_panel' => GuardNameEnum::ADMIN->value]);
    $role = Role::findOrCreate('Mobile Language Test Admin', GuardNameEnum::ADMIN->value);

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

it('enables every client language surface with rider app terminology', function () {
    expect(config('languages.enabled_client_surfaces'))->toBe([
        'web',
        'customer_app',
        'seller_app',
        'rider_app',
    ])->and(config('languages.enabled_client_surfaces'))->not->toContain('delivery_boy_app');
});

it('seeds the approved active languages independently for every mobile surface', function (string $surface, array $expectedCodes) {
    $languages = Language::query()
        ->where('surface', $surface)
        ->orderBy('sort_order')
        ->get();

    expect($languages->pluck('code')->all())->toBe($expectedCodes)
        ->and($languages->every(fn (Language $language): bool => $language->is_active))->toBeTrue()
        ->and($languages->where('is_default', true)->pluck('code')->all())->toBe(['en'])
        ->and($languages->firstWhere('code', 'ar')?->direction)->toBe('rtl');
})->with([
    'customer app' => ['customer_app', ['en', 'fr', 'hi', 'ar', 'gu', 'te']],
    'seller app' => ['seller_app', ['en', 'hi', 'ar']],
    'rider app' => ['rider_app', ['en', 'fr', 'hi', 'ar', 'gu', 'te']],
]);

it('imports every canonical supplied mobile translation', function (string $surface, int $sourceCount, array $translatedCodes, int $translationCount) {
    $service = app(ClientLanguageService::class);

    expect($service->source(LanguageSurfaceEnum::from($surface)))->toHaveCount($sourceCount);

    foreach ($translatedCodes as $code) {
        expect(mobileLanguage($surface, $code)->translations()->count())->toBe($translationCount);
    }
})->with([
    'customer app' => ['customer_app', 1080, ['ar', 'fr', 'gu', 'hi', 'te'], 1053],
    'seller app' => ['seller_app', 1304, ['ar', 'hi'], 1256],
    'rider app' => ['rider_app', 915, ['ar', 'fr', 'gu', 'hi', 'te'], 914],
]);

it('preserves customer ARB metadata through English fallback', function () {
    $service = app(ClientLanguageService::class);
    $surface = LanguageSurfaceEnum::CUSTOMER_APP;
    $hindi = mobileLanguage($surface->value, 'hi');
    $source = $service->source($surface);
    $labels = $service->resolvedLabels($surface, $hindi);
    $fallbackKeys = [
        '@returnDeadlineCrossed.placeholders.date.type',
        '@referralShareMessage.description',
        '@referralShareMessage.placeholders.referralCode.type',
        '@referralShareMessage.placeholders.appName.type',
        '@referralShareMessage.placeholders.appLink.type',
        '@deleteWishlistWithName.placeholders.name.type',
    ];

    foreach ($fallbackKeys as $key) {
        expect($hindi->translations()->where('key', $key)->exists())->toBeFalse()
            ->and(data_get($labels, $key))->toBe($source[$key]);
    }

    expect(data_get($labels, '@minimumQuantityRequired.placeholders.minQty'))
        ->toBeInstanceOf(stdClass::class);
});

it('stores seller keys that differ only by casing independently', function () {
    $hindi = mobileLanguage('seller_app', 'hi');

    expect($hindi->translations()->where('key', 'addOnGroup')->exists())->toBeTrue()
        ->and($hindi->translations()->where('key', 'addonGroup')->exists())->toBeTrue()
        ->and($hindi->translations()->whereIn('key', ['addOnGroup', 'addonGroup'])->count())->toBe(2);
});

it('does not overwrite mobile language records or translations when its migration is reapplied', function () {
    $hindi = mobileLanguage('customer_app', 'hi');
    $hindi->update(['name' => 'Custom Hindi']);
    $hindi->translations()->where('key', 'welcome')->update(['value' => 'Custom welcome']);

    $migration = require database_path('migrations/2026_10_01_000002_seed_mobile_app_languages.php');
    $migration->up();

    expect($hindi->refresh()->name)->toBe('Custom Hindi')
        ->and($hindi->translations()->where('key', 'welcome')->value('value'))->toBe('Custom welcome')
        ->and(Language::query()->where('surface', 'customer_app')->where('code', 'hi')->count())->toBe(1);
});

it('returns independent language lists through the shared public API', function (string $surface, int $count) {
    $this->getJson(route('api.languages.index', ['surface' => $surface]))
        ->assertOk()
        ->assertJsonPath('data.default', 'en')
        ->assertJsonCount($count, 'data.languages')
        ->assertJsonFragment(['code' => 'ar', 'direction' => 'rtl']);
})->with([
    'customer app' => ['customer_app', 6],
    'seller app' => ['seller_app', 3],
    'rider app' => ['rider_app', 6],
]);

it('returns localized labels for every mobile app', function (string $surface, string $key, string $expectedText) {
    $this->getJson(route('api.language-labels.index', ['surface' => $surface, 'locale' => 'HI']))
        ->assertOk()
        ->assertJsonPath('data.locale', 'hi')
        ->assertJsonPath('data.direction', 'ltr')
        ->assertJsonPath("data.labels.{$key}", $expectedText);
})->with([
    'customer app' => ['customer_app', 'welcome', 'स्वागत है'],
    'seller app' => ['seller_app', 'accept', 'स्वीकार करें'],
    'rider app' => ['rider_app', 'createAccount', 'खाता बनाएं'],
]);

it('returns newly added english keys for all mobile surfaces', function (string $surface, string $key, string $expectedText) {
    $this->getJson(route('api.language-labels.index', ['surface' => $surface, 'locale' => 'en']))
        ->assertOk()
        ->assertJsonPath('data.locale', 'en')
        ->assertJsonPath('data.direction', 'ltr')
        ->assertJsonPath("data.labels.{$key}", $expectedText);
})->with([
    'customer app shared cart' => ['customer_app', 'cartLinkCopied', 'Link copied'],
    'customer app reels' => ['customer_app', 'reels', 'Reels'],
    'customer app store notification' => ['customer_app', 'unnotifyStoreConfirm', 'Are you sure you want to stop receiving notifications when this store is back online?'],
    'rider app skip' => ['rider_app', 'skip', 'Skip'],
    'seller app return details' => ['seller_app', 'returnsDetails', 'Return Details'],
    'seller app return inspect photo' => ['seller_app', 'returnsAddPhoto', 'Add inspection photo'],
]);

it('falls back to the independent surface default for inactive or invalid locales', function () {
    $service = app(ClientLanguageService::class);
    mobileLanguage('rider_app', 'hi')->update(['is_active' => false]);
    $service->forgetLanguages(LanguageSurfaceEnum::RIDER_APP);

    $this->getJson(route('api.language-labels.index', ['surface' => 'rider_app', 'locale' => 'hi']))
        ->assertOk()
        ->assertJsonPath('data.locale', 'en');

    $this->getJson(route('api.language-labels.index', ['surface' => 'seller_app', 'locale' => 'invalid']))
        ->assertOk()
        ->assertJsonPath('data.locale', 'en');
});

it('rejects the retired delivery boy surface name', function () {
    $this->getJson(route('api.languages.index', ['surface' => 'delivery_boy_app']))
        ->assertUnprocessable()
        ->assertJsonValidationErrors('surface');
});

it('renders every mobile management area and overview link', function (string $surface, string $heading, int $sourceCount) {
    $admin = mobileLanguageAdmin([AdminPermissionEnum::LANGUAGE_VIEW->value]);

    $this->actingAs($admin)
        ->get(route('admin.languages.index'))
        ->assertOk()
        ->assertSee(route('admin.languages.clients.show', $surface));

    $this->actingAs($admin)
        ->get(route('admin.languages.clients.show', $surface))
        ->assertOk()
        ->assertSee($heading)
        ->assertSee("resources/language-sources/{$surface}/en.json")
        ->assertSee((string) $sourceCount);
})->with([
    'customer app' => ['customer_app', 'Customer App Languages', 1080],
    'seller app' => ['seller_app', 'Seller App Languages', 1304],
    'rider app' => ['rider_app', 'Rider App Languages', 915],
]);

it('prevents cross-surface mobile language access', function () {
    $admin = mobileLanguageAdmin([AdminPermissionEnum::LANGUAGE_EDIT->value]);
    $customerHindi = mobileLanguage('customer_app', 'hi');

    $this->actingAs($admin)
        ->get(route('admin.languages.clients.edit', ['seller_app', $customerHindi]))
        ->assertNotFound();
});

it('invalidates only the mutated mobile surface language cache', function () {
    $service = app(ClientLanguageService::class);
    $service->languages(LanguageSurfaceEnum::CUSTOMER_APP);
    $service->languages(LanguageSurfaceEnum::SELLER_APP);

    expect(Cache::has('client-languages.customer_app'))->toBeTrue()
        ->and(Cache::has('client-languages.seller_app'))->toBeTrue();

    $service->forgetLanguages(LanguageSurfaceEnum::CUSTOMER_APP);

    expect(Cache::has('client-languages.customer_app'))->toBeFalse()
        ->and(Cache::has('client-languages.seller_app'))->toBeTrue();
});

it('provides a valid quick add pack for every configured language and surface', function () {
    foreach (LanguageSurfaceEnum::cases() as $surface) {
        foreach (array_keys(config('languages.quick_add')) as $code) {
            $path = resource_path("language-packs/{$surface->value}/{$code}.json");

            expect(is_file($path))->toBeTrue()
                ->and(json_decode((string) file_get_contents($path), true, 512, JSON_THROW_ON_ERROR))->toBeArray();
        }
    }
});

it('shows only uninstalled quick add options and identifies surface pack contents', function () {
    $service = app(ClientLanguageService::class);
    $surface = LanguageSurfaceEnum::CUSTOMER_APP;
    $installedCodes = Language::query()
        ->where('surface', $surface->value)
        ->pluck('code')
        ->all();

    $options = $service->quickAddOptions($surface, $installedCodes);

    expect(array_keys($options))->toBe(['es', 'de', 'zh'])
        ->and(collect($options)->every(fn (array $option): bool => ! $option['has_translations']))->toBeTrue();

    mobileLanguage($surface->value, 'hi')->delete();
    $installedCodes = Language::query()
        ->where('surface', $surface->value)
        ->pluck('code')
        ->all();
    $options = $service->quickAddOptions($surface, $installedCodes);

    expect($options)->toHaveKey('hi')
        ->and($options['hi']['has_translations'])->toBeTrue();
});

it('explains whether each visible quick add option has translations', function () {
    $admin = mobileLanguageAdmin([AdminPermissionEnum::LANGUAGE_VIEW->value]);

    $this->actingAs($admin)
        ->get(route('admin.languages.clients.show', 'customer_app'))
        ->assertOk()
        ->assertSeeText('Empty starter template')
        ->assertDontSeeText('Translation pack included');

    mobileLanguage('customer_app', 'hi')->delete();

    $this->actingAs($admin)
        ->get(route('admin.languages.clients.show', 'customer_app'))
        ->assertOk()
        ->assertSeeText('Translation pack included');
});
