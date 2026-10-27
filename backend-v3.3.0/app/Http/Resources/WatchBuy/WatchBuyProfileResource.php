<?php

namespace App\Http\Resources\WatchBuy;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class WatchBuyProfileResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'type' => $this->profile_type?->value ?? $this->profile_type,
            'username' => $this->username,
            'bio' => $this->bio,
            'photo_url' => $this->photo_url,
            'status' => $this->status?->value ?? $this->status,
            'suspension_reason' => $this->suspension_reason,
        ];
    }
}
