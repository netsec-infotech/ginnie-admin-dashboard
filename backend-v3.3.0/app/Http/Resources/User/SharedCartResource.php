<?php

namespace App\Http\Resources\User;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SharedCartResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'share_code' => $this['share_code'],
            'share_url' => $this['share_url'],
            'title' => $this['title'],
            'expires_at' => $this['expires_at'],
            'is_expired' => (bool) $this['is_expired'],
            'items' => $this['items'],
            'summary' => $this['summary'],
        ];
    }
}
