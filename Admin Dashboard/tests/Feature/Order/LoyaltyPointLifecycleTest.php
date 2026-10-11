<?php

use App\Enums\LoyaltyPoint\LoyaltyPointTransactionStatusEnum;
use App\Enums\LoyaltyPoint\LoyaltyPointTransactionTypeEnum;
use App\Enums\Order\OrderStatusEnum;
use App\Enums\Payment\PaymentTypeEnum;
use App\Enums\Product\ProductStatusEnum;
use App\Enums\SettingTypeEnum;
use App\Http\Middleware\VerifyLicense;
use App\Models\Address;
use App\Models\Cart;
use App\Models\CartItem;
use App\Models\LoyaltyPointTransaction;
use App\Models\Order;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Setting;
use App\Models\Store;
use App\Models\StoreProductVariant;
use App\Models\User;
use App\Services\LoyaltyPointService;
use App\Services\OrderService;
use App\Services\SettingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->withoutMiddleware([VerifyLicense::class]);
    $this->artisan('db:seed', ['--class' => 'CountriesSeeder']);

    Setting::updateOrCreate(
        ['variable' => SettingTypeEnum::LOYALTY_POINT()],
        [
            'value' => json_encode([
                'is_enabled' => true,
                'point_rate' => 100.0,
                'redemption_value' => 1.0,
                'max_redemption_percentage' => 10.0,
                'min_order_value_to_redeem' => 0.0,
                'expiry_days' => 0,
            ]),
        ]
    );

    Setting::updateOrCreate(
        ['variable' => SettingTypeEnum::PAYMENT()],
        [
            'value' => json_encode([
                'cod' => true,
                'wallet' => true,
            ]),
        ]
    );

    Setting::updateOrCreate(
        ['variable' => SettingTypeEnum::SYSTEM()],
        [
            'value' => json_encode([
                'currency' => 'USD',
                'minimumCartAmount' => 1,
                'checkoutType' => 'multi_store',
            ]),
        ]
    );

    Cache::flush();
    $settingService = app(SettingService::class);
    foreach (SettingTypeEnum::values() as $v) {
        $settingService->clearSettingCache($v);
    }

    $this->user = User::factory()->create();
    $this->store = Store::factory()->create(['status' => 'online', 'verification_status' => 'approved', 'allows_pickup' => true]);
    $this->product = Product::factory()->create([
        'status' => ProductStatusEnum::ACTIVE(),
        'is_returnable' => true,
        'returnable_days' => 7,
    ]);
    $this->variant = ProductVariant::create([
        'uuid' => (string) \Illuminate\Support\Str::uuid(),
        'product_id' => $this->product->id,
        'title' => 'Default Variant',
        'slug' => 'default-variant-'.time(),
        'price' => 500.00,
        'availability' => 'in_stock',
        'barcode' => 'BC-'.rand(1000, 9999),
        'visibility' => 'visible',
        'is_default' => true,
    ]);

    StoreProductVariant::create([
        'store_id' => $this->store->id,
        'product_variant_id' => $this->variant->id,
        'sku' => 'SKU-'.rand(1000, 9999),
        'cost' => 300.00,
        'price' => 500.00,
        'special_price' => 500.00,
        'stock' => 50,
    ]);

    $this->address = Address::create([
        'user_id' => $this->user->id,
        'title' => 'Home',
        'mobile' => '1234567890',
        'address_line1' => '123 Main St',
        'city' => 'City',
        'state' => 'State',
        'country' => 'Country',
        'country_code' => 'US',
        'zipcode' => '12345',
        'latitude' => 12.9716,
        'longitude' => 77.5946,
        'address_type' => 'home',
    ]);

    $zone = \App\Models\DeliveryZone::factory()->create([
        'center_latitude' => 12.9716,
        'center_longitude' => 77.5946,
        'radius_km' => 100.0,
        'boundary_json' => null,
        'status' => \App\Enums\ActiveInactiveStatusEnum::ACTIVE(),
    ]);

    DB::table('store_zone')->insert([
        'store_id' => $this->store->id,
        'zone_id' => $zone->id,
    ]);
});

