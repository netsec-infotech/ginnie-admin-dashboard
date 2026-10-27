<?php

use App\Models\DeliveryBoy;
use App\Models\DeliveryBoyAssignment;
use App\Models\Order;
use App\Models\Setting;
use App\Models\User;
use App\Enums\SettingTypeEnum;
use App\Enums\DeliveryBoy\DeliveryBoyAssignmentStatusEnum;
use App\Enums\DeliveryBoy\DeliveryBoyAssignmentTypeEnum;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->withoutMiddleware(\App\Http\Middleware\VerifyLicense::class);
});

/**
 * Helper to make a delivery boy user
 */
function testMakeDeliveryBoy(array $overrides = []): User
{
    static $counter = 0;
    $counter++;

    $user = User::create([
        'name' => 'Rider Limit Tester '.$counter,
        'email' => 'rider-limit-'.$counter.'-'.uniqid().'@example.test',
        'password' => bcrypt('password'),
    ]);

    DB::table('delivery_boys')->insert(array_merge([
        'user_id' => $user->id,
        'status' => 'active',
        'verification_status' => 'verified',
        'created_at' => now(),
        'updated_at' => now(),
    ], $overrides));

    return $user;
}

/**
 * Helper to set global cash collection limit
 */
function setGlobalLimit(bool $status, float $amount): void
{
    Setting::updateOrCreate(
        ['variable' => SettingTypeEnum::DELIVERY_BOY()],
        [
            'value' => json_encode([
                'cashCollectionLimitStatus' => $status,
                'cashCollectionLimitAmount' => (string) $amount,
                'termsCondition' => '',
                'privacyPolicy' => '',
            ])
        ]
    );
}

/**
 * Helper to create a valid order with all required columns populated
 */
function createCashLimitTestOrder(array $overrides = []): Order
{
    $zone = \App\Models\DeliveryZone::factory()->create();

    $id = DB::table('orders')->insertGetId(array_merge([
        'uuid' => (string) Str::uuid(),
        'user_id' => 1,
        'slug' => 'order-' . uniqid(),
        'email' => 'customer@example.test',
        'ip_address' => '127.0.0.1',
        'currency_code' => 'INR',
        'currency_rate' => 1.0,
        'payment_method' => 'cod',
        'payment_status' => 'pending',
        'fulfillment_type' => 'hyperlocal',
        'delivery_zone_id' => $zone->id,
        'wallet_balance' => 0.00,
        'subtotal' => 0.00,
        'total_payable' => 0.00,
        'final_total' => 0.00,
        'billing_name' => 'John Doe',
        'billing_address_1' => '123 Street',
        'billing_landmark' => 'Near Mall',
        'billing_zip' => '123456',
        'billing_phone' => '9999999999',
        'billing_address_type' => 'home',
        'billing_latitude' => 12.971598,
        'billing_longitude' => 77.594562,
        'billing_city' => 'Bengaluru',
        'billing_state' => 'Karnataka',
        'billing_country' => 'India',
        'billing_country_code' => 'IN',
        'shipping_name' => 'John Doe',
        'shipping_address_1' => '123 Street',
        'shipping_landmark' => 'Near Mall',
        'shipping_zip' => '123456',
        'shipping_phone' => '9999999999',
        'shipping_address_type' => 'home',
        'shipping_latitude' => 12.971598,
        'shipping_longitude' => 77.594562,
        'shipping_city' => 'Bengaluru',
        'shipping_state' => 'Karnataka',
        'shipping_country' => 'India',
        'shipping_country_code' => 'IN',
        'created_at' => now(),
        'updated_at' => now(),
    ], $overrides));

    return Order::findOrFail($id);
}

/**
 * Helper to create a completed COD assignment for a rider
 */
function createCompletedCodAssignment(int $deliveryBoyId, float $collected, float $submitted = 0.0): DeliveryBoyAssignment
{
    $order = createCashLimitTestOrder([
        'payment_method' => 'cod',
        'final_total' => $collected,
        'total_payable' => $collected,
        'status' => 'delivered',
    ]);

    return DeliveryBoyAssignment::create([
        'order_id' => $order->id,
        'delivery_boy_id' => $deliveryBoyId,
        'assignment_type' => DeliveryBoyAssignmentTypeEnum::DELIVERY(),
        'assigned_at' => now(),
        'status' => DeliveryBoyAssignmentStatusEnum::COMPLETED(),
        'payment_status' => 'pending',
        'cod_cash_collected' => $collected,
        'cod_cash_submitted' => $submitted,
        'cod_submission_status' => $submitted >= $collected ? 'submitted' : ($submitted > 0 ? 'partially_submitted' : 'pending'),
    ]);
}

