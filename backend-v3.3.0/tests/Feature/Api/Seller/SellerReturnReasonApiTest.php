<?php

use App\Enums\DefaultSystemRolesEnum;
use App\Enums\GuardNameEnum;
use App\Enums\Seller\SellerVerificationStatusEnum;
use App\Models\Product;
use App\Models\ReturnReason;
use App\Models\Seller;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->withoutMiddleware([\App\Http\Middleware\VerifyLicense::class]);

    $this->user = User::factory()->create(['access_panel' => GuardNameEnum::SELLER->value]);
    $this->seller = Seller::factory()->create([
        'user_id' => $this->user->id,
        'verification_status' => SellerVerificationStatusEnum::Approved(),
    ]);

    Role::findOrCreate(DefaultSystemRolesEnum::SELLER->value, GuardNameEnum::SELLER->value);
    $this->user->assignRole(DefaultSystemRolesEnum::SELLER->value);

    // Refresh user instance from DB to ensure cachedSeller is fresh
    $this->user = User::find($this->user->id);
    Sanctum::actingAs($this->user);
});

test('seller can list their custom return reasons via API', function () {
    ReturnReason::create([
        'seller_id' => $this->seller->id,
        'title' => 'Seller Custom Reason API',
        'proof_required' => true,
        'otp_required' => false,
        'status' => 'active',
    ]);

    $response = $this->getJson(route('seller.api.return_reasons.index'));

    $response->assertStatus(200)
        ->assertJsonPath('success', true)
        ->assertJsonFragment(['title' => 'Seller Custom Reason API']);
});

test('seller can create custom return reason with assigned products via API', function () {
    $product1 = Product::factory()->create(['seller_id' => $this->seller->id]);
    $product2 = Product::factory()->create(['seller_id' => $this->seller->id]);

    $payload = [
        'title' => 'Defective Color Received',
        'proof_required' => true,
        'otp_required' => true,
        'status' => 'active',
        'product_ids' => [$product1->id, $product2->id],
    ];

    $response = $this->postJson(route('seller.api.return_reasons.store'), $payload);

    $response->assertStatus(201)
        ->assertJsonPath('success', true)
        ->assertJsonPath('data.title', 'Defective Color Received')
        ->assertJsonPath('data.proof_required', true)
        ->assertJsonPath('data.otp_required', true);

    $this->assertDatabaseHas('return_reasons', [
        'seller_id' => $this->seller->id,
        'title' => 'Defective Color Received',
    ]);

    $this->assertDatabaseHas('product_return_reasons', [
        'product_id' => $product1->id,
    ]);
});

test('seller cannot assign another seller products via API', function () {
    $otherUser = User::factory()->create();
    $otherSeller = Seller::factory()->create([
        'user_id' => $otherUser->id,
        'verification_status' => SellerVerificationStatusEnum::Approved(),
    ]);
    $otherProduct = Product::factory()->create(['seller_id' => $otherSeller->id]);

    $payload = [
        'title' => 'Unauthorized Product Reason',
        'product_ids' => [$otherProduct->id],
    ];

    $response = $this->postJson(route('seller.api.return_reasons.store'), $payload);

    $response->assertStatus(403)
        ->assertJsonPath('success', false);
});

test('seller can update their custom return reason via API', function () {
    $reason = ReturnReason::create([
        'seller_id' => $this->seller->id,
        'title' => 'Initial Reason Title',
        'proof_required' => false,
        'otp_required' => false,
        'status' => 'active',
    ]);

    $payload = [
        'title' => 'Updated Reason Title',
        'proof_required' => true,
        'otp_required' => true,
        'status' => 'inactive',
    ];

    $response = $this->putJson(route('seller.api.return_reasons.update', $reason->id), $payload);

    $response->assertStatus(200)
        ->assertJsonPath('success', true)
        ->assertJsonPath('data.title', 'Updated Reason Title')
        ->assertJsonPath('data.proof_required', true)
        ->assertJsonPath('data.status', 'inactive');
});

test('seller can delete their custom return reason via API', function () {
    $reason = ReturnReason::create([
        'seller_id' => $this->seller->id,
        'title' => 'Reason To Be Deleted',
    ]);

    $response = $this->deleteJson(route('seller.api.return_reasons.destroy', $reason->id));

    $response->assertStatus(200)
        ->assertJsonPath('success', true);

    $this->assertDatabaseMissing('return_reasons', [
        'id' => $reason->id,
    ]);
});
