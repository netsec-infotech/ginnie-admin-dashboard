<?php

use App\Enums\DefaultSystemRolesEnum;
use App\Enums\GuardNameEnum;
use App\Enums\Order\OrderStatusEnum;
use App\Enums\SellerPermissionEnum;
use App\Enums\Subscription\SubscriptionPlanKeyEnum;
use App\Http\Middleware\VerifyLicense;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Seller;
use App\Models\SellerSubscription;
use App\Models\SellerSubscriptionUsage;
use App\Models\SellerUser;
use App\Models\Setting;
use App\Models\Store;
use App\Models\SubscriptionPlan;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Laravel\Sanctum\PersonalAccessToken;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->withoutMiddleware(VerifyLicense::class);

    Setting::forceCreate([
        'variable' => 'system',
        'value' => json_encode(['systemVendorType' => 'multiple', 'demoMode' => false]),
    ]);

    Setting::forceCreate([
        'variable' => 'subscription',
        'value' => json_encode(['enableSubscription' => true]),
    ]);
});

/** @return array{User, Seller} */
function makeAccountDeletionSeller(): array
{
    $owner = User::factory()->create(['access_panel' => GuardNameEnum::SELLER()]);
    $seller = Seller::factory()->create(['user_id' => $owner->id]);

    return [$owner, $seller];
}

function makeAccountDeletionSubscription(Seller $seller): void
{
    $plan = SubscriptionPlan::create([
        'name' => 'Deletion test '.$seller->id,
        'price' => 100,
        'duration_type' => 'days',
        'duration_days' => 30,
        'status' => true,
    ]);

    SellerSubscription::create([
        'seller_id' => $seller->id,
        'plan_id' => $plan->id,
        'status' => 'active',
        'start_date' => now()->subDay(),
        'end_date' => now()->addMonth(),
        'price_paid' => 100,
    ]);

    SellerSubscriptionUsage::create([
        'seller_id' => $seller->id,
        'key' => SubscriptionPlanKeyEnum::SYSTEM_USER_LIMIT(),
        'used' => 1,
    ]);
}

it('deletes only a team member from the seller account route regardless of seller delete permission', function (bool $hasSellerDeletePermission): void {
    [$owner, $seller] = makeAccountDeletionSeller();
    $member = User::factory()->create(['access_panel' => GuardNameEnum::SELLER()]);
    SellerUser::create(['user_id' => $member->id, 'seller_id' => $seller->id]);

    $role = Role::create([
        'name' => 'deletion-test-staff-'.$member->id,
        'guard_name' => GuardNameEnum::SELLER(),
        'team_id' => $seller->id,
    ]);

    if ($hasSellerDeletePermission) {
        $permission = Permission::findOrCreate(SellerPermissionEnum::SELLER_DELETE(), GuardNameEnum::SELLER());
        $role->givePermissionTo($permission);
        $member->givePermissionTo($permission);
    }

    $member->assignRole($role);
    $tokenId = $member->createToken('deletion test')->accessToken->id;

    $this->actingAs($member, 'sanctum')
        ->deleteJson('/api/seller/delete-account')
        ->assertJsonPath('success', true);

    $this->assertSoftDeleted('users', ['id' => $member->id]);
    $this->assertDatabaseHas('users', ['id' => $owner->id, 'deleted_at' => null]);
    $this->assertDatabaseHas('sellers', ['id' => $seller->id, 'deleted_at' => null]);
    $this->assertDatabaseMissing('seller_user', ['user_id' => $member->id]);
    $this->assertDatabaseMissing('model_has_roles', ['model_id' => $member->id, 'model_type' => $member->getMorphClass()]);
    $this->assertDatabaseMissing('model_has_permissions', ['model_id' => $member->id, 'model_type' => $member->getMorphClass()]);
    $this->assertDatabaseMissing('personal_access_tokens', ['id' => $tokenId]);
})->with([false, true]);

