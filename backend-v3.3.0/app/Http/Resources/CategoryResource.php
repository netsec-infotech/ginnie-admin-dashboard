<?php

namespace App\Http\Resources;

use App\Enums\Category\CategoryBackgroundTypeEnum;
use App\Helpers\TranslationHelper;
use Illuminate\Http\Resources\Json\JsonResource;

class CategoryResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     */
    public function toArray($request): array
    {
        $isColorBg = $this->background_type === CategoryBackgroundTypeEnum::COLOR || $this->background_type?->value === CategoryBackgroundTypeEnum::COLOR->value;
        $isImageBg = $this->background_type === CategoryBackgroundTypeEnum::IMAGE || $this->background_type?->value === CategoryBackgroundTypeEnum::IMAGE->value;
        $isAnimationBg = $this->background_type === CategoryBackgroundTypeEnum::ANIMATION || $this->background_type?->value === CategoryBackgroundTypeEnum::ANIMATION->value;
        $titles = $this->getTranslations('title') ?: [];
        $descriptions = $this->getTranslations('description') ?: [];

        return [
            'id' => $this->id,
            'title' => TranslationHelper::resolveText($titles),
            'slug' => $this->slug,
            'image' => $this->custom_image_url ?? $this->image ?? '',
            'icon' => $this->icon ?? '',
            'active_icon' => $this->active_icon ?? '',
            'background_type' => $this->background_type?->value ?? null,
            'background_color' => $isColorBg ? ($this->background_color ?? '') : '',
            'background_color_web' => $isColorBg ? ($this->background_color_web ?? '') : '',
            'background_color_app' => $isColorBg ? ($this->background_color_app ?? '') : '',
            'background_image' => $isImageBg ? ($this->background_image ?? '') : '',
            'background_image_web' => $isImageBg ? ($this->background_image_web ?? '') : '',
            'background_image_web_mobile' => $isImageBg ? ($this->background_image_web_mobile ?? '') : '',
            'background_image_app' => $isImageBg ? ($this->background_image_app ?? '') : '',
            'background_animation' => $isAnimationBg ? ($this->background_animation ?? '') : '',
            'background_animation_web' => $isAnimationBg ? ($this->background_animation_web ?? '') : '',
            'background_animation_app' => $isAnimationBg ? ($this->background_animation_app ?? '') : '',
            'font_color' => $this->font_color ?? '',
            'search_labels' => $this->resource->resolveSearchLabels(),
            'parent_id' => $this->parent_id,
            'commission' => $this->commission ?? 0,
            'parent_slug' => $this->parent->slug ?? null,
            'description' => $descriptions === [] ? null : TranslationHelper::resolveText($descriptions),
            'status' => $this->status,
            'requires_approval' => $this->requires_approval,
            'metadata' => $this->metadataWithSeo(),
            'subcategory_count' => $this->children_count ?? 0,
            'product_count' => $this->products_count ?? 0,
            // Optional flag injected by controllers to indicate if this option is currently selectable
            'enabled' => (bool) data_get($this->resource, 'enabled', true),
        ];
    }
}
