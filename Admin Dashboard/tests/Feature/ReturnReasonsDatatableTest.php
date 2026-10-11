<?php

use App\Enums\DefaultSystemRolesEnum;
use App\Enums\GuardNameEnum;
use App\Enums\Seller\SellerVerificationStatusEnum;
use App\Models\ReturnReason;
use App\Models\ReturnRejectionReason;
use App\Models\Seller;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->withoutMiddleware();
});

test('admin return reasons datatable returns json response', function () {
    $admin = User::factory()->create(['access_panel' => GuardNameEnum::ADMIN->value]);
    Role::findOrCreate(DefaultSystemRolesEnum::SUPER_ADMIN->value, GuardNameEnum::ADMIN->value);
    $admin->assignRole(DefaultSystemRolesEnum::SUPER_ADMIN->value);

    ReturnReason::create([
        'seller_id' => null,
        'title' => 'Datatable Global Reason',
        'proof_required' => true,
        'otp_required' => false,
        'status' => 'active',
    ]);

    $response = $this->actingAs($admin, GuardNameEnum::ADMIN->value)
        ->getJson(route('admin.return-reasons.datatable'));

    $response->assertStatus(200)
        ->assertJsonPath('recordsTotal', 1)
        ->assertJsonFragment(['title' => 'Datatable Global Reason']);
});

test('admin return rejection reasons datatable returns json response', function () {
    $admin = User::factory()->create(['access_panel' => GuardNameEnum::ADMIN->value]);
    Role::findOrCreate(DefaultSystemRolesEnum::SUPER_ADMIN->value, GuardNameEnum::ADMIN->value);
    $admin->assignRole(DefaultSystemRolesEnum::SUPER_ADMIN->value);

    ReturnRejectionReason::create([
        'title' => 'Datatable Rejection Reason',
        'comment_required' => true,
        'status' => 'active',
    ]);

    $response = $this->actingAs($admin, GuardNameEnum::ADMIN->value)
        ->getJson(route('admin.return-rejection-reasons.datatable'));

    $response->assertStatus(200)
        ->assertJsonPath('recordsTotal', 1)
        ->assertJsonFragment(['title' => 'Datatable Rejection Reason']);
});

test('seller return reasons datatable returns json response', function () {
    $user = User::factory()->create(['access_panel' => GuardNameEnum::SELLER->value]);
    $seller = Seller::factory()->create([
        'user_id' => $user->id,
        'verification_status' => SellerVerificationStatusEnum::Approved(),
    ]);
    Role::findOrCreate(DefaultSystemRolesEnum::SELLER->value, GuardNameEnum::SELLER->value);
    $user->assignRole(DefaultSystemRolesEnum::SELLER->value);

    ReturnReason::create([
        'seller_id' => $seller->id,
        'title' => 'Seller Datatable Reason',
        'proof_required' => false,
        'otp_required' => true,
        'status' => 'active',
    ]);

    $user = User::find($user->id);

    $response = $this->actingAs($user, GuardNameEnum::SELLER->value)
        ->getJson(route('seller.return-reasons.datatable'));

    $response->assertStatus(200)
        ->assertJsonPath('recordsTotal', 1)
        ->assertJsonFragment(['title' => 'Seller Datatable Reason']);
});
