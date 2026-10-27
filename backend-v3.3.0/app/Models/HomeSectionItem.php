<?php

namespace App\Models;

use App\Enums\SpatieMediaCollectionName;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

class HomeSectionItem extends Model implements HasMedia
{
    use HasFactory, InteractsWithMedia;

    protected $appends = ['image', 'image_web_mobile'];

    protected $fillable = [
        'home_section_id',
        'purpose',
        'itemable_type',
        'itemable_id',
        'title',
        'link_url',
        'config',
        'sort_order',
    ];

    protected $casts = [
        'config' => 'array',
        'sort_order' => 'integer',
    ];

    public function section(): BelongsTo
    {
        return $this->belongsTo(HomeSection::class, 'home_section_id');
    }

    /** The Product / Category / Brand this item points at (null for custom-URL banners). */
    public function itemable(): MorphTo
    {
        return $this->morphTo();
    }

    public function registerMediaCollections(): void
    {
        $this->addMediaCollection(SpatieMediaCollectionName::HOME_SECTION_ITEM_IMAGE())->singleFile();
        $this->addMediaCollection(SpatieMediaCollectionName::HOME_SECTION_ITEM_IMAGE_WEB_MOBILE())->singleFile();
    }

    public function getImageAttribute(): ?string
    {
        return $this->getFirstMediaUrl(SpatieMediaCollectionName::HOME_SECTION_ITEM_IMAGE()) ?: null;
    }

    public function getImageWebMobileAttribute(): ?string
    {
        return $this->getFirstMediaUrl(SpatieMediaCollectionName::HOME_SECTION_ITEM_IMAGE_WEB_MOBILE()) ?: null;
    }

    protected static function booted(): void
    {
        static::deleting(function (HomeSectionItem $item) {
            $item->clearMediaCollection(SpatieMediaCollectionName::HOME_SECTION_ITEM_IMAGE());
            $item->clearMediaCollection(SpatieMediaCollectionName::HOME_SECTION_ITEM_IMAGE_WEB_MOBILE());
        });
    }
}
