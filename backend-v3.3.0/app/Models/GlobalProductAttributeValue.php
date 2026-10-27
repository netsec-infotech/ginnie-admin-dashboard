<?php

namespace App\Models;

use App\Enums\Attribute\AttributeTypesEnum;
use App\Services\CatalogLanguageService;
use App\Services\LanguageService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;
use Spatie\Translatable\HasTranslations;

class GlobalProductAttributeValue extends Model implements HasMedia
{
    use HasTranslations, InteractsWithMedia;

    public array $translatable = ['title'];

    public bool $useFallbackLocale = true;

    protected $fillable = [
        'global_attribute_id',
        'title',
        'swatche_value',
    ];

    protected $casts = [
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function attribute(): BelongsTo
    {
        return $this->belongsTo(GlobalProductAttribute::class, 'global_attribute_id');
    }

    public function productVariantAttributeValue(): HasMany
    {
        return $this->hasMany(ProductVariantAttribute::class, 'global_attribute_value_id');
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

    public function getSwatcheValueAttribute()
    {
        return ($this->attribute->swatche_type === AttributeTypesEnum::IMAGE())
            ? $this->getFirstMediaUrl('swatche_image')
            : $this->attributes['swatche_value'] ?? null;
    }

    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('swatche_image')->singleFile();
    }

    protected static function booted(): void
    {
        static::deleting(function ($attributeValue) {
            $attributeValue->clearMediaCollection('swatche_image');
        });
    }

    public function getFallbackLocale(): string
    {
        return 'en';
    }
}
