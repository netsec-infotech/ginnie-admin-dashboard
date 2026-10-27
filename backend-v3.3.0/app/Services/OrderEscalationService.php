<?php

namespace App\Services;

use App\Enums\Order\OrderItemStatusEnum;
use App\Enums\Order\OrderStatusEnum;
use App\Events\Order\OrderEscalated;
use App\Models\Order;
use App\Models\OrderAuditLog;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Phase 3C — sweeps for stuck orders by SLA threshold and flags them so the
 * admin dashboard can surface them.
 *
 * Thresholds are intentionally hardcoded for now; promote to Settings in
 * Phase 4 if the business wants per-zone tuning. Each rule is a simple
 * "where status X for longer than N minutes" check, easy to reason about.
 *
 * Flagging is idempotent — already-flagged orders aren't re-flagged with the
 * same reason, so rerunning the sweep doesn't spam audit log or notifications.
 */
class OrderEscalationService
{
    /**
     * SLA thresholds in minutes. Tuned for hyperlocal: the seller has 15 min
     * to respond after a payment lands, the rider pool gets 10 min before
     * "no rider available" is flagged, and items physically returning to a
     * store need 30 min before we worry the seller forgot to confirm.
     *
     * @var array<string, int>
     */
    public const SLA_MINUTES = [
        'awaiting_store_response' => 15,
        'ready_for_pickup'        => 10,
        'returning_to_store'      => 30,
    ];

    /**
     * Check every relevant order and flag the stuck ones.
     *
     * @return array{flagged: int, scanned: int}
     */
    public function checkStuckOrders(): array
    {
        $now = Carbon::now();
        $flagged = 0;

        // Pull every order that COULD be stuck. Item-aware: an order with
        // status PARTIALLY_ACCEPTED can still have one store sitting on items,
        // so we look at items in AWAITING_STORE_RESPONSE regardless of the
        // parent order status (multi-vendor case).
        $candidates = Order::where(function ($q) {
                $q->where('status', OrderStatusEnum::READY_FOR_PICKUP())
                  ->orWhereHas('items', fn($i) => $i->whereIn('status', [
                      OrderItemStatusEnum::AWAITING_STORE_RESPONSE(),
                      OrderItemStatusEnum::RETURNING_TO_STORE(),
                  ]));
            })
            ->with(['items', 'items.store'])
            ->get();

        foreach ($candidates as $order) {
            $reasons = $this->reasonsFor($order, $now);
            if (empty($reasons)) {
                continue;
            }

            $existingReasons = $order->escalation_reasons ?? [];
            $newReasons = array_values(array_diff(
                array_keys($reasons),
                array_keys($existingReasons),
            ));

            if (empty($newReasons) && $order->is_flagged) {
                continue; // Already flagged with the same reasons — skip.
            }

            $merged = array_merge($existingReasons, $reasons);
            $order->update([
                'is_flagged' => true,
                'escalation_reasons' => $merged,
                'escalated_at' => $order->escalated_at ?? $now,
            ]);

            // Audit log + event fire only for newly-added reasons.
            if (!empty($newReasons)) {
                $this->writeEscalationAudit($order, $newReasons, $merged);
                event(new OrderEscalated($order, $newReasons));
                $flagged++;
            }
        }

        Log::info('Stuck-order check complete', [
            'scanned' => $candidates->count(),
            'flagged' => $flagged,
        ]);

        return ['flagged' => $flagged, 'scanned' => $candidates->count()];
    }

    /**
     * Build a reasons-keyed array for a single order. Each entry maps a reason
     * code to an explanation payload that gets serialised to JSON on the
     * orders.escalation_reasons column.
     *
     * @return array<string, array<string, mixed>>
     */
    private function reasonsFor(Order $order, Carbon $now): array
    {
        $reasons = [];

        // Multi-vendor aware: a single store can be unresponsive even when the
        // ORDER status has moved past AWAITING_STORE_RESPONSE (other stores
        // already accepted, so the order is now PARTIALLY_ACCEPTED). Look at
        // items directly so we catch the laggard store.
        $stuckAwaitingItems = $order->items
            ->filter(fn($item) => $item->status === OrderItemStatusEnum::AWAITING_STORE_RESPONSE())
            ->filter(fn($item) => $item->updated_at?->diffInMinutes($now) >= self::SLA_MINUTES['awaiting_store_response']);

        if ($stuckAwaitingItems->isNotEmpty()) {
            // The earliest stuck item drives the "since" — represents how long
            // the slowest store has been ignoring the order.
            $earliest = $stuckAwaitingItems->min(fn($item) => $item->updated_at?->getTimestamp());
            $reasons['seller_unresponsive'] = [
                'priority' => 'high',
                'item_ids' => $stuckAwaitingItems->pluck('id')->all(),
                'store_ids' => $stuckAwaitingItems->pluck('store_id')->unique()->filter()->values()->all(),
                'since' => $earliest ? Carbon::createFromTimestamp($earliest)->toIso8601String() : null,
                'flagged_at' => $now->toIso8601String(),
            ];
        }

        if ((string) $order->status === OrderStatusEnum::READY_FOR_PICKUP()
            && $order->updated_at?->diffInMinutes($now) >= self::SLA_MINUTES['ready_for_pickup']
        ) {
            $reasons['no_rider_available'] = [
                'priority' => 'high',
                'minutes' => $order->updated_at?->diffInMinutes($now),
                'since' => $order->updated_at?->toIso8601String(),
                'flagged_at' => $now->toIso8601String(),
            ];
        }

        $stuckReturning = $order->items
            ->filter(fn($item) => $item->status === OrderItemStatusEnum::RETURNING_TO_STORE())
            ->filter(fn($item) => $item->updated_at?->diffInMinutes($now) >= self::SLA_MINUTES['returning_to_store']);

        if ($stuckReturning->isNotEmpty()) {
            $earliest = $stuckReturning->min(fn($item) => $item->updated_at?->getTimestamp());
            $reasons['return_unconfirmed'] = [
                'priority' => 'medium',
                'item_ids' => $stuckReturning->pluck('id')->all(),
                'since' => $earliest ? Carbon::createFromTimestamp($earliest)->toIso8601String() : null,
                'flagged_at' => $now->toIso8601String(),
            ];
        }

        return $reasons;
    }

    private function writeEscalationAudit(Order $order, array $newReasons, array $allReasons): void
    {
        try {
            OrderAuditLog::create([
                'order_id' => $order->id,
                'order_item_id' => null,
                'admin_id' => null, // System-generated entry.
                'action' => 'escalation_flagged',
                'old_value' => null,
                'new_value' => ['new_reasons' => $newReasons, 'all_reasons' => array_keys($allReasons)],
                'reason' => __('labels.order_escalated') . ': ' . implode(', ', Str::replace("_", " ", $newReasons)),
            ]);
        } catch (\Throwable $e) {
            Log::warning('Escalation audit log write failed', [
                'order_id' => $order->id, 'error' => $e->getMessage(),
            ]);
        }
    }
}
