<?php

namespace App\Models;

use App\Enums\SpatieMediaCollectionName;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

class HomeNavbarItem extends Model implements HasMedia
{
    use InteractsWithMedia, SoftDeletes;

    protected $fillable = [
        'title',
        'slug',
        'sort_order',
        'app_sort_order',
        'web_sort_order',
        'is_active',
        'show_on_app',
        'show_on_web',
        'home_appearance',
        'search_labels',
    ];

    protected $casts = [
        'sort_order' => 'integer',
        'app_sort_order' => 'integer',
        'web_sort_order' => 'integer',
        'is_active' => 'boolean',
        'show_on_app' => 'boolean',
        'show_on_web' => 'boolean',
        'home_appearance' => 'array',
        'search_labels' => 'array',
    ];

    /**
     * Get associated home layouts scoped to this navbar item.
     */
    public function layouts(): HasMany
    {
        return $this->hasMany(HomeLayout::class, 'scope_id')
            ->where('scope_type', 'navbar');
    }

    /**
     * Scope query ordered by platform sort order.
     */
    public function scopeOrdered(Builder $query, string $platform = 'app'): Builder
    {
        return $query->orderBy(self::sortColumn($platform))->orderBy('id');
    }

    /**
     * Resolve sort column based on platform.
     */
    public static function sortColumn(string $platform): string
    {
        return $platform === 'web' ? 'web_sort_order' : 'app_sort_order';
    }

    /**
     * Scope query filtered for platform visibility.
     */
    public function scopeForPlatform(Builder $query, string $platform): Builder
    {
        return $query->where($platform === 'web' ? 'show_on_web' : 'show_on_app', true);
    }

    /**
     * Resolved appearance dictionary with full fallback hierarchy.
     */
    public function getResolvedHomeAppearanceAttribute(): array
    {
        $stored = is_array($this->home_appearance) ? $this->home_appearance : [];
        $flatType = $this->appearanceBackgroundType($stored['backgroundType'] ?? ($stored['background_type'] ?? null));
        $flatAppColor = $stored['backgroundColorApp'] ?? ($stored['background_color_app'] ?? '#ffffff');
        $flatDesktopColor = $stored['backgroundColorWeb'] ?? ($stored['background_color_web'] ?? $flatAppColor);
        $flatFontColor = $stored['fontColor'] ?? ($stored['font_color'] ?? '#475569');
        $defaults = [
            'background_type' => 'none',
            'gradient_start' => '#ffffff',
            'gradient_end' => '#ffffff',
            'gradient_angle' => 90,
            'font_color' => '#475569',
            'active_font_color' => '#111827',
        ];
        $flatApp = array_filter([
            'background_type' => $flatType,
            'gradient_start' => $flatAppColor,
            'gradient_end' => $flatAppColor,
            'font_color' => $flatFontColor,
        ], fn ($value) => $value !== null && $value !== '');
        $flatDesktop = array_filter([
            'background_type' => $flatType,
            'gradient_start' => $flatDesktopColor,
            'gradient_end' => $flatDesktopColor,
            'font_color' => $flatFontColor,
        ], fn ($value) => $value !== null && $value !== '');

        $app = array_merge($defaults, $flatApp, (array) ($stored['app'] ?? []), [
            'icon' => $this->app_icon,
            'active_icon' => $this->app_active_icon ?: $this->app_icon,
            'background_image' => $this->app_background_image,
            'mobile_background_image' => $this->app_background_image,
            'tablet_background_image' => $this->app_tablet_background_image ?: $this->app_background_image,
        ]);
        $desktopBackground = $this->desktop_background_image ?: $this->app_background_image;
        $desktopTabletBackground = $this->desktop_tablet_background_image ?: $desktopBackground;
        $desktopMobileBackground = $this->desktop_mobile_background_image ?: $desktopTabletBackground ?: $desktopBackground;
        $desktop = array_merge($app, $flatDesktop, (array) ($stored['desktop'] ?? []), [
            'icon' => $this->desktop_icon,
            'active_icon' => $this->desktop_active_icon ?: $this->desktop_icon,
            'background_image' => $desktopBackground,
            'desktop_background_image' => $desktopBackground,
            'tablet_background_image' => $desktopTabletBackground,
            'mobile_background_image' => $desktopMobileBackground,
            'app_background_image' => $desktopMobileBackground,
        ]);

        return ['app' => $app, 'desktop' => $desktop];
    }

    private function appearanceBackgroundType(?string $type): ?string
    {
        return match ($type) {
            'color', 'gradient' => 'color',
            'animation', 'lottie' => 'lottie',
            'image' => 'image',
            'none' => 'none',
            default => null,
        };
    }

    public function getAppIconAttribute(): string
    {
        return $this->getFirstMediaUrl(SpatieMediaCollectionName::HOME_NAVBAR_APP_ICON()) ?: '';
    }

    public function getAppActiveIconAttribute(): string
    {
        return $this->getFirstMediaUrl(SpatieMediaCollectionName::HOME_NAVBAR_APP_ACTIVE_ICON()) ?: '';
    }

    public function getAppBackgroundImageAttribute(): string
    {
        return $this->getFirstMediaUrl(SpatieMediaCollectionName::HOME_NAVBAR_APP_BACKGROUND()) ?: '';
    }

    public function getDesktopIconAttribute(): string
    {
        return $this->getFirstMediaUrl(SpatieMediaCollectionName::HOME_NAVBAR_DESKTOP_ICON()) ?: $this->app_icon;
    }

    public function getDesktopActiveIconAttribute(): string
    {
        return $this->getFirstMediaUrl(SpatieMediaCollectionName::HOME_NAVBAR_DESKTOP_ACTIVE_ICON()) ?: $this->app_active_icon ?: $this->desktop_icon;
    }

