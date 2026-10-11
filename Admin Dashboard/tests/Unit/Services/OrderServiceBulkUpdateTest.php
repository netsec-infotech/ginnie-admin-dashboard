<?php

use App\Services\DeliveryBoyService;
use App\Services\OrderService;
use App\Services\PaymentService;
use App\Services\SellerStatementService;
use App\Services\SettingService;
use App\Services\StockService;
use App\Services\WalletService;

/**
 * Coverage for OrderService::bulkUpdateOrderItemStatusByAdmin — the per-item
 * aggregation contract. Partial-mock the single-item method so we exercise
 * only the loop/aggregation logic, not the per-item state machine.
 */

beforeEach(function () {
    $this->stockService = Mockery::mock(StockService::class);
    $this->deliveryBoyService = Mockery::mock(DeliveryBoyService::class);
    $this->settingService = Mockery::mock(SettingService::class);
    $this->paymentService = Mockery::mock(PaymentService::class);
    $this->sellerStatementService = Mockery::mock(SellerStatementService::class);
    $this->walletService = Mockery::mock(WalletService::class);
});

afterEach(function () {
    Mockery::close();
});

it('reports every item as succeeded when each single update passes', function () {
    $service = Mockery::mock(OrderService::class, [
        $this->stockService,
        $this->deliveryBoyService,
        $this->settingService,
        $this->paymentService,
        $this->sellerStatementService,
        $this->walletService,
    ])->makePartial();

    $service->shouldReceive('updateOrderItemStatusByAdmin')
        ->times(3)
        ->andReturn(['success' => true, 'message' => 'ok', 'data' => []]);

    $out = $service->bulkUpdateOrderItemStatusByAdmin([1, 2, 3], 'accept', 99, null, null);

    expect($out['success'])->toBeTrue()
        ->and($out['data']['success_count'])->toBe(3)
        ->and($out['data']['fail_count'])->toBe(0)
        ->and($out['data']['results'])->toHaveCount(3);
});

it('aggregates partial-failure with per-item reasons preserved', function () {
    $service = Mockery::mock(OrderService::class, [
        $this->stockService,
        $this->deliveryBoyService,
        $this->settingService,
        $this->paymentService,
        $this->sellerStatementService,
        $this->walletService,
    ])->makePartial();

    $service->shouldReceive('updateOrderItemStatusByAdmin')
        ->with(10, Mockery::any(), Mockery::any(), Mockery::any(), Mockery::any())
        ->once()
        ->andReturn(['success' => true, 'message' => 'ok', 'data' => []]);

    $service->shouldReceive('updateOrderItemStatusByAdmin')
        ->with(20, Mockery::any(), Mockery::any(), Mockery::any(), Mockery::any())
        ->once()
        ->andReturn(['success' => false, 'message' => 'invalid transition', 'data' => []]);

    $out = $service->bulkUpdateOrderItemStatusByAdmin([10, 20], 'preparing', 99, 'bulk', null);

    expect($out['success'])->toBeFalse()
        ->and($out['data']['success_count'])->toBe(1)
        ->and($out['data']['fail_count'])->toBe(1);

    $failed = collect($out['data']['results'])->firstWhere('order_item_id', 20);
    expect($failed['success'])->toBeFalse()
        ->and($failed['message'])->toBe('invalid transition');
});

it('reports all-failure when no single update succeeds', function () {
    $service = Mockery::mock(OrderService::class, [
        $this->stockService,
        $this->deliveryBoyService,
        $this->settingService,
        $this->paymentService,
        $this->sellerStatementService,
        $this->walletService,
    ])->makePartial();

    $service->shouldReceive('updateOrderItemStatusByAdmin')
        ->times(2)
        ->andReturn(['success' => false, 'message' => 'nope', 'data' => []]);

    $out = $service->bulkUpdateOrderItemStatusByAdmin([1, 2], 'delivered', 99, null, null);

    expect($out['success'])->toBeFalse()
        ->and($out['data']['success_count'])->toBe(0)
        ->and($out['data']['fail_count'])->toBe(2);
});
