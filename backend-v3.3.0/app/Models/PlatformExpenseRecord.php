<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * ==========================================================
 * Return Dispute System
 * Phase 4
 * Platform Expense Record Model
 * ==========================================================
 */
class PlatformExpenseRecord extends Model
{
    protected $table = 'platform_expense_records';

    protected $fillable = [
        'order_item_return_id',
        'delivery_boy_id',
        'amount',
        'currency_code',
        'reference_number',
        'classification',
        'remarks',
    ];

    public function orderItemReturn(): BelongsTo
    {
        return $this->belongsTo(OrderItemReturn::class, 'order_item_return_id');
    }

    public function deliveryBoy(): BelongsTo
    {
        return $this->belongsTo(DeliveryBoy::class, 'delivery_boy_id');
    }
}
