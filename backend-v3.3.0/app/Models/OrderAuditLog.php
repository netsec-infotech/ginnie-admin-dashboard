<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Phase 3 — audit row for every admin override + system-flagged event on an
 * order. Pure log: writes only, never updates. The admin order detail page
 * renders these in chronological order so the operator can see the full timeline.
 */
class OrderAuditLog extends Model
{
    protected $fillable = [
        'order_id',
        'order_item_id',
        'admin_id',
        'action',
        'old_value',
        'new_value',
        'reason',
    ];

    protected $casts = [
        'old_value' => 'array',
        'new_value' => 'array',
    ];

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function orderItem(): BelongsTo
    {
        return $this->belongsTo(OrderItem::class);
    }

    /**
     * Admin user who triggered the action. Nullable for system entries.
     */
    public function admin(): BelongsTo
    {
        return $this->belongsTo(User::class, 'admin_id');
    }
}
