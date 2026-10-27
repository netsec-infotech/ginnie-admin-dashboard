<?php

use App\Enums\ActiveInactiveStatusEnum;
use App\Enums\DefaultSystemRolesEnum;
use App\Enums\GuardNameEnum;
use App\Helpers\SidebarBadgeHelper;
use App\Http\Middleware\CheckInstallation;
use App\Http\Middleware\VerifyLicense;
use App\Models\Country;
use App\Models\DeliveryZone;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->withoutMiddleware([VerifyLicense::class, CheckInstallation::class]);

    // Clear static cache in SidebarBadgeHelper
    $ref = new ReflectionClass(SidebarBadgeHelper::class);
    $prop = $ref->getProperty('cache');
    $prop->setAccessible(true);
    $prop->setValue(null, []);

    Setting::create([
        'variable' => 'system',
        'value' => json_encode([
            'currency' => 'INR',
            'currencySymbol' => '₹',
            'countryCode' => 'IN',
            'systemVendorType' => 'multiple',
        ]),
    ]);

    Setting::create([
        'variable' => 'subscription',
        'value' => json_encode([
            'enableSubscription' => false,
        ]),
    ]);

    Setting::create([
        'variable' => 'web',
        'value' => json_encode([
            'googleMapKey' => 'fake-google-map-key',
            'defaultLatitude' => 21.1702,
            'defaultLongitude' => 72.8311,
        ]),
    ]);
});

function createDeliveryZoneAdmin(): User
{
    $admin = User::factory()->create(['access_panel' => GuardNameEnum::ADMIN->value]);
    
    $roleWeb = Role::findOrCreate(DefaultSystemRolesEnum::SUPER_ADMIN->value, 'web');
    $roleAdmin = Role::findOrCreate(DefaultSystemRolesEnum::SUPER_ADMIN->value, GuardNameEnum::ADMIN->value);

    Permission::findOrCreate('delivery_zone.view', 'web');
    Permission::findOrCreate('delivery_zone.view', GuardNameEnum::ADMIN->value);
    Permission::findOrCreate('delivery_zone.create', 'web');
    Permission::findOrCreate('delivery_zone.create', GuardNameEnum::ADMIN->value);
    Permission::findOrCreate('delivery_zone.edit', 'web');
    Permission::findOrCreate('delivery_zone.edit', GuardNameEnum::ADMIN->value);
    Permission::findOrCreate('delivery_zone.delete', 'web');
    Permission::findOrCreate('delivery_zone.delete', GuardNameEnum::ADMIN->value);

    $admin->assignRole($roleWeb);
    $admin->assignRole($roleAdmin);

    return $admin;
}

test('sidebar badge helper counts active delivery zones missing country or currency', function () {
    // Active zone with missing country
    DeliveryZone::factory()->create([
        'name' => 'Active Zone Missing Country',
        'status' => ActiveInactiveStatusEnum::ACTIVE(),
        'country_id' => null,
        'currency_code' => null,
    ]);

    // Inactive zone with missing country (should NOT be counted)
    DeliveryZone::factory()->create([
        'name' => 'Inactive Zone Missing Country',
        'status' => ActiveInactiveStatusEnum::INACTIVE(),
        'country_id' => null,
        'currency_code' => null,
    ]);

    // Active zone with country and currency assigned (should NOT be counted)
    $country = Country::create([
        'name' => 'India',
        'iso3' => 'IND',
        'iso2' => 'IN',
        'numeric_code' => '356',
        'phonecode' => '91',
        'currency' => 'INR',
        'currency_symbol' => '₹',
    ]);

    DeliveryZone::factory()->create([
        'name' => 'Configured Active Zone',
        'status' => ActiveInactiveStatusEnum::ACTIVE(),
        'country_id' => $country->id,
        'country_code' => 'IN',
        'currency_code' => 'INR',
        'currency_symbol' => '₹',
    ]);

    $badgeCounts = SidebarBadgeHelper::getBadgeCounts('admin');
    expect($badgeCounts['delivery_zones'])->toBe(1);
});

