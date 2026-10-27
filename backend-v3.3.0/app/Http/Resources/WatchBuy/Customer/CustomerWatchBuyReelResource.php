<?php

namespace App\Http\Resources\WatchBuy\Customer;

use App\Enums\GuardNameEnum;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CustomerWatchBuyReelResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $customer = $request->user('sanctum');
        $userId = $customer?->access_panel === GuardNameEnum::WEB ? $customer->id : null;
        $likedByMe = $this->whenLoaded('likes', function () use ($userId) {
            return $userId ? $this->likes->contains('user_id', $userId) : false;
        }, function () use ($userId) {
            return $userId ? $this->likes()->where('user_id', $userId)->exists() : false;
        });

        $effectivePrimaryVariantId = null;
        if ($this->relationLoaded('products')) {
            $effectivePrimary = $this->products->firstWhere('is_effective_primary', true)
                ?? $this->products->firstWhere('is_primary', true)
                ?? $this->products->first();
            $effectivePrimaryVariantId = $effectivePrimary?->product_variant_id;
        }

        return [
            'id' => $this->id,
            'uuid' => $this->uuid,
            'slug' => $this->slug,
            'caption' => $this->caption,
            'source' => $this->source?->value ?? $this->source,
            'video_url' => $this->video_url,
            'cover_url' => $this->cover_url,
            'video_width' => $this->video_width,
            'video_height' => $this->video_height,
            'duration_ms' => $this->duration_ms,
            'like_count' => (int) $this->like_count,
            'liked_by_me' => (bool) $likedByMe,
            'effective_primary_variant_id' => $effectivePrimaryVariantId,
            'profile' => new WatchBuyProfileResource($this->whenLoaded('profile')),
            'products' => CustomerWatchBuyProductAttachmentResource::collection($this->whenLoaded('products')),
            'published_at' => $this->published_at?->toISOString(),
        ];
    }
}
