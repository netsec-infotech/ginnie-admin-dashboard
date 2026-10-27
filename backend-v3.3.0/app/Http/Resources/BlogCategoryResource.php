<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class BlogCategoryResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $seo = is_array($this->metadata) ? $this->metadata : [];

        return [
            'id' => $this->id,
            'name' => $this->name,
            'slug' => $this->slug,
            'description' => $this->description,
            'image' => $this->image,
            'blogs_count' => (int) $this->blogs_count,
            'seo' => [
                'title' => $seo['seo_title'] ?? $this->name,
                'description' => $seo['seo_description'] ?? $this->description,
                'keywords' => $seo['seo_keywords'] ?? [],
            ],
        ];
    }
}
