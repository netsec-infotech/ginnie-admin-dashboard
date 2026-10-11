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

    protected $appends = ['image'];

    protected $fillable = [
        'home_section_id',
        'purpose',
        'itemable_type',
        'itemable_id',
        'title',
        'link_url',
        'sort_order',
    ];

    protected $casts = [
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
    }

    public function getImageAttribute(): ?string
    {
        return $this->getFirstMediaUrl(SpatieMediaCollectionName::HOME_SECTION_ITEM_IMAGE()) ?: null;
    }

    protected static function booted(): void
    {
        static::deleting(function (HomeSectionItem $item) {
            $item->clearMediaCollection(SpatieMediaCollectionName::HOME_SECTION_ITEM_IMAGE());
        });
    }
}
