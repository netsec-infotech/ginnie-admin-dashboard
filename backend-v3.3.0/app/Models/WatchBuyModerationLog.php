<?php

namespace App\Models;

use App\Enums\WatchBuy\WatchBuyModerationActionEnum;
use App\Enums\WatchBuy\WatchBuyModerationTargetEnum;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class WatchBuyModerationLog extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = [
        'target_type',
        'target_id',
        'action',
        'actor_id',
        'previous_status',
        'new_status',
        'reason',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'target_type' => WatchBuyModerationTargetEnum::class,
            'action' => WatchBuyModerationActionEnum::class,
            'metadata' => 'array',
        ];
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id');
    }

    public function target(): MorphTo
    {
        return $this->morphTo();
    }
}
