<?php

namespace App\Http\Resources\WatchBuy;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class WatchBuyReelResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'uuid' => $this->uuid,
            'slug' => $this->slug,
            'source' => $this->source?->value ?? $this->source,
            'caption' => $this->caption,
            'status' => $this->status?->value ?? $this->status,
            'video_url' => $this->video_url,
            'cover_url' => $this->cover_url,
            'duration_ms' => $this->duration_ms,
            'video_width' => $this->video_width,
            'video_height' => $this->video_height,
            'video_size_bytes' => $this->video_size_bytes,
            'like_count' => $this->like_count,
            'published_at' => $this->published_at?->toIso8601String(),
            'block_reason' => $this->block_reason,
            'profile' => new WatchBuyProfileResource($this->whenLoaded('profile')),
            'products' => $this->whenLoaded('products', fn () => $this->products->map(fn ($product) => [
                'product_id' => $product->product_id,
                'product_variant_id' => $product->product_variant_id,
                'name' => $product->product?->title ?? $product->variant?->title,
                'image_url' => $product->variant?->image ?: $product->product?->main_image,
                'store_id' => $product->store_id,
                'sort_order' => $product->sort_order,
                'is_primary' => $product->is_primary,
            ])),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
