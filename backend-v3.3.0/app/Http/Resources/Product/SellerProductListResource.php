<?php

namespace App\Http\Resources\Product;

class SellerProductListResource extends ProductListResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray($request): array
    {
        $data = parent::toArray($request);
        $data['title'] = $this->getTranslation('title', 'en', false);
        $data['short_description'] = $this->getTranslation('short_description', 'en', false);
        $data['category_name'] = $this->category?->getTranslation('title', 'en', false);
        $data['brand_name'] = $this->brand?->getTranslation('title', 'en', false);
        $data['translations'] = [
            'title' => $this->getTranslations('title'),
            'short_description' => $this->getTranslations('short_description'),
            'description' => $this->getTranslations('description'),
        ];

        return $data;
    }
}
