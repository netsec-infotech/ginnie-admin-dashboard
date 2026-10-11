<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * Model representing return rejection reasons managed by admin for store inspection.
 */
class ReturnRejectionReason extends Model
{
    protected $fillable = [
        'title',
        'comment_required',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'comment_required' => 'boolean',
        ];
    }

    /**
     * Scope query to active rejection reasons.
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', 'active');
    }
}
