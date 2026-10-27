<?php

namespace App\Http\Resources\WatchBuy\Customer;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CustomerWatchBuyProfileStatusGroupResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'profile' => new WatchBuyProfileResource($this->resource['profile']),
            'status_count' => (int) ($this->resource['status_count'] ?? 0),
        ];
    }
}


