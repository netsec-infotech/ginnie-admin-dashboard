<?php

namespace App\Http\Resources\WatchBuy\Customer;

use App\Models\Store;
use App\Services\DeliveryZoneService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CustomerWatchBuyProductAttachmentResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $variant = $this->variant;
        $product = $variant?->product;
        $storeProductVariant = $this->storeProductVariant;
        $store = $this->store ?? $storeProductVariant?->store;
        $distanceKm = $this->distanceFromCustomer($request, $store);

        return [
            'id' => $this->id,
            'sort_order' => (int) $this->sort_order,
            'is_primary' => (bool) $this->is_primary,
            'is_effective_primary' => (bool) ($this->is_effective_primary ?? $this->is_primary),
            'product' => [
                'id' => $product?->id,
                'slug' => $product?->slug,
                'title' => $product?->title,
                'image_url' => $product?->main_image,
            ],
            'variant' => [
                'id' => $variant?->id,
                'title' => $variant?->title,
                'sku' => $storeProductVariant?->sku ?? $variant?->sku,
                'price' => $storeProductVariant ? (float) $storeProductVariant->price : null,
                'special_price' => ($storeProductVariant && $storeProductVariant->special_price !== null) ? (float) $storeProductVariant->special_price : null,
                'stock' => $storeProductVariant ? (int) $storeProductVariant->stock : 0,
                'availability' => (bool) ($variant?->availability && ($storeProductVariant ? $storeProductVariant->stock > 0 : false)),
            ],
            'store' => [
                'id' => $store?->id,
                'slug' => $store?->slug,
                'name' => $store?->name,
                'distance_km' => $distanceKm !== null ? round($distanceKm, 2) : null,
                'distance_label' => $distanceKm !== null ? $this->formatDistance($distanceKm) : null,
            ],
        ];
    }

    private function distanceFromCustomer(Request $request, ?Store $store): ?float
    {
        $latitude = $request->input('latitude');
        $longitude = $request->input('longitude');

        if (! is_numeric($latitude)
            || ! is_numeric($longitude)
            || ! is_numeric($store?->latitude)
            || ! is_numeric($store?->longitude)) {
            return null;
        }

        return DeliveryZoneService::calculateDistance(
            (float) $latitude,
            (float) $longitude,
            (float) $store->latitude,
            (float) $store->longitude,
        );
    }

    private function formatDistance(float $distanceKm): string
    {
        if ($distanceKm < 1) {
            return ((int) round($distanceKm * 1000)).' m';
        }

        $roundedDistance = number_format(round($distanceKm, 1), 1, '.', '');

        return rtrim(rtrim($roundedDistance, '0'), '.').' km';
    }
}
