<?php

namespace App\Http\Resources\Admin;

use Illuminate\Http\Resources\Json\JsonResource;

class BrandManagementResource extends JsonResource
{
    /**
     * Transform the resource into an array for Admin and Seller management interfaces.
     */
    public function toArray($request): array
    {
        $hasTranslations = method_exists($this->resource, 'getTranslations');

        $titleTranslations = $hasTranslations ? ($this->getTranslations('title') ?: []) : [];
        $descTranslations = $hasTranslations ? ($this->getTranslations('description') ?: []) : [];

        return [
            'id' => $this->id,
            'uuid' => $this->uuid,
            'title' => (string) ($titleTranslations['en'] ?? ''),
            'slug' => $this->slug,
            'logo' => $this->custom_image_url ?? $this->logo ?? '',
            'status' => $this->status,
            'scope_type' => $this->scope_type,
            'scope_id' => $this->scope_id,
            'scope_category_slug' => $this->scopeCategory->slug ?? '',
            'scope_category_title' => $this->scopeCategory?->getTranslation('title', 'en', false) ?? '',
            'description' => (string) ($descTranslations['en'] ?? ''),
            'translations' => [
                'title' => $titleTranslations,
                'description' => $descTranslations,
            ],
            'metadata' => $this->metadataWithSeo(),
            'total_products' => $this->products_count ?? $this->products()->count(),
            'enabled' => (bool) data_get($this->resource, 'enabled', true),
        ];
    }
}
