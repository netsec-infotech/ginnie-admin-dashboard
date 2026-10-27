<?php

namespace App\Models;

use App\Enums\Attribute\AttributeTypesEnum;
use App\Enums\SpatieMediaCollectionName;
use App\Traits\HasSeoMetadata;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

class Banner extends Model implements HasMedia
{
    use HasFactory, InteractsWithMedia, HasSeoMetadata;
    protected $appends = ['banner_image'];
    protected $fillable = [
        'type', 'title', 'slug', 'custom_url',
        'product_id', 'category_id', 'brand_id', 'position',
        'visibility_status', 'display_order', 'metadata',
        'scope_type', 'scope_id'
    ];

    protected $casts = [
        'metadata' => 'array',
    ];

    public function setTitleAttribute($value): void
    {
        $this->attributes['title'] = $value;
        $this->attributes['slug'] = generateUniqueSlug(self::class, $value);
    }

    public function getBannerImageAttribute(): string
    {
        return $this->getFirstMediaUrl(SpatieMediaCollectionName::BANNER_IMAGE());
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function brand(): BelongsTo
    {
        return $this->belongsTo(Brand::class);
    }

    public function scopeCategory(): BelongsTo
    {
        return $this->belongsTo(Category::class, 'scope_id');
    }

    // Query scopes for filtering
    public function scopeGlobal($query)
    {
        return $query->where('scope_type', 'global');
    }

    public static function scopeByCategory($query, $categoryId = null)
    {
        $query = $query->where('scope_type', 'category');
        if ($categoryId) {
            $query->where('scope_id', $categoryId);
        }
        return $query;
    }

    public function scopeByScopeType($query, $scopeType)
    {
        return $query->where('scope_type', $scopeType);
    }

    /**
     * Delivery zones this banner is restricted to. Empty pivot = available everywhere.
     */
    public function zones(): BelongsToMany
    {
        return $this->belongsToMany(DeliveryZone::class, 'banner_zone', 'banner_id', 'zone_id')
            ->withTimestamps();
    }

    /**
     * Restrict to banners visible in the given zone.
     * Empty pivot rows are treated as available in every zone.
     */
    public function scopeAvailableInZone(Builder $query, ?int $zoneId): Builder
    {
        if (is_null($zoneId)) {
            return $query;
        }

        return $query->where(function (Builder $q) use ($zoneId) {
            $q->whereDoesntHave('zones')
                ->orWhereHas('zones', fn(Builder $z) => $z->where('delivery_zones.id', $zoneId));
        });
    }

    /**
     * Names of zones this banner is restricted to. Empty collection = available everywhere.
     */
    public function getAvailableZonesAttribute(): Collection
    {
        return $this->zones->pluck('name');
    }

    public function registerMediaCollections(): void
    {
        $this->addMediaCollection(SpatieMediaCollectionName::BANNER_IMAGE())->singleFile();
    }
    protected static function booted(): void
    {
        static::deleting(function ($category) {
            $category->clearMediaCollection(SpatieMediaCollectionName::BANNER_IMAGE());
        });
    }

}
