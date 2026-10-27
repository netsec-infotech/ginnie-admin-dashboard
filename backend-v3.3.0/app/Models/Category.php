<?php

namespace App\Models;

use App\Enums\Category\CategoryBackgroundTypeEnum;
use App\Enums\SettingTypeEnum;
use App\Enums\SpatieMediaCollectionName;
use App\Services\CatalogLanguageService;
use App\Services\LanguageService;
use App\Services\SettingService;
use App\Traits\HasSeoMetadata;
use Exception;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;
use Spatie\Translatable\HasTranslations;

class Category extends Model implements HasMedia
{
    use SoftDeletes, InteractsWithMedia, HasSeoMetadata, HasTranslations;

    public array $translatable = ['title', 'description'];

    public bool $useFallbackLocale = true;

    protected $appends = [
        'image',
        'icon',
        'active_icon',
        'background_color_web',
        'background_color_app',
        'background_image',
        'background_image_web',
        'background_image_app',
        'background_animation',
        'background_animation_web',
        'background_animation_app',
    ];

    protected $fillable = [
        'uuid',
        'parent_id',
        'title',
        'slug',
        'description',
        'status',
        'requires_approval',
        'commission',
        'sort_order',
        'is_home_category',
        'background_type',
        'background_color',
        'font_color',
        'metadata'
    ];

    protected $casts = [
        'requires_approval' => 'boolean',
        'is_home_category' => 'boolean',
        'commission' => 'decimal:2',
        'background_type' => CategoryBackgroundTypeEnum::class,
        'metadata' => 'array',
    ];

    /**
     * Get the featured sections associated with the category.
     */
    public function featuredSections(): BelongsToMany
    {
        return $this->belongsToMany(FeaturedSection::class, 'category_featured_section')
            ->withTimestamps();
    }

    public function setCommissionAttribute($value): void
    {
        $this->attributes['commission'] = empty($value) ? 0 : $value;
    }

    public function getImageAttribute(): ?string
    {
        return !empty($this->getFirstMediaUrl('image')) ? $this->getFirstMediaUrl('image') : asset('assets/images/category-placeholder.png');
    }

    public function getIconAttribute(): ?string
    {
        return $this->getFirstMediaUrl(SpatieMediaCollectionName::CATEGORY_ICON());
    }

    public function getActiveIconAttribute(): ?string
    {
        return $this->getFirstMediaUrl(SpatieMediaCollectionName::CATEGORY_ACTIVE_ICON());
    }

    public function getBackgroundColorWebAttribute(): ?string
    {
        return $this->metadata['background_color_web'] ?? $this->background_color;
    }

    public function getBackgroundColorAppAttribute(): ?string
    {
        return $this->metadata['background_color_app'] ?? $this->background_color;
    }

    public function getBackgroundImageAttribute(): ?string
    {
        return $this->getFirstMediaUrl(SpatieMediaCollectionName::CATEGORY_BACKGROUND_IMAGE())
            ?: ($this->getFirstMediaUrl(SpatieMediaCollectionName::CATEGORY_BACKGROUND_IMAGE_WEB())
            ?: $this->getFirstMediaUrl(SpatieMediaCollectionName::CATEGORY_BACKGROUND_IMAGE_APP()));
    }

    public function getBackgroundImageWebAttribute(): ?string
    {
        return $this->getFirstMediaUrl(SpatieMediaCollectionName::CATEGORY_BACKGROUND_IMAGE_WEB())
            ?: $this->getFirstMediaUrl(SpatieMediaCollectionName::CATEGORY_BACKGROUND_IMAGE());
    }

    public function getBackgroundImageWebMobileAttribute(): ?string
    {
        return $this->getFirstMediaUrl(SpatieMediaCollectionName::CATEGORY_BACKGROUND_IMAGE_WEB_MOBILE())
            ?: $this->getFirstMediaUrl(SpatieMediaCollectionName::CATEGORY_BACKGROUND_IMAGE_WEB());
    }

