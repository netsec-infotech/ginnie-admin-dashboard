<?php

namespace Tests\Feature\Auth;

use App\Models\Country;
use App\Models\User;
use App\Services\OtpService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CustomerRegistrationCountryCodeTest extends TestCase
{
    use RefreshDatabase;

    public function test_customer_registration_endpoint_saves_country_code(): void
    {
        $payload = [
            'name' => 'Alice Customer',
            'email' => 'alice@example.com',
            'mobile' => '9876543210',
            'password' => 'secret123',
            'password_confirmation' => 'secret123',
            'country_code' => '+91',
            'country' => 'India',
        ];

        $response = $this->postJson(route('register'), $payload);

        $response->assertStatus(200);
        $user = User::where('email', 'alice@example.com')->first();
        $this->assertNotNull($user);
        $this->assertEquals('+91', $user->country_code);
        $this->assertEquals('9876543210', $user->mobile);
    }

    public function test_customer_registration_falls_back_to_country_phonecode_when_country_code_empty(): void
    {
        Country::create([
            'name' => 'India',
            'iso3' => 'IND',
            'iso2' => 'IN',
            'phonecode' => '+91',
        ]);

        $payload = [
            'name' => 'Bob Customer',
            'email' => 'bob@example.com',
            'mobile' => '9876543211',
            'password' => 'secret123',
            'password_confirmation' => 'secret123',
            'country' => 'India',
        ];

        $response = $this->postJson(route('register'), $payload);

        $response->assertStatus(200);
        $user = User::where('email', 'bob@example.com')->first();
        $this->assertNotNull($user);
        $this->assertEquals('+91', $user->country_code);
    }

    public function test_otp_service_resolve_mobile_user_saves_country_code(): void
    {
        $otpService = app(OtpService::class);

        $result = $otpService->resolveMobileUser('9876543212', [
            'name' => 'Charlie Customer',
            'email' => 'charlie@example.com',
            'password' => 'secret123',
            'country_code' => '+1',
            'country' => 'United States',
        ]);

        $this->assertEquals('created', $result['status']);
        $this->assertNotNull($result['user']);
        $this->assertEquals('+1', $result['user']->country_code);
    }
}
