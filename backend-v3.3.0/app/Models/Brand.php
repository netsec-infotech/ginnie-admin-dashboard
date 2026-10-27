<?php

namespace App\Models;

use App\Enums\SpatieMediaCollectionName;
use App\Services\CatalogLanguageService;
use App\Services\LanguageService;
use App\Traits\HasSeoMetadata;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;
use Spatie\Translatable\HasTranslations;

/**
 * @method static create(mixed $validated)
 * @method static find($id)
 * @method static count()
 */
class Brand extends Model implements HasMedia
{
    use HasFactory, InteractsWithMedia, HasSeoMetadata, HasTranslations;

    public array $translatable = ['title', 'description'];

    public bool $useFallbackLocale = true;

    protected $appends = ['logo'];
    protected $fillable = [
        'uuid',
        'slug',
        'title',
        'description',
        'status',
        'metadata',
        'scope_type',
        'scope_id',
    ];

    protected $casts = [
        'metadata' => 'array',
    ];

    public function getLogoAttribute(): string
    {
        return $this->getFirstMediaUrl(SpatieMediaCollectionName::BRAND_LOGO());
    }

    public function scopeCategory(): BelongsTo
    {
        return $this->belongsTo(Category::class, 'scope_id');
    }

    public function products(): HasMany
    {
        return $this->hasMany(Product::class);
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
     * Search across requested locale and canonical English fallback.
     */
    public function scopeWhereTitleLike(Builder $query, string $search, ?string $locale = null): Builder
    {
        $locale = $this->catalogLocale($locale);
        $escaped = str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], trim($search));
        $normalized = mb_strtolower($escaped);
        $lowerLikeExpr = "%{$normalized}%";
        $driver = $query->getConnection()->getDriverName();

        return $query->where(function ($q) use ($driver, $locale, $lowerLikeExpr) {
            match ($driver) {
                'sqlite' => $q->whereRaw("LOWER(json_extract(title, '$.{$locale}')) LIKE ? OR LOWER(json_extract(title, '$.en')) LIKE ?", [$lowerLikeExpr, $lowerLikeExpr]),
                'pgsql' => $q->whereRaw("LOWER(title->>'{$locale}') LIKE ? OR LOWER(title->>'en') LIKE ?", [$lowerLikeExpr, $lowerLikeExpr]),
                default => $q->whereRaw("LOWER(JSON_UNQUOTE(JSON_EXTRACT(title, '$.\"{$locale}\"'))) LIKE ? OR LOWER(JSON_UNQUOTE(JSON_EXTRACT(title, '$.\"en\"'))) LIKE ?", [$lowerLikeExpr, $lowerLikeExpr]),
            };
        });
    }

    /**
     * Match the canonical English title case-insensitively across supported databases.
     */
    public function scopeWhereEnglishTitle(Builder $query, string $title): Builder
    {
        $normalized = mb_strtolower(trim($title));
        $driver = $query->getConnection()->getDriverName();

        return match ($driver) {
            'sqlite' => $query->whereRaw("LOWER(json_extract(title, '$.en')) = ?", [$normalized]),
            'pgsql' => $query->whereRaw("LOWER(title->>'en') = ?", [$normalized]),
            default => $query->whereRaw("LOWER(JSON_UNQUOTE(JSON_EXTRACT(title, '$.en'))) = ?", [$normalized]),
        };
    }

    /**
     * Order by localized title with fallback to canonical English.
     */
    public function scopeOrderByTitle(Builder $query, string $direction = 'asc', ?string $locale = null): Builder
    {
        $locale = $this->catalogLocale($locale);
        $direction = strtolower($direction) === 'desc' ? 'desc' : 'asc';

        $driver = $query->getConnection()->getDriverName();
        $expression = match ($driver) {
            'sqlite' => "COALESCE(NULLIF(json_extract(title, '$.{$locale}'), ''), NULLIF(json_extract(title, '$.en'), ''), id)",
            'pgsql' => "COALESCE(NULLIF(title->>'{$locale}', ''), NULLIF(title->>'en', ''), id::text)",
            default => "COALESCE(NULLIF(JSON_UNQUOTE(JSON_EXTRACT(title, '$.\"{$locale}\"')), ''), NULLIF(JSON_UNQUOTE(JSON_EXTRACT(title, '$.\"en\"')), ''), id)",
        };

        return $query->orderByRaw("{$expression} {$direction}");
    }

    public function getFallbackLocale(): string
    {
        return 'en';
    }

    private function catalogLocale(?string $locale): string
    {
        $requested = app(LanguageService::class)->normalizeCode($locale ?: app()->getLocale());

        return app(CatalogLanguageService::class)->isValidCatalogLocale($requested) ? $requested : 'en';
    }

    public function registerMediaCollections(): void
    {
        $this->addMediaCollection(SpatieMediaCollectionName::BRAND_LOGO())->singleFile();
    }

    protected static function booted(): void
    {
        static::creating(function (Brand $brand) {
            if (empty($brand->uuid)) {
                $brand->uuid = (string) Str::uuid();
            }

            if (empty($brand->slug)) {
                $translations = $brand->getTranslations('title') ?: [];
                $canonicalTitle = $translations['en'] ?? (reset($translations) ?: '');
                if (! empty($canonicalTitle)) {
                    $brand->slug = generateUniqueSlug(self::class, $canonicalTitle, 'slug');
                }
            }
        });

        static::deleting(function ($brand) {
            $brand->clearMediaCollection(SpatieMediaCollectionName::BRAND_LOGO());
        });
    }
}
