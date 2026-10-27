<?php

namespace App\Models;

use App\Enums\Order\OrderStatusEnum;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DeliveryTimeSlot extends Model
{
    protected $fillable = [
        'store_id',
        'label',
        'start_time',
        'end_time',
        'max_orders',
        'is_active',
        'sort_order',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }

    /**
     * Check if this time slot applies to a specific date.
     * Since operational day filtering is handled by store operating hours, this always returns true.
     */
    public function appliesToDay(string $dateString): bool
    {
        return true;
    }

    /**
     * Get remaining capacity for this time slot on a specific date.
     */
    public function getRemainingCapacityForDate(string $dateString): int
    {
        if ($this->max_orders === null || (int) $this->max_orders <= 0) {
            return 999999; // Unlimited capacity if max_orders is 0 or null
        }

        $activeOrdersCount = Order::where('delivery_time_slot_id', $this->id)
            ->whereDate('scheduled_date', $dateString)
            ->whereNotIn('status', [
                OrderStatusEnum::CANCELLED(),
                OrderStatusEnum::REJECTED_BY_SELLER(),
                OrderStatusEnum::FAILED(),
            ])
            ->count();

        return max(0, (int) $this->max_orders - $activeOrdersCount);
    }
}

