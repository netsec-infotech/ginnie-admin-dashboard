<?php

namespace App\Http\Resources\Admin;

use App\Enums\Category\CategoryBackgroundTypeEnum;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CategoryManagementResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $titles = $this->getTranslations('title') ?: [];
        $descriptions = $this->getTranslations('description') ?: [];
        $isColor = $this->background_type === CategoryBackgroundTypeEnum::COLOR || $this->background_type?->value === CategoryBackgroundTypeEnum::COLOR->value;
        $isImage = $this->background_type === CategoryBackgroundTypeEnum::IMAGE || $this->background_type?->value === CategoryBackgroundTypeEnum::IMAGE->value;
        $isAnimation = $this->background_type === CategoryBackgroundTypeEnum::ANIMATION || $this->background_type?->value === CategoryBackgroundTypeEnum::ANIMATION->value;

        return [
            'id' => $this->id,
            'uuid' => $this->uuid,
            'title' => (string) ($titles['en'] ?? ''),
            'description' => array_key_exists('en', $descriptions) ? $descriptions['en'] : null,
            'slug' => $this->slug,
            'parent_id' => $this->parent_id,
            'parent_title' => $this->parent?->getTranslation('title', 'en', false),
            'image' => $this->custom_image_url ?? $this->image ?? '',
            'icon' => $this->icon ?? '',
            'active_icon' => $this->active_icon ?? '',
            'background_type' => $this->background_type?->value ?? null,
            'background_color' => $isColor ? ($this->background_color ?? '') : '',
            'background_color_web' => $isColor ? ($this->background_color_web ?? '') : '',
            'background_color_app' => $isColor ? ($this->background_color_app ?? '') : '',
            'background_image' => $isImage ? ($this->background_image ?? '') : '',
            'background_image_web' => $isImage ? ($this->background_image_web ?? '') : '',
            'background_image_web_mobile' => $isImage ? ($this->background_image_web_mobile ?? '') : '',
            'background_image_app' => $isImage ? ($this->background_image_app ?? '') : '',
            'background_animation' => $isAnimation ? ($this->background_animation ?? '') : '',
            'background_animation_web' => $isAnimation ? ($this->background_animation_web ?? '') : '',
            'background_animation_app' => $isAnimation ? ($this->background_animation_app ?? '') : '',
            'font_color' => $this->font_color ?? '',
            'search_labels' => $this->searchLabelSetting?->search_labels ?? [],
            'commission' => $this->commission ?? 0,
            'status' => $this->status,
            'requires_approval' => $this->requires_approval,
            'is_home_category' => $this->is_home_category,
            'sort_order' => $this->sort_order,
            'metadata' => $this->metadataWithSeo(),
            'translations' => [
                'title' => $titles,
                'description' => $descriptions,
            ],
        ];
    }
}