    public function getDesktopBackgroundImageAttribute(): string
    {
        return $this->getFirstMediaUrl(SpatieMediaCollectionName::HOME_NAVBAR_DESKTOP_BACKGROUND()) ?: '';
    }

    public function getAppTabletBackgroundImageAttribute(): string
    {
        return $this->getFirstMediaUrl(SpatieMediaCollectionName::HOME_NAVBAR_APP_TABLET_BACKGROUND()) ?: '';
    }

    public function getDesktopTabletBackgroundImageAttribute(): string
    {
        return $this->getFirstMediaUrl(SpatieMediaCollectionName::HOME_NAVBAR_DESKTOP_TABLET_BACKGROUND()) ?: '';
    }

    public function getDesktopMobileBackgroundImageAttribute(): string
    {
        return $this->getFirstMediaUrl(SpatieMediaCollectionName::HOME_NAVBAR_DESKTOP_MOBILE_BACKGROUND()) ?: '';
    }

    public function registerMediaCollections(): void
    {
        $this->addMediaCollection(SpatieMediaCollectionName::HOME_NAVBAR_APP_ICON())->singleFile();
        $this->addMediaCollection(SpatieMediaCollectionName::HOME_NAVBAR_APP_ACTIVE_ICON())->singleFile();
        $this->addMediaCollection(SpatieMediaCollectionName::HOME_NAVBAR_APP_BACKGROUND())->singleFile();
        $this->addMediaCollection(SpatieMediaCollectionName::HOME_NAVBAR_APP_TABLET_BACKGROUND())->singleFile();
        $this->addMediaCollection(SpatieMediaCollectionName::HOME_NAVBAR_DESKTOP_ICON())->singleFile();
        $this->addMediaCollection(SpatieMediaCollectionName::HOME_NAVBAR_DESKTOP_ACTIVE_ICON())->singleFile();
        $this->addMediaCollection(SpatieMediaCollectionName::HOME_NAVBAR_DESKTOP_BACKGROUND())->singleFile();
        $this->addMediaCollection(SpatieMediaCollectionName::HOME_NAVBAR_DESKTOP_TABLET_BACKGROUND())->singleFile();
        $this->addMediaCollection(SpatieMediaCollectionName::HOME_NAVBAR_DESKTOP_MOBILE_BACKGROUND())->singleFile();
    }

    public function getIconAttribute(): string
    {
        return $this->app_icon;
    }

    public function getActiveIconAttribute(): string
    {
        return $this->app_active_icon ?: $this->app_icon;
    }

    public function getBackgroundTypeAttribute(): ?string
    {
        $appearance = $this->resolved_home_appearance['app'] ?? [];

        return match ($appearance['background_type'] ?? null) {
            'color', 'gradient' => 'color',
            'lottie', 'animation' => 'animation',
            'image' => 'image',
            default => null,
        };
    }

    public function getBackgroundColorWebAttribute(): string
    {
        return $this->resolved_home_appearance['desktop']['gradient_start'] ?? '#ffffff';
    }

    public function getBackgroundColorAppAttribute(): string
    {
        return $this->resolved_home_appearance['app']['gradient_start'] ?? '#ffffff';
    }

    public function getBackgroundImageWebAttribute(): string
    {
        return $this->desktop_background_image;
    }

    public function getBackgroundImageWebMobileAttribute(): string
    {
        return $this->desktop_mobile_background_image;
    }

    public function getBackgroundImageAppAttribute(): string
    {
        return $this->app_background_image;
    }

    public function getBackgroundAnimationWebAttribute(): string
    {
        return $this->desktop_background_image;
    }

    public function getBackgroundAnimationAppAttribute(): string
    {
        return $this->app_background_image;
    }

    public function getBackgroundColorAttribute(): string
    {
        return $this->background_color_web ?: ($this->background_color_app ?: '#ffffff');
    }

    public function getBackgroundImageAttribute(): string
    {
        return $this->desktop_background_image ?: $this->app_background_image;
    }

    public function getBackgroundAnimationAttribute(): string
    {
        return $this->background_animation_web ?: $this->background_animation_app;
    }

    public function getFontColorAttribute(): string
    {
        return $this->resolved_home_appearance['app']['font_color'] ?? '#000000';
    }

    protected static function booted(): void
    {
        static::deleting(function (HomeNavbarItem $item) {
            $item->layouts()->get()->each->delete();
            $item->clearMediaCollection(SpatieMediaCollectionName::HOME_NAVBAR_APP_ICON());
            $item->clearMediaCollection(SpatieMediaCollectionName::HOME_NAVBAR_APP_ACTIVE_ICON());
            $item->clearMediaCollection(SpatieMediaCollectionName::HOME_NAVBAR_APP_BACKGROUND());
            $item->clearMediaCollection(SpatieMediaCollectionName::HOME_NAVBAR_APP_TABLET_BACKGROUND());
            $item->clearMediaCollection(SpatieMediaCollectionName::HOME_NAVBAR_DESKTOP_ICON());
            $item->clearMediaCollection(SpatieMediaCollectionName::HOME_NAVBAR_DESKTOP_ACTIVE_ICON());
            $item->clearMediaCollection(SpatieMediaCollectionName::HOME_NAVBAR_DESKTOP_BACKGROUND());
            $item->clearMediaCollection(SpatieMediaCollectionName::HOME_NAVBAR_DESKTOP_TABLET_BACKGROUND());
            $item->clearMediaCollection(SpatieMediaCollectionName::HOME_NAVBAR_DESKTOP_MOBILE_BACKGROUND());
        });
    }
}
