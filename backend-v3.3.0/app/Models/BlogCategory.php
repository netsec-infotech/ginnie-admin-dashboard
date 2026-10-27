<?php

namespace App\Models;

use App\Enums\SpatieMediaCollectionName;
use App\Traits\HasSeoMetadata;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

class BlogCategory extends Model implements HasMedia
{
    /** @use HasFactory<\Database\Factories\BlogCategoryFactory> */
    use HasFactory, HasSeoMetadata, InteractsWithMedia, SoftDeletes;

    protected $fillable = [
        'name',
        'slug',
        'description',
        'status',
        'sort_order',
        'metadata',
    ];

    protected $appends = ['image'];

    protected function casts(): array
    {
        return [
            'status' => 'boolean',
            'sort_order' => 'integer',
            'metadata' => 'array',
        ];
    }

    public function registerMediaCollections(): void
    {
        $this->addMediaCollection(SpatieMediaCollectionName::BLOG_CATEGORY_IMAGE())->singleFile();
    }

    protected static function booted(): void
    {
        static::deleting(function (BlogCategory $category): void {
            Blog::query()->whereJsonContains('category_ids', $category->id)->get()->each(function (Blog $blog) use ($category): void {
                $ids = array_values(array_diff((array) ($blog->category_ids ?? []), [$category->id]));
                $blog->update(['category_ids' => $ids]);
            });
        });
    }

    public function getImageAttribute(): ?string
    {
        return $this->getFirstMediaUrl(SpatieMediaCollectionName::BLOG_CATEGORY_IMAGE()) ?: null;
    }

    /**
     * Get count of published blogs in this category.
     */
    public function getBlogsCountAttribute(): int
    {
        return Blog::query()->published()->whereJsonContains('category_ids', $this->id)->count();
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', true);
    }
}
