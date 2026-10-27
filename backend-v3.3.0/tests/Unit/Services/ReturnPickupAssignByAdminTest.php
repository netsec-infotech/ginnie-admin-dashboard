<?php

use App\Enums\Order\OrderItemReturnPickupStatusEnum;
use App\Enums\Order\OrderItemReturnStatusEnum;
use App\Models\OrderItemReturn;
use App\Services\OrderService;
use App\Services\ReturnPickupService;
use App\Services\SellerStatementService;

/**
 * Guard-level coverage for ReturnPickupService::assignByAdmin.
 *
 * The happy-path assign exercises acceptPickup() which needs the full
 * Order/DeliveryBoy/Zone factory chain (not yet built — same blocker as PR 1),
 * and RefreshDatabase can't run here because a pre-existing migration uses
 * MySQL's JSON_UNQUOTE which SQLite lacks. So we build the service with mocked
 * dependencies and exercise only the pure guard logic that runs before any DB
 * access, against an in-memory model.
 */
beforeEach(function () {
    $this->service = new ReturnPickupService(
        Mockery::mock(SellerStatementService::class),
        Mockery::mock(OrderService::class),
    );
});

function makePickupReturn(string $returnStatus, string $pickupStatus): OrderItemReturn
{
    $r = new OrderItemReturn();
    $r->id = 1;
    $r->return_status = $returnStatus;
    $r->pickup_status = $pickupStatus;
    $r->delivery_boy_id = null;
    $r->exists = true;
    $r->syncOriginal();

    return $r;
}

it('rejects assignment when the return is not seller-approved', function () {
    $return = makePickupReturn(
        (string) OrderItemReturnStatusEnum::REQUESTED(),
        (string) OrderItemReturnPickupStatusEnum::PENDING(),
    );

    $result = $this->service->assignByAdmin($return, 5, 'rider was nearby');

    expect($result['success'])->toBeFalse();
});

it('rejects assignment when the item is already picked up', function () {
    $return = makePickupReturn(
        (string) OrderItemReturnStatusEnum::SELLER_APPROVED(),
        (string) OrderItemReturnPickupStatusEnum::PICKED_UP(),
    );

    $result = $this->service->assignByAdmin($return, 5, 'too late');

    expect($result['success'])->toBeFalse();
});

it('rejects assignment when already delivered to seller', function () {
    $return = makePickupReturn(
        (string) OrderItemReturnStatusEnum::SELLER_APPROVED(),
        (string) OrderItemReturnPickupStatusEnum::DELIVERED_TO_SELLER(),
    );

    $result = $this->service->assignByAdmin($return, 5, 'too late');

    expect($result['success'])->toBeFalse();
});