it('allows accepting orders when limit is disabled', function () {
    $user = testMakeDeliveryBoy();
    $rider = DeliveryBoy::where('user_id', $user->id)->firstOrFail();
    Sanctum::actingAs($user);

    // Enable limit as false
    setGlobalLimit(false, 3000);

    // Create an assignment that puts them over 3000
    createCompletedCodAssignment($rider->id, 3500);

    expect($rider->isCashLimitReached())->toBeFalse();
});

it('blocks accepting orders when limit is enabled and reached', function () {
    $user = testMakeDeliveryBoy();
    $rider = DeliveryBoy::where('user_id', $user->id)->firstOrFail();

    // Enable limit
    setGlobalLimit(true, 3000);

    // Create completed COD assignment putting them over the limit
    createCompletedCodAssignment($rider->id, 3200);

    expect($rider->unsettled_cash)->toBe(3200.0);
    expect($rider->isCashLimitReached())->toBeTrue();
});

it('creates a pending online settlement session order-wise', function () {
    $user = testMakeDeliveryBoy();
    $rider = DeliveryBoy::where('user_id', $user->id)->firstOrFail();
    Sanctum::actingAs($user);

    setGlobalLimit(true, 3000);

    $assignment1 = createCompletedCodAssignment($rider->id, 1500);
    $assignment2 = createCompletedCodAssignment($rider->id, 2000);

    $response = $this->postJson('/api/delivery-boy/cash-collections/settle-online', [
        'delivery_boy_assignment_ids' => [$assignment1->id, $assignment2->id],
    ]);

    $response->assertOk()
        ->assertJsonPath('success', true);

    $uuid = $response->json('data.settlement_uuid');
    
    // Assert database has settlement record
    $this->assertDatabaseHas('delivery_boy_online_settlements', [
        'uuid' => $uuid,
        'delivery_boy_id' => $rider->id,
        'amount' => 3500.00,
        'status' => 'pending',
    ]);

    // Assert pivot links exist
    $this->assertDatabaseHas('delivery_boy_online_settlement_assignments', [
        'delivery_boy_assignment_id' => $assignment1->id,
        'amount' => 1500.00,
    ]);
});

it('allocates bulk online payment correctly to selected assignments and unblocks rider', function () {
    $user = testMakeDeliveryBoy();
    $rider = DeliveryBoy::where('user_id', $user->id)->firstOrFail();
    Sanctum::actingAs($user);

    setGlobalLimit(true, 3000);

    $assignment1 = createCompletedCodAssignment($rider->id, 1500);
    $assignment2 = createCompletedCodAssignment($rider->id, 2000);

    // Initiate settlement
    $settlement = \App\Models\DeliveryBoyOnlineSettlement::create([
        'uuid' => (string) Str::uuid(),
        'delivery_boy_id' => $rider->id,
        'amount' => 3500.00,
        'status' => 'pending',
    ]);
    $settlement->assignments()->attach($assignment1->id, ['amount' => 1500.00]);
    $settlement->assignments()->attach($assignment2->id, ['amount' => 2000.00]);

    expect($rider->isCashLimitReached())->toBeTrue();

    // Mock completing Razorpay webhook payment
    $razorpayController = app(\App\Http\Controllers\Payments\RazorpayController::class);
    
    // Invoke completion engine privately or via DB process directly
    $method = new ReflectionMethod($razorpayController, 'completeOnlineSettlement');
    $method->setAccessible(true);
    $method->invoke($razorpayController, $settlement, 'pay_mock_12345');

    // Assert settlement status is updated
    expect($settlement->fresh()->status)->toBe('completed');
    expect($settlement->fresh()->transaction_reference)->toBe('pay_mock_12345');

    // Assert assignments are fully submitted
    expect((float) $assignment1->fresh()->cod_cash_submitted)->toBe(1500.00);
    expect($assignment1->fresh()->cod_submission_status)->toBe('submitted');

    expect((float) $assignment2->fresh()->cod_cash_submitted)->toBe(2000.00);
    expect($assignment2->fresh()->cod_submission_status)->toBe('submitted');

    // Assert Cash Transaction logs exist
    $this->assertDatabaseHas('delivery_boy_cash_transactions', [
        'delivery_boy_assignment_id' => $assignment1->id,
        'amount' => 1500.00,
        'payment_channel' => 'online',
        'payment_gateway' => 'razorpay',
        'online_settlement_id' => $settlement->id,
    ]);

    // Assert rider is unblocked
    expect($rider->fresh()->unsettled_cash)->toBe(0.0);
    expect($rider->fresh()->isCashLimitReached())->toBeFalse();
});
