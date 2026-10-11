<?php

namespace App\Http\Resources\Seller\Pos;

use App\Enums\SpatieMediaCollectionName;
use App\Models\StoreProductVariant;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Collection;

/**
 * Product-centric POS resource.
 *
 * The data layer is variant-centric (StoreProductVariant rows), but the POS
 * UI is product-centric: cashier picks a product card, then picks a variant
 * if there are several, then picks add-ons if the chosen variant offers any.
 *
 * `$this->resource` is expected to be a Product Eloquent model with the
 * variants relation eager-loaded; pre-grouped pricing/addon data is passed
 * via transient attributes on the model instance (see PosProductGrouper).
 *
 * @property-read \App\Models\Product $resource
 */
class PosProductResource extends JsonResource
{
    public function toArray($request): array
    {
        $product = $this->resource;
        /** @var Collection<int,StoreProductVariant> $storeVariants */
        $storeVariants = $product->pos_store_variants ?? collect();
        /** @var Collection<int,Collection> $addonsByVariant */
        $addonsByVariant = $product->pos_addons_by_variant ?? collect();

        // Effective tax % = sum across all rates from all attached tax classes.
        $taxPercent = (float) ($product->taxClasses?->flatMap->taxRates?->sum('rate') ?? 0);
        $isInclusive = (string) ($product->is_inclusive_tax ?? '1') === '1';

        $variants = $storeVariants->map(function (StoreProductVariant $sv) use ($addonsByVariant, $taxPercent, $isInclusive) {
            $priceStored = (float) $sv->price;
            $specialPriceStored = (float) $sv->special_price;
            $effectiveStored = $specialPriceStored > 0 && $specialPriceStored < $priceStored ? $specialPriceStored : $priceStored;

            // Normalise to display (tax-inclusive) prices regardless of
            // is_inclusive_tax — prices shown on POS/receipts are always inclusive.
            if ($isInclusive) {
                $priceDisplay = $priceStored;
                $effectiveDisplay = $effectiveStored;
            } else {
                $factor = $taxPercent > 0 ? (1 + $taxPercent / 100) : 1.0;
                $priceDisplay = $priceStored * $factor;
                $effectiveDisplay = $effectiveStored * $factor;
            }

            $variantId = (int) $sv->product_variant_id;
            $addonGroups = ($addonsByVariant->get($variantId) ?? collect())
                ->map(fn ($g) => $this->shapeGroup($g, $taxPercent, $isInclusive))
                ->values()
                ->all();

            $variantImage = $sv->productVariant?->image ?: null;

            return [
                'store_product_variant_id' => $sv->id,
                'product_variant_id' => $variantId,
                'title' => $sv->productVariant?->title ?? 'Default',
                'sku' => $sv->sku,
                // Display prices (always tax-inclusive). Backend re-derives at order-create time.
                'price' => $priceDisplay,
                'special_price' => $specialPriceStored > 0 && $specialPriceStored < $priceStored
                    ? ($isInclusive ? $specialPriceStored : $specialPriceStored * (1 + $taxPercent / 100))
                    : 0.0,
                'effective_price' => $effectiveDisplay,
                'stock' => (int) $sv->stock,
                'is_default_variant' => (bool) ($sv->productVariant?->is_default ?? false),
                'image' => $variantImage ?: null,
                'addon_groups' => $addonGroups,
            ];
        })->values()->all();

        $defaultVariant = collect($variants)->firstWhere('is_default_variant', true) ?? ($variants[0] ?? null);

        return [
            'product_id' => $product->id,
            'title' => $product->title,
            'short_description' => $product->short_description,
            'image' => $product->getFirstMediaUrl(SpatieMediaCollectionName::PRODUCT_MAIN_IMAGE()) ?: null,
            'type' => $product->type,
            'indicator' => $product->indicator ?? null,
            'minimum_order_quantity' => (int) ($product->minimum_order_quantity ?? 1),
            'quantity_step_size' => (int) ($product->quantity_step_size ?? 1),
            'total_allowed_quantity' => (int) ($product->total_allowed_quantity ?? 0),
            'has_variants' => count($variants) > 1,
            'has_addons' => collect($variants)->contains(fn ($v) => count($v['addon_groups']) > 0),
            'tax_percent' => $taxPercent,
            'is_inclusive_tax' => $isInclusive,
            'default_variant' => $defaultVariant,
            'variants' => $variants,
        ];
    }

    /**
     * @param  array  $g  expected keys: group, items[]
     */
    private function shapeGroup(array $g, float $taxPercent, bool $isInclusive): array
    {
        $group = $g['group'];

        return [
            'addon_group_id' => $group->id,
            'title' => $group->title,
            'selection_type' => is_object($group->selection_type) ? $group->selection_type->value : $group->selection_type,
            'is_required' => (bool) $group->is_required,
            'sort_order' => (int) ($group->sort_order ?? 0),
            'items' => collect($g['items'])->map(function ($row) use ($taxPercent, $isInclusive) {
                $item = $row['item'];
                $store = $row['store_addon_item'];
                $available = $store && $store->is_available && (int) $store->stock > 0;
                $stored = (float) ($store->price ?? $item->price);
                // Addons inherit the parent product's tax shape — render tax-inclusive price.
                $display = $isInclusive
                    ? $stored
                    : ($taxPercent > 0 ? $stored * (1 + $taxPercent / 100) : $stored);

                return [
                    'addon_item_id' => $item->id,
                    'title' => $item->title,
                    'price' => $display,
                    'stock' => (int) ($store->stock ?? 0),
                    'is_available' => (bool) $available,
                    'indicator' => $item->indicator ?? null,
                ];
            })->values()->all(),
        ];
    }
}
