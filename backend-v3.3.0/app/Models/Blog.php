<?php

namespace App\Models;

use App\Enums\Blog\BlogStatusEnum;
use App\Enums\SpatieMediaCollectionName;
use App\Traits\HasSeoMetadata;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Collection;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

class Blog extends Model implements HasMedia
{
    /** @use HasFactory<\Database\Factories\BlogFactory> */
    use HasFactory, HasSeoMetadata, InteractsWithMedia, SoftDeletes;

    protected $fillable = [
        'author_profile_id',
        'category_ids',
        'title',
        'slug',
        'short_description',
        'content',
        'product_ids',
        'tags',
        'status',
        'is_editors_pick',
        'published_at',
        'views_count',
        'metadata',
    ];

    protected $appends = ['image'];

    protected function casts(): array
    {
        return [
            'category_ids' => 'array',
            'product_ids' => 'array',
            'tags' => 'array',
            'status' => BlogStatusEnum::class,
            'is_editors_pick' => 'boolean',
            'published_at' => 'datetime',
            'views_count' => 'integer',
            'metadata' => 'array',
        ];
    }

    public function registerMediaCollections(): void
    {
        $this->addMediaCollection(SpatieMediaCollectionName::BLOG_IMAGE())->singleFile();
    }

    public function authorProfile(): BelongsTo
    {
        return $this->belongsTo(WatchBuyProfile::class, 'author_profile_id');
    }

    /**
     * Get all categories assigned to this blog.
     *
     * @return Collection<int, BlogCategory>
     */
    public function getCategoriesAttribute(): Collection
    {
        $ids = array_values(array_filter(array_map('intval', (array) ($this->category_ids ?? []))));
        if ($ids === []) {
            return collect();
        }

        return BlogCategory::query()->whereIn('id', $ids)->get();
    }

    /**
     * Get the primary (first) category for backwards compatibility.
     */
    public function getCategoryAttribute(): ?BlogCategory
    {
        return $this->categories->first();
    }

    /**
     * Fetch related blogs based on categories and shared tags.
     *
     * @return Collection<int, Blog>
     */
    public function getRelatedBlogs(int $limit = 4): Collection
    {
        $tags = (array) ($this->tags ?? []);
        $categoryIds = array_values(array_filter(array_map('intval', (array) ($this->category_ids ?? []))));

        $query = static::published()
            ->with(['authorProfile.media', 'media'])
            ->where('id', '!=', $this->id);

        if (! empty($categoryIds) || ! empty($tags)) {
            $query->where(function (Builder $q) use ($categoryIds, $tags): void {
                foreach ($categoryIds as $catId) {
                    $q->orWhereJsonContains('category_ids', $catId);
                }
                foreach ($tags as $tag) {
                    $q->orWhereJsonContains('tags', $tag);
                }
            });
        }

        return $query->orderByDesc('published_at')
            ->orderByDesc('id')
            ->take($limit)
            ->get();
    }

    public function getImageAttribute(): ?string
    {
        return $this->getFirstMediaUrl(SpatieMediaCollectionName::BLOG_IMAGE()) ?: null;
    }

    public function scopePublished(Builder $query): Builder
    {
        return $query->where('status', BlogStatusEnum::PUBLISHED->value)
            ->where(function (Builder $q): void {
                $q->whereNull('published_at')
                    ->orWhere('published_at', '<=', now());
            });
    }

    public function scopeEditorsPick(Builder $query): Builder
    {
        return $query->where('is_editors_pick', true);
    }

    public function scopeInCategory(Builder $query, int $categoryId): Builder
    {
        return $query->whereJsonContains('category_ids', $categoryId);
    }

    /**
     * Fetch products linked to this blog.
     *
     * @return Collection<int, Product>
     */
    public function getLinkedProducts(): Collection
    {
        $ids = array_values(array_filter(array_map('intval', (array) ($this->product_ids ?? []))));

        if ($ids === []) {
            return collect();
        }

        return Product::query()
            ->with(['media', 'variants.media', 'category', 'brand'])
            ->whereIn('id', $ids)
            ->get();
    }
}
