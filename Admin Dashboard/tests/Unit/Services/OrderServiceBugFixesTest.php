<?php

use App\Enums\Order\OrderItemStatusEnum;
use App\Enums\Order\OrderStatusEnum;
use App\Enums\Payment\PaymentTypeEnum;
use App\Enums\PaymentStatusEnum;
use App\Models\Order;
use App\Models\OrderItem;
use App\Services\DeliveryBoyService;
use App\Services\OrderService;
use App\Services\PaymentService;
use App\Services\SellerStatementService;
use App\Services\SettingService;
use App\Services\StockService;
use App\Services\WalletService;

/**
 * Coverage for the four lifecycle bugs fixed in Phase 1A:
 *  - BUG-1 processOrderItemRefund operator precedence
 *  - BUG-2 determineSellerOrderStatus premature READY_FOR_PICKUP
 *  - BUG-3 handleSellerFlow stale guard (covered in Phase 1B integration tests)
 *  - BUG-4 validateStatusTransition explicit pre-checks
 */

beforeEach(function () {
    $this->stockService = Mockery::mock(StockService::class);
    $this->deliveryBoyService = Mockery::mock(DeliveryBoyService::class);
    $this->settingService = Mockery::mock(SettingService::class);
    $this->paymentService = Mockery::mock(PaymentService::class);
    $this->sellerStatementService = Mockery::mock(SellerStatementService::class);
    $this->walletService = Mockery::mock(WalletService::class);

    $this->service = new OrderService(
        $this->stockService,
        $this->deliveryBoyService,
        $this->settingService,
        $this->paymentService,
        $this->sellerStatementService,
        $this->walletService,
    );

    $this->callPrivate = function (string $method, array $args) {
        $ref = new ReflectionMethod(OrderService::class, $method);
        $ref->setAccessible(true);
        return $ref->invokeArgs($this->service, $args);
    };

    $this->makeOrderItem = function (string $paymentMethod, string $paymentStatus, float $subtotal = 100.0, float $promoDiscount = 0.0, float $walletBalance = 0.0, array $activeItems = []): OrderItem {
        $order = new Order();
        $order->id = 42;
        $order->user_id = 99;
        $order->payment_method = $paymentMethod;
        $order->payment_status = $paymentStatus;
        $order->wallet_balance = $walletBalance;
        $order->final_total = $subtotal;
        $order->delivery_charge = 0;
        $order->handling_charges = 0;
        $order->per_store_drop_off_fee = 0;
        $order->gift_card_discount = 0;
        $order->setRelation('items', collect($activeItems));

        $orderItem = new OrderItem();
        $orderItem->id = 7;
        $orderItem->subtotal = $subtotal;
        $orderItem->promo_discount = $promoDiscount;
        $orderItem->status = OrderItemStatusEnum::REJECTED();
        $orderItem->setRelation('order', $order);

        return $orderItem;
    };
});

afterEach(function () {
    Mockery::close();
});

// ---------------------------------------------------------------------------
// BUG-1 — processOrderItemRefund operator precedence
// ---------------------------------------------------------------------------