test('delivery zones index page renders action required badge when active zones need country', function () {
    $admin = createDeliveryZoneAdmin();

    DeliveryZone::factory()->create([
        'name' => 'Zone Needing Setup',
        'status' => ActiveInactiveStatusEnum::ACTIVE(),
        'country_id' => null,
        'currency_code' => null,
    ]);

    $response = $this->actingAs($admin, GuardNameEnum::ADMIN->value)->get(route('admin.delivery-zones.index'));

    $response->assertStatus(200);
    $response->assertSee(__('labels.action_required'));
    $response->assertSee('Needs Country');
    $response->assertSee('countryStatusFilter');
});

test('delivery zones datatable returns country column and warning badge for unassigned zones', function () {
    $admin = createDeliveryZoneAdmin();

    $country = Country::create([
        'name' => 'United States',
        'iso3' => 'USA',
        'iso2' => 'US',
        'numeric_code' => '840',
        'phonecode' => '1',
        'currency' => 'USD',
        'currency_symbol' => '$',
    ]);

    $unassignedZone = DeliveryZone::factory()->create([
        'name' => 'Unassigned Zone Alpha',
        'status' => ActiveInactiveStatusEnum::ACTIVE(),
        'country_id' => null,
        'currency_code' => null,
    ]);

    $assignedZone = DeliveryZone::factory()->create([
        'name' => 'Assigned Zone Beta',
        'status' => ActiveInactiveStatusEnum::ACTIVE(),
        'country_id' => $country->id,
        'country_code' => 'US',
        'currency_code' => 'USD',
        'currency_symbol' => '$',
    ]);

    // Fetch all datatable records
    $response = $this->actingAs($admin, GuardNameEnum::ADMIN->value)->getJson(route('admin.delivery-zones.datatable', [
        'draw' => 1,
        'start' => 0,
        'length' => 10,
    ]));
    $response->assertStatus(200);
    $data = $response->json('data');

    expect(count($data))->toBe(2);

    $unassignedRow = collect($data)->firstWhere('id', $unassignedZone->id);
    expect($unassignedRow['name'])->toContain('Needs Country');
    expect($unassignedRow['country'])->toContain('Needs Country');

    $assignedRow = collect($data)->firstWhere('id', $assignedZone->id);
    expect($assignedRow['name'])->not->toContain('Needs Country');
    expect($assignedRow['country'])->toContain('United States');
    expect($assignedRow['country'])->toContain('USD');
});

test('delivery zones datatable filters by country status correctly', function () {
    $admin = createDeliveryZoneAdmin();

    $country = Country::create([
        'name' => 'Germany',
        'iso3' => 'DEU',
        'iso2' => 'DE',
        'numeric_code' => '276',
        'phonecode' => '49',
        'currency' => 'EUR',
        'currency_symbol' => '€',
    ]);

    $unassignedZone = DeliveryZone::factory()->create([
        'name' => 'Zone Missing Country',
        'status' => ActiveInactiveStatusEnum::ACTIVE(),
        'country_id' => null,
        'currency_code' => null,
    ]);

    $assignedZone = DeliveryZone::factory()->create([
        'name' => 'Zone With Country',
        'status' => ActiveInactiveStatusEnum::ACTIVE(),
        'country_id' => $country->id,
        'country_code' => 'DE',
        'currency_code' => 'EUR',
        'currency_symbol' => '€',
    ]);

    // Filter by needs_country
    $responseNeedsCountry = $this->actingAs($admin, GuardNameEnum::ADMIN->value)->getJson(route('admin.delivery-zones.datatable', [
        'country_status' => 'needs_country',
        'draw' => 1,
        'start' => 0,
        'length' => 10,
    ]));
    $responseNeedsCountry->assertStatus(200);
    $dataNeedsCountry = $responseNeedsCountry->json('data');
    expect(count($dataNeedsCountry))->toBe(1);
    expect($dataNeedsCountry[0]['id'])->toBe($unassignedZone->id);

    // Filter by configured
    $responseConfigured = $this->actingAs($admin, GuardNameEnum::ADMIN->value)->getJson(route('admin.delivery-zones.datatable', [
        'country_status' => 'configured',
        'draw' => 1,
        'start' => 0,
        'length' => 10,
    ]));
    $responseConfigured->assertStatus(200);
    $dataConfigured = $responseConfigured->json('data');
    expect(count($dataConfigured))->toBe(1);
    expect($dataConfigured[0]['id'])->toBe($assignedZone->id);
});
