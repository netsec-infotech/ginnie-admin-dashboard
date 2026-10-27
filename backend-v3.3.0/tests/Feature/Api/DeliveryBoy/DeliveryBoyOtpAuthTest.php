<?php

use App\Enums\DefaultSystemRolesEnum;
use App\Enums\DeliveryBoy\DeliveryBoyVerificationStatusEnum;
use App\Enums\GuardNameEnum;
use App\Models\DeliveryBoy;
use App\Models\User;
use App\Services\OtpService;
use App\Services\SettingService;
use App\Services\SmsService;
use App\Services\WalletService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
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

function makeDeliveryBoyTestUser(string $mobile, string $status = 'verified', bool $isBlocked = false): User
{
    $user = User::forceCreate([
        'name' => 'Delivery Boy Test',
        'email' => 'delivery-boy-' . uniqid() . '@example.test',
        'mobile' => $mobile,
        'password' => 'secret-password',
        'access_panel' => GuardNameEnum::DELIVERY_BOY->value,
    ]);

    Role::findOrCreate(DefaultSystemRolesEnum::DELIVERY_BOY->value, GuardNameEnum::DELIVERY_BOY->value);
    $user->assignRole(DefaultSystemRolesEnum::DELIVERY_BOY->value);

    $verificationStatus = $status === 'verified'
        ? DeliveryBoyVerificationStatusEnum::VERIFIED->value
        : DeliveryBoyVerificationStatusEnum::PENDING->value;

    DB::table('delivery_boys')->insert([
        'user_id' => $user->id,
        'status' => 'active',
        'verification_status' => $verificationStatus,
        'is_blocked' => $isBlocked,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    return $user;
}

it('sends OTP to a verified delivery boy', function () {
    makeDeliveryBoyTestUser('9876543210', 'verified', false);

    $this->postJson('/api/delivery-boy/auth/send-otp', [
        'mobile' => '9876543210',
    ])
        ->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonPath('data.mobile', '9876543210');
});

it('rejects sending OTP to an unverified delivery boy', function () {
    makeDeliveryBoyTestUser('9876543211', 'pending', false);

    $this->postJson('/api/delivery-boy/auth/send-otp', [
        'mobile' => '9876543211',
    ])
        ->assertOk()
        ->assertJsonPath('success', false)
        ->assertJsonPath('message', __('labels.account_not_verified'));
});

it('rejects sending OTP to a blocked delivery boy', function () {
    makeDeliveryBoyTestUser('9876543212', 'verified', true);

    $this->postJson('/api/delivery-boy/auth/send-otp', [
        'mobile' => '9876543212',
    ])
        ->assertOk()
        ->assertJsonPath('success', false)
        ->assertJsonPath('message', __('labels.delivery_boy_account_blocked'));
});

it('sends OTP to a new unregistered mobile for registration', function () {
    $res = $this->postJson('/api/delivery-boy/auth/send-otp', [
        'mobile' => '0000000000',
    ]);
    
    expect($res->json('success'))->toBe(true);
});

it('verifies OTP for a new unregistered mobile during registration', function () {
    $res = $this->postJson('/api/delivery-boy/auth/verify-otp', [
        'mobile' => '0000000000',
        'otp' => '123456',
    ]);
    
    expect($res->json('success'))->toBe(true)
        ->and($res->json('status'))->toBe('pending');
});

it('verifies OTP and logs in a verified delivery boy', function () {
    makeDeliveryBoyTestUser('9876543210', 'verified', false);

    $this->postJson('/api/delivery-boy/auth/verify-otp', [
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

it('rejects verifying OTP with invalid code for delivery boy', function () {
    makeDeliveryBoyTestUser('9876543210', 'verified', false);

    $this->postJson('/api/delivery-boy/auth/verify-otp', [
        'mobile' => '9876543210',
        'otp' => '654321',
    ])
        ->assertOk()
        ->assertJsonPath('success', false)
        ->assertJsonPath('message', 'Invalid OTP');
});
