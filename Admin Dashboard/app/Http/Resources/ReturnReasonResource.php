<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ReturnReasonResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => (int) $this->id,
            'seller_id' => $this->seller_id ? (int) $this->seller_id : null,
            'is_global' => $this->seller_id === null,
            'title' => (string) $this->title,
            'proof_required' => (bool) $this->proof_required,
            'otp_required' => (bool) $this->otp_required,
            'status' => (string) ($this->status ?? 'active'),
            'products' => $this->whenLoaded('products', function () {
                return $this->products->map(function ($product) {
                    return [
                        'id' => (int) $product->id,
                        'title' => (string) $product->title,
                    ];
                });
            }),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
