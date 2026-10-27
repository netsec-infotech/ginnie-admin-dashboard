<?php

namespace App\Http\Resources\WatchBuy\Customer;

use App\Enums\GuardNameEnum;
use App\Models\WatchBuyStatus;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CustomerWatchBuyStatusResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $customer = $request->user('sanctum');
        $userId = $customer?->access_panel === GuardNameEnum::WEB ? $customer->id : null;
        $seenByMe = $this->whenLoaded('views', function () use ($userId) {
            return $userId ? $this->views->contains('user_id', $userId) : false;
        }, function () use ($userId) {
            return $userId ? $this->views()->where('user_id', $userId)->exists() : false;
        });

        return [
            'id' => $this->id,
            'uuid' => $this->uuid,
            'source' => $this->source?->value ?? $this->source,
            'content_type' => $this->content_type?->value ?? $this->content_type,
            'text' => $this->text,
            'media_url' => $this->media_url,
            'media_width' => $this->media_width,
            'media_height' => $this->media_height,
            'duration_ms' => $this->duration_ms,
            'seen_by_me' => (bool) $seenByMe,
            'products' => CustomerWatchBuyProductAttachmentResource::collection($this->whenLoaded('products')),
            'created_at' => $this->created_at?->toISOString(),
            'time_ago' => $this->created_at ? (int) $this->created_at->diffInSeconds(now()) : 0,
            'expires_at' => WatchBuyStatus::doesNotExpireInDemoMode() ? null : $this->expires_at?->toISOString(),
        ];
    }
}
