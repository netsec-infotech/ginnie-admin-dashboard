<?php

namespace App\Http\Resources;

use App\Helpers\TranslationHelper;
use Illuminate\Http\Resources\Json\JsonResource;

class BrandResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     */
    public function toArray($request): array
    {
        $hasTranslations = method_exists($this->resource, 'getTranslations');

        $titleTranslations = $hasTranslations ? ($this->getTranslations('title') ?: []) : [];
        $descTranslations = $hasTranslations ? ($this->getTranslations('description') ?: []) : [];

        return [
            'id' => $this->id,
            'title' => ! empty($titleTranslations) ? TranslationHelper::resolveText($titleTranslations) : ($this->title ?? ''),
            'slug' => $this->slug,
            'logo' => $this->custom_image_url ?? $this->logo ?? '',
            'status' => $this->status,
            'scope_type' => $this->scope_type,
            'scope_id' => $this->scope_id,
            'scope_category_slug' => $this->scopeCategory->slug ?? '',
            'scope_category_title' => $this->scopeCategory->title ?? '',
            'description' => ! empty($descTranslations) ? TranslationHelper::resolveText($descTranslations) : ($this->description ?? ''),
            'metadata' => $this->metadataWithSeo(),
            'total_products' => $this->products_count ?? $this->products()->count(),
            // Optional flag injected by controllers to indicate if this option is currently selectable
            'enabled' => (bool) data_get($this->resource, 'enabled', true),
        ];
    }
}