describe('processOrderItemRefund (BUG-1) — wallet refund only when money was collected', function () {

    it('does NOT refund a COD return_pickup whose payment was never completed', function () {
        // The original bug: `(NOT_COD && COMPLETED) || return_pickup` always refunded
        // on return_pickup, including COD orders that never collected any money.
        $this->walletService->shouldNotReceive('addBalance');

        $orderItem = ($this->makeOrderItem)(
            paymentMethod: PaymentTypeEnum::COD(),
            paymentStatus: PaymentStatusEnum::PENDING(),
        );

        $result = $this->service->processOrderItemRefund($orderItem, 'return_pickup');

        expect($result['success'])->toBeTrue()
            ->and($result['data']['refund_amount'])->toBe(0)
            ->and($result['data']['refund_method'])->toBe('none');
    });

    it('refunds a COD return_pickup whose payment WAS collected on delivery', function () {
        $this->walletService->shouldReceive('addBalance')
            ->once()
            ->andReturn(['success' => true, 'message' => 'ok']);

        $orderItem = ($this->makeOrderItem)(
            paymentMethod: PaymentTypeEnum::COD(),
            paymentStatus: PaymentStatusEnum::COMPLETED(),
            subtotal: 100,
            promoDiscount: 10,
        );

        $result = $this->service->processOrderItemRefund($orderItem, 'return_pickup');

        expect($result['success'])->toBeTrue()
            ->and($result['data']['refund_amount'])->toBe(90.0)
            ->and($result['data']['refund_method'])->toBe('wallet');
    });

    it('refunds a prepaid cancel when payment was completed', function () {
        $this->walletService->shouldReceive('addBalance')
            ->once()
            ->andReturn(['success' => true, 'message' => 'ok']);

        $orderItem = ($this->makeOrderItem)(
            paymentMethod: PaymentTypeEnum::STRIPE(),
            paymentStatus: PaymentStatusEnum::COMPLETED(),
        );

        $result = $this->service->processOrderItemRefund($orderItem, 'cancel');

        expect($result['success'])->toBeTrue()
            ->and($result['data']['refund_method'])->toBe('wallet');
    });

    it('does NOT refund a prepaid cancel whose payment never completed', function () {
        $this->walletService->shouldNotReceive('addBalance');

        $orderItem = ($this->makeOrderItem)(
            paymentMethod: PaymentTypeEnum::STRIPE(),
            paymentStatus: PaymentStatusEnum::PENDING(),
        );

        $result = $this->service->processOrderItemRefund($orderItem, 'cancel');

        expect($result['success'])->toBeTrue()
            ->and($result['data']['refund_amount'])->toBe(0)
            ->and($result['data']['refund_method'])->toBe('none');
    });

    it('does NOT refund a COD cancel — no money was ever collected pre-delivery', function () {
        $this->walletService->shouldNotReceive('addBalance');

        $orderItem = ($this->makeOrderItem)(
            paymentMethod: PaymentTypeEnum::COD(),
            paymentStatus: PaymentStatusEnum::PENDING(),
        );

        $result = $this->service->processOrderItemRefund($orderItem, 'cancel');

        expect($result['success'])->toBeTrue()
            ->and($result['data']['refund_amount'])->toBe(0);
    });

    it('refunds the remaining wallet balance when a wallet plus COD order is fully rejected', function () {
        $this->walletService->shouldReceive('addBalance')
            ->once()
            ->with(99, Mockery::on(fn(array $data) => $data['amount'] === 25.0))
            ->andReturn(['success' => true, 'message' => 'ok']);

        $orderItem = ($this->makeOrderItem)(
            paymentMethod: PaymentTypeEnum::COD(),
            paymentStatus: PaymentStatusEnum::PENDING(),
            subtotal: 80,
            walletBalance: 25,
        );

        $result = $this->service->processOrderItemRefund($orderItem, OrderItemStatusEnum::REJECTED());

        expect($result['success'])->toBeTrue()
            ->and($result['data']['refund_amount'])->toBe(25.0)
            ->and($result['data']['refund_method'])->toBe('wallet');
    });

    it('settles wallet against accepted sellers first before refunding wallet plus COD rejection', function () {
        $acceptedItem = new OrderItem();
        $acceptedItem->id = 8;
        $acceptedItem->subtotal = 40;
        $acceptedItem->promo_discount = 0;
        $acceptedItem->status = OrderItemStatusEnum::ACCEPTED();

        $this->walletService->shouldReceive('addBalance')
            ->once()
            ->with(99, Mockery::on(fn(array $data) => $data['amount'] === 10.0))
            ->andReturn(['success' => true, 'message' => 'ok']);

        $orderItem = ($this->makeOrderItem)(
            paymentMethod: PaymentTypeEnum::COD(),
            paymentStatus: PaymentStatusEnum::PENDING(),
            subtotal: 60,
            walletBalance: 50,
            activeItems: [$acceptedItem],
        );

        $result = $this->service->processOrderItemRefund($orderItem, OrderItemStatusEnum::REJECTED());

        expect($result['success'])->toBeTrue()
            ->and($result['data']['refund_amount'])->toBe(10.0)
            ->and($result['data']['refund_method'])->toBe('wallet');
    });
});

