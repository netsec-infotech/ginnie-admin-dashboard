<?php

use App\Enums\DefaultSystemRolesEnum;
use App\Enums\GuardNameEnum;
use App\Enums\Seller\SellerVerificationStatusEnum;
use App\Models\Seller;
use App\Models\User;
use App\Services\OtpService;
use App\Services\SettingService;
use App\Services\SmsService;
use App\Services\WalletService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->app->bind(OtpService::class, function ($app) {
        return new class($app->make(SmsService::class), $app->make(SettingService::class), $app->make(WalletService::class)) extends OtpService
        {
            public function sendOtp(string $mobile): array
            {
                return [
                    'success' => true,
                    'message' => 'OTP sent successfully',
                    'expires_in' => 600,
                ];
            }

            public function verifyOtp(string $mobile, string $otpCode): array
            {
                if ($otpCode === '123456') {
                    return [
                        'success' => true,
                        'message' => 'labels.verified_successfully',
                    ];
                }

                return [
                    'success' => false,
                    'message' => 'Invalid OTP',
                ];
            }
        };
    });
});

function makeSellerTestUser(string $mobile, string $status = 'approved'): User
{
    $user = User::forceCreate([
        'name' => 'Seller Test',
        'email' => 'seller-' . uniqid() . '@example.test',
        'mobile' => $mobile,
        'password' => 'secret-password',
        'access_panel' => GuardNameEnum::SELLER->value,
    ]);

    Role::findOrCreate(DefaultSystemRolesEnum::SELLER->value, GuardNameEnum::SELLER->value);
    $user->assignRole(DefaultSystemRolesEnum::SELLER->value);

    $verificationStatus = $status === 'approved'
        ? SellerVerificationStatusEnum::Approved->value
        : SellerVerificationStatusEnum::NotApproved->value;

    Seller::factory()->create([
        'user_id' => $user->id,
        'verification_status' => $verificationStatus,
    ]);

    return $user;
}

it('sends OTP to an approved seller', function () {
    makeSellerTestUser('9876543210', 'approved');

    $this->postJson('/api/seller/auth/send-otp', [
        'mobile' => '9876543210',
    ])
        ->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonPath('data.mobile', '9876543210');
});

it('rejects sending OTP to an unapproved seller', function () {
    makeSellerTestUser('9876543211', 'not_approved');

    $this->postJson('/api/seller/auth/send-otp', [
        'mobile' => '9876543211',
    ])
        ->assertStatus(403)
        ->assertJsonPath('success', false);
});

it('rejects sending OTP to an unregistered mobile since seller OTP is for login only', function () {
    $this->postJson('/api/seller/auth/send-otp', [
        'mobile' => '0000000000',
    ])
        ->assertStatus(404)
        ->assertJsonPath('success', false);
});

it('rejects verifying OTP for an unregistered mobile since seller OTP is for login only', function () {
    $this->postJson('/api/seller/auth/verify-otp', [
        'mobile' => '0000000000',
        'otp' => '123456',
    ])
        ->assertStatus(404)
        ->assertJsonPath('success', false);
});

it('verifies OTP and logs in an approved seller', function () {
    makeSellerTestUser('9876543210', 'approved');

    $this->postJson('/api/seller/auth/verify-otp', [
        'mobile' => '9876543210',
        'otp' => '123456',
    ])
        ->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonStructure([
            'success',
            'message',
            'access_token',
            'token_type',
            'data',
            'assigned_permissions',
        ]);
});

it('rejects verifying OTP with invalid code for seller', function () {
    makeSellerTestUser('9876543210', 'approved');

    $this->postJson('/api/seller/auth/verify-otp', [
        'mobile' => '9876543210',
        'otp' => '654321',
    ])
        ->assertOk()
        ->assertJsonPath('success', false)
        ->assertJsonPath('message', 'Invalid OTP');
});
