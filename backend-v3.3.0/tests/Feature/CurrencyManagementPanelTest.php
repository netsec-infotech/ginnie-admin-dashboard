<?php

use App\Enums\DefaultSystemRolesEnum;
use App\Enums\GuardNameEnum;
use App\Models\Country;
use App\Models\CurrencyMetadata;
use App\Models\DeliveryZone;
use App\Models\Setting;
use App\Models\Store;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

beforeEach(function () {
    Setting::create([
        'variable' => 'system',
        'value' => json_encode([
            'currency' => 'USD',
            'currencySymbol' => '$',
            'countryCode' => 'US',
        ]),
    ]);

    CurrencyMetadata::create([
        'code' => 'USD',
        'name' => 'US Dollar',
        'symbol' => '$',
        'decimal_places' => 2,
        'minor_unit_factor' => 100,
        'is_enabled' => true,
    ]);

    CurrencyMetadata::create([
        'code' => 'AED',
        'name' => 'UAE Dirham',
        'symbol' => 'د.إ',
        'decimal_places' => 2,
        'minor_unit_factor' => 100,
        'is_enabled' => true,
    ]);
});

function phaseSevenAdmin(): User
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

it('renders the read-only country management panel with zone currency warnings', function () {
    $this->withoutMiddleware();

    $zone = DeliveryZone::factory()->create(['name' => 'Border Zone']);
    $usdStore = Store::factory()->create(['currency_code' => 'USD', 'country_code' => 'US']);
    $aedStore = Store::factory()->create(['currency_code' => 'AED', 'country_code' => 'AE']);
    $zone->stores()->sync([$usdStore->id, $aedStore->id]);

    $response = $this->actingAs(phaseSevenAdmin(), GuardNameEnum::ADMIN->value)
        ->get(route('admin.country-management.index'));

    $response->assertOk()
        ->assertSee('Country Management')
        ->assertSee('Countries Used')
        ->assertSee('Gateway Readiness')
        ->assertSee('country-management-zones')
        ->assertDontSee('Run Audit')
        ->assertDontSee('Execute');

    $datatable = $this->actingAs(phaseSevenAdmin(), GuardNameEnum::ADMIN->value)
        ->getJson(route('admin.country-management.zones.datatable', [
            'draw' => 1,
            'start' => 0,
            'length' => 10,
        ]));

    $datatable->assertOk()
        ->assertJsonPath('recordsTotal', 1);

    expect($datatable->json('data.0.name'))->toContain('Border Zone')
        ->and($datatable->json('data.0.review'))->toContain('Multiple store currencies');
});

it('does not expose currency management actions from the country management route', function () {
    $this->withoutMiddleware();

    $store = Store::factory()->create(['currency_code' => 'USD']);
    $zone = DeliveryZone::factory()->create(['name' => 'Read Only Zone']);
    $zone->stores()->sync([$store->id]);

    $response = $this->actingAs(phaseSevenAdmin(), GuardNameEnum::ADMIN->value)
        ->get(route('admin.country-management.index'));

    $response->assertOk()
        ->assertSee('country-management-stores')
        ->assertDontSee('currency-toggle-form')
        ->assertDontSee('store-currency-form')
        ->assertDontSee('Change Currency');
});