it('removes every team link and reduces each seller subscription usage through the user route', function (): void {
    [, $firstSeller] = makeAccountDeletionSeller();
    [, $secondSeller] = makeAccountDeletionSeller();
    $member = User::factory()->create(['access_panel' => GuardNameEnum::SELLER()]);

    foreach ([$firstSeller, $secondSeller] as $seller) {
        SellerUser::create(['user_id' => $member->id, 'seller_id' => $seller->id]);
        makeAccountDeletionSubscription($seller);
        $role = Role::create([
            'name' => 'deletion-test-team-'.$seller->id,
            'guard_name' => GuardNameEnum::SELLER(),
            'team_id' => $seller->id,
        ]);
        $member->assignRole($role);
    }

    $this->actingAs($member, 'sanctum')
        ->deleteJson('/api/user/delete-account')
        ->assertJsonPath('success', true);

    $this->assertSoftDeleted('users', ['id' => $member->id]);
    $this->assertDatabaseMissing('seller_user', ['user_id' => $member->id]);
    $this->assertDatabaseMissing('model_has_roles', ['model_id' => $member->id, 'model_type' => $member->getMorphClass()]);

    foreach ([$firstSeller, $secondSeller] as $seller) {
        $this->assertDatabaseHas('sellers', ['id' => $seller->id, 'deleted_at' => null]);
        $this->assertDatabaseHas('seller_subscription_usages', [
            'seller_id' => $seller->id,
            'key' => SubscriptionPlanKeyEnum::SYSTEM_USER_LIMIT(),
            'used' => 0,
        ]);
    }
});

it('deletes the main seller and their login from either account route', function (string $path): void {
    [$owner, $seller] = makeAccountDeletionSeller();
    $tokenId = $owner->createToken('deletion test')->accessToken->id;

    $this->actingAs($owner, 'sanctum')
        ->deleteJson($path)
        ->assertJsonPath('success', true);

    $this->assertSoftDeleted('users', ['id' => $owner->id]);
    $this->assertSoftDeleted('sellers', ['id' => $seller->id]);
    expect(PersonalAccessToken::find($tokenId))->toBeNull();
})->with(['/api/seller/delete-account', '/api/user/delete-account']);

it('blocks main seller deletion while a store has a pending order', function (string $path): void {
    [$owner, $seller] = makeAccountDeletionSeller();
    $store = Store::factory()->create(['seller_id' => $seller->id]);
    $order = Order::factory()->create(['status' => OrderStatusEnum::PENDING()]);
    OrderItem::factory()->create(['order_id' => $order->id, 'store_id' => $store->id]);
    $tokenId = $owner->createToken('deletion test')->accessToken->id;

    $this->actingAs($owner, 'sanctum')
        ->deleteJson($path)
        ->assertStatus(422)
        ->assertJsonPath('success', false)
        ->assertJsonPath('message', __('labels.cannot_delete_seller_with_active_orders'));

    $this->assertDatabaseHas('users', ['id' => $owner->id, 'deleted_at' => null]);
    $this->assertDatabaseHas('sellers', ['id' => $seller->id, 'deleted_at' => null]);
    $this->assertDatabaseHas('stores', ['id' => $store->id, 'deleted_at' => null]);
    $this->assertDatabaseHas('personal_access_tokens', ['id' => $tokenId]);
})->with(['/api/seller/delete-account', '/api/user/delete-account']);

it('allows main seller deletion when its store orders are complete', function (): void {
    [$owner, $seller] = makeAccountDeletionSeller();
    $store = Store::factory()->create(['seller_id' => $seller->id]);
    $order = Order::factory()->create(['status' => OrderStatusEnum::DELIVERED()]);
    OrderItem::factory()->create(['order_id' => $order->id, 'store_id' => $store->id]);

    $this->actingAs($owner, 'sanctum')
        ->deleteJson('/api/seller/delete-account')
        ->assertJsonPath('success', true);

    $this->assertSoftDeleted('users', ['id' => $owner->id]);
    $this->assertSoftDeleted('sellers', ['id' => $seller->id]);
});

