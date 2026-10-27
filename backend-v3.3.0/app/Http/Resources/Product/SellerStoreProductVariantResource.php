<?php

namespace App\Http\Resources\Product;

use Illuminate\Http\Resources\Json\JsonResource;

class SellerStoreProductVariantResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id' => $this->id,
            'store_id' => $this->store_id,
            'store_slug' => $this->whenLoaded('store', fn () => $this->store?->slug),
            'store_name' => $this->whenLoaded('store', fn () => $this->store?->name),
            'sku' => $this->sku,
            'price' => $this->price_exclude_tax,
            'special_price' => $this->special_price_exclude_tax,
            'display_price' => $this->price,
            'display_special_price' => $this->special_price,
            'cost' => $this->cost,
            'stock' => $this->stock,
        ];
    }
}
