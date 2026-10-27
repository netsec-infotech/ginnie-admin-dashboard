<?php

namespace App\Models;

use App\Enums\SpatieMediaCollectionName;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

class OnboardingScreen extends Model implements HasMedia
{
    use HasFactory, InteractsWithMedia;

    public const MAX_PER_APP = 5;

    protected $appends = ['image', 'media_mime_type', 'media_type', 'background_image', 'overlay_image'];

    protected $fillable = [
        'app_type',
        'title',
        'subtitle',
        'background_type',
        'background_color',
        'gradient_start_color',
        'gradient_end_color',
        'gradient_angle',
        'background_image_fit',
        'background_image_position',
        'overlay_enabled',
        'overlay_type',
        'overlay_color',
        'overlay_opacity',
        'overlay_image_fit',
        'overlay_image_position',
        'overlay_image_size',
        'title_color',
        'subtitle_color',
        'content_alignment',
        'media_fit',
        'button_background_color',
        'button_foreground_color',
        'indicator_active_color',
        'indicator_inactive_color',
    ];

    protected function casts(): array
    {
        return [
            'gradient_angle' => 'integer',
            'overlay_enabled' => 'boolean',
            'overlay_opacity' => 'float',
            'overlay_image_size' => 'integer',
        ];
    }

    public function scopeForApp(Builder $query, string $appType): Builder
    {
        return $query->where('app_type', $appType);
    }

    public function scopeOrdered(Builder $query): Builder
    {
        return $query->orderBy('id');
    }

    public function getImageAttribute(): ?string
    {
        return $this->getFirstMediaUrl(SpatieMediaCollectionName::ONBOARDING_SCREEN_IMAGE()) ?: null;
    }

    public function getMediaMimeTypeAttribute(): ?string
    {
        return $this->getFirstMedia(SpatieMediaCollectionName::ONBOARDING_SCREEN_IMAGE())?->mime_type;
    }

    public function getMediaTypeAttribute(): ?string
    {
        $mimeType = (string) $this->media_mime_type;

        if ($mimeType === '') {
            return null;
        }

        if (str_contains($mimeType, 'json') || $mimeType === 'text/plain') {
            return 'lottie';
        }

        if (str_starts_with($mimeType, 'video/')) {
            return 'video';
        }

        return 'image';
    }

    public function getBackgroundImageAttribute(): ?string
    {
        return $this->getFirstMediaUrl(SpatieMediaCollectionName::ONBOARDING_SCREEN_BACKGROUND()) ?: null;
    }

    public function getOverlayImageAttribute(): ?string
    {
        return $this->getFirstMediaUrl(SpatieMediaCollectionName::ONBOARDING_SCREEN_OVERLAY()) ?: null;
    }

    public function designPayload(bool $nullDisabledOverlay = false, bool $emptyStrings = false): array
    {
        $defaults = self::designDefaults();
        $overlayEnabled = (bool) ($this->overlay_enabled ?? $defaults['overlay']['enabled']);
        $overlayOpacity = $overlayEnabled ? ($this->overlay_opacity ?? $defaults['overlay']['opacity']) : 0;
        $emptyValue = $emptyStrings ? '' : null;

        return [
            'background' => [
                'type' => $this->background_type ?: $defaults['background']['type'],
                'color' => $this->background_color ?: $defaults['background']['color'],
                'gradient_start_color' => $this->gradient_start_color ?: $defaults['background']['gradient_start_color'],
                'gradient_end_color' => $this->gradient_end_color ?: $defaults['background']['gradient_end_color'],
                'gradient_angle' => $this->gradient_angle ?? $defaults['background']['gradient_angle'],
                'image' => $this->background_image ?: $emptyValue,
                'image_fit' => $this->background_image_fit ?: $defaults['background']['image_fit'],
                'image_position' => $this->background_image_position ?: $defaults['background']['image_position'],
            ],
            'overlay' => $nullDisabledOverlay && ! $overlayEnabled ? null : [
                'enabled' => $overlayEnabled,
                'type' => $this->overlay_type ?: $defaults['overlay']['type'],
                'color' => $this->overlay_color ?: $defaults['overlay']['color'],
                'opacity' => $overlayOpacity,
                'image' => $overlayEnabled ? ($this->overlay_image ?: $emptyValue) : $emptyValue,
                'image_fit' => $this->overlay_image_fit ?: $defaults['overlay']['image_fit'],
                'image_position' => $this->overlay_image_position ?: $defaults['overlay']['image_position'],
                'image_size' => $this->overlay_image_size ?? $defaults['overlay']['image_size'],
            ],
            'overlay_opacity' => $overlayOpacity,
            'text' => [
                'title_color' => $this->title_color ?: $defaults['text']['title_color'],
                'subtitle_color' => $this->subtitle_color ?: $defaults['text']['subtitle_color'],
                'alignment' => $this->content_alignment ?: $defaults['text']['alignment'],
            ],
            'media' => [
                'fit' => $this->media_fit ?: $defaults['media']['fit'],
            ],
            'button' => [
                'background_color' => $this->button_background_color ?: $defaults['button']['background_color'],
                'foreground_color' => $this->button_foreground_color ?: $defaults['button']['foreground_color'],
            ],
            'indicator' => [
                'active_color' => $this->indicator_active_color ?: $defaults['indicator']['active_color'],
                'inactive_color' => $this->indicator_inactive_color ?: $defaults['indicator']['inactive_color'],
            ],
        ];
    }

    public static function designDefaults(): array
    {
        return [
            'background' => [
                'type' => 'solid',
                'color' => '#ffffff',
                'gradient_start_color' => '#0d6efd',
                'gradient_end_color' => '#20c997',
                'gradient_angle' => 180,
                'image_fit' => 'cover',
                'image_position' => 'center',
            ],
            'overlay' => [
                'enabled' => false,
                'type' => 'color',
                'color' => '#000000',
                'opacity' => 0,
                'image_fit' => 'cover',
                'image_position' => 'center',
                'image_size' => 100,
            ],
            'text' => [
                'title_color' => '#111827',
                'subtitle_color' => '#6b7280',
                'alignment' => 'center',
            ],
            'media' => [
                'fit' => 'contain',
            ],
            'button' => [
                'background_color' => '#0d6efd',
                'foreground_color' => '#ffffff',
            ],
            'indicator' => [
                'active_color' => '#0d6efd',
                'inactive_color' => '#dbe2ea',
            ],
        ];
    }

    public function registerMediaCollections(): void
    {
        $this->addMediaCollection(SpatieMediaCollectionName::ONBOARDING_SCREEN_IMAGE())->singleFile();
        $this->addMediaCollection(SpatieMediaCollectionName::ONBOARDING_SCREEN_BACKGROUND())->singleFile();
        $this->addMediaCollection(SpatieMediaCollectionName::ONBOARDING_SCREEN_OVERLAY())->singleFile();
    }

    protected static function booted(): void
    {
        static::deleting(function (OnboardingScreen $screen): void {
            $screen->clearMediaCollection(SpatieMediaCollectionName::ONBOARDING_SCREEN_IMAGE());
            $screen->clearMediaCollection(SpatieMediaCollectionName::ONBOARDING_SCREEN_BACKGROUND());
            $screen->clearMediaCollection(SpatieMediaCollectionName::ONBOARDING_SCREEN_OVERLAY());
        });
    }
}