it('still allows a team member to delete their login while the seller has a pending order', function (): void {
    [$owner, $seller] = makeAccountDeletionSeller();
    $store = Store::factory()->create(['seller_id' => $seller->id]);
    $order = Order::factory()->create(['status' => OrderStatusEnum::PENDING()]);
    OrderItem::factory()->create(['order_id' => $order->id, 'store_id' => $store->id]);
    $member = User::factory()->create(['access_panel' => GuardNameEnum::SELLER()]);
    SellerUser::create(['user_id' => $member->id, 'seller_id' => $seller->id]);

    $this->actingAs($member, 'sanctum')
        ->deleteJson('/api/seller/delete-account')
        ->assertJsonPath('success', true);

    $this->assertSoftDeleted('users', ['id' => $member->id]);
    $this->assertDatabaseHas('users', ['id' => $owner->id, 'deleted_at' => null]);
    $this->assertDatabaseHas('sellers', ['id' => $seller->id, 'deleted_at' => null]);
});

it('deletes an ordinary user without affecting sellers', function (): void {
    [, $seller] = makeAccountDeletionSeller();
    $customer = User::factory()->create();

    $this->actingAs($customer, 'sanctum')
        ->deleteJson('/api/user/delete-account')
        ->assertJsonPath('success', true);

    $this->assertSoftDeleted('users', ['id' => $customer->id]);
    $this->assertDatabaseHas('sellers', ['id' => $seller->id, 'deleted_at' => null]);
});

it('rejects the seller deletion route for an unrelated user', function (): void {
    $customer = User::factory()->create();

    $this->actingAs($customer, 'sanctum')
        ->deleteJson('/api/seller/delete-account')
        ->assertStatus(404);

    $this->assertDatabaseHas('users', ['id' => $customer->id, 'deleted_at' => null]);
});

it('does not delete accounts in demo mode', function (string $path): void {
    [$owner, $seller] = makeAccountDeletionSeller();
    Setting::query()->where('variable', 'system')->update([
        'value' => json_encode(['systemVendorType' => 'multiple', 'demoMode' => true]),
    ]);
    Cache::forget('settings:system');

    $this->actingAs($owner, 'sanctum')
        ->deleteJson($path)
        ->assertJsonPath('success', false);

    $this->assertDatabaseHas('users', ['id' => $owner->id, 'deleted_at' => null]);
    $this->assertDatabaseHas('sellers', ['id' => $seller->id, 'deleted_at' => null]);
})->with(['/api/seller/delete-account', '/api/user/delete-account']);

it('does not offer or accept seller deletion as a team role permission', function (): void {
    [$owner, $seller] = makeAccountDeletionSeller();
    $sellerRole = Role::findOrCreate(DefaultSystemRolesEnum::SELLER(), GuardNameEnum::SELLER());
    $owner->assignRole($sellerRole);

    $role = Role::create([
        'name' => 'deletion-test-team-role',
        'guard_name' => GuardNameEnum::SELLER(),
        'team_id' => $seller->id,
    ]);
    $permission = Permission::findOrCreate(SellerPermissionEnum::SELLER_DELETE(), GuardNameEnum::SELLER());
    $role->givePermissionTo($permission);

    $this->actingAs($owner, 'sanctum')
        ->getJson('/api/seller/permissions/'.$role->name)
        ->assertOk()
        ->assertJsonMissingPath('data.grouped_permissions.seller')
        ->assertJsonPath('data.assigned', []);

    $this->postJson('/api/seller/permissions', [
        'role' => $role->name,
        'permissions' => [SellerPermissionEnum::SELLER_DELETE()],
    ])->assertStatus(422);

    expect($role->fresh()->hasPermissionTo(SellerPermissionEnum::SELLER_DELETE()))->toBeTrue();
});