it('shows country and zone operational usage in currency usage review', function () {
    $this->withoutMiddleware();

    $country = Country::create([
        'name' => 'United Arab Emirates',
        'iso3' => 'ARE',
        'iso2' => 'AE',
        'numeric_code' => '784',
        'phonecode' => '971',
        'currency' => 'AED',
        'currency_name' => 'UAE Dirham',
        'currency_symbol' => 'د.إ',
    ]);

    DeliveryZone::factory()->create([
        'name' => 'Dubai Zone',
        'country_id' => $country->id,
        'country_code' => 'AE',
        'currency_code' => 'AED',
        'currency_symbol' => 'د.إ',
        'country_detection_status' => 'confirmed',
    ]);

    $response = $this->actingAs(phaseSevenAdmin(), GuardNameEnum::ADMIN->value)
        ->get(route('admin.country-management.index'));

    $response->assertOk()
        ->assertSee('Currency Usage')
        ->assertSee('country-management-currencies')
        ->assertDontSee('US Dollar');

    $datatable = $this->actingAs(phaseSevenAdmin(), GuardNameEnum::ADMIN->value)
        ->getJson(route('admin.country-management.currencies.datatable', [
            'draw' => 1,
            'start' => 0,
            'length' => 10,
        ]));

    expect($datatable->json('data.0.usage_text'))->toContain('1 countries / 1 zones')
        ->and($datatable->json('data.0.usage_text'))->toContain('United Arab Emirates')
        ->and($datatable->json('data.0.usage_text'))->toContain('Dubai Zone');
});

it('shows zone currency usage even when currency metadata is missing', function () {
    $this->withoutMiddleware();

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

    DeliveryZone::factory()->create([
        'name' => 'Bhuj',
        'country_id' => $country->id,
        'country_code' => 'IN',
        'currency_code' => 'INR',
        'currency_symbol' => '₹',
        'country_detection_status' => 'confirmed',
    ]);

    $response = $this->actingAs(phaseSevenAdmin(), GuardNameEnum::ADMIN->value)
        ->get(route('admin.country-management.index'));

    $response->assertOk()
        ->assertSee('Currency Usage');

    $datatable = $this->actingAs(phaseSevenAdmin(), GuardNameEnum::ADMIN->value)
        ->getJson(route('admin.country-management.currencies.datatable', [
            'draw' => 1,
            'start' => 0,
            'length' => 10,
        ]));

    $row = collect($datatable->json('data'))->firstWhere('code', '<strong>INR</strong>');

    expect($row['usage_text'])->toContain('India')
        ->and($row['usage_text'])->toContain('Bhuj')
        ->and($row['status'])->toContain('Used by zones');
});

it('loads country management datatables with search and pagination', function () {
    $this->withoutMiddleware();

    DeliveryZone::factory()->create(['name' => 'Dubai Zone', 'currency_code' => 'AED', 'currency_symbol' => 'د.إ']);
    DeliveryZone::factory()->create(['name' => 'New York Zone', 'currency_code' => 'USD', 'currency_symbol' => '$']);

    $response = $this->actingAs(phaseSevenAdmin(), GuardNameEnum::ADMIN->value)
        ->getJson(route('admin.country-management.zones.datatable', [
            'draw' => 7,
            'start' => 0,
            'length' => 1,
            'search' => ['value' => 'Dubai'],
        ]));

    $response->assertOk()
        ->assertJsonPath('draw', 7)
        ->assertJsonPath('recordsTotal', 2)
        ->assertJsonPath('recordsFiltered', 1);

    expect($response->json('data'))->toHaveCount(1)
        ->and($response->json('data.0.name'))->toContain('Dubai Zone');
});

it('shows gateway readiness for active zone currencies', function () {
    $this->withoutMiddleware();

    Setting::query()->updateOrCreate(['variable' => 'payment'], ['value' => json_encode([
        'razorpayPayment' => true,
        'razorpayInternationalPayments' => false,
        'razorpayAllowedCurrencyCodes' => [],
    ])]);

    DeliveryZone::factory()->create(['name' => 'Dubai Zone', 'currency_code' => 'AED', 'currency_symbol' => 'د.إ']);

    $response = $this->actingAs(phaseSevenAdmin(), GuardNameEnum::ADMIN->value)
        ->getJson(route('admin.country-management.gateway-readiness.datatable', [
            'draw' => 1,
            'start' => 0,
            'length' => 10,
        ]));

    $response->assertOk();

    expect($response->json('data.0.currency_code'))->toContain('AED')
        ->and($response->json('data.0.warnings'))->toContain('No online gateway supports this zone currency')
        ->and($response->json('data.0.warnings'))->toContain('Razorpay international currencies are not configured');
});
