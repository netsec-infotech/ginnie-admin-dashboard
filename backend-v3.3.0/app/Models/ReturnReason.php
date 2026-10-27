<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * Model representing return reasons (global admin reasons or seller product-specific reasons).
 */
class ReturnReason extends Model
{
    protected $fillable = [
        'seller_id',
        'title',
        'proof_required',
        'otp_required',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'proof_required' => 'boolean',
            'otp_required' => 'boolean',
            'seller_id' => 'integer',
        ];
    }

    /**
     * Scope query to active reasons.
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', 'active');
    }

    /**
     * Scope query to global reasons created by admin (seller_id is null).
     */
    public function scopeGlobal(Builder $query): Builder
    {
        return $query->whereNull('seller_id');
    }

    /**
     * Associated seller (null if global admin reason).
     */
    public function seller(): BelongsTo
    {
        return $this->belongsTo(Seller::class);
    }

    /**
     * Products assigned to this return reason.
     */
    public function products(): BelongsToMany
    {
        return $this->belongsToMany(Product::class, 'product_return_reasons');
    }
}
