<?php

namespace App\Http\Controllers\Api\User;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Services\DeliveryScheduleService;
use App\Services\OrderService;
use App\Services\PreparationSchedulerService;
use App\Types\Api\ApiResponseType;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

/**
 * Handles customer recovery actions for scheduled orders flagged as needs_reschedule.
 * Provides endpoints to reschedule to a new slot or cancel with wallet refund.
 */
class ScheduledOrderRecoveryController extends Controller
{
    public function __construct(
        protected OrderService $orderService,
        protected DeliveryScheduleService $deliveryScheduleService,
        protected PreparationSchedulerService $prepSchedulerService,
    ) {}

    public function slots(Request $request, int $order): JsonResponse
    {
        try {
            $request->validate([
                'date' => 'required|date|after_or_equal:today',
            ]);

            $user = auth()->user();
            $orderModel = Order::where('id', $order)
                ->where('user_id', $user->id)
                ->with(['deliveryTimeSlot', 'items.store', 'items.product'])
                ->first();

            if (! $orderModel) {
                return ApiResponseType::sendJsonResponse(false, 'Order not found', [], 404);
            }

            if ($orderModel->reschedule_status !== 'needs_reschedule') {
                return ApiResponseType::sendJsonResponse(false, 'This order is not pending reschedule', [], 422);
            }

            if ($orderModel->reschedule_deadline && $orderModel->reschedule_deadline->lt(now())) {
                return ApiResponseType::sendJsonResponse(false, 'The reschedule deadline has expired. Please cancel the order instead.', [], 422);
            }

            $store = $orderModel->items->first()?->store;
            if (! $store) {
                return ApiResponseType::sendJsonResponse(false, 'Store not found for this order', [], 404);
            }

            $date = $request->input('date');
            $result = $this->deliveryScheduleService->getAvailableSlotsForStore($store, $date);
            $result['slots'] = $this->filterRescheduleSlots($result['slots'] ?? [], $orderModel, $store, $date);

            if (empty($result['slots'])) {
                $result['message'] = 'No available delivery time slots found for this store.';
            }

            return ApiResponseType::sendJsonResponse(
                true,
                $result['message'] ?? 'Delivery time slots retrieved successfully.',
                $result
            );
        } catch (ValidationException $e) {
            return ApiResponseType::sendJsonResponse(false, $e->getMessage(), $e->errors(), 422);
        } catch (\Throwable $e) {
            Log::error("ScheduledOrderRecovery: Slots failed for order #{$order}: {$e->getMessage()}");

            return ApiResponseType::sendJsonResponse(false, $e->getMessage(), [], 500);
        }
    }

    /**
     * Reschedule a flagged order to a new valid delivery slot.
     * Validates slot availability, updates order dates, clears reschedule flags,
     * and recalculates staggered preparation schedules.
     */
    public function reschedule(Request $request, int $order): JsonResponse
    {
        try {
            $request->validate([
                'scheduled_date' => 'required|date|after_or_equal:today',
                'scheduled_slot_start_time' => 'required|date_format:H:i:s',
                'scheduled_slot_end_time' => 'required|date_format:H:i:s|after:scheduled_slot_start_time',
            ]);

            $user = auth()->user();

            // Find and validate order ownership
            $orderModel = Order::where('id', $order)
                ->where('user_id', $user->id)
                ->with(['deliveryTimeSlot', 'items.store', 'items.product'])
                ->first();

            if (! $orderModel) {
                return ApiResponseType::sendJsonResponse(false, 'Order not found', [], 404);
            }

            // Ensure order is flagged for reschedule
            if ($orderModel->reschedule_status !== 'needs_reschedule') {
                return ApiResponseType::sendJsonResponse(false, 'This order is not pending reschedule', [], 422);
            }

            // Check if reschedule deadline has expired
            if ($orderModel->reschedule_deadline && $orderModel->reschedule_deadline->lt(now())) {
                return ApiResponseType::sendJsonResponse(false, 'The reschedule deadline has expired. Please cancel the order instead.', [], 422);
            }

            // Validate the new slot is available via DeliveryScheduleService
            $store = $orderModel->items->first()?->store;
            if (! $store) {
                return ApiResponseType::sendJsonResponse(false, 'Store not found for this order', [], 404);
            }

            $newDate = $request->input('scheduled_date');
            $newStartTime = $request->input('scheduled_slot_start_time');
            $newEndTime = $request->input('scheduled_slot_end_time');
            $storeTz = $store->timezone ?? config('app.timezone', 'UTC');
            $slotStart = Carbon::parse($newDate.' '.$newStartTime, $storeTz);
            $leadTimeMinutes = $this->resolveOrderSlotLeadTimeMinutes($orderModel, $store);

            if ($slotStart->lte(Carbon::now($storeTz)->addMinutes($leadTimeMinutes))) {
                return ApiResponseType::sendJsonResponse(false, 'The selected slot is no longer available. Please choose a later slot.', [], 422);
            }

            $currentSlotStart = $this->resolveCurrentScheduledSlotStart($orderModel, $store);
            if ($currentSlotStart && $slotStart->lt($currentSlotStart)) {
                return ApiResponseType::sendJsonResponse(false, 'Please choose a slot at or after the current scheduled slot.', [], 422);
            }

            // Validate slot capacity using DeliveryScheduleService
            $slots = $this->deliveryScheduleService->getAvailableSlotsForStore($store, $newDate);
            $slotAvailable = false;
            foreach ($slots['slots'] ?? [] as $slot) {
                if (($slot['start_time'] ?? '') === $newStartTime
                    && ($slot['end_time'] ?? '') === $newEndTime
                    && ($slot['remaining_capacity'] ?? 0) > 0) {
                    $slotAvailable = true;
                    break;
                }
            }

            if (! $slotAvailable) {
                return ApiResponseType::sendJsonResponse(false, 'The selected slot is not available or is full. Please choose a different slot.', [], 422);
            }

            // Update the order with the new schedule and clear reschedule flags
            DB::transaction(function () use ($orderModel, $store, $newDate, $newStartTime, $newEndTime) {
                $newSlotLabel = Carbon::parse($newStartTime)->format('h:i A').' - '.Carbon::parse($newEndTime)->format('h:i A');

                $orderModel->update([
                    'scheduled_date' => $newDate,
                    'delivery_time_slot_id' => null,
                    'scheduled_time_slot_label' => $newSlotLabel,
                    'scheduled_slot_start_time' => $newStartTime,
                    'scheduled_slot_end_time' => $newEndTime,
                    'reschedule_status' => null,
                    'reschedule_reason' => null,
                    'reschedule_deadline' => null,
                ]);

                // Recalculate staggered preparation schedule for the new slot
                $this->prepSchedulerService->schedulePrepForSlot(
                    $store,
                    $newDate,
                    $newStartTime
                );
            });

            return ApiResponseType::sendJsonResponse(true, 'Order rescheduled successfully', [
                'order_id' => $orderModel->id,
                'new_scheduled_date' => $newDate,
                'new_slot' => $newStartTime.' - '.$newEndTime,
            ]);
        } catch (ValidationException $e) {
            return ApiResponseType::sendJsonResponse(false, $e->getMessage(), $e->errors(), 422);
        } catch (\Throwable $e) {
            Log::error("ScheduledOrderRecovery: Reschedule failed for order #{$order}: {$e->getMessage()}");

            return ApiResponseType::sendJsonResponse(false, $e->getMessage(), [], 500);
        }
    }

