<?php

use App\Enums\DefaultSystemRolesEnum;
use App\Enums\GuardNameEnum;
use App\Models\Country;
use App\Models\CurrencyMetadata;
use App\Models\DeliveryZone;
use App\Models\Setting;
use App\Models\Store;
use App\Models\User;
use App\Support\Currency\ZoneCurrencyResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

beforeEach(function () {
    Setting::create([
        'variable' => 'system',
        'value' => json_encode([
            'currency' => 'AED',
            'currencySymbol' => 'د.إ',
            'countryCode' => 'AE',
        ]),
    ]);

    foreach ([['AED', 'UAE Dirham', 'د.إ'], ['USD', 'US Dollar', '$']] as [$code, $name, $symbol]) {
        CurrencyMetadata::create([
            'code' => $code,
            'name' => $name,
            'symbol' => $symbol,
            'decimal_places' => 2,
            'minor_unit_factor' => 100,
            'is_enabled' => true,
        ]);
    }
});

function zoneCurrencyAdmin(): User
{
    $admin = User::factory()->create(['access_panel' => GuardNameEnum::ADMIN->value]);
    Role::findOrCreate(DefaultSystemRolesEnum::SUPER_ADMIN->value, GuardNameEnum::ADMIN->value);
    Permission::findOrCreate('setting.system.view', GuardNameEnum::ADMIN->value);
    Permission::findOrCreate('setting.system.edit', GuardNameEnum::ADMIN->value);
    Permission::findOrCreate('delivery_zone.view', GuardNameEnum::ADMIN->value);
    $admin->assignRole(DefaultSystemRolesEnum::SUPER_ADMIN->value);
    $admin->givePermissionTo(['setting.system.view', 'setting.system.edit', 'delivery_zone.view']);

    return $admin;
}

function zoneCurrencyCountry(): Country
{
    return Country::create([
        'name' => 'United Arab Emirates',
        'iso3' => 'ARE',
        'iso2' => 'AE',
        'numeric_code' => '784',
        'phonecode' => '971',
        'currency' => 'AED',
        'currency_name' => 'UAE Dirham',
        'currency_symbol' => 'د.إ',
    ]);
}

it('adds explicit country and currency context to delivery zones', function () {
    expect(Schema::hasColumn('delivery_zones', 'country_id'))->toBeTrue()
        ->and(Schema::hasColumn('delivery_zones', 'country_code'))->toBeTrue()
        ->and(Schema::hasColumn('delivery_zones', 'currency_code'))->toBeTrue()
        ->and(Schema::hasColumn('delivery_zones', 'currency_symbol'))->toBeTrue()
        ->and(Schema::hasColumn('delivery_zones', 'country_detection_status'))->toBeTrue();
});

it('resolves zone currency from the selected country', function () {
    $country = zoneCurrencyCountry();

    $resolved = app(ZoneCurrencyResolver::class)->resolveForZonePayload([
        'country_id' => $country->id,
        'detected_countries' => json_encode(['AE']),
    ]);

    expect($resolved['country']->id)->toBe($country->id)
        ->and($resolved['country_code'])->toBe('AE')
        ->and($resolved['currency_code'])->toBe('AED')
        ->and($resolved['currency_symbol'])->toBe('د.إ')
        ->and($resolved['country_detection_status'])->toBe('confirmed');
});

it('enables an existing currency when it is selected by zone country', function () {
    CurrencyMetadata::query()->whereKey('AED')->update(['is_enabled' => false]);
    $country = zoneCurrencyCountry();

    app(ZoneCurrencyResolver::class)->resolveForZonePayload([
        'country_id' => $country->id,
    ]);

    expect(CurrencyMetadata::query()->whereKey('AED')->first()->is_enabled)->toBeTrue();
});

it('creates enabled currency metadata when a valid zone country currency is missing', function () {
    $country = Country::create([
        'name' => 'India',
        'iso3' => 'IND',
        'iso2' => 'IN',
        'numeric_code' => '356',
        'phonecode' => '91',
        'currency' => 'INR',
        'currency_name' => 'Indian Rupee',
        'currency_symbol' => '₹',
    ]);

    app(ZoneCurrencyResolver::class)->resolveForZonePayload([
        'country_id' => $country->id,
    ]);

    $metadata = CurrencyMetadata::query()->whereKey('INR')->first();

    expect($metadata)->not->toBeNull()
        ->and($metadata->is_enabled)->toBeTrue()
        ->and($metadata->code)->toBe('INR');
});

