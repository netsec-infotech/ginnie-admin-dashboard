<?php

namespace App\Services;

use App\Enums\Order\OrderStatusEnum;
use App\Enums\SettingTypeEnum;
use App\Models\Cart;
use App\Models\DeliveryTimeSlot;
use App\Models\Order;
use App\Models\Store;
use App\Models\StoreSlotOverride;
use Carbon\Carbon;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Service handling scheduled delivery capacity checks, time slot discovery, and single-store cart validation.
 */
class DeliveryScheduleService
{
    /**
     * Retrieve available delivery time slots for a specific store on a target date.
     * Evaluates store-level enable toggle, advance booking windows, cutoff buffer times, and dynamic capacity.
     *
     * @return array{is_enabled: bool, message?: string, slots: array<int, array{id: int, start_time: string, end_time: string, label: string, remaining_capacity: int, is_available: bool, reason?: string}>}
     */
    public function getAvailableSlotsForStore(Store $store, string $dateString): array
    {
        // 1. Verify if scheduled delivery is enabled on this store
        if (! $store->is_scheduled_delivery_enabled) {
            return [
                'is_enabled' => false,
                'message' => __('messages.scheduled_delivery_disabled_for_store') ?? 'Scheduled delivery is currently disabled for this store.',
                'slots' => [],
            ];
        }

        if ($store->is_temporarily_closed) {
            return [
                'is_enabled' => true,
                'message' => $store->temporary_closure_reason ?? __('messages.store_temporarily_closed') ?? 'Store is temporarily closed.',
                'slots' => [],
            ];
        }

        $tz = $store->timezone ?? config('app.timezone', 'UTC');
        $now = Carbon::now($tz);
        $targetDate = Carbon::parse($dateString, $tz)->startOfDay();
        $today = Carbon::now($tz)->startOfDay();

        // 1. Verify scheduled delivery is globally enabled
        if (! $this->isScheduledDeliveryGloballyEnabled()) {
            return [
                'is_enabled' => false,
                'message' => 'Scheduled delivery is temporarily unavailable.',
                'slots' => [],
            ];
        }

        // 2. Verify target date is within allowed advance booking days [today, today + max_advance_booking_days]
        if ($targetDate->lt($today)) {
            return [
                'is_enabled' => true,
                'message' => __('messages.scheduled_delivery_date_in_past') ?? 'Cannot schedule delivery for a past date.',
                'slots' => [],
            ];
        }

        $maxDays = $this->getMaxAdvanceBookingDays($store);
        $maxDate = $today->copy()->addDays($maxDays);
        if ($targetDate->gt($maxDate)) {
            return [
                'is_enabled' => true,
                'message' => __('messages.scheduled_delivery_date_too_far') ?? "Cannot schedule delivery more than {$maxDays} days in advance.",
                'slots' => [],
            ];
        }

        $dateValidation = $this->validateScheduledDateForStore($store, $dateString);
        if (! $dateValidation['is_valid']) {
            return [
                'is_enabled' => true,
                'message' => $dateValidation['message'],
                'slots' => [],
            ];
        }

        if (empty($store->scheduled_delivery_slot_duration) || empty($store->metadata['operating_hours'])) {
            return [
                'is_enabled' => true,
                'message' => __('messages.scheduled_delivery_no_slots') ?? 'Scheduled delivery is date-only for this store.',
                'slots' => [],
            ];
        }

        $dayName = strtolower($targetDate->format('l'));
        $operatingHours = $store->metadata['operating_hours'] ?? null;
        $dayConfig = is_array($operatingHours) && isset($operatingHours[$dayName]) ? $operatingHours[$dayName] : null;

        if (! $dayConfig || (isset($dayConfig['is_open']) && ! filter_var($dayConfig['is_open'], FILTER_VALIDATE_BOOLEAN))) {
            return [
                'is_enabled' => true,
                'message' => __('messages.store_closed_on_selected_day') ?? "Store is closed on {$targetDate->format('l')}.",
                'slots' => [],
            ];
        }

        if (empty($dayConfig['open_time']) || empty($dayConfig['close_time'])) {
            return [
                'is_enabled' => true,
                'message' => __('messages.invalid_operating_hours') ?? 'Invalid operating hours configured for this day.',
                'slots' => [],
            ];
        }

        $openTime = Carbon::parse($targetDate->format('Y-m-d').' '.$dayConfig['open_time'], $tz);
        $closeTime = Carbon::parse($targetDate->format('Y-m-d').' '.$dayConfig['close_time'], $tz);

        if ($closeTime->lte($openTime)) {
            $closeTime->addDay();
        }

        $duration = (int) ($store->scheduled_delivery_slot_duration ?? 60);
        $cutoffThreshold = Carbon::now($tz)->addMinutes((int) ($store->scheduled_delivery_buffer_minutes ?? 60));
        $isToday = $targetDate->isSameDay($today);

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

        $formattedSlots = [];
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

            if ($override && $override->is_blocked) {
                $current->addMinutes($duration);

                continue;
            }

            // Check break overlap
            $hasBreak = ! empty($dayConfig['has_break']) && filter_var($dayConfig['has_break'], FILTER_VALIDATE_BOOLEAN);
            if ($hasBreak) {
                $breakStartStr = $dayConfig['break_start_time'] ?? null;
                $breakEndStr = $dayConfig['break_end_time'] ?? null;
                if ($breakStartStr && $breakEndStr) {
                    $breakStart = Carbon::parse($targetDate->format('Y-m-d').' '.$breakStartStr, $tz);
                    $breakEnd = Carbon::parse($targetDate->format('Y-m-d').' '.$breakEndStr, $tz);
                    if ($breakEnd->lte($breakStart)) {
                        $breakEnd->addDay();
                    }
                    if ($start->lt($breakEnd) && $end->gt($breakStart)) {
                        $current->addMinutes($duration);

                        continue;
                    }
                }
            }

            $slotCapacity = ($override && $override->capacity !== null)
                ? (int) $override->capacity
                : (int) ($store->scheduled_delivery_slot_capacity ?? 10);

            $key = substr($startStr, 0, 5).'-'.substr($endStr, 0, 5);
            $bookedCount = isset($bookedOrdersCounts[$key]) ? (int) $bookedOrdersCounts[$key]->count : 0;
            $remainingCapacity = max(0, $slotCapacity - $bookedCount);

            // Hide slot if reached capacity
            if ($remainingCapacity <= 0) {
                $current->addMinutes($duration);

                continue;
            }

            if ($isToday && $start->lte($cutoffThreshold)) {
                $current->addMinutes($duration);

                continue;
            }

            $label = $start->format('h:i A').' - '.$end->format('h:i A');

            $formattedSlots[] = [
                'label' => $label,
                'start_time' => $startStr,
                'end_time' => $endStr,
                'remaining_capacity' => $remainingCapacity,
                'max_orders_reached' => $remainingCapacity <= 0,
                'is_available' => true,
                'reason' => null,
            ];

            $current->addMinutes($duration);
        }

