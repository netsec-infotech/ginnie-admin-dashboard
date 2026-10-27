<?php

namespace App\Models;

use App\Enums\SpatieMediaCollectionName;
use App\Enums\WatchBuy\WatchBuyReelStatusEnum;
use App\Enums\WatchBuy\WatchBuySourceEnum;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Support\Str;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

class WatchBuyReel extends Model implements HasMedia
{
    use HasFactory, InteractsWithMedia;

    protected $attributes = [
        'status' => 'published',
        'like_count' => 0,
    ];

    protected $fillable = [
        'uuid',
        'slug',
        'profile_id',
        'source',
        'caption',
        'status',
        'status_before_block',
        'duration_ms',
        'video_width',
        'video_height',
        'video_size_bytes',
        'like_count',
        'published_at',
        'blocked_by',
        'block_reason',
        'blocked_at',
        'created_by',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'source' => WatchBuySourceEnum::class,
            'status' => WatchBuyReelStatusEnum::class,
            'status_before_block' => WatchBuyReelStatusEnum::class,
            'duration_ms' => 'integer',
            'video_width' => 'integer',
            'video_height' => 'integer',
            'video_size_bytes' => 'integer',
            'like_count' => 'integer',
            'published_at' => 'datetime',
            'blocked_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (WatchBuyReel $reel): void {
            $reel->uuid ??= (string) Str::uuid();
            $reel->slug ??= Str::lower(Str::random(12));
        });
    }

    public function registerMediaCollections(): void
    {
        $this->addMediaCollection(SpatieMediaCollectionName::WATCH_BUY_REEL_VIDEO())->singleFile();
        $this->addMediaCollection(SpatieMediaCollectionName::WATCH_BUY_REEL_COVER())->singleFile();
    }

    public function profile(): BelongsTo
    {
        return $this->belongsTo(WatchBuyProfile::class, 'profile_id');
    }

    public function products(): HasMany
    {
        return $this->hasMany(WatchBuyReelProduct::class, 'reel_id')->orderBy('sort_order');
    }

    public function likes(): HasMany
    {
        return $this->hasMany(WatchBuyReelLike::class, 'reel_id');
    }

    public function moderationLogs(): MorphMany
    {
        return $this->morphMany(WatchBuyModerationLog::class, 'target');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public function blockedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'blocked_by');
    }

    public function scopePubliclyVisible(Builder $query): Builder
    {
        return $query
            ->where('status', 'published')
            ->whereNotNull('published_at')
            ->whereHas('profile', fn (Builder $profile) => $profile->where('status', 'active'));
    }

    public function getVideoUrlAttribute(): ?string
    {
        return $this->getFirstMediaUrl(SpatieMediaCollectionName::WATCH_BUY_REEL_VIDEO()) ?: null;
    }

    public function getCoverUrlAttribute(): ?string
    {
        return $this->getFirstMediaUrl(SpatieMediaCollectionName::WATCH_BUY_REEL_COVER()) ?: null;
    }
}