    public function getBackgroundImageAppAttribute(): ?string
    {
        return $this->getFirstMediaUrl(SpatieMediaCollectionName::CATEGORY_BACKGROUND_IMAGE_APP())
            ?: $this->getFirstMediaUrl(SpatieMediaCollectionName::CATEGORY_BACKGROUND_IMAGE());
    }

    public function getBackgroundAnimationAttribute(): ?string
    {
        return $this->getFirstMediaUrl(SpatieMediaCollectionName::CATEGORY_BACKGROUND_ANIMATION())
            ?: ($this->getFirstMediaUrl(SpatieMediaCollectionName::CATEGORY_BACKGROUND_ANIMATION_WEB())
            ?: $this->getFirstMediaUrl(SpatieMediaCollectionName::CATEGORY_BACKGROUND_ANIMATION_APP()));
    }

    public function getBackgroundAnimationWebAttribute(): ?string
    {
        return $this->getFirstMediaUrl(SpatieMediaCollectionName::CATEGORY_BACKGROUND_ANIMATION_WEB())
            ?: $this->getFirstMediaUrl(SpatieMediaCollectionName::CATEGORY_BACKGROUND_ANIMATION());
    }

    public function getBackgroundAnimationAppAttribute(): ?string
    {
        return $this->getFirstMediaUrl(SpatieMediaCollectionName::CATEGORY_BACKGROUND_ANIMATION_APP())
            ?: $this->getFirstMediaUrl(SpatieMediaCollectionName::CATEGORY_BACKGROUND_ANIMATION());
    }

    // Relationships
    public function parent(): BelongsTo
    {
        return $this->belongsTo(Category::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(Category::class, 'parent_id');
    }

    public function searchLabelSetting(): HasOne
    {
        return $this->hasOne(CategorySearchLabel::class);
    }

    public function products(): HasMany
    {
        return $this->hasMany(Product::class);
    }

    public function resolveSearchLabels(): array
    {
        $searchLabels = $this->searchLabelSetting?->search_labels ?? [];

        if (! empty($searchLabels)) {
            return $searchLabels;
        }

        static $globalSearchLabels = null;

        if ($globalSearchLabels === null) {
            $resource = app(SettingService::class)->getSettingByVariable(SettingTypeEnum::HOME_GENERAL_SETTINGS());
            $globalSearchLabels = $resource?->value['searchLabels'] ?? [];
        }

        return $globalSearchLabels;
    }

    // Scopes
    public function scopeOrdered($query)
    {
        return $query->orderBy('sort_order');
    }

    public function scopeWhereEnglishTitle(Builder $query, string $title): Builder
    {
        $normalized = mb_strtolower(trim($title));
        $driver = $query->getConnection()->getDriverName();

        return match ($driver) {
            'sqlite' => $query->whereRaw("LOWER(json_extract(title, '$.\"en\"')) = ?", [$normalized]),
            'pgsql' => $query->whereRaw("LOWER(title->>'en') = ?", [$normalized]),
            default => $query->whereRaw("LOWER(JSON_UNQUOTE(JSON_EXTRACT(title, '$.en'))) = ?", [$normalized]),
        };
    }

    public function scopeWhereEnglishTitleLike(Builder $query, string $search): Builder
    {
        $escaped = str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], mb_strtolower(trim($search)));
        $like = "%{$escaped}%";
        $driver = $query->getConnection()->getDriverName();

