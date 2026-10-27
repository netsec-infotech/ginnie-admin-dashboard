<?php

use App\Enums\Order\OrderItemReturnStatusEnum;
use App\Http\Controllers\Admin\ReturnRequestController as AdminReturnRequestController;
use App\Http\Controllers\ReturnRequestController as SellerReturnRequestController;
use App\Models\OrderItemReturn;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('filters returns by insight status groups properly in admin controller', function () {
    $user = \App\Models\User::factory()->create();
    $seller = \App\Models\Seller::factory()->create(['user_id' => $user->id]);
    $store = \App\Models\Store::factory()->create(['seller_id' => $seller->id]);
    $order = \App\Models\Order::factory()->create(['user_id' => $user->id, 'seller_id' => $seller->id, 'store_id' => $store->id]);
    $orderItem1 = \App\Models\OrderItem::factory()->create(['order_id' => $order->id, 'seller_id' => $seller->id, 'store_id' => $store->id]);
    $orderItem2 = \App\Models\OrderItem::factory()->create(['order_id' => $order->id, 'seller_id' => $seller->id, 'store_id' => $store->id]);
    $orderItem3 = \App\Models\OrderItem::factory()->create(['order_id' => $order->id, 'seller_id' => $seller->id, 'store_id' => $store->id]);

    $approvedReturn = OrderItemReturn::forceCreate([
        'order_id' => $order->id,
        'order_item_id' => $orderItem1->id,
        'user_id' => $user->id,
        'seller_id' => $seller->id,
        'store_id' => $store->id,
        'reason' => 'Defective',
        'refund_amount' => 50,
        'return_status' => OrderItemReturnStatusEnum::SELLER_APPROVED(),
    ]);

    $refundProcessedReturn = OrderItemReturn::forceCreate([
        'order_id' => $order->id,
        'order_item_id' => $orderItem2->id,
        'user_id' => $user->id,
        'seller_id' => $seller->id,
        'store_id' => $store->id,
        'reason' => 'Defective',
        'refund_amount' => 50,
        'return_status' => OrderItemReturnStatusEnum::REFUND_PROCESSED(),
    ]);

    $underReviewReturn = OrderItemReturn::forceCreate([
        'order_id' => $order->id,
        'order_item_id' => $orderItem3->id,
        'user_id' => $user->id,
        'seller_id' => $seller->id,
        'store_id' => $store->id,
        'reason' => 'Defective',
        'refund_amount' => 50,
        'return_status' => OrderItemReturnStatusEnum::REQUESTED(),
    ]);

    $approvedQuery = AdminReturnRequestController::applyInsightStatusGroup(OrderItemReturn::query(), 'approved');
    expect($approvedQuery->pluck('id')->all())->toContain($approvedReturn->id, $refundProcessedReturn->id)
        ->not->toContain($underReviewReturn->id);

    $refundQuery = AdminReturnRequestController::applyInsightStatusGroup(OrderItemReturn::query(), 'refund_processed');
    expect($refundQuery->pluck('id')->all())->toContain($refundProcessedReturn->id)
        ->not->toContain($approvedReturn->id)
        ->not->toContain($underReviewReturn->id);

    $underReviewQuery = AdminReturnRequestController::applyInsightStatusGroup(OrderItemReturn::query(), 'under_review');
    expect($underReviewQuery->pluck('id')->all())->toContain($underReviewReturn->id)
        ->not->toContain($approvedReturn->id)
        ->not->toContain($refundProcessedReturn->id);
});

it('keeps return card links and browser history synchronized with status groups', function () {
    $adminView = file_get_contents(resource_path('views/admin/returns/index.blade.php'));
    $sellerView = file_get_contents(resource_path('views/seller/returns/index.blade.php'));
    $adminScript = file_get_contents(public_path('assets/js/admin-returns.js'));
    $sellerScript = file_get_contents(public_path('assets/js/seller-returns.js'));

    expect($adminView)
        ->toContain("'status_group' => 'approved'")
        ->toContain('return-insight-card-link')
        ->and($sellerView)
        ->toContain("'status_group' => 'approved'")
        ->toContain('return-insight-card-link')
        ->and($adminScript)
        ->toContain("url.searchParams.set('status_group', statusGroup)")
        ->toContain("window.addEventListener('popstate'")
        ->toContain("params.status_group = activeStatusGroup")
        ->and($sellerScript)
        ->toContain("url.searchParams.set('status_group', statusGroup)")
        ->toContain("window.addEventListener('popstate'")
        ->toContain("params.status_group = activeStatusGroup");
});