it('blocks zone payloads that detect multiple countries', function () {
    app(ZoneCurrencyResolver::class)->resolveForZonePayload([
        'detected_countries' => json_encode(['AE', 'SA']),
    ]);
})->throws(ValidationException::class);

it('blocks a manually selected country that differs from the detected zone country', function () {
    $selectedCountry = zoneCurrencyCountry();
    Country::create([
        'name' => 'India',
        'iso3' => 'IND',
        'iso2' => 'IN',
        'numeric_code' => '356',
        'phonecode' => '91',
        'currency' => 'INR',
        'currency_name' => 'Indian Rupee',
        'currency_symbol' => '₹',
    ]);

    app(ZoneCurrencyResolver::class)->resolveForZonePayload([
        'country_id' => $selectedCountry->id,
        'detected_countries' => json_encode(['IN']),
    ]);
})->throws(ValidationException::class, 'Selected country does not match the country detected from the delivery zone.');

it('uses the detected country when no manual country is submitted', function () {
    $country = zoneCurrencyCountry();

    $resolved = app(ZoneCurrencyResolver::class)->resolveForZonePayload([
        'detected_countries' => json_encode(['AE']),
    ]);

    expect($resolved['country']->is($country))->toBeTrue()
        ->and($resolved['currency_code'])->toBe('AED')
        ->and($resolved['country_detection_status'])->toBe('confirmed');
});

it('copies zone country and currency to store data without changing legacy store country code semantics', function () {
    $country = zoneCurrencyCountry();

    $storeData = app(ZoneCurrencyResolver::class)->applyStoreCurrencyFromZone([
        'country' => 'Seller submitted country',
        'country_code' => 'old',
        'currency_code' => 'USD',
    ], $country, 'AED');

    expect($storeData['country'])->toBe('United Arab Emirates')
        ->and($storeData['country_code'])->toBe('971')
        ->and($storeData['currency_code'])->toBe('AED');
});

it('reports zone/store currency mismatches in the currency audit', function () {
    $country = zoneCurrencyCountry();

    $zone = DeliveryZone::factory()->create([
        'country_id' => $country->id,
        'country_code' => 'AE',
        'currency_code' => 'AED',
        'currency_symbol' => 'د.إ',
        'country_detection_status' => 'confirmed',
    ]);
    $store = Store::factory()->create(['currency_code' => 'USD', 'country_code' => '1']);
    $zone->stores()->sync([$store->id]);

    $this->artisan('hyperlocal:currency-audit --json')
        ->expectsOutputToContain('zone_store_currency_mismatch')
        ->assertExitCode(0);
});

it('shows explicit zone currency and store mismatch in the management panel', function () {
    $this->withoutMiddleware();

    $country = zoneCurrencyCountry();

    $zone = DeliveryZone::factory()->create([
        'name' => 'Dubai Zone',
        'country_id' => $country->id,
        'country_code' => 'AE',
        'currency_code' => 'AED',
        'currency_symbol' => 'د.إ',
        'country_detection_status' => 'confirmed',
    ]);
    $store = Store::factory()->create(['currency_code' => 'USD', 'country_code' => '1']);
    $zone->stores()->sync([$store->id]);

    $this->actingAs(zoneCurrencyAdmin(), GuardNameEnum::ADMIN->value)
        ->get(route('admin.country-management.index'))
        ->assertOk()
        ->assertSee('country-management-zones');

    $response = $this->actingAs(zoneCurrencyAdmin(), GuardNameEnum::ADMIN->value)
        ->getJson(route('admin.country-management.zones.datatable', [
            'draw' => 1,
            'start' => 0,
            'length' => 10,
        ]));

    expect($response->json('data.0.name'))->toContain('Dubai Zone')
        ->and($response->json('data.0.currency_code'))->toContain('AED')
        ->and($response->json('data.0.review'))->toContain('Store/zone mismatch');
});
