<?php

namespace App\Models;

use App\Enums\SpatieMediaCollectionName;
use App\Enums\WatchBuy\WatchBuySourceEnum;
use App\Enums\WatchBuy\WatchBuyStatusContentTypeEnum;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

class WatchBuyStatus extends Model implements HasMedia
{
    use HasFactory, InteractsWithMedia;

    public const UPDATED_AT = null;

    protected $fillable = [
        'uuid',
        'profile_id',
        'source',
        'content_type',
        'text',
        'media_mime',
        'media_size_bytes',
        'media_width',
        'media_height',
        'duration_ms',
        'created_by',
        'expires_at',
        'created_at',
    ];

    protected function casts(): array
    {
        return [
            'source' => WatchBuySourceEnum::class,
            'content_type' => WatchBuyStatusContentTypeEnum::class,
            'media_size_bytes' => 'integer',
            'media_width' => 'integer',
            'media_height' => 'integer',
            'duration_ms' => 'integer',
            'expires_at' => 'datetime',
            'created_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (WatchBuyStatus $status): void {
            $status->uuid ??= (string) Str::uuid();
        });
    }

    public function registerMediaCollections(): void
    {
        $this->addMediaCollection(SpatieMediaCollectionName::WATCH_BUY_STATUS_MEDIA())->singleFile();
    }

    public function profile(): BelongsTo
    {
        return $this->belongsTo(WatchBuyProfile::class, 'profile_id');
    }

    public function products(): HasMany
    {
        return $this->hasMany(WatchBuyStatusProduct::class, 'status_id')->orderBy('sort_order');
    }

    public function views(): HasMany
    {
        return $this->hasMany(WatchBuyStatusView::class, 'status_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function scopeActive(Builder $query): Builder
    {
        if (self::doesNotExpireInDemoMode()) {
            return $query;
        }

        return $query->where('expires_at', '>', now());
    }

    public static function doesNotExpireInDemoMode(): bool
    {
        try {
            return (bool) (Setting::systemSettings()['demoMode'] ?? false);
        } catch (\Throwable) {
            return false;
        }
    }

    public function scopePubliclyVisible(Builder $query): Builder
    {
        return $query->active()->whereHas('profile', fn (Builder $profile) => $profile->where('status', 'active'));
    }

    public function getMediaUrlAttribute(): ?string
    {
        return $this->getFirstMediaUrl(SpatieMediaCollectionName::WATCH_BUY_STATUS_MEDIA()) ?: null;
    }
}
