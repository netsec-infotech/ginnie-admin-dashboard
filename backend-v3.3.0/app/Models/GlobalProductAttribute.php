<?php

namespace App\Models;

use App\Services\CatalogLanguageService;
use App\Services\LanguageService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Translatable\HasTranslations;

class GlobalProductAttribute extends Model
{
    use HasTranslations, SoftDeletes;

    public array $translatable = ['title'];

    public bool $useFallbackLocale = true;

    protected $fillable = [
        'seller_id',
        'title',
        'label',
        'slug',
        'swatche_type',
    ];

    protected $casts = [
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];

    public function seller(): BelongsTo
    {
        return $this->belongsTo(Seller::class);
    }

    public function values(): HasMany
    {
        return $this->hasMany(GlobalProductAttributeValue::class, 'global_attribute_id');
    }

    public function productVariantAttribute(): HasMany
    {
        return $this->hasMany(ProductVariantAttribute::class, 'global_attribute_id');
    }

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

    public function scopeWhereTitleLike(Builder $query, string $search, ?string $locale = null): Builder
    {
        $locale = app(CatalogLanguageService::class)->isValidCatalogLocale($locale)
            ? app(LanguageService::class)->normalizeCode((string) $locale)
            : 'en';
        $like = '%'.mb_strtolower(str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], trim($search))).'%';
        $driver = $query->getConnection()->getDriverName();

        return $query->where(function (Builder $builder) use ($driver, $locale, $like): void {
            match ($driver) {
                'sqlite' => $builder->whereRaw("LOWER(json_extract(title, '$.{$locale}')) LIKE ? OR LOWER(json_extract(title, '$.en')) LIKE ?", [$like, $like]),
                'pgsql' => $builder->whereRaw("LOWER(title->>'{$locale}') LIKE ? OR LOWER(title->>'en') LIKE ?", [$like, $like]),
                default => $builder->whereRaw("LOWER(JSON_UNQUOTE(JSON_EXTRACT(title, '$.\"{$locale}\"'))) LIKE ? OR LOWER(JSON_UNQUOTE(JSON_EXTRACT(title, '$.en'))) LIKE ?", [$like, $like]),
            };
        });
    }

    protected static function booted(): void
    {
        static::saving(function (GlobalProductAttribute $attribute): void {
            $englishTitle = $attribute->getTranslation('title', 'en', false);
            if ($englishTitle !== '') {
                $attribute->slug = generateUniqueSlug(self::class, $englishTitle, $attribute->id);
            }
        });
    }

    public function getFallbackLocale(): string
    {
        return 'en';
    }
}
