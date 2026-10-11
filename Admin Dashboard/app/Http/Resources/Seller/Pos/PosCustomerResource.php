<?php

namespace App\Http\Resources\Seller\Pos;

use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Lightweight customer record for the POS customer search/select widget.
 *
 * Mobile is masked (last 4 digits visible) so a cashier can confirm they
 * have the right person without exposing the full PII to anyone watching.
 *
 * N+1 note: `last_order_at` must be set by the caller as a transient attribute
 * (e.g. via `withMax('orders', 'created_at') as last_order_at`). The resource
 * never queries Order directly.
 */
class PosCustomerResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id'            => $this->id,
            'name'          => $this->name,
            'mobile_masked' => $this->maskMobile(),
            'country_code'  => $this->country_code,
            'email'         => $this->email,
            // Caller must eager-load or set via withMax/subquery to avoid N+1.
            'last_order_at' => $this->last_order_at ?? null,
        ];
    }

    private function maskMobile(): ?string
    {
        $m = (string) ($this->mobile ?? '');
        if ($m === '') {
            return null;
        }
        $len = strlen($m);
        if ($len <= 4) {
            return str_repeat('*', $len);
        }
        return str_repeat('*', $len - 4) . substr($m, -4);
    }
}
