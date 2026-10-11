<?php

namespace App\Console\Commands;

use App\Enums\Order\OrderStatusEnum;
use App\Models\Order;
use App\Services\PreparationSchedulerService;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class DispatchScheduledOrders extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'orders:dispatch-scheduled';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Automatically dispatch scheduled delivery orders to the rider pool when approaching their delivery slot';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $this->info('Checking for scheduled orders ready for dispatch...');

        $eligibleStatuses = [
            OrderStatusEnum::ACCEPTED_BY_SELLER(),
            OrderStatusEnum::PREPARING(),
        ];

        $orders = Order::where(function ($query) {
            $query->whereNotNull('delivery_time_slot_id')
                ->orWhereNotNull('scheduled_date');
        })
            ->whereNull('delivery_boy_id')
            ->whereIn('status', $eligibleStatuses)
            ->with(['deliveryTimeSlot', 'items.product', 'items.store'])
            ->get();

        $dispatchedCount = 0;
        $unlockedCount = 0;

        foreach ($orders as $order) {
            if (! $order->scheduled_date && ! $order->deliveryTimeSlot) {
                continue;
            }

            // Determine store timezone & buffer
            $store = $order->items->first()?->store;
            $storeTz = $store?->timezone ?? config('app.timezone', 'UTC');
            $bufferMinutes = (int) ($store?->scheduled_delivery_buffer_minutes ?? 60);
            $now = Carbon::now($storeTz);

            if ($order->scheduled_date && $order->scheduled_slot_start_time) {
                // Dynamic slot based scheduled delivery
                $slotStartString = $order->scheduled_date->format('Y-m-d').' '.$order->scheduled_slot_start_time;
                $slotEndString = $order->scheduled_date->format('Y-m-d').' '.$order->scheduled_slot_end_time;

                $slotStart = Carbon::parse($slotStartString, $storeTz);
                $slotEnd = Carbon::parse($slotEndString, $storeTz);

                // Preparation opens according to buffer minutes
                $prepThreshold = $slotStart->copy()->subMinutes($bufferMinutes);
                // Rider pool dispatch threshold (e.g. 45 mins before slot)
                $dispatchThreshold = $slotStart->copy()->subMinutes(45);
            } elseif ($order->deliveryTimeSlot && $order->scheduled_date) {
                // Time-slot based scheduled delivery
                $slotStartString = $order->scheduled_date->format('Y-m-d').' '.$order->deliveryTimeSlot->start_time;
                $slotEndString = $order->scheduled_date->format('Y-m-d').' '.$order->deliveryTimeSlot->end_time;

                $slotStart = Carbon::parse($slotStartString, $storeTz);
                $slotEnd = Carbon::parse($slotEndString, $storeTz);

                // Preparation opens according to buffer minutes
                $prepThreshold = $slotStart->copy()->subMinutes($bufferMinutes);
                // Rider pool dispatch threshold (e.g. 45 mins before slot)
                $dispatchThreshold = $slotStart->copy()->subMinutes(45);
            } elseif ($order->scheduled_date) {
                // Date-only scheduled delivery
                $dayName = strtolower($order->scheduled_date->format('l'));
                $openTime = $store?->metadata['operating_hours'][$dayName]['open_time'] ?? '09:00:00';
                $closeTime = $store?->metadata['operating_hours'][$dayName]['close_time'] ?? '23:59:59';

                $slotStart = Carbon::parse($order->scheduled_date->format('Y-m-d').' '.$openTime, $storeTz);
                $slotEnd = Carbon::parse($order->scheduled_date->format('Y-m-d').' '.$closeTime, $storeTz);

                $prepThreshold = $slotStart->copy()->subMinutes($bufferMinutes);
                $dispatchThreshold = $slotStart->copy()->subMinutes(60);
            } else {
                continue;
            }

            $statusVal = is_string($order->status) ? $order->status : ($order->status->value ?? $order->status);

            if ($now->gte($dispatchThreshold) && $now->lte($slotEnd)) {
                if (in_array($statusVal, [OrderStatusEnum::ACCEPTED_BY_SELLER(), OrderStatusEnum::PREPARING()])) {
                    $order->update([
                        'status' => OrderStatusEnum::READY_FOR_PICKUP(),
                    ]);

                    Log::info("DispatchScheduledOrders: Dispatched scheduled order #{$order->id} (UUID: {$order->uuid}) to rider pool.", [
                        'order_id' => $order->id,
                        'scheduled_date' => $order->scheduled_date?->format('Y-m-d'),
                        'slot' => $order->scheduled_time_slot_label ?? 'Date Only',
                        'now' => $now->toDateTimeString(),
                        'dispatch_threshold' => $dispatchThreshold->toDateTimeString(),
                    ]);

                    $dispatchedCount++;
                }
            } elseif ($statusVal === OrderStatusEnum::ACCEPTED_BY_SELLER()) {
                $prepTime = $order->preparation_scheduled_at
                    ? Carbon::parse($order->preparation_scheduled_at, $storeTz)
                    : $slotStart->copy()->subMinutes(app(PreparationSchedulerService::class)->getOrderPrepTime($order));
                if ($now->gte($prepTime) && $now->lte($slotEnd)) {
                    Log::info("DispatchScheduledOrders: Preparation window is now OPEN for scheduled order #{$order->id} (UUID: {$order->uuid}). Seller can now mark as Preparing.", [
                        'order_id' => $order->id,
                        'scheduled_date' => $order->scheduled_date?->format('Y-m-d'),
                        'slot' => $order->scheduled_time_slot_label ?? 'Date Only',
                        'now' => $now->toDateTimeString(),
                        'prep_threshold' => $prepTime->toDateTimeString(),
                    ]);
                    $unlockedCount++;
                }
            }
        }

        $this->info("Unlocked {$unlockedCount} order preparation window(s). Dispatched {$dispatchedCount} scheduled order(s) to rider pool.");

        return Command::SUCCESS;
    }
}
