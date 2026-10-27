<?php

namespace App\Models;

use App\Enums\LoyaltyPoint\LoyaltyPointTransactionStatusEnum;
use App\Enums\LoyaltyPoint\LoyaltyPointTransactionTypeEnum;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LoyaltyPointTransaction extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'order_id',
        'points',
        'expires_at',
        'used_points',
        'type',
        'status',
        'description',
    ];

    protected function casts(): array
    {
        return [
            'points' => 'integer',
            'used_points' => 'integer',
            'expires_at' => 'datetime',
            'type' => LoyaltyPointTransactionTypeEnum::class,
            'status' => LoyaltyPointTransactionStatusEnum::class,
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }
}
