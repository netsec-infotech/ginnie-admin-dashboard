<?php

use App\Enums\Order\OrderStatusEnum;
use App\Models\Order;
use App\Services\OrderEscalationService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->service = new OrderEscalationService();
});

/**
 * Helper — create a bare-minimum order with a known status + updated_at.
 * Avoids the full Order factory chain we don't have yet by inserting via DB.
 */
function makeStuckOrder(string $status, int $minutesAgo): int
{
    $id = \DB::table('orders')->insertGetId([
        'user_id' => null,
        'status' => $status,
        'subtotal' => 100.00,
        'final_total' => 100.00,
        'total_payable' => 100.00,
        'created_at' => now()->subMinutes($minutesAgo),
        'updated_at' => now()->subMinutes($minutesAgo),
    ]);

    return $id;
}

it('does not flag orders inside the SLA window', function () {
    makeStuckOrder(OrderStatusEnum::AWAITING_STORE_RESPONSE(), minutesAgo: 5);

    $result = $this->service->checkStuckOrders();

    expect($result['flagged'])->toBe(0);
    expect(\DB::table('orders')->where('is_flagged', true)->count())->toBe(0);
})->skip('Needs base Order schema columns; revisit once an Order factory ships.');

it('flags an order whose seller has been unresponsive past 15 minutes', function () {
    $orderId = makeStuckOrder(OrderStatusEnum::AWAITING_STORE_RESPONSE(), minutesAgo: 20);

    $result = $this->service->checkStuckOrders();

    expect($result['flagged'])->toBeGreaterThanOrEqual(1);
    $row = \DB::table('orders')->where('id', $orderId)->first();
    expect((bool) $row->is_flagged)->toBeTrue();
    expect($row->escalation_reasons)->toContain('seller_unresponsive');
})->skip('Needs base Order schema columns; revisit once an Order factory ships.');

it('does not double-flag an order that was already flagged for the same reason', function () {
    $orderId = makeStuckOrder(OrderStatusEnum::AWAITING_STORE_RESPONSE(), minutesAgo: 30);

    $first = $this->service->checkStuckOrders();
    $second = $this->service->checkStuckOrders();

    expect($first['flagged'])->toBeGreaterThanOrEqual(1);
    expect($second['flagged'])->toBe(0); // Idempotent.

    $auditCount = \DB::table('order_audit_logs')
        ->where('order_id', $orderId)
        ->where('action', 'escalation_flagged')
        ->count();
    expect($auditCount)->toBe(1);
})->skip('Needs base Order schema columns; revisit once an Order factory ships.');

todo('flags an order stuck in READY_FOR_PICKUP past 10 minutes');

todo('flags an order with items in RETURNING_TO_STORE past 30 minutes');

todo('OrderEscalated event fires for newly-flagged orders only');

todo('NotifyAdminOnEscalation listener pushes a notification to every Super Admin');

todo('CheckStuckOrders artisan command reports scanned + flagged counts');
