<?php

namespace App\Models;

use App\Enums\SpatieMediaCollectionName;
use App\Enums\SplashSlotEnum;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

class SplashScreen extends Model implements HasMedia
{
    use HasFactory, InteractsWithMedia;

    protected $appends = [
        'background_image_url',
        'background_video_url',
        'overlay_image_url',
        'overlay_lottie_url',
        'overlay_url',
    ];

    protected $fillable = [
        'app_type',
        'slot',
        'version',
        'media_type',
        'bg_type',
        'bg_color',
        'min_display_seconds',
        'activate_at',
        'overlay_enabled',
        'overlay_type',
        'overlay_x_percent',
        'overlay_y_percent',
        'overlay_width_percent',
    ];

    protected $casts = [
        'activate_at' => 'datetime',
        'overlay_enabled' => 'boolean',
        'overlay_x_percent' => 'float',
        'overlay_y_percent' => 'float',
        'overlay_width_percent' => 'float',
    ];

    public function scopeForApp(Builder $query, string $appType): Builder
    {
        return $query->where('app_type', $appType);
    }

    public function scopeForSlot(Builder $query, SplashSlotEnum|string $slot): Builder
    {
        $value = $slot instanceof SplashSlotEnum ? $slot->value : $slot;

        return $query->where('slot', $value);
    }

    public function getBackgroundImageUrlAttribute(): ?string
    {
        return $this->getFirstMediaUrl(SpatieMediaCollectionName::SPLASH_BACKGROUND_IMAGE()) ?: null;
    }

    public function getBackgroundVideoUrlAttribute(): ?string
    {
        return $this->getFirstMediaUrl(SpatieMediaCollectionName::SPLASH_BACKGROUND_VIDEO()) ?: null;
    }

    public function getOverlayImageUrlAttribute(): ?string
    {
        return $this->getFirstMediaUrl(SpatieMediaCollectionName::SPLASH_OVERLAY_IMAGE()) ?: null;
    }

    public function getOverlayLottieUrlAttribute(): ?string
    {
        return $this->getFirstMediaUrl(SpatieMediaCollectionName::SPLASH_OVERLAY_LOTTIE()) ?: null;
    }

    public function getOverlayUrlAttribute(): ?string
    {
        return match ($this->overlay_type) {
            'image' => $this->overlay_image_url,
            'lottie' => $this->overlay_lottie_url,
            default => null,
        };
    }

    public function registerMediaCollections(): void
    {
        $this->addMediaCollection(SpatieMediaCollectionName::SPLASH_BACKGROUND_IMAGE())->singleFile();
        $this->addMediaCollection(SpatieMediaCollectionName::SPLASH_BACKGROUND_VIDEO())->singleFile();
        $this->addMediaCollection(SpatieMediaCollectionName::SPLASH_OVERLAY_IMAGE())->singleFile();
        $this->addMediaCollection(SpatieMediaCollectionName::SPLASH_OVERLAY_LOTTIE())->singleFile();
    }

    protected static function booted(): void
    {
        static::deleting(function (SplashScreen $screen): void {
            $screen->clearMediaCollection(SpatieMediaCollectionName::SPLASH_BACKGROUND_IMAGE());
            $screen->clearMediaCollection(SpatieMediaCollectionName::SPLASH_BACKGROUND_VIDEO());
            $screen->clearMediaCollection(SpatieMediaCollectionName::SPLASH_OVERLAY_IMAGE());
            $screen->clearMediaCollection(SpatieMediaCollectionName::SPLASH_OVERLAY_LOTTIE());
        });
    }
}
