<?php

namespace App\Services;

use App\Models\Order;
use App\Models\Store;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class PreparationSchedulerService
{
    /**
     * Resolve the standard preparation duration (in minutes) for a given order.
     * Uses the maximum base_prep_time of products in the order, falling back to the store default.
     */
    public function getOrderPrepTime(Order $order): int
    {
        $maxPrep = 0;
        foreach ($order->items as $item) {
            $product = $item->product;
            if ($product && (int) ($product->base_prep_time) > $maxPrep) {
                $maxPrep = (int) $product->base_prep_time;
            }
        }

        // Fallback to store default preparation time if no product has standard preparation time configured
        if ($maxPrep === 0) {
            $store = $order->items->first()?->store;
            $maxPrep = (int) ($store->order_preparation_time ?? 15);
        }

        return $maxPrep;
    }

    /**
     * Calculate and schedule staggered preparation start times for all orders in a specific slot
     * so that the number of concurrent preparations at any given minute does not exceed prep_concurrency.
     */
    public function schedulePrepForSlot(Store $store, string $dateString, ?string $startTime): void
    {
        if (empty($startTime)) {
            return;
        }

        $concurrencyLimit = (int) ($store->prep_concurrency ?? 2);
        if ($concurrencyLimit <= 0) {
            $concurrencyLimit = 2;
        }

        // Fetch all active scheduled orders in this slot for this store, sorted by creation date (FIFO)
        $orders = Order::whereHas('items', function ($query) use ($store) {
                $query->where('store_id', $store->id);
            })
            ->whereDate('scheduled_date', $dateString)
            ->where('scheduled_slot_start_time', $startTime)
            ->whereNotIn('status', [
                \App\Enums\Order\OrderStatusEnum::CANCELLED(),
                \App\Enums\Order\OrderStatusEnum::REJECTED_BY_SELLER(),
                \App\Enums\Order\OrderStatusEnum::FAILED(),
            ])
            ->with(['items.product', 'items.store'])
            ->get()
            ->sortBy('created_at');

        if ($orders->isEmpty()) {
            return;
        }

        $tz = $store->timezone ?? config('app.timezone', 'UTC');
        $slotStart = Carbon::parse($dateString . ' ' . $startTime, $tz);

        $allocatedIntervals = [];

        DB::transaction(function () use ($orders, $slotStart, $concurrencyLimit, &$allocatedIntervals) {
            foreach ($orders as $order) {
                $prepDuration = $this->getOrderPrepTime($order);

                // Proposed standard preparation interval: [Slot Start - Prep Duration, Slot Start]
                $start = $slotStart->copy()->subMinutes($prepDuration);
                $end = $slotStart->copy();

                // Shift preparation start time earlier if prep concurrency is exceeded at any minute
                while (!$this->checkConcurrencyFits($start, $end, $allocatedIntervals, $concurrencyLimit)) {
                    $start->subMinute();
                    $end->subMinute();
                }

                // Add to allocated intervals list
                $allocatedIntervals[] = [
                    'start' => $start->copy(),
                    'end' => $end->copy(),
                ];

                // Persist the staggered preparation time back to the order
                $order->update([
                    'preparation_scheduled_at' => $start,
                ]);

                Log::info("PreparationSchedulerService: Scheduled preparation for order #{$order->id} to start at {$start->toDateTimeString()} (Prep duration: {$prepDuration} mins).");
            }
        });
    }

    /**
     * Check if a proposed interval fits within the concurrency limit at every minute.
     */
    protected function checkConcurrencyFits(Carbon $start, Carbon $end, array $allocatedIntervals, int $limit): bool
    {
        $current = $start->copy();
        while ($current->lt($end)) {
            $overlapCount = 0;
            foreach ($allocatedIntervals as $allocated) {
                if ($current->gte($allocated['start']) && $current->lt($allocated['end'])) {
                    $overlapCount++;
                }
            }
            if ($overlapCount >= $limit) {
                return false;
            }
            $current->addMinute();
        }
        return true;
    }
}
