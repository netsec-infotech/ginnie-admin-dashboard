<?php

namespace App\Models;

use App\Enums\ActiveInactiveStatusEnum;
use App\Enums\FeaturedSection\FeaturedSectionTypeEnum;
use App\Enums\SpatieMediaCollectionName;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Collection;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

/**
 * @method static create(mixed $validated)
 */
class FeaturedSection extends Model implements HasMedia
{
    use HasFactory, InteractsWithMedia;

    protected $appends = [
        'background_image',
        'desktop_4k_background_image',
        'desktop_fdh_background_image',
        'tablet_background_image',
        'mobile_background_image',
    ];

    protected $fillable = [
        'title',
        'slug',
        'short_description',
        'style',
        'section_type',
        'sort_order',
        'status',
        'scope_type',
        'scope_id',
        'background_type',
        'background_color',
        'text_color',
    ];

    protected $casts = [
        'sort_order' => 'integer',
        'scope_id' => 'integer',
    ];

    public function setTitleAttribute($value): void
    {
        $this->attributes['title'] = $value;
        $this->attributes['slug'] = generateUniqueSlug(self::class, $value);
    }

    /**
     * Get the categories associated with the featured section.
     */
    public function categories(): BelongsToMany
    {
        return $this->belongsToMany(Category::class, 'category_featured_section')
            ->withTimestamps();
    }

    /**
     * Get the scope category for this featured section.
     */
    public function scopeCategory(): BelongsTo
    {
        return $this->belongsTo(Category::class, 'scope_id');
    }

    /**
     * Products manually assigned to this featured section.
     */
    public function products(): BelongsToMany
    {
        return $this->belongsToMany(Product::class, 'featured_section_product')
            ->withTimestamps()
            ->orderBy('featured_section_product.created_at', 'desc'); // Default order by pivot creation time, can be overridden in getProductsQuery
    }

    /**
     * Delivery zones this featured section is restricted to. Empty pivot = available everywhere.
     */
    public function zones(): BelongsToMany
    {
        return $this->belongsToMany(DeliveryZone::class, 'featured_section_zone', 'featured_section_id', 'zone_id')
            ->withTimestamps();
    }

    /**
     * Restrict to featured sections visible in the given zone.
     * Empty pivot rows are treated as available in every zone.
     */
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

    /**
     * Names of zones this featured section is restricted to. Empty collection = available everywhere.
     */
    public function getAvailableZonesAttribute(): Collection
    {
        return $this->zones->pluck('name');
    }

    public function getBackgroundImageAttribute(): string
    {
        return $this->getFirstMediaUrl(SpatieMediaCollectionName::FEATURED_SECTION_BACKGROUND_IMAGE());
    }

    public function getDesktop4kBackgroundImageAttribute(): string
    {
        return $this->getFirstMediaUrl(SpatieMediaCollectionName::FEATURED_SECTION_BG_DESKTOP_4K());
    }

    public function getDesktopFdhBackgroundImageAttribute(): string
    {
        return $this->getFirstMediaUrl(SpatieMediaCollectionName::FEATURED_SECTION_BG_DESKTOP_FHD());
    }

    public function getTabletBackgroundImageAttribute(): string
    {
        return $this->getFirstMediaUrl(SpatieMediaCollectionName::FEATURED_SECTION_BG_TABLET());
    }

    public function getMobileBackgroundImageAttribute(): string
    {
        return $this->getFirstMediaUrl(SpatieMediaCollectionName::FEATURED_SECTION_BG_MOBILE());
    }

    public function getProductsQuery(?string $sort = null, array $storeIds = []): Builder
    {
        if ($this->section_type === FeaturedSectionTypeEnum::CUSTOM_PRODUCTS()) {
            $query = $this->products()
                ->select('products.*')
                ->getQuery();

            if ($this->scope_type === 'category' && $this->scope_id) {
                $query->where('products.category_id', $this->scope_id);
            }

            $query->applySorting($sort, $storeIds);

            return $query;
        }

        $query = Product::query();

        $categoryIds = $this->categories->pluck('id')->toArray() ?? [];
        if ($this->scope_type === 'category' && $this->scope_id) {
            $query->whereIn('category_id', $categoryIds);
        } elseif ($this->scope_type === 'global') {
            if ($this->categories->isNotEmpty()) {
                $query->whereIn('category_id', $this->categories->pluck('id'));
            }
        }

        switch ($this->section_type) {
            case FeaturedSectionTypeEnum::NEWLY_ADDED():
                $query->orderBy('created_at', 'desc');
                break;
            case FeaturedSectionTypeEnum::TOP_RATED():
                $query->withAvg('reviews', 'rating')
                    ->orderBy('reviews_avg_rating', 'desc');
                break;
            case FeaturedSectionTypeEnum::FEATURED():
                $query->where('featured', '1')
                    ->orderBy('created_at', 'desc');
                break;
            case FeaturedSectionTypeEnum::RECOMMENDED():
                $query->whereNotNull('badge_id')
                    ->orderBy('created_at', 'desc');
                break;
            case FeaturedSectionTypeEnum::BEST_SELLER():
                $query->withCount('orderItems')
                    ->orderBy('order_items_count', 'desc');
                break;
        }

        $query->applySorting($sort, $storeIds);

        return $query;
    }

    /**
     * Scope to get only active featured sections.
     */
    public function scopeActive($query): Builder
    {
        return $query->where('status', ActiveInactiveStatusEnum::ACTIVE());
    }

    /**
     * Scope to order by sort order.
     */
    public function scopeOrdered($query): Builder
    {
        return $query->orderBy('sort_order');
    }

    /**
     * Scope to filter by section type.
     */
    public function scopeByType($query, $type): Builder
    {
        return $query->where('section_type', $type);
    }

    /**
     * Scope to filter by scope type.
     */
    public function scopeByScopeType($query, $scopeType): Builder
    {
        return $query->where('scope_type', $scopeType);
    }

    /**
     * Scope to get global featured sections.
     */
    public function scopeGlobal($query): Builder
    {
        return $query->where('scope_type', 'global');
    }

    /**
     * Scope to get category-specific featured sections.
     */
    public static function scopeByCategory($query, $categoryId = null): Builder
    {
        $query = $query->where('scope_type', 'category');

        if ($categoryId) {
            $query->where('scope_id', $categoryId);
        }

        return $query;
    }

    /**
     * Get the route key for the model.
     */
    public function getRouteKeyName(): string
    {
        return 'slug';
    }
}
