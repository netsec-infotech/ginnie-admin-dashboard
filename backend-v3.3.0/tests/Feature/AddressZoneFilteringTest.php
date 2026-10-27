<?php

use App\Models\Address;
use App\Models\DeliveryZone;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('address index returns all addresses regardless of zone and includes availability flags', function () {
    $user = User::factory()->create();

    // Create a delivery zone enclosing (10.0, 10.0) but not (20.0, 20.0)
    $zone = DeliveryZone::factory()->create([
        'boundary_json' => [
            'coordinates' => [
                [
                    ['lat' => 5.0, 'lng' => 5.0],
                    ['lat' => 15.0, 'lng' => 5.0],
                    ['lat' => 15.0, 'lng' => 15.0],
                    ['lat' => 5.0, 'lng' => 15.0],
                ],
            ],
        ],
    ]);

    // Address inside zone
    $addressInZone = Address::create([
        'user_id' => $user->id,
        'address_line1' => 'Inside Zone Street',
        'city' => 'Test City',
        'state' => 'Test State',
        'zipcode' => '12345',
        'mobile' => '1234567890',
        'country' => 'Test Country',
        'country_code' => 'TC',
        'latitude' => 10.0,
        'longitude' => 10.0,
    ]);

    // Address outside zone
    $addressOutsideZone = Address::create([
        'user_id' => $user->id,
        'address_line1' => 'Outside Zone Street',
        'city' => 'Test City',
        'state' => 'Test State',
        'zipcode' => '54321',
        'mobile' => '0987654321',
        'country' => 'Test Country',
        'country_code' => 'TC',
        'latitude' => 20.0,
        'longitude' => 20.0,
    ]);

    // Call index API with zone_id
    $response = $this->actingAs($user, 'sanctum')->getJson('/api/v1/addresses?zone_id=' . $zone->id);

    $response->assertStatus(200);
    $response->assertJsonCount(2, 'data.data');

    $items = collect($response->json('data.data'));

    $itemInZone = $items->firstWhere('id', $addressInZone->id);
    expect($itemInZone['is_deliverable'])->toBeTrue();

    $itemOutsideZone = $items->firstWhere('id', $addressOutsideZone->id);
    expect($itemOutsideZone['is_deliverable'])->toBeFalse();
});
