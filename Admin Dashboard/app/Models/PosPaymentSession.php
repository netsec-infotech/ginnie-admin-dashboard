<?php

namespace App\Models;

use App\Enums\Pos\PosPaymentSessionStatusEnum;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PosPaymentSession extends Model
{
    protected $fillable = [
        'token',
        'store_id',
        'seller_id',
        'payload',
        'amount',
        'cash_portion',
        'currency_code',
        'status',
        'gateway',
        'gateway_order_id',
        'gateway_payment_id',
        'failure_reason',
        'order_id',
        'expires_at',
    ];

    protected $casts = [
        'payload' => 'array',
        'amount' => 'decimal:2',
        'cash_portion' => 'decimal:2',
        'status' => PosPaymentSessionStatusEnum::class,
        'expires_at' => 'datetime',
    ];

    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function isPending(): bool
    {
        return $this->status === PosPaymentSessionStatusEnum::PENDING && !$this->isExpired();
    }

    public function isExpired(): bool
    {
        return $this->expires_at && $this->expires_at->isPast()
            && $this->status === PosPaymentSessionStatusEnum::PENDING;
    }

    public function isPaid(): bool
    {
        return $this->status === PosPaymentSessionStatusEnum::PAID;
    }
}
