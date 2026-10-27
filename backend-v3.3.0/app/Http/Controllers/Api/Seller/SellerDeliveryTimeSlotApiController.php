<?php

namespace App\Http\Controllers\Api\Seller;

use App\Enums\Order\OrderStatusEnum;
use App\Http\Controllers\Controller;
use App\Models\DeliveryTimeSlot;
use App\Models\Order;
use App\Models\Store;
use App\Models\StoreSlotOverride;
use App\Notifications\ScheduledOrderNeedsAttention;
use App\Types\Api\ApiResponseType;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class SellerDeliveryTimeSlotApiController extends Controller
{
    /**
     * Verify that the store belongs to the authenticated seller.
     */
    protected function verifyStoreOwnership(Store $store): bool
    {
        $user = auth()->user() ?? auth('seller')->user() ?? auth('api')->user();
        $seller = $user?->seller();

        return $seller && $store->seller_id === $seller->id;
    }

    /**
     * List all delivery time slots for the specified store.
     */
    public function index(Request $request, Store $store): JsonResponse
    {
        if (! $this->verifyStoreOwnership($store)) {
            return ApiResponseType::sendJsonResponse(false, __('labels.permission_denied') ?: 'Unauthorized action on store.', null, 403);
        }

        $slots = $store->deliveryTimeSlots()->orderBy('sort_order', 'asc')->orderBy('start_time', 'asc')->get();

        return ApiResponseType::sendJsonResponse(true, __('labels.slots_fetched_successfully') ?: 'Delivery time slots fetched successfully.', $slots);
    }

    /**
     * Create a new delivery time slot for the store.
     */
    public function store(Request $request, Store $store): JsonResponse
    {
        if (! $this->verifyStoreOwnership($store)) {
            return ApiResponseType::sendJsonResponse(false, __('labels.permission_denied') ?: 'Unauthorized action on store.', null, 403);
        }

        $validated = $request->validate([
            'start_time' => ['required', 'date_format:H:i'],
            'end_time' => ['required', 'date_format:H:i', 'after:start_time'],
            'max_orders' => ['nullable', 'integer', 'min:1', 'max:500'],
            'is_active' => ['nullable', 'boolean'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
        ]);

        if ($errorMsg = $this->validateSlotOperatingHours($store, $validated['start_time'], $validated['end_time'])) {
            return ApiResponseType::sendJsonResponse(false, $errorMsg, null, 422);
        }

        if ($errorMsg = $this->validateDuplicateSlot($store, $validated['start_time'], $validated['end_time'])) {
            return ApiResponseType::sendJsonResponse(false, $errorMsg, null, 422);
        }

        $slot = $store->deliveryTimeSlots()->create([
            'start_time' => $validated['start_time'],
            'end_time' => $validated['end_time'],
            'max_orders' => $validated['max_orders'] ?? 10,
            'is_active' => $validated['is_active'] ?? true,
            'sort_order' => $validated['sort_order'] ?? 0,
        ]);

        return ApiResponseType::sendJsonResponse(true, __('labels.slot_created_successfully') ?: 'Delivery time slot created successfully.', $slot, 201);
    }

    /**
     * Update an existing delivery time slot.
     */
    public function update(Request $request, Store $store, DeliveryTimeSlot $deliveryTimeSlot): JsonResponse
    {
        if (! $this->verifyStoreOwnership($store) || $deliveryTimeSlot->store_id !== $store->id) {
            return ApiResponseType::sendJsonResponse(false, __('labels.permission_denied') ?: 'Unauthorized action on store or slot.', null, 403);
        }

        $validated = $request->validate([
            'start_time' => ['sometimes', 'required', 'date_format:H:i'],
            'end_time' => ['sometimes', 'required', 'date_format:H:i', 'after:start_time'],
            'max_orders' => ['nullable', 'integer', 'min:1', 'max:500'],
            'is_active' => ['nullable', 'boolean'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
        ]);

        $startTime = $validated['start_time'] ?? substr($deliveryTimeSlot->start_time, 0, 5);
        $endTime = $validated['end_time'] ?? substr($deliveryTimeSlot->end_time, 0, 5);

        if ($startTime >= $endTime) {
            return ApiResponseType::sendJsonResponse(false, 'End time must be after start time.', null, 422);
        }

        if ($errorMsg = $this->validateSlotOperatingHours($store, $startTime, $endTime)) {
            return ApiResponseType::sendJsonResponse(false, $errorMsg, null, 422);
        }

        if ($errorMsg = $this->validateDuplicateSlot($store, $startTime, $endTime, $deliveryTimeSlot->id)) {
            return ApiResponseType::sendJsonResponse(false, $errorMsg, null, 422);
        }

        $deliveryTimeSlot->update($validated);

        return ApiResponseType::sendJsonResponse(true, __('labels.slot_updated_successfully') ?: 'Delivery time slot updated successfully.', $deliveryTimeSlot);
    }

    /**
     * Delete a delivery time slot.
     */
    public function destroy(Request $request, Store $store, DeliveryTimeSlot $deliveryTimeSlot): JsonResponse
    {
        if (! $this->verifyStoreOwnership($store) || $deliveryTimeSlot->store_id !== $store->id) {
            return ApiResponseType::sendJsonResponse(false, __('labels.permission_denied') ?: 'Unauthorized action on store or slot.', null, 403);
        }

        $deliveryTimeSlot->delete();

        return ApiResponseType::sendJsonResponse(true, __('labels.slot_deleted_successfully') ?: 'Delivery time slot deleted successfully.');
    }

    /**
     * Validate that the slot start and end times fall within the store's operating hours.
     */
    protected function validateSlotOperatingHours(Store $store, string $startTime, string $endTime): ?string
    {
        $operatingHours = $store->metadata['operating_hours'] ?? [];
        if (empty($operatingHours) || ! is_array($operatingHours)) {
            return null;
        }

        $start = substr($startTime, 0, 5);
        $end = substr($endTime, 0, 5);

        $openDaysCount = 0;
        foreach ($operatingHours as $day => $config) {
            $isOpen = ! empty($config['is_open']) && filter_var($config['is_open'], FILTER_VALIDATE_BOOLEAN);
            if ($isOpen) {
                $openDaysCount++;
                $openTime = substr($config['open_time'] ?? '00:00', 0, 5);
                $closeTime = substr($config['close_time'] ?? '23:59', 0, 5);

                if ($start < $openTime || $end > $closeTime) {
                    $dayName = ucfirst($day);

                    return "Time slot ({$start} - {$end}) falls outside of {$dayName}'s store operating hours ({$openTime} - {$closeTime}).";
                }
            }
        }

        if ($openDaysCount === 0) {
            return 'Store has no open operating days configured. Please configure operating hours first.';
        }

        return null;
    }

    /**
     * Check if a time slot with identical start and end times already exists for the store.
     */
    protected function validateDuplicateSlot(Store $store, string $startTime, string $endTime, ?int $ignoreSlotId = null): ?string
    {
        $start = substr($startTime, 0, 5);
        $end = substr($endTime, 0, 5);

        $exists = $store->deliveryTimeSlots()
            ->when($ignoreSlotId !== null, fn ($query) => $query->where('id', '!=', $ignoreSlotId))
            ->get()
            ->contains(function ($slot) use ($start, $end) {
                return substr($slot->start_time, 0, 5) === $start && substr($slot->end_time, 0, 5) === $end;
            });

        if ($exists) {
            return __('labels.slot_already_exists') ?: 'A delivery time slot with these exact start and end times already exists.';
        }

        return null;
    }

    /**
     * List all custom slot overrides for the specified store.
     */
    public function listOverrides(Request $request, Store $store): JsonResponse
    {
        if (! $this->verifyStoreOwnership($store)) {
            return ApiResponseType::sendJsonResponse(false, __('labels.permission_denied') ?: 'Unauthorized action on store.', null, 403);
        }

        $overrides = StoreSlotOverride::where('store_id', $store->id)
            ->orderBy('override_date', 'asc')
            ->orderBy('start_time', 'asc')
            ->get();

        return ApiResponseType::sendJsonResponse(true, 'Overrides fetched successfully.', $overrides);
    }

    /**
     * Resolve all virtual slots for the seller overrides calendar view (ignoring global disabled switches).
     */
    public function resolvedSlots(Request $request, Store $store): JsonResponse
    {
        if (! $this->verifyStoreOwnership($store)) {
            return ApiResponseType::sendJsonResponse(false, __('labels.permission_denied') ?: 'Unauthorized action on store.', null, 403);
        }

        $request->validate([
            'date' => ['required', 'date'],
        ]);

        $dateString = $request->input('date');
        $tz = $store->timezone ?? config('app.timezone', 'UTC');
        $targetDate = Carbon::parse($dateString, $tz)->startOfDay();

        $dayName = strtolower($targetDate->format('l'));
        $operatingHours = $store->metadata['operating_hours'] ?? null;
        $dayConfig = is_array($operatingHours) && isset($operatingHours[$dayName]) ? $operatingHours[$dayName] : null;

        if (! $dayConfig || (isset($dayConfig['is_open']) && ! filter_var($dayConfig['is_open'], FILTER_VALIDATE_BOOLEAN))) {
            return ApiResponseType::sendJsonResponse(true, "Store is closed on {$targetDate->format('l')}.", [
                'is_open' => false,
                'slots' => [],
            ]);
        }

        if (empty($dayConfig['open_time']) || empty($dayConfig['close_time'])) {
            return ApiResponseType::sendJsonResponse(true, 'Invalid operating hours configured for this day.', [
                'is_open' => true,
                'slots' => [],
            ]);
        }

        $openTime = Carbon::parse($targetDate->format('Y-m-d').' '.$dayConfig['open_time'], $tz);
        $closeTime = Carbon::parse($targetDate->format('Y-m-d').' '.$dayConfig['close_time'], $tz);

        if ($closeTime->lte($openTime)) {
            $closeTime->addDay();
        }

        $duration = (int) ($request->input('duration') ?: ($store->scheduled_delivery_slot_duration ?? 60));

        // Fetch overrides
        $overrides = StoreSlotOverride::where('store_id', $store->id)
            ->whereDate('override_date', $dateString)
            ->get();

        // Fetch booked orders counts
        $bookedOrdersCounts = Order::whereHas('items', function ($query) use ($store) {
            $query->where('store_id', $store->id);
        })
            ->whereDate('scheduled_date', $dateString)
            ->whereNotIn('status', [
                OrderStatusEnum::CANCELLED(),
                OrderStatusEnum::REJECTED_BY_SELLER(),
                OrderStatusEnum::FAILED(),
            ])
            ->selectRaw('scheduled_slot_start_time, scheduled_slot_end_time, COUNT(*) as count')
            ->groupBy('scheduled_slot_start_time', 'scheduled_slot_end_time')
            ->get()
            ->keyBy(function ($item) {
                $start = $item->scheduled_slot_start_time;
                $end = $item->scheduled_slot_end_time;
                if ($start instanceof Carbon) {
                    $start = $start->format('H:i:s');
                }
                if ($end instanceof Carbon) {
                    $end = $end->format('H:i:s');
                }

                return substr($start, 0, 5).'-'.substr($end, 0, 5);
            });

        $slots = [];
        $current = $openTime->copy();

        while ($current->copy()->addMinutes($duration)->lte($closeTime)) {
            $start = $current->copy();
            $end = $current->copy()->addMinutes($duration);

            $startStr = $start->format('H:i:s');
            $endStr = $end->format('H:i:s');

            // Find override
            $override = $overrides->first(function ($o) use ($startStr, $endStr) {
                return substr($o->start_time, 0, 5) === substr($startStr, 0, 5) && substr($o->end_time, 0, 5) === substr($endStr, 0, 5);
            });

            $isBlocked = $override ? (bool) $override->is_blocked : false;
            $overrideCapacity = $override ? $override->capacity : null;
            $defaultCapacity = (int) ($store->scheduled_delivery_slot_capacity ?? 10);

            $key = substr($startStr, 0, 5).'-'.substr($endStr, 0, 5);
            $bookedCount = isset($bookedOrdersCounts[$key]) ? (int) $bookedOrdersCounts[$key]->count : 0;

            $slots[] = [
                'start_time' => $startStr,
                'end_time' => $endStr,
                'is_blocked' => $isBlocked,
                'override_capacity' => $overrideCapacity,
                'default_capacity' => $defaultCapacity,
                'booked_count' => $bookedCount,
                'override_id' => $override ? $override->id : null,
            ];

            $current->addMinutes($duration);
        }

        return ApiResponseType::sendJsonResponse(true, 'Resolved slots retrieved successfully.', [
            'is_open' => true,
            'slots' => $slots,
        ]);
    }

    /**
     * Create or update a custom slot override (blocking or capacity adjustment).
     * Returns a warning response if active future orders exist in the slot and 'confirmed' is false.
     */
    public function storeOverride(Request $request, Store $store): JsonResponse
    {
        if (! $this->verifyStoreOwnership($store)) {
            return ApiResponseType::sendJsonResponse(false, __('labels.permission_denied') ?: 'Unauthorized action on store.', null, 403);
        }

        $validated = $request->validate([
            'override_date' => ['required', 'date', 'after_or_equal:today'],
            'start_time' => ['required', 'date_format:H:i:s'],
            'end_time' => ['required', 'date_format:H:i:s', 'after:start_time'],
            'is_blocked' => ['required', 'boolean'],
            'capacity' => ['nullable', 'integer', 'min:0'],
            'confirmed' => ['nullable', 'boolean'],
        ]);

        $dateStr = $validated['override_date'];
        $startTime = $validated['start_time'];
        $endTime = $validated['end_time'];

        // Count affected active orders in this slot
        $affectedOrdersQuery = Order::whereHas('items', function ($query) use ($store) {
            $query->where('store_id', $store->id);
        })
            ->where('scheduled_date', $dateStr)
            ->where('scheduled_slot_start_time', $startTime)
            ->where('scheduled_slot_end_time', $endTime)
            ->whereNotIn('status', [
                OrderStatusEnum::CANCELLED(),
                OrderStatusEnum::REJECTED_BY_SELLER(),
                OrderStatusEnum::FAILED(),
                OrderStatusEnum::DELIVERED(),
            ]);

        $affectedCount = $affectedOrdersQuery->count();

        // Return warning if blocking or capacity is less than current orders, and not confirmed yet
        if ($affectedCount > 0 && empty($validated['confirmed'])) {
            if ($validated['is_blocked'] || (isset($validated['capacity']) && $affectedCount > $validated['capacity'])) {
                return ApiResponseType::sendJsonResponse(
                    true,
                    'Warning: active orders exist in this slot.',
                    [
                        'warning' => true,
                        'affected_orders_count' => $affectedCount,
                    ],
                    202
                );
            }
        }

        // Save override
        $override = StoreSlotOverride::updateOrCreate(
            [
                'store_id' => $store->id,
                'override_date' => $dateStr,
                'start_time' => $startTime,
                'end_time' => $endTime,
            ],
            [
                'is_blocked' => $validated['is_blocked'],
                'capacity' => $validated['capacity'] ?? 0,
            ]
        );

        // If slot is blocked, flag affected orders for reschedule immediately
        if ($validated['is_blocked']) {
            $affectedOrders = $affectedOrdersQuery->get();
            foreach ($affectedOrders as $order) {
                $deadline = now()->addHours((int) ($store->reschedule_deadline_hours ?? 24));

                $order->update([
                    'reschedule_status' => 'needs_reschedule',
                    'reschedule_reason' => 'slot_unavailable',
                    'reschedule_deadline' => $deadline,
                ]);

                try {
                    $order->user?->notify(new ScheduledOrderNeedsAttention($order, 'slot_unavailable'));
                } catch (\Throwable $e) {
                    Log::warning("SellerOverride: Failed to notify user for order #{$order->id}: {$e->getMessage()}");
                }
            }
        }

        return ApiResponseType::sendJsonResponse(true, 'Slot override saved successfully.', $override);
    }

    /**
     * Delete an override.
     */
    public function deleteOverride(Request $request, Store $store, int $overrideId): JsonResponse
    {
        if (! $this->verifyStoreOwnership($store)) {
            return ApiResponseType::sendJsonResponse(false, __('labels.permission_denied') ?: 'Unauthorized action on store.', null, 403);
        }

        $override = StoreSlotOverride::where('store_id', $store->id)
            ->where('id', $overrideId)
            ->first();

        if (! $override) {
            return ApiResponseType::sendJsonResponse(false, 'Override not found.', null, 404);
        }

        $override->delete();

        return ApiResponseType::sendJsonResponse(true, 'Slot override deleted successfully.');
    }
}
