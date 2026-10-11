<?php

namespace App\Models;

use App\Enums\Pos\PosRefundMethodEnum;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PosRefund extends Model
{
    public const METHODS = ['cash', 'other'];

    protected $fillable = [
        'order_id',
        'store_id',
        'refunded_by_user_id',
        'total_amount',
        'refund_method',
        'refund_method_meta',
        'reason',
    ];

    protected $casts = [
        'total_amount'       => 'decimal:2',
        'refund_method_meta' => 'array',
    ];

    public static function methodLabel(string $method): string
    {
        return PosRefundMethodEnum::label($method);
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }

    public function refundedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'refunded_by_user_id');
    }

    public function lines(): HasMany
    {
        return $this->hasMany(PosRefundLine::class);
    }
}
