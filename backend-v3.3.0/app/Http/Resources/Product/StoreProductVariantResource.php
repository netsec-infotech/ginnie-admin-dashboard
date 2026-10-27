<?php

namespace App\Http\Resources\Product;

use App\Support\Currency\CurrencySnapshotPayload;
use Illuminate\Http\Resources\Json\JsonResource;

class StoreProductVariantResource extends JsonResource
{
    public function toArray($request): array
    {
        $currencyCode = $this->store?->currency_code;

        return [
            'id' => $this->id,
            'store_id' => $this->store_id,
            'sku' => $this->sku,
            'price' => $this->price,
            'special_price' => $this->special_price,
            'cost' => $this->cost,
            'formatted_price' => CurrencySnapshotPayload::formatAmount($this->price, $currencyCode),
            'formatted_special_price' => CurrencySnapshotPayload::formatAmount($this->special_price, $currencyCode),
            'formatted_cost' => CurrencySnapshotPayload::formatAmount($this->cost, $currencyCode),
            'currency_code' => $currencyCode,
            'stock' => $this->stock,
        ];
    }
}
