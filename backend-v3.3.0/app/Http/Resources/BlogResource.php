<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class BlogResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $author = $this->authorProfile;
        $categories = $this->categories;
        $primaryCategory = $categories->first();

        return [
            'id' => $this->id,
            'title' => $this->title,
            'slug' => $this->slug,
            'short_description' => $this->short_description,
            'image' => $this->image,
            'is_editors_pick' => (bool) $this->is_editors_pick,
            'published_at' => $this->published_at?->toIso8601String(),
            'views_count' => (int) $this->views_count,
            'categories' => $categories->map(fn ($cat): array => [
                'id' => $cat->id,
                'name' => $cat->name,
                'slug' => $cat->slug,
                'image' => $cat->image,
            ])->values()->all(),
            'category' => $primaryCategory ? [
                'id' => $primaryCategory->id,
                'name' => $primaryCategory->name,
                'slug' => $primaryCategory->slug,
                'image' => $primaryCategory->image,
            ] : null,
            'tags' => (array) ($this->tags ?? []),
            'author' => $author ? [
                'id' => $author->id,
                'username' => $author->username,
                'bio' => $author->bio,
                'photo_url' => $author->photo_url,
            ] : null,
        ];
    }
}
