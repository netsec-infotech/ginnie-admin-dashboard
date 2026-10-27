<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WatchBuyReelLike extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = [
        'reel_id',
        'user_id',
    ];

    public function reel(): BelongsTo
    {
        return $this->belongsTo(WatchBuyReel::class, 'reel_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
