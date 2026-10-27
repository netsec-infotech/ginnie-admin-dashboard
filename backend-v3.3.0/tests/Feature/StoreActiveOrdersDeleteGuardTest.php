<?php

use App\Enums\Order\OrderStatusEnum;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Seller;
use App\Models\Store;
use App\Models\User;
use App\Services\StoreService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('prevents deleting a store that has active in-flight orders', function () {
    $user = User::factory()->create();
    $seller = Seller::factory()->create(['user_id' => $user->id]);
    $store = Store::factory()->create(['seller_id' => $seller->id]);

    $storeService = app(StoreService::class);

    // Initial state: no orders -> can delete
    expect($storeService->hasActiveOrders($store))->toBeFalse();

    // Create an active order for this store
    $order = Order::factory()->create([
        'status' => OrderStatusEnum::PREPARING(),
    ]);

    OrderItem::factory()->create([
        'order_id' => $order->id,
        'store_id' => $store->id,
    ]);

    // Active order exists -> hasActiveOrders returns true
    expect($storeService->hasActiveOrders($store))->toBeTrue();

    // Trying to delete via StoreService should throw RuntimeException
    expect(fn () => $storeService->deleteForSeller($store))
        ->toThrow(\RuntimeException::class, __('labels.cannot_delete_store_with_active_orders'));

    // Verify store was not deleted
    expect(Store::where('id', $store->id)->exists())->toBeTrue();
});

it('allows deleting a store when all orders are in terminal states', function () {
    $user = User::factory()->create();
    $seller = Seller::factory()->create(['user_id' => $user->id]);
    $store = Store::factory()->create(['seller_id' => $seller->id]);

    $storeService = app(StoreService::class);

    // Create a delivered order
    $deliveredOrder = Order::factory()->create([
        'status' => OrderStatusEnum::DELIVERED(),
    ]);
    OrderItem::factory()->create([
        'order_id' => $deliveredOrder->id,
        'store_id' => $store->id,
    ]);

    // Create a cancelled order
    $cancelledOrder = Order::factory()->create([
        'status' => OrderStatusEnum::CANCELLED(),
    ]);
    OrderItem::factory()->create([
        'order_id' => $cancelledOrder->id,
        'store_id' => $store->id,
    ]);

    // Has no active orders
    expect($storeService->hasActiveOrders($store))->toBeFalse();

    // Should delete without error
    $storeService->deleteForSeller($store);

    // Store is soft-deleted / removed
    expect(Store::where('id', $store->id)->exists())->toBeFalse();
});