        return match ($driver) {
            'sqlite' => $query->whereRaw("LOWER(json_extract(title, '$.\"en\"')) LIKE ? ESCAPE '\\'", [$like]),
            'pgsql' => $query->whereRaw("LOWER(title->>'en') LIKE ? ESCAPE '\\'", [$like]),
            default => $query->whereRaw("LOWER(JSON_UNQUOTE(JSON_EXTRACT(title, '$.\"en\"'))) LIKE ?", [$like]),
        };
    }

    public function scopeWhereLocalizedTextLike(Builder $query, string $search, ?string $locale = null): Builder
    {
        $locale = $this->catalogLocale($locale);
        $escaped = str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], mb_strtolower(trim($search)));
        $like = "%{$escaped}%";
        $driver = $query->getConnection()->getDriverName();

        return $query->where(function (Builder $builder) use ($driver, $locale, $like): void {
            match ($driver) {
                'sqlite' => $builder->whereRaw("LOWER(json_extract(title, '$.\"{$locale}\"')) LIKE ? ESCAPE '\\' OR LOWER(json_extract(title, '$.\"en\"')) LIKE ? ESCAPE '\\' OR LOWER(json_extract(description, '$.\"{$locale}\"')) LIKE ? ESCAPE '\\' OR LOWER(json_extract(description, '$.\"en\"')) LIKE ? ESCAPE '\\'", [$like, $like, $like, $like]),
                'pgsql' => $builder->whereRaw("LOWER(title->>'{$locale}') LIKE ? ESCAPE '\\' OR LOWER(title->>'en') LIKE ? ESCAPE '\\' OR LOWER(description->>'{$locale}') LIKE ? ESCAPE '\\' OR LOWER(description->>'en') LIKE ? ESCAPE '\\'", [$like, $like, $like, $like]),
                default => $builder->whereRaw("LOWER(JSON_UNQUOTE(JSON_EXTRACT(title, '$.\"{$locale}\"'))) LIKE ? OR LOWER(JSON_UNQUOTE(JSON_EXTRACT(title, '$.\"en\"'))) LIKE ? OR LOWER(JSON_UNQUOTE(JSON_EXTRACT(description, '$.\"{$locale}\"'))) LIKE ? OR LOWER(JSON_UNQUOTE(JSON_EXTRACT(description, '$.\"en\"'))) LIKE ?", [$like, $like, $like, $like]),
            };
        });
    }

    public function scopeWhereEnglishTextLike(Builder $query, string $search): Builder
    {
        return $query->whereLocalizedTextLike($search, 'en');
    }

    public function scopeOrderByTitle(Builder $query, string $direction = 'asc', ?string $locale = null): Builder
    {
        $locale = $this->catalogLocale($locale);

        return $this->orderByTranslatedTitle($query, $direction, $locale);
    }

    public function scopeOrderByEnglishTitle(Builder $query, string $direction = 'asc'): Builder
    {
        return $this->orderByTranslatedTitle($query, $direction, 'en');
    }

    public function getFallbackLocale(): string
    {
        return 'en';
    }

    public function getCategoryHierarchy(): array
    {
        $hierarchy = [];
        $current = $this;

        while ($current) {
            array_unshift($hierarchy, [
                'id' => $current->id,
                'uuid' => $current->uuid,
                'title' => $current->title,
                'slug' => $current->slug,
            ]);
            $current = $current->parent;
        }

        return $hierarchy;
    }

    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('image')->singleFile();
        $this->addMediaCollection(SpatieMediaCollectionName::CATEGORY_ICON())->singleFile();
        $this->addMediaCollection(SpatieMediaCollectionName::CATEGORY_ACTIVE_ICON())->singleFile();
        $this->addMediaCollection(SpatieMediaCollectionName::CATEGORY_BACKGROUND_IMAGE())->singleFile();
        $this->addMediaCollection(SpatieMediaCollectionName::CATEGORY_BACKGROUND_IMAGE_WEB())->singleFile();
        $this->addMediaCollection(SpatieMediaCollectionName::CATEGORY_BACKGROUND_IMAGE_WEB_MOBILE())->singleFile();
        $this->addMediaCollection(SpatieMediaCollectionName::CATEGORY_BACKGROUND_IMAGE_APP())->singleFile();
        $this->addMediaCollection(SpatieMediaCollectionName::CATEGORY_BACKGROUND_ANIMATION())->singleFile();
        $this->addMediaCollection(SpatieMediaCollectionName::CATEGORY_BACKGROUND_ANIMATION_WEB())->singleFile();
        $this->addMediaCollection(SpatieMediaCollectionName::CATEGORY_BACKGROUND_ANIMATION_APP())->singleFile();
    }

    protected static function booted(): void
    {
        static::creating(function (Category $category): void {
            $rawTitle = $category->getRawOriginal('title');
            if (is_string($rawTitle) && ! is_array(json_decode($rawTitle, true))) {
                $category->setTranslations('title', ['en' => $rawTitle]);
            }

            $rawDescription = $category->getRawOriginal('description');
            if (is_string($rawDescription) && ! is_array(json_decode($rawDescription, true))) {
                $category->setTranslations('description', ['en' => $rawDescription]);
            }

            if (empty($category->uuid)) {
                $category->uuid = (string) Str::uuid();
            }

            if (empty($category->slug)) {
                $title = $category->getTranslations('title')['en'] ?? '';
                if ($title !== '') {
                    $category->slug = generateUniqueSlug(self::class, $title);
                }
            }
        });

        static::deleting(function ($category) {
            if ($category->products()->exists()) {
                throw new Exception(__('messages.category_cannot_be_deleted_with_products'));
            }
            // Prevent deletion if any direct child category has products assigned
            if ($category->children()->whereHas('products')->exists()) {
                throw new Exception(__('messages.category_cannot_be_deleted_with_products'));
            }
            $category->clearMediaCollection('image');
            $category->clearMediaCollection(SpatieMediaCollectionName::CATEGORY_ICON());
            $category->clearMediaCollection(SpatieMediaCollectionName::CATEGORY_ACTIVE_ICON());
            $category->clearMediaCollection(SpatieMediaCollectionName::CATEGORY_BACKGROUND_IMAGE());
            $category->clearMediaCollection(SpatieMediaCollectionName::CATEGORY_BACKGROUND_IMAGE_WEB());
            $category->clearMediaCollection(SpatieMediaCollectionName::CATEGORY_BACKGROUND_IMAGE_WEB_MOBILE());
            $category->clearMediaCollection(SpatieMediaCollectionName::CATEGORY_BACKGROUND_IMAGE_APP());
            $category->clearMediaCollection(SpatieMediaCollectionName::CATEGORY_BACKGROUND_ANIMATION());
            $category->clearMediaCollection(SpatieMediaCollectionName::CATEGORY_BACKGROUND_ANIMATION_WEB());
            $category->clearMediaCollection(SpatieMediaCollectionName::CATEGORY_BACKGROUND_ANIMATION_APP());
        });
    }

    private function catalogLocale(?string $locale): string
    {
        $requested = app(LanguageService::class)->normalizeCode($locale ?: app()->getLocale());

        return app(CatalogLanguageService::class)->isValidCatalogLocale($requested) ? $requested : 'en';
    }

    private function orderByTranslatedTitle(Builder $query, string $direction, string $locale): Builder
    {
        $direction = strtolower($direction) === 'desc' ? 'desc' : 'asc';
        $driver = $query->getConnection()->getDriverName();
        $expression = match ($driver) {
            'sqlite' => "COALESCE(NULLIF(json_extract(title, '$.\"{$locale}\"'), ''), NULLIF(json_extract(title, '$.\"en\"'), ''), id)",
            'pgsql' => "COALESCE(NULLIF(title->>'{$locale}', ''), NULLIF(title->>'en', ''), id::text)",
            default => "COALESCE(NULLIF(JSON_UNQUOTE(JSON_EXTRACT(title, '$.\"{$locale}\"')), ''), NULLIF(JSON_UNQUOTE(JSON_EXTRACT(title, '$.\"en\"')), ''), id)",
        };

        return $query->orderByRaw("{$expression} {$direction}");
    }

    protected function seoFallbackTitle(): ?string
    {
        return $this->getTranslation('title', 'en', false);
    }

    protected function seoFallbackDescription(): ?string
    {
        return $this->getTranslation('description', 'en', false) ?: $this->seoFallbackTitle();
    }
}
