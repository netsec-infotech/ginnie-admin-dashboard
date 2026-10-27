<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class LoyaltyPointTransactionResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $typeValue = (string) (is_callable($this->type) ? ($this->type)() : ($this->type->value ?? $this->type));
        $statusValue = (string) (is_callable($this->status) ? ($this->status)() : ($this->status->value ?? $this->status));

        return [
            'id' => $this->id,
            'order_id' => $this->order_id,
            'points' => (int) $this->points,
            'used_points' => (int) ($this->used_points ?? 0),
            'type' => $typeValue,
            'status' => $statusValue,
            'description' => $this->description,
            'expires_at' => $this->expires_at?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
