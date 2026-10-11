<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StoreSlotOverride extends Model
{
    protected $fillable = [
        'store_id',
        'override_date',
        'start_time',
        'end_time',
        'is_blocked',
        'capacity',
    ];

    protected $casts = [
        'override_date' => 'date:Y-m-d',
        'is_blocked' => 'boolean',
        'capacity' => 'integer',
    ];

    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }
}
