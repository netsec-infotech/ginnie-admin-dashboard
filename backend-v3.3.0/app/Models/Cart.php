<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @method static where(string $string, mixed $id)
 */
class Cart extends Model
{
    protected $fillable = [
        'uuid',
        'user_id',
        'rider_tip_amount',
        'use_loyalty_points',
        'last_activity_at',
        'activity_version',
        'abandoned_cart_notified_at',
    ];

    protected function casts(): array
    {
        return [
            'rider_tip_amount' => 'float',
            'use_loyalty_points' => 'boolean',
            'last_activity_at' => 'datetime',
            'activity_version' => 'integer',
            'abandoned_cart_notified_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(CartItem::class);
    }
}
