<?php

use App\Enums\Order\OrderItemReturnStatusEnum;
use App\Enums\Order\OrderReturnActorEnum;
use App\Models\OrderItemReturn;
use App\Models\User;
use App\Services\ReturnRequestService;

/**
 * Service-level guarantees for the shared return decision write-path.
 *
 * Verified at the service tier without persistence — exercises the decision
 * mutation + idempotency guard. Both the seller controller and the future
 * admin controller inherit these guarantees because they call the same
 * service method. Full DB-backed integration tests land in PR 2 once the
 * Order/OrderItem/OrderItemReturn factory chain exists.
 */
beforeEach(function () {
    $this->service = app(ReturnRequestService::class);

    $this->actor = new User();
    $this->actor->id = 999;
});

function makeReturn(string $status): OrderItemReturn
{
    $r = new OrderItemReturn();
    $r->id = 1;
    $r->return_status = $status;
    // Suppress persistence so we can exercise pure decision logic without
    // standing up the full Order/OrderItem/Seller/Store factory chain.
    $r->setAttribute('id', 1);
    $r->exists = true;
    $r->syncOriginal();

    return $r;
}

it('marks a return as seller_approved when seller approves', function () {
    $return = makeReturn((string) OrderItemReturnStatusEnum::REQUESTED());
    $return->saveQuietly = fn () => true;

    $saved = false;
    OrderItemReturn::saving(function () use (&$saved) {
        $saved = true;

        return false; // prevent actual write
    });

    $result = $this->service->decide(
        return: $return,
        action: 'approve',
        actor: $this->actor,
        actorType: OrderReturnActorEnum::SELLER,
        comment: 'looks legit',
    );

    expect((string) $result->return_status)->toBe((string) OrderItemReturnStatusEnum::SELLER_APPROVED())
        ->and($result->seller_approved_at)->not->toBeNull()
        ->and($result->seller_comment)->toBe('looks legit')
        ->and($result->decided_by_id)->toBe(999)
        ->and($result->decided_by_type->value)->toBe(OrderReturnActorEnum::SELLER->value)
        ->and($result->admin_decided_at)->toBeNull()
        ->and($saved)->toBeTrue();

    OrderItemReturn::flushEventListeners();
});

it('marks a return as seller_rejected when seller rejects', function () {
    $return = makeReturn((string) OrderItemReturnStatusEnum::REQUESTED());

    OrderItemReturn::saving(fn () => false);

    $result = $this->service->decide(
        return: $return,
        action: 'reject',
        actor: $this->actor,
        actorType: OrderReturnActorEnum::SELLER,
        comment: 'product damaged on customer side',
    );

    expect((string) $result->return_status)->toBe((string) OrderItemReturnStatusEnum::SELLER_REJECTED())
        ->and($result->seller_approved_at)->toBeNull()
        ->and($result->seller_comment)->toBe('product damaged on customer side');

    OrderItemReturn::flushEventListeners();
});

it('tags admin decisions with admin actor + admin_comment', function () {
    $return = makeReturn((string) OrderItemReturnStatusEnum::REQUESTED());

    OrderItemReturn::saving(fn () => false);

    $result = $this->service->decide(
        return: $return,
        action: 'approve',
        actor: $this->actor,
        actorType: OrderReturnActorEnum::ADMIN,
        comment: 'support team override',
    );

    expect($result->decided_by_type->value)->toBe(OrderReturnActorEnum::ADMIN->value)
        ->and($result->admin_decided_at)->not->toBeNull()
        ->and($result->admin_comment)->toBe('support team override')
        ->and($result->seller_comment)->toBeNull();

    OrderItemReturn::flushEventListeners();
});

it('rejects repeat decisions to prevent seller/admin races', function () {
    $return = makeReturn((string) OrderItemReturnStatusEnum::SELLER_APPROVED());

    $this->service->decide(
        return: $return,
        action: 'reject',
        actor: $this->actor,
        actorType: OrderReturnActorEnum::ADMIN,
    );
})->throws(RuntimeException::class);

it('rejects unsupported actions', function () {
    $return = makeReturn((string) OrderItemReturnStatusEnum::REQUESTED());

    $this->service->decide(
        return: $return,
        action: 'maybe',
        actor: $this->actor,
        actorType: OrderReturnActorEnum::SELLER,
    );
})->throws(InvalidArgumentException::class);