it('creates pending loyalty points upon placement and delays crediting until return window closes', function () {
    $cart = Cart::create([
        'user_id' => $this->user->id,
        'uuid' => (string) \Illuminate\Support\Str::uuid(),
        'use_loyalty_points' => false,
    ]);

    CartItem::create([
        'cart_id' => $cart->id,
        'store_id' => $this->store->id,
        'product_id' => $this->product->id,
        'product_variant_id' => $this->variant->id,
        'quantity' => 1,
        'price' => 500.00,
    ]);

    $orderService = app(OrderService::class);
    $result = $orderService->createOrder($this->user, [
        'payment_type' => PaymentTypeEnum::COD(),
        'delivery_type' => 'delivery',
        'address_id' => $this->address->id,
        'rush_delivery' => false,
        'use_loyalty_points' => false,
        'minimumCartAmount' => 0,
    ]);

    expect($result['success'])->toBeTrue();
    $order = $result['data'];

    // Earned points should be stored in PENDING status
    $pendingTx = LoyaltyPointTransaction::where('order_id', $order->id)
        ->where('type', LoyaltyPointTransactionTypeEnum::EARNED())
        ->first();

    expect($pendingTx)->not->toBeNull()
        ->and($pendingTx->points)->toBe(5)
        ->and($pendingTx->status->value)->toBe('pending')
        ->and($this->user->fresh()->loyalty_points_balance)->toBe(0);

    // Update order status to DELIVERED delivered 2 days ago (return window 7 days not passed)
    Order::where('id', $order->id)->update([
        'status' => OrderStatusEnum::DELIVERED(),
        'updated_at' => now()->subDays(2),
    ]);

    $this->artisan('loyalty:process-earned-points')->assertExitCode(0);

    // Return window has NOT passed, points must remain PENDING
    expect($pendingTx->fresh()->status->value)->toBe('pending')
        ->and($this->user->fresh()->loyalty_points_balance)->toBe(0);

    // Update updated_at to 8 days ago (return window passed)
    Order::where('id', $order->id)->update([
        'updated_at' => now()->subDays(8),
    ]);

    $this->artisan('loyalty:process-earned-points')->assertExitCode(0);

    // Points should now be CREDITED and balance updated to 5
    expect($pendingTx->fresh()->status->value)->toBe('credited')
        ->and($this->user->fresh()->loyalty_points_balance)->toBe(5);
});

it('refunds redeemed points and cancels pending points on order cancellation', function () {
    // Give user 100 points
    app(LoyaltyPointService::class)->adjustBalance(
        userId: $this->user->id,
        points: 100,
        type: LoyaltyPointTransactionTypeEnum::EARNED(),
        description: 'Initial balance'
    );

    $cart = Cart::create([
        'user_id' => $this->user->id,
        'uuid' => (string) \Illuminate\Support\Str::uuid(),
        'use_loyalty_points' => true,
    ]);

    CartItem::create([
        'cart_id' => $cart->id,
        'store_id' => $this->store->id,
        'product_id' => $this->product->id,
        'product_variant_id' => $this->variant->id,
        'quantity' => 1,
        'price' => 500.00,
    ]);

    $orderService = app(OrderService::class);
    $result = $orderService->createOrder($this->user, [
        'payment_type' => PaymentTypeEnum::COD(),
        'delivery_type' => 'delivery',
        'address_id' => $this->address->id,
        'rush_delivery' => false,
        'use_loyalty_points' => true,
        'minimumCartAmount' => 0,
    ]);

    expect($result['success'])->toBeTrue();
    $order = $result['data'];

    // 50 points redeemed, balance left is 50
    expect($this->user->fresh()->loyalty_points_balance)->toBe(50);

    // Cancel order
    app(LoyaltyPointService::class)->handleOrderCancellationOrReturn($order);

    // 50 redeemed points refunded back -> balance restored to 100
    expect($this->user->fresh()->loyalty_points_balance)->toBe(100);

    // Pending earned transaction should be CANCELLED
    $pendingTx = LoyaltyPointTransaction::where('order_id', $order->id)
        ->where('type', LoyaltyPointTransactionTypeEnum::EARNED())
        ->first();

    expect($pendingTx->status->value)->toBe('cancelled');
});
