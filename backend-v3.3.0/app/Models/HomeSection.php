<?php

namespace App\Models;

use App\Enums\HomePage\ItemPurposeEnum;
use App\Enums\SpatieMediaCollectionName;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

class HomeSection extends Model implements HasMedia
{
    use HasFactory, InteractsWithMedia;

    protected $appends = ['background_image', 'hero_media', 'hero_media_web_mobile', 'card_background_image'];

    protected $fillable = [
        'home_layout_id',
        'type',
        'title',
        'style',
        'config',
        'sort_order',
        'is_active',
        'start_at',
        'end_at',
    ];

    protected $casts = [
        'config' => 'array',
        'sort_order' => 'integer',
        'is_active' => 'boolean',
        'start_at' => 'datetime',
        'end_at' => 'datetime',
    ];

    public function layout(): BelongsTo
    {
        return $this->belongsTo(HomeLayout::class, 'home_layout_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(HomeSectionItem::class)->orderBy('sort_order');
    }

    /** Items rendered as cards/entries (hero items, custom picks, banners). */
    public function displayItems(): HasMany
    {
        return $this->items()->where('purpose', ItemPurposeEnum::DISPLAY());
    }

    /** Items that constrain a query (e.g. a products section's category filter). */
    public function filterItems(): HasMany
    {
        return $this->items()->where('purpose', ItemPurposeEnum::FILTER());
    }

    public function zones(): BelongsToMany
    {
        return $this->belongsToMany(DeliveryZone::class, 'home_section_zone', 'home_section_id', 'zone_id')->withTimestamps();
    }

    public function isScheduledActive(?\Illuminate\Support\Carbon $at = null): bool
    {
        if (! $this->is_active) {
            return false;
        }

        $now = $at ?? now();

        if ($this->start_at && $this->start_at->isAfter($now)) {
            return false;
        }

        if ($this->end_at && $this->end_at->isBefore($now)) {
            return false;
        }

        return true;
    }

    public function scopeActive(Builder $query, ?\Illuminate\Support\Carbon $at = null): Builder
    {
        $now = $at ?? now();

        return $query->where('is_active', true)
            ->where(fn (Builder $q) => $q->whereNull('start_at')->orWhere('start_at', '<=', $now))
            ->where(fn (Builder $q) => $q->whereNull('end_at')->orWhere('end_at', '>=', $now));
    }

    public function scopeOfType(Builder $query, string $type): Builder
    {
        return $query->where('type', $type);
    }

    /** Sections visible in the given zone. Empty zone pivot = visible everywhere. */
    public function scopeAvailableInZone(Builder $query, ?int $zoneId): Builder
    {
        if (is_null($zoneId)) {
            return $query;
        }

        return $query->where(function (Builder $q) use ($zoneId) {
            $q->whereDoesntHave('zones')
                ->orWhereHas('zones', fn (Builder $z) => $z->where('delivery_zones.id', $zoneId));
        });
    }

    public function registerMediaCollections(): void
    {
        $this->addMediaCollection(SpatieMediaCollectionName::HOME_SECTION_BACKGROUND())->singleFile();
        $this->addMediaCollection(SpatieMediaCollectionName::HOME_SECTION_HERO_MEDIA())->singleFile();
        $this->addMediaCollection(SpatieMediaCollectionName::HOME_SECTION_HERO_MEDIA_WEB_MOBILE())->singleFile();
        $this->addMediaCollection(SpatieMediaCollectionName::HOME_SECTION_CARD_BACKGROUND())->singleFile();
        $this->addMediaCollection(SpatieMediaCollectionName::HOME_SECTION_ROW_BACKGROUND());
    }

    public function getBackgroundImageAttribute(): ?string
    {
        return $this->getFirstMediaUrl(SpatieMediaCollectionName::HOME_SECTION_BACKGROUND()) ?: null;
    }

    public function getHeroMediaAttribute(): ?string
    {
        return $this->getFirstMediaUrl(SpatieMediaCollectionName::HOME_SECTION_HERO_MEDIA()) ?: null;
    }

    public function getHeroMediaWebMobileAttribute(): ?string
    {
        return $this->getFirstMediaUrl(SpatieMediaCollectionName::HOME_SECTION_HERO_MEDIA_WEB_MOBILE()) ?: null;
    }

    public function getCardBackgroundImageAttribute(): ?string
    {
        return $this->getFirstMediaUrl(SpatieMediaCollectionName::HOME_SECTION_CARD_BACKGROUND()) ?: null;
    }

    protected static function booted(): void
    {
        static::deleting(function (HomeSection $section) {
            $section->clearMediaCollection(SpatieMediaCollectionName::HOME_SECTION_BACKGROUND());
            $section->clearMediaCollection(SpatieMediaCollectionName::HOME_SECTION_HERO_MEDIA());
            $section->clearMediaCollection(SpatieMediaCollectionName::HOME_SECTION_HERO_MEDIA_WEB_MOBILE());
            $section->clearMediaCollection(SpatieMediaCollectionName::HOME_SECTION_CARD_BACKGROUND());
            $section->clearMediaCollection(SpatieMediaCollectionName::HOME_SECTION_ROW_BACKGROUND());
        });
    }
}
