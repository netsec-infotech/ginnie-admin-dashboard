<?php

namespace Tests\Feature\DeliveryBoy;

use App\Enums\DeliveryBoy\DeliveryBoyVehicleTypeEnum;
use App\Models\Country;
use App\Models\DeliveryBoy;
use App\Models\DeliveryZone;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class DeliveryBoyRegistrationCountryCodeTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');
    }

    public function test_rider_registration_populates_country_code_on_user(): void
    {
        $zone = DeliveryZone::factory()->create();

        $payload = [
            'full_name' => 'Fast Rider',
            'email' => 'rider@example.com',
            'mobile' => '9876543210',
            'password' => 'secret123',
            'password_confirmation' => 'secret123',
            'address' => '456 Express Highway',
            'driver_license_number' => 'DL12345678',
            'vehicle_type' => DeliveryBoyVehicleTypeEnum::BIKE(),
            'delivery_zone_id' => $zone->id,
            'country_code' => '+91',
            'country' => 'India',
            'driver_license' => [UploadedFile::fake()->image('dl.jpg')],
            'vehicle_registration' => [UploadedFile::fake()->image('rc.jpg')],
        ];

        $response = $this->postJson(route('delivery-boy.register'), $payload);

        $response->assertStatus(200);
        $user = User::where('email', 'rider@example.com')->first();
        $this->assertNotNull($user);
        $this->assertEquals('+91', $user->country_code);
        $this->assertEquals('9876543210', $user->mobile);

        $deliveryBoy = DeliveryBoy::where('user_id', $user->id)->first();
        $this->assertNotNull($deliveryBoy);
    }

    public function test_rider_registration_falls_back_to_country_phonecode_when_country_code_empty(): void
    {
        Country::create([
            'name' => 'India',
            'iso3' => 'IND',
            'iso2' => 'IN',
            'phonecode' => '+91',
        ]);

        $zone = DeliveryZone::factory()->create();

        $payload = [
            'full_name' => 'Speedy Rider',
            'email' => 'speedy@example.com',
            'mobile' => '9876543211',
            'password' => 'secret123',
            'password_confirmation' => 'secret123',
            'address' => '789 Quick Ave',
            'driver_license_number' => 'DL87654321',
            'vehicle_type' => DeliveryBoyVehicleTypeEnum::BIKE(),
            'delivery_zone_id' => $zone->id,
            'country' => 'India',
            'driver_license' => [UploadedFile::fake()->image('dl.jpg')],
            'vehicle_registration' => [UploadedFile::fake()->image('rc.jpg')],
        ];

        $response = $this->postJson(route('delivery-boy.register'), $payload);

        $response->assertStatus(200);
        $user = User::where('email', 'speedy@example.com')->first();
        $this->assertNotNull($user);
        $this->assertEquals('+91', $user->country_code);
    }
}