// ---------------------------------------------------------------------------
// BUG-2 — determineSellerOrderStatus premature READY_FOR_PICKUP
// ---------------------------------------------------------------------------

describe('determineSellerOrderStatus (BUG-2) — order-level status from item statuses', function () {

    it('returns AWAITING_STORE_RESPONSE when every item is still awaiting', function () {
        $result = ($this->callPrivate)('determineSellerOrderStatus', [[
            OrderItemStatusEnum::AWAITING_STORE_RESPONSE(),
            OrderItemStatusEnum::AWAITING_STORE_RESPONSE(),
        ]]);

        expect($result)->toBe(OrderStatusEnum::AWAITING_STORE_RESPONSE());
    });

    it('returns REJECTED_BY_SELLER when every item is rejected', function () {
        $result = ($this->callPrivate)('determineSellerOrderStatus', [[
            OrderItemStatusEnum::REJECTED(),
            OrderItemStatusEnum::REJECTED(),
        ]]);

        expect($result)->toBe(OrderStatusEnum::REJECTED_BY_SELLER());
    });

    it('returns PARTIALLY_ACCEPTED when some items are still awaiting and some are not', function () {
        $result = ($this->callPrivate)('determineSellerOrderStatus', [[
            OrderItemStatusEnum::AWAITING_STORE_RESPONSE(),
            OrderItemStatusEnum::ACCEPTED(),
        ]]);

        expect($result)->toBe(OrderStatusEnum::PARTIALLY_ACCEPTED());
    });

    it('returns PARTIALLY_REJECTED when one seller rejected and another seller is still awaiting response', function () {
        $result = ($this->callPrivate)('determineSellerOrderStatus', [[
            OrderItemStatusEnum::AWAITING_STORE_RESPONSE(),
            OrderItemStatusEnum::REJECTED(),
        ]]);

        expect($result)->toBe(OrderStatusEnum::PARTIALLY_REJECTED());
    });

    it('returns ACCEPTED_BY_SELLER when items are accepted but none are preparing yet (BUG-2)', function () {
        // Pre-fix this returned READY_FOR_PICKUP — firing the rider notification
        // observer prematurely. Now the order should sit in ACCEPTED_BY_SELLER until
        // the seller actually starts preparing.
        $result = ($this->callPrivate)('determineSellerOrderStatus', [[
            OrderItemStatusEnum::ACCEPTED(),
            OrderItemStatusEnum::ACCEPTED(),
        ]]);

        expect($result)->toBe(OrderStatusEnum::ACCEPTED_BY_SELLER());
    });

    it('returns ACCEPTED_BY_SELLER when an accepted item sits alongside a cancelled one (BUG-2 regression)', function () {
        // The original bug surfaced specifically with a mixed cancelled + accepted set.
        $result = ($this->callPrivate)('determineSellerOrderStatus', [[
            OrderItemStatusEnum::ACCEPTED(),
            OrderItemStatusEnum::CANCELLED(),
        ]]);

        expect($result)->toBe(OrderStatusEnum::ACCEPTED_BY_SELLER());
    });

    it('returns READY_FOR_PICKUP only once every live item is at least PREPARING', function () {
        $result = ($this->callPrivate)('determineSellerOrderStatus', [[
            OrderItemStatusEnum::PREPARING(),
            OrderItemStatusEnum::PREPARING(),
        ]]);

        expect($result)->toBe(OrderStatusEnum::READY_FOR_PICKUP());
    });

    it('returns READY_FOR_PICKUP when remaining live items are PREPARING and the rest are terminal', function () {
        $result = ($this->callPrivate)('determineSellerOrderStatus', [[
            OrderItemStatusEnum::PREPARING(),
            OrderItemStatusEnum::CANCELLED(),
            OrderItemStatusEnum::REJECTED(),
        ]]);

        expect($result)->toBe(OrderStatusEnum::READY_FOR_PICKUP());
    });

    it('returns PENDING when the input is empty', function () {
        $result = ($this->callPrivate)('determineSellerOrderStatus', [[]]);

        expect($result)->toBe(OrderStatusEnum::PENDING());
    });
});