        return [
            'is_enabled' => true,
            'message' => empty($formattedSlots) ? 'No available delivery time slots found for this store.' : null,
            'slots' => $formattedSlots,
        ];
    }

    /**
     * Validate whether a user's cart satisfies the single-store requirement for scheduled delivery.
     *
     * @param  Cart|JsonResource|mixed  $cart
     * @return array{is_valid: bool, message?: string, store_id?: int}
     */
    public function validateCartForScheduledDelivery($cart): array
    {
        if ($cart instanceof JsonResource) {
            $cart = $cart->resource instanceof Cart
                ? $cart->resource
                : ($cart->resource['cart'] ?? ($cart['cart'] ?? null));
        }

        if (! $cart instanceof Cart || ! $cart->items || $cart->items->isEmpty()) {
            return [
                'is_valid' => false,
                'message' => __('messages.cart_is_empty') ?? 'Cart is empty.',
            ];
        }

        $distinctStores = $cart->items->pluck('store_id')->unique();

        if ($distinctStores->count() > 1) {
            return [
                'is_valid' => false,
                'message' => __('messages.scheduled_delivery_single_store_only') ?? 'Scheduled delivery is only allowed when ordering from a single store. Please split your cart or select regular delivery.',
            ];
        }

        return [
            'is_valid' => true,
            'store_id' => $distinctStores->first(),
        ];
    }

    protected function isScheduledDeliveryGloballyEnabled(): bool
    {
        $settingService = app(SettingService::class);
        $settingsResource = $settingService->getSettingByVariable(SettingTypeEnum::SCHEDULED_DELIVERY());
        $settings = $settingsResource?->toArray(request())['value'] ?? [];

        return (bool) ($settings['enableScheduledDelivery'] ?? true);
    }

    protected function getMaxAdvanceBookingDays(Store $store): int
    {
        $settingService = app(SettingService::class);
        $settingsResource = $settingService->getSettingByVariable(SettingTypeEnum::SCHEDULED_DELIVERY());
        $settings = $settingsResource?->toArray(request())['value'] ?? [];
        $globalLimit = (int) ($settings['maxAdvanceBookingDaysLimit'] ?? 7);

        $storeLimit = (int) ($store->max_advance_booking_days ?? 7);

        return min($storeLimit, $globalLimit);
    }

    /**
     * Retrieve all available dates for a store within advance booking window based on date-only schedule.
     *
     * @return array{is_enabled: bool, message?: string, dates?: array}
     */
    public function getAvailableDatesForStore(Store $store): array
    {
        // 1. Verify scheduled delivery is globally enabled
        if (! $this->isScheduledDeliveryGloballyEnabled()) {
            return [
                'is_enabled' => false,
                'message' => 'Scheduled delivery is temporarily unavailable.',
                'available_days' => [],
                'slots' => [],
            ];
        }

        $maxDays = $this->getMaxAdvanceBookingDays($store);
        $bufferMinutes = (int) ($store->scheduled_delivery_buffer_minutes ?? 60);

        if (! $store->is_scheduled_delivery_enabled) {
            return [
                'is_enabled' => false,
                'message' => __('messages.scheduled_delivery_disabled_for_store') ?? 'Scheduled delivery is currently disabled for this store.',
                'available_days' => [],
                'slots' => [],
            ];
        }

        $availableDays = $this->resolveAvailableDaysFromTiming($store);
        $allSlots = $this->getStoreTimeSlots($store);

        return [
            'is_enabled' => true,
            'max_advance_booking_days' => $maxDays,
            'max_days' => $maxDays,
            'buffer_minutes' => $bufferMinutes,
            'available_days' => $availableDays,
            'slots' => $allSlots,
        ];
    }

    public function getStoreTimeSlots(Store $store): array
    {
        $res = $this->getAvailableSlotsForStore($store, today()->format('Y-m-d'));

        return array_map(function ($slot) {
            return [
                'label' => $slot['label'],
                'start_time' => $slot['start_time'],
                'end_time' => $slot['end_time'],
                'max_orders_reached' => $slot['max_orders_reached'],
                'is_active' => $slot['is_available'],
            ];
        }, $res['slots'] ?? []);
    }

    /**
     * Parse available days of the week from store timing ($store->timing) or operating hours metadata.
     *
     * @return array<string>
     */
    protected function resolveAvailableDaysFromTiming(Store $store): array
    {
        // 1. Check if store->timing is valid JSON or array
        $timing = $store->timing;
        if (is_string($timing) && (str_starts_with(trim($timing), '[') || str_starts_with(trim($timing), '{'))) {
            $decoded = json_decode($timing, true);
            if (is_array($decoded)) {
                return $this->extractDaysFromArray($decoded);
            }
        } elseif (is_array($timing)) {
            return $this->extractDaysFromArray($timing);
        }

        // 2. Check metadata['operating_hours'] if structured array is present
        if (! empty($store->metadata['operating_hours']) && is_array($store->metadata['operating_hours'])) {
            return $this->extractDaysFromArray($store->metadata['operating_hours']);
        }

        // 3. Parse human-readable timing string (e.g. "Mon-Fri 9AM-5PM", "Mon to Sat", etc.)
        $allDaysMap = [
            'mon' => 'Monday',
            'tue' => 'Tuesday',
            'wed' => 'Wednesday',
            'thu' => 'Thursday',
            'fri' => 'Friday',
            'sat' => 'Saturday',
            'sun' => 'Sunday',
        ];
        $allDaysKeys = array_keys($allDaysMap);

        if (empty($timing) || ! is_string($timing)) {
            return array_values($allDaysMap);
        }

        $timingLower = strtolower($timing);

        // Check if explicitly daily / everyday / 7 days
        if (preg_match('/\b(daily|everyday|all days|7 days|monday\s*(?:-|to|–|—)\s*sunday|mon\s*(?:-|to|–|—)\s*sun)\b/', $timingLower)) {
            return array_values($allDaysMap);
        }

        $available = [];

        // Check for ranges like "mon-fri", "monday to saturday", etc.
        if (preg_match('/(mon|tue|wed|thu|fri|sat|sun)[a-z]*\s*(?:-|to|–|—)\s*(mon|tue|wed|thu|fri|sat|sun)[a-z]*/i', $timingLower, $matches)) {
            $startKey = substr(strtolower($matches[1]), 0, 3);
            $endKey = substr(strtolower($matches[2]), 0, 3);

            $startIndex = array_search($startKey, $allDaysKeys);
            $endIndex = array_search($endKey, $allDaysKeys);

            if ($startIndex !== false && $endIndex !== false) {
                if ($startIndex <= $endIndex) {
                    for ($i = $startIndex; $i <= $endIndex; $i++) {
                        $available[$allDaysMap[$allDaysKeys[$i]]] = true;
                    }
                } else {
                    for ($i = $startIndex; $i < count($allDaysKeys); $i++) {
                        $available[$allDaysMap[$allDaysKeys[$i]]] = true;
                    }
                    for ($i = 0; $i <= $endIndex; $i++) {
                        $available[$allDaysMap[$allDaysKeys[$i]]] = true;
                    }
                }
            }
        }

        // Check for specific days mentioned explicitly if no range was found
        if (empty($available)) {
            foreach ($allDaysMap as $prefix => $fullDay) {
                if (preg_match('/\b'.$prefix.'[a-z]*\b/', $timingLower)) {
                    $available[$fullDay] = true;
                }
            }
        }

        // If still empty after parsing string (e.g. string was just "9:00 AM - 10:00 PM"), assume all 7 days
        if (empty($available)) {
            foreach ($allDaysMap as $fullDay) {
                $available[$fullDay] = true;
            }
        }

        // Remove explicitly closed days (e.g. "Sunday: Closed", "Sun closed")
        foreach ($allDaysMap as $prefix => $fullDay) {
            if (preg_match('/\b'.$prefix.'[a-z]*\s*(?::|is)?\s*closed\b/', $timingLower)) {
                unset($available[$fullDay]);
            }
        }

        return array_values(array_unique(array_keys($available)));
    }

    protected function extractDaysFromArray(array $data): array
    {
        $days = [];
        $dayMap = ['monday' => 'Monday', 'tuesday' => 'Tuesday', 'wednesday' => 'Wednesday', 'thursday' => 'Thursday', 'friday' => 'Friday', 'saturday' => 'Saturday', 'sunday' => 'Sunday'];

        foreach ($data as $key => $val) {
            if (is_string($val) && in_array(strtolower($val), array_keys($dayMap))) {
                $days[] = $dayMap[strtolower($val)];
            } elseif (is_array($val) && isset($val['day']) && in_array(strtolower($val['day']), array_keys($dayMap))) {
                if (! isset($val['is_open']) || filter_var($val['is_open'], FILTER_VALIDATE_BOOLEAN)) {
                    $days[] = $dayMap[strtolower($val['day'])];
                }
            } elseif (is_string($key) && in_array(strtolower($key), array_keys($dayMap))) {
                if (is_bool($val) || is_numeric($val) || is_string($val)) {
                    if (filter_var($val, FILTER_VALIDATE_BOOLEAN)) {
                        $days[] = $dayMap[strtolower($key)];
                    }
                } elseif (is_array($val)) {
                    if (! isset($val['is_open']) || filter_var($val['is_open'], FILTER_VALIDATE_BOOLEAN)) {
                        $days[] = $dayMap[strtolower($key)];
                    }
                }
            }
        }

        return ! empty($days) ? array_values(array_unique($days)) : array_values($dayMap);
    }

    /**
     * Validate whether a specific date is allowed for scheduled delivery for a store.
     *
     * @return array{is_valid: bool, message?: string}
     */
    public function validateScheduledDateForStore(Store $store, string $dateString): array
    {
        // 1. Verify scheduled delivery is globally enabled
        if (! $this->isScheduledDeliveryGloballyEnabled()) {
            return [
                'is_valid' => false,
                'message' => 'Scheduled delivery is temporarily unavailable.',
            ];
        }

        if (! $store->is_scheduled_delivery_enabled) {
            return [
                'is_valid' => false,
                'message' => __('messages.scheduled_delivery_disabled_for_store') ?? 'Scheduled delivery is currently disabled for this store.',
            ];
        }

        $tz = $store->timezone ?? config('app.timezone', 'UTC');
        $now = Carbon::now($tz);
        try {
            $targetDate = Carbon::parse($dateString, $tz)->startOfDay();
        } catch (\Exception $e) {
            return [
                'is_valid' => false,
                'message' => 'Invalid date format.',
            ];
        }
        $today = $now->copy()->startOfDay();

        if ($targetDate->lt($today)) {
            return [
                'is_valid' => false,
                'message' => __('messages.scheduled_delivery_date_in_past') ?? 'Cannot schedule delivery for a past date.',
            ];
        }

        $maxDays = $this->getMaxAdvanceBookingDays($store);
        $maxDate = $today->copy()->addDays($maxDays);
        if ($targetDate->gt($maxDate)) {
            return [
                'is_valid' => false,
                'message' => __('messages.scheduled_delivery_date_too_far') ?? "Cannot schedule delivery more than {$maxDays} days in advance.",
            ];
        }

        // Check if store operating hours specify the store is closed on this day of the week
        $dayName = strtolower($targetDate->format('l')); // monday, tuesday, etc.
        $operatingHours = $store->metadata['operating_hours'] ?? null;
        if (is_array($operatingHours) && isset($operatingHours[$dayName])) {
            $dayConfig = $operatingHours[$dayName];
            if (isset($dayConfig['is_open']) && ! $dayConfig['is_open']) {
                return [
                    'is_valid' => false,
                    'message' => __('messages.store_closed_on_selected_day') ?? "Store is closed on {$targetDate->format('l')}.",
                ];
            }

            // If target date is today and store has a close_time or buffer, check if today's window is already past
            if ($targetDate->isSameDay($today) && ! empty($dayConfig['close_time'])) {
                try {
                    $closeTimestamp = Carbon::parse($dateString.' '.$dayConfig['close_time'], $tz);
                    $bufferMinutes = (int) ($store->scheduled_delivery_buffer_minutes ?? 60);
                    // Cutoff is close_time minus buffer minutes
                    $cutoffTimestamp = $closeTimestamp->copy()->subMinutes($bufferMinutes);
                    if ($now->gte($cutoffTimestamp)) {
                        return [
                            'is_valid' => false,
                            'message' => __('messages.scheduled_delivery_cutoff_passed') ?? 'Cutoff time for scheduling delivery today has passed.',
                        ];
                    }
                } catch (\Exception $e) {
                    // Ignore parsing error for close_time
                }
            }
        }

        return [
            'is_valid' => true,
        ];
    }

    /**
     * Validate both cart single-store requirement and date availability for scheduled delivery.
     *
     * @param  Cart|JsonResource|mixed  $cart
     * @return array{is_valid: bool, message?: string, store_id?: int}
     */
    public function validateScheduledDateForCart($cart, string $dateString): array
    {
        $cartValidation = $this->validateCartForScheduledDelivery($cart);
        if (! $cartValidation['is_valid']) {
            return $cartValidation;
        }

        $storeId = $cartValidation['store_id'];
        $store = Store::find($storeId);
        if (! $store) {
            return [
                'is_valid' => false,
                'message' => __('messages.store_not_found') ?? 'Store not found.',
            ];
        }

        return $this->validateScheduledDateForStore($store, $dateString);
    }

    /**
     * Validate cart, store, date and specific time slot availability.
     *
     * @param  mixed  $cart
     * @return array{is_valid: bool, message?: string, slot?: DeliveryTimeSlot}
     */
    public function validateTimeSlotForCart($cart, int $slotId, string $dateString): array
    {
        $dateValidation = $this->validateScheduledDateForCart($cart, $dateString);
        if (! $dateValidation['is_valid']) {
            return $dateValidation;
        }

        $cartValidation = $this->validateCartForScheduledDelivery($cart);
        $store = Store::find($cartValidation['store_id']);

        $slot = DeliveryTimeSlot::where('id', $slotId)
            ->where('store_id', $store->id)
            ->where('is_active', true)
            ->first();

        if (! $slot) {
            return [
                'is_valid' => false,
                'message' => __('messages.slot_not_found') ?? 'Delivery time slot not found or inactive for this store.',
            ];
        }

        $tz = $store->timezone ?? config('app.timezone', 'UTC');
        $now = Carbon::now($tz);
        $targetDate = Carbon::parse($dateString, $tz)->startOfDay();
        $dayName = strtolower($targetDate->format('l'));
        $operatingHours = $store->metadata['operating_hours'] ?? null;
        $dayConfig = is_array($operatingHours) && isset($operatingHours[$dayName]) ? $operatingHours[$dayName] : null;

        if ($dayConfig) {
            if (isset($dayConfig['is_open']) && ! filter_var($dayConfig['is_open'], FILTER_VALIDATE_BOOLEAN)) {
                return [
                    'is_valid' => false,
                    'message' => __('messages.store_closed_on_selected_day') ?? "Store is closed on {$targetDate->format('l')}.",
                ];
            }
            if (! empty($dayConfig['open_time']) && ! empty($slot->start_time)) {
                if (substr($slot->start_time, 0, 5) < substr($dayConfig['open_time'], 0, 5)) {
                    return [
                        'is_valid' => false,
                        'message' => __('messages.slot_outside_store_hours') ?? "This delivery time slot is outside the store's operating hours for the selected date.",
                    ];
                }
            }
            if (! empty($dayConfig['close_time']) && ! empty($slot->end_time)) {
                if (substr($slot->end_time, 0, 5) > substr($dayConfig['close_time'], 0, 5)) {
                    return [
                        'is_valid' => false,
                        'message' => __('messages.slot_outside_store_hours') ?? "This delivery time slot is outside the store's operating hours for the selected date.",
                    ];
                }
            }
        }

        if ($targetDate->isSameDay($now->copy()->startOfDay()) && ! empty($slot->start_time)) {
            $slotStart = Carbon::parse($targetDate->format('Y-m-d').' '.$slot->start_time, $tz);
            $cutoffThreshold = $now->copy()->addMinutes($this->getCartSlotLeadTimeMinutes($cart, $store));
            if ($slotStart->lte($cutoffThreshold)) {
                return [
                    'is_valid' => false,
                    'message' => __('messages.slot_cutoff_passed') ?? 'Cutoff buffer time passed for this delivery window.',
                ];
            }
        }

        if ($slot->getRemainingCapacityForDate($dateString) <= 0) {
            return [
                'is_valid' => false,
                'message' => __('messages.slot_capacity_full') ?? 'Batch slot capacity is full for this date.',
            ];
        }

        return [
            'is_valid' => true,
            'slot' => $slot,
        ];
    }

    /**
     * Validate cart, store, date and specific dynamic time slot availability.
     *
     * @param  mixed  $cart
     * @return array{is_valid: bool, message?: string}
     */
    public function validateDynamicSlotForCart($cart, string $dateString, string $startTime, string $endTime): array
    {
        $dateValidation = $this->validateScheduledDateForCart($cart, $dateString);
        if (! $dateValidation['is_valid']) {
            return $dateValidation;
        }

        $cartValidation = $this->validateCartForScheduledDelivery($cart);
        $store = Store::find($cartValidation['store_id']);

        if ($store->is_temporarily_closed) {
            return [
                'is_valid' => false,
                'message' => $store->temporary_closure_reason ?? __('messages.store_temporarily_closed') ?? 'Store is temporarily closed.',
            ];
        }

        $tz = $store->timezone ?? config('app.timezone', 'UTC');
        $now = Carbon::now($tz);
        $targetDate = Carbon::parse($dateString, $tz)->startOfDay();
        $today = $now->copy()->startOfDay();
        $dayName = strtolower($targetDate->format('l'));
        $operatingHours = $store->metadata['operating_hours'] ?? null;
        $dayConfig = is_array($operatingHours) && isset($operatingHours[$dayName]) ? $operatingHours[$dayName] : null;

        if (! $dayConfig || (isset($dayConfig['is_open']) && ! filter_var($dayConfig['is_open'], FILTER_VALIDATE_BOOLEAN))) {
            return [
                'is_valid' => false,
                'message' => "Store is closed on {$targetDate->format('l')}.",
            ];
        }

        // Verify slot is within operating hours
        if (! empty($dayConfig['open_time']) && substr($startTime, 0, 5) < substr($dayConfig['open_time'], 0, 5)) {
            return [
                'is_valid' => false,
                'message' => "Selected time slot is outside the store's operating hours.",
            ];
        }
        if (! empty($dayConfig['close_time']) && substr($endTime, 0, 5) > substr($dayConfig['close_time'], 0, 5)) {
            return [
                'is_valid' => false,
                'message' => "Selected time slot is outside the store's operating hours.",
            ];
        }

        // Break hours overlap verification
        $hasBreak = ! empty($dayConfig['has_break']) && filter_var($dayConfig['has_break'], FILTER_VALIDATE_BOOLEAN);
        if ($hasBreak) {
            $breakStartStr = $dayConfig['break_start_time'] ?? null;
            $breakEndStr = $dayConfig['break_end_time'] ?? null;
            if ($breakStartStr && $breakEndStr) {
                $slotStart = Carbon::parse($targetDate->format('Y-m-d').' '.$startTime, $tz);
                $slotEnd = Carbon::parse($targetDate->format('Y-m-d').' '.$endTime, $tz);
                if ($slotEnd->lte($slotStart)) {
                    $slotEnd->addDay();
                }

                $breakStart = Carbon::parse($targetDate->format('Y-m-d').' '.$breakStartStr, $tz);
                $breakEnd = Carbon::parse($targetDate->format('Y-m-d').' '.$breakEndStr, $tz);
                if ($breakEnd->lte($breakStart)) {
                    $breakEnd->addDay();
                }

                if ($slotStart->lt($breakEnd) && $slotEnd->gt($breakStart)) {
                    return [
                        'is_valid' => false,
                        'message' => "Selected time slot falls during the store's lunch/break hours.",
                    ];
                }
            }
        }

        // Buffer verification
        if ($targetDate->isSameDay($today)) {
            $slotStart = Carbon::parse($targetDate->format('Y-m-d').' '.$startTime, $tz);
            $cutoffThreshold = $now->copy()->addMinutes($this->getCartSlotLeadTimeMinutes($cart, $store));
            if ($slotStart->lte($cutoffThreshold)) {
                return [
                    'is_valid' => false,
                    'message' => 'Cutoff buffer time passed for this delivery window.',
                ];
            }
        }

        // Override verification
        $override = StoreSlotOverride::where('store_id', $store->id)
            ->whereDate('override_date', $dateString)
            ->where('start_time', $startTime)
            ->where('end_time', $endTime)
            ->first();

        if ($override && $override->is_blocked) {
            return [
                'is_valid' => false,
                'message' => 'Selected delivery slot is blocked by the seller.',
            ];
        }

        $slotCapacity = ($override && $override->capacity !== null)
            ? (int) $override->capacity
            : (int) ($store->scheduled_delivery_slot_capacity ?? 10);

        // Booked count check
        $bookedCount = Order::whereHas('items', function ($query) use ($store) {
            $query->where('store_id', $store->id);
        })
            ->whereDate('scheduled_date', $dateString)
            ->where('scheduled_slot_start_time', $startTime)
            ->where('scheduled_slot_end_time', $endTime)
            ->whereNotIn('status', [
                OrderStatusEnum::CANCELLED(),
                OrderStatusEnum::REJECTED_BY_SELLER(),
                OrderStatusEnum::FAILED(),
            ])
            ->count();

        if ($bookedCount >= $slotCapacity) {
            return [
                'is_valid' => false,
                'message' => 'Selected delivery slot has reached capacity.',
            ];
        }

        return [
            'is_valid' => true,
        ];
    }

    public function getCartSlotLeadTimeMinutes($cart, Store $store): int
    {
        $bufferMinutes = (int) ($store->scheduled_delivery_buffer_minutes ?? 60);
        $prepMinutes = 0;

        if ($cart instanceof JsonResource) {
            $cart = $cart->resource instanceof Cart
                ? $cart->resource
                : ($cart->resource['cart'] ?? ($cart['cart'] ?? null));
        }

        if ($cart instanceof Cart && $cart->items) {
            foreach ($cart->items as $item) {
                $productPrep = (int) ($item->product?->base_prep_time ?? 0);
                if ($productPrep > $prepMinutes) {
                    $prepMinutes = $productPrep;
                }
            }
        }

        if ($prepMinutes <= 0) {
            $prepMinutes = (int) ($store->order_preparation_time ?? 0);
        }

        return max($bufferMinutes, $prepMinutes);
    }
}