    private function resolveOrderSlotLeadTimeMinutes(Order $order, $store): int
    {
        $bufferMinutes = (int) ($store->scheduled_delivery_buffer_minutes ?? 60);
        $prepMinutes = 0;

        $order->loadMissing('items.product');
        foreach ($order->items as $item) {
            $productPrep = (int) ($item->product?->base_prep_time ?? 0);
            if ($productPrep > $prepMinutes) {
                $prepMinutes = $productPrep;
            }
        }

        if ($prepMinutes <= 0) {
            $prepMinutes = (int) ($store->order_preparation_time ?? 0);
        }

        return max($bufferMinutes, $prepMinutes);
    }

    private function filterRescheduleSlots(array $slots, Order $order, $store, string $date): array
    {
        $storeTz = $store->timezone ?? config('app.timezone', 'UTC');
        $currentSlotStart = $this->resolveCurrentScheduledSlotStart($order, $store);
        $leadTimeCutoff = Carbon::now($storeTz)->addMinutes($this->resolveOrderSlotLeadTimeMinutes($order, $store));

        return array_values(array_filter($slots, function ($slot) use ($currentSlotStart, $date, $storeTz, $leadTimeCutoff) {
            if (empty($slot['start_time'])) {
                return false;
            }

            $slotStart = Carbon::parse($date.' '.$slot['start_time'], $storeTz);

            if ($slotStart->lte($leadTimeCutoff)) {
                return false;
            }

            return ! $currentSlotStart || $slotStart->gte($currentSlotStart);
        }));
    }

    private function resolveCurrentScheduledSlotStart(Order $order, $store): ?Carbon
    {
        if (! $order->scheduled_date) {
            return null;
        }

        $storeTz = $store->timezone ?? config('app.timezone', 'UTC');
        $slotStartTime = $order->scheduled_slot_start_time;

        if (empty($slotStartTime) && $order->deliveryTimeSlot) {
            $slotStartTime = $order->deliveryTimeSlot->start_time;
        }

        if (empty($slotStartTime)) {
            return null;
        }

        return Carbon::parse($order->scheduled_date->format('Y-m-d').' '.$slotStartTime, $storeTz);
    }

    /**
     * Cancel a flagged scheduled order with full wallet refund (non-COD only).
     */
    public function cancel(Request $request, int $order): JsonResponse
    {
        try {
            $user = auth()->user();

            // Find and validate order ownership
            $orderModel = Order::where('id', $order)
                ->where('user_id', $user->id)
                ->first();

            if (! $orderModel) {
                return ApiResponseType::sendJsonResponse(false, 'Order not found', [], 404);
            }

            // Ensure order is flagged for reschedule
            if ($orderModel->reschedule_status !== 'needs_reschedule') {
                return ApiResponseType::sendJsonResponse(false, 'This order is not pending reschedule', [], 422);
            }

            // Cancel and refund via OrderService
            $this->orderService->cancelScheduledOrderWithRefund(
                $orderModel,
                'Cancelled by customer during recovery'
            );

            return ApiResponseType::sendJsonResponse(true, 'Order cancelled and refund processed successfully', [
                'order_id' => $orderModel->id,
            ]);
        } catch (\Throwable $e) {
            Log::error("ScheduledOrderRecovery: Cancel failed for order #{$order}: {$e->getMessage()}");

            return ApiResponseType::sendJsonResponse(false, $e->getMessage(), [], 500);
        }
    }
}
