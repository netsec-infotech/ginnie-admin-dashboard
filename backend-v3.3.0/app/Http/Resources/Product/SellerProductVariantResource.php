<?php

namespace App\Http\Resources\Product;

use Illuminate\Http\Resources\Json\JsonResource;

class SellerProductVariantResource extends JsonResource
{
    public function toArray($request): array
    {
        // Format attributes as key-value pairs (attribute_slug => value_title)
        $attributes = [];

        if ($this->relationLoaded('attributes')) {
            foreach ($this->attributes as $attribute) {
                if ($attribute->attribute && $attribute->attributeValue) {
                    $attributes[$attribute->attribute->slug] = $attribute->attributeValue->getTranslation('title', 'en', false);
                }
            }
        } else {
            $this->loadMissing(['attributes.attribute', 'attributes.attributeValue']);
            foreach ($this->attributes as $attribute) {
                if ($attribute->attribute && $attribute->attributeValue) {
                    $attributes[$attribute->attribute->slug] = $attribute->attributeValue->getTranslation('title', 'en', false);
                }
            }
        }

        return [
            'id' => $this->id,
            'title' => $this->getTranslation('title', 'en', false),
            'translations' => [
                'title' => $this->getTranslations('title'),
            ],
            'slug' => $this->slug,
            'image' => $this->image ?? '',
            'weight' => (float) ($this->weight ?? 0),
            'height' => (float) ($this->height ?? 0),
            'breadth' => (float) ($this->breadth ?? 0),
            'length' => (float) ($this->length ?? 0),
            'availability' => $this->availability,
            'barcode' => $this->barcode,
            'is_default' => $this->is_default,
            // Provide store-wise pricing details for this variant
            'stores' => SellerStoreProductVariantResource::collection(
                $this->whenLoaded('storeProductVariants', fn () => $this->storeProductVariants)
            ),
            'attributes' => $attributes,
        ];
    }
}