// ---------------------------------------------------------------------------
// BUG-4 — validateStatusTransition explicit pre-checks
// ---------------------------------------------------------------------------

describe('validateStatusTransition (BUG-4) — seller pre-checks return clean translation keys', function () {

    it('blocks ACCEPT when the item is no longer awaiting a response', function () {
        // Same status returns false via the early "status_already_set" check.
        $sameStatus = ($this->callPrivate)('validateStatusTransition', [
            OrderItemStatusEnum::ACCEPTED(),
            OrderItemStatusEnum::ACCEPTED(),
            'seller',
        ]);
        expect($sameStatus['success'])->toBeFalse();

        // Distinct prior status (PREPARING) hits the new BUG-4 guard.
        $result = ($this->callPrivate)('validateStatusTransition', [
            OrderItemStatusEnum::PREPARING(),
            OrderItemStatusEnum::ACCEPTED(),
            'seller',
        ]);

        expect($result['success'])->toBeFalse()
            ->and($result['message'])->toBe(__('labels.order_item_can_only_be_accepted_while_awaiting_response'));
    });

    it('blocks REJECT when the item is no longer awaiting a response', function () {
        $fromAccepted = ($this->callPrivate)('validateStatusTransition', [
            OrderItemStatusEnum::ACCEPTED(),
            OrderItemStatusEnum::REJECTED(),
            'seller',
        ]);

        expect($fromAccepted['success'])->toBeFalse()
            ->and($fromAccepted['message'])->toBe(__('labels.order_item_can_only_be_rejected_while_awaiting_response'));

        $fromPreparing = ($this->callPrivate)('validateStatusTransition', [
            OrderItemStatusEnum::PREPARING(),
            OrderItemStatusEnum::REJECTED(),
            'seller',
        ]);

        expect($fromPreparing['success'])->toBeFalse()
            ->and($fromPreparing['message'])->toBe(__('labels.order_item_can_only_be_rejected_while_awaiting_response'));
    });

    it('allows ACCEPT and REJECT from AWAITING_STORE_RESPONSE', function () {
        $accept = ($this->callPrivate)('validateStatusTransition', [
            OrderItemStatusEnum::AWAITING_STORE_RESPONSE(),
            OrderItemStatusEnum::ACCEPTED(),
            'seller',
        ]);
        $reject = ($this->callPrivate)('validateStatusTransition', [
            OrderItemStatusEnum::AWAITING_STORE_RESPONSE(),
            OrderItemStatusEnum::REJECTED(),
            'seller',
        ]);

        expect($accept['success'])->toBeTrue()
            ->and($reject['success'])->toBeTrue();
    });

    it('still blocks PREPARING when the item has not been accepted', function () {
        $result = ($this->callPrivate)('validateStatusTransition', [
            OrderItemStatusEnum::AWAITING_STORE_RESPONSE(),
            OrderItemStatusEnum::PREPARING(),
            'seller',
        ]);

        expect($result['success'])->toBeFalse()
            ->and($result['message'])->toBe(__('labels.order_must_be_accepted_first'));
    });
});

// ---------------------------------------------------------------------------
// BUG-3 — handleSellerFlow widened guard
// ---------------------------------------------------------------------------

todo('handleSellerFlow recomputes status when order is in ACCEPTED_BY_SELLER or READY_FOR_PICKUP (BUG-3) — needs Order/OrderItem factories from Phase 1B');
