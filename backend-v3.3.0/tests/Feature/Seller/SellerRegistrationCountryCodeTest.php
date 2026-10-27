<?php

namespace Tests\Feature\Seller;

use App\Enums\Seller\SellerVerificationStatusEnum;
use App\Enums\Seller\SellerVisibilityStatusEnum;
use App\Models\Country;
use App\Models\Seller;
use App\Models\User;
use App\Services\SellerService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class SellerRegistrationCountryCodeTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');
    }

    public function test_seller_registration_populates_country_code_on_user_and_seller(): void
    {
        $sellerService = app(SellerService::class);

        $payload = [
            'name' => 'John Seller',
            'email' => 'johnseller@example.com',
            'mobile' => '9876543210',
            'password' => 'secret123',
            'country_code' => '+91',
            'address' => '123 Market Street',
            'city' => 'Mumbai',
            'state' => 'Maharashtra',
            'landmark' => 'Near Gateway',
            'zipcode' => '400001',
            'country' => 'India',
            'verification_status' => SellerVerificationStatusEnum::NotApproved(),
            'visibility_status' => SellerVisibilityStatusEnum::Draft(),
        ];

        $files = [
            'business_license' => UploadedFile::fake()->image('license.jpg'),
            'articles_of_incorporation' => UploadedFile::fake()->image('articles.jpg'),
            'national_identity_card' => UploadedFile::fake()->image('nic.jpg'),
            'authorized_signature' => UploadedFile::fake()->image('signature.jpg'),
        ];

        $seller = $sellerService->createSeller($payload, $files);

        $this->assertNotNull($seller);
        $this->assertEquals('+91', $seller->country_code);

        $user = User::where('email', 'johnseller@example.com')->first();
        $this->assertNotNull($user);
        $this->assertEquals('+91', $user->country_code);
        $this->assertEquals('9876543210', $user->mobile);
    }

    public function test_seller_registration_falls_back_to_country_phonecode_when_country_code_is_empty(): void
    {
        Country::create([
            'name' => 'India',
            'iso3' => 'IND',
            'iso2' => 'IN',
            'phonecode' => '+91',
        ]);

        $sellerService = app(SellerService::class);

        $payload = [
            'name' => 'Jane Seller',
            'email' => 'janeseller@example.com',
            'mobile' => '9876543211',
            'password' => 'secret123',
            'address' => '456 Commercial Road',
            'city' => 'Delhi',
            'state' => 'Delhi',
            'landmark' => 'Connaught Place',
            'zipcode' => '110001',
            'country' => 'India',
            'verification_status' => SellerVerificationStatusEnum::NotApproved(),
            'visibility_status' => SellerVisibilityStatusEnum::Draft(),
        ];

        $files = [
            'business_license' => UploadedFile::fake()->image('license.jpg'),
            'articles_of_incorporation' => UploadedFile::fake()->image('articles.jpg'),
            'national_identity_card' => UploadedFile::fake()->image('nic.jpg'),
            'authorized_signature' => UploadedFile::fake()->image('signature.jpg'),
        ];

        $seller = $sellerService->createSeller($payload, $files);

        $this->assertNotNull($seller);
        $this->assertEquals('+91', $seller->country_code);

        $user = User::where('email', 'janeseller@example.com')->first();
        $this->assertNotNull($user);
        $this->assertEquals('+91', $user->country_code);
    }
}
