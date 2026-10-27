<?php

namespace App\Http\Resources\WatchBuy;

use App\Models\WatchBuyStatus;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class WatchBuyStatusResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'uuid' => $this->uuid,
            'source' => $this->source?->value ?? $this->source,
            'content_type' => $this->content_type?->value ?? $this->content_type,
            'text' => $this->text,
            'media_url' => $this->media_url,
            'media_mime' => $this->media_mime,
            'media_size_bytes' => $this->media_size_bytes,
            'media_width' => $this->media_width,
            'media_height' => $this->media_height,
            'duration_ms' => $this->duration_ms,
            'expires_at' => WatchBuyStatus::doesNotExpireInDemoMode() ? null : $this->expires_at?->toIso8601String(),
            'profile' => new WatchBuyProfileResource($this->whenLoaded('profile')),
            'products' => $this->whenLoaded('products', fn () => $this->products->map(fn ($product) => [
                'product_id' => $product->product_id,
                'product_variant_id' => $product->product_variant_id,
                'name' => $product->product?->title ?? $product->variant?->title,
                'image_url' => $product->variant?->image ?: $product->product?->main_image,
                'store_id' => $product->store_id,
                'sort_order' => $product->sort_order,
            ])),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
