<?php

namespace App\Http\Resources\User;

use Illuminate\Http\Resources\Json\JsonResource;

class WishlistResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'slug' => $this->slug,
            'items_count' => $this->relationLoaded('items')
                ? $this->items->filter(fn($item) => $item->product !== null)->count()
                : ($this->items_count ?? 0),
            'items' => WishlistItemResource::collection($this->whenLoaded('items', function () {
                return $this->items->filter(fn($item) => $item->product !== null);
            })),
            'created_at' => $this->created_at?->format('Y-m-d H:i:s'),
            'updated_at' => $this->updated_at?->format('Y-m-d H:i:s'),
        ];
    }
}
