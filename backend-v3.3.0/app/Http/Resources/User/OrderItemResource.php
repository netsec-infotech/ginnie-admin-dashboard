<?php

namespace App\Http\Resources\User;

use App\Enums\Order\DeliveryOtpModeEnum;
use App\Enums\SpatieMediaCollectionName;
use App\Services\OrderService;
use App\Support\Currency\CurrencySnapshotPayload;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class OrderItemResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $isReviewExist = OrderService::checkUserReviewExistByOrderItemId($this->id);
        if ($isReviewExist) {
            $userReview = OrderService::getUserReviewByOrderItemId($this->id);
        }
        $sellerId = $this->store?->seller?->id;
        $sellerName = $this->store?->seller?->user?->name;
        $currencyCode = $this->order?->currency_code;

        $attachments = [];
        try {
            $mediaItems = $this->getMedia(SpatieMediaCollectionName::ORDER_ITEM_ATTACHMENTS());
            foreach ($mediaItems as $media) {
                $attachments[] = $media->getUrl();
            }
        } catch (\Throwable $e) {
            // Silently ignore if media library not set up for this resource
        }

        return [
            'id' => $this->id,
            'order_id' => $this->order_id,
            'product_id' => $this->product_id,
            'product_variant_id' => $this->product_variant_id,
            'store_id' => $this->store_id,
            'seller_id' => $sellerId,
            'seller_name' => $sellerName,
            'title' => $this->title,
            'variant_title' => $this->variant_title,
            'gift_card_discount' => $this->gift_card_discount,
            'formatted_gift_card_discount' => CurrencySnapshotPayload::formatAmount($this->gift_card_discount, $currencyCode),
            'admin_commission_amount' => $this->admin_commission_amount,
            'seller_commission_amount' => $this->seller_commission_amount,
            'commission_settled' => $this->commission_settled,
            'discounted_price' => $this->discounted_price,
            'formatted_discounted_price' => CurrencySnapshotPayload::formatAmount($this->discounted_price, $currencyCode),
            'promo_discount' => $this->promo_discount,
            'formatted_promo_discount' => CurrencySnapshotPayload::formatAmount($this->promo_discount, $currencyCode),
            'discount' => $this->discount,
            'tax_amount' => $this->tax_amount,
            'formatted_tax_amount' => CurrencySnapshotPayload::formatAmount($this->tax_amount, $currencyCode),
            'tax_percent' => $this->tax_percent,
            'sku' => $this->sku,
            'return_eligible' => $this->return_eligible,
            'return_deadline' => $this->return_deadline ?? '',
            'quantity' => $this->quantity,
            'price' => $this->price,
            'formatted_price' => CurrencySnapshotPayload::formatAmount($this->price, $currencyCode),
            'subtotal' => $this->subtotal,
            'formatted_subtotal' => CurrencySnapshotPayload::formatAmount($this->subtotal, $currencyCode),
            'currency_code' => $currencyCode,
            'status' => $this->status,
            // Phase 2 — surface localised label + the new lifecycle reasons.
            'status_label' => formatOrderStatusLabel($this->status),
            'cancellation_reason' => $this->cancellation_reason,
            'delivery_fail_reason' => $this->delivery_fail_reason,
            'requires_otp' => $this->requires_otp ?? $this->product?->requires_otp ?? false,
            'otp' => $this->order?->delivery_otp_mode === DeliveryOtpModeEnum::ORDER_WISE() ? null : ($this->otp ?? null),
            'otp_verified' => $this->otp_verified ?? false,
            'is_user_review_given' => $isReviewExist,
            'user_review' => $userReview ?? null,
            'product' => $this->when($this->product, [
                'id' => $this->product->id ?? 'N/A',
                'name' => $this->title ?? 'N/A',
                'slug' => $this->product->slug ?? 'N/A',
                'is_returnable' => (bool) $this->product?->is_returnable ?? false,
                'returnable_days' => (int) $this->product?->returnable_days ?? 0,
                'is_cancelable' => (bool) $this->is_cancelable,
                'cancelable_till' => $this->cancelable_till,
                'image' => $this->product->main_image ?? null,
                'requires_otp' => $this->requires_otp ?? $this->product->requires_otp ?? false,
            ]),
            'variant' => $this->when($this->variant, [
                'id' => $this->variant->id ?? 'N/A',
                'title' => $this->variant->title ?? 'N/A',
                'slug' => $this->variant->slug ?? 'N/A',
                'image' => $this->variant->image ?? null,
            ]),
            'store' => $this->when($this->store, function () {
                $isPickup = $this->order && $this->order->sellerOrders->first()?->delivery_type?->value === 'pickup';
                $storeData = [
                    'id' => $this->store->id ?? 'N/A',
                    'name' => $this->store->name ?? 'N/A',
                    'slug' => $this->store->slug ?? 'N/A',
                    'store_logo' => url($this->store->store_logo) ?? null,
                ];
                if ($isPickup) {
                    $storeData['address'] = $this->store->address ?? null;
                    $storeData['city'] = $this->store->city ?? null;
                    $storeData['state'] = $this->store->state ?? null;
                    $storeData['zipcode'] = $this->store->zipcode ?? null;
                    $storeData['country'] = $this->store->country ?? null;
                    $storeData['latitude'] = $this->store->latitude !== null ? (float) $this->store->latitude : null;
                    $storeData['longitude'] = $this->store->longitude !== null ? (float) $this->store->longitude : null;
                    $storeData['contact_number'] = $this->store->contact_number ?? null;
                    $storeData['pickup_instructions'] = $this->store->pickup_instructions ?? null;
                }

                return $storeData;
            }),
            'returns' => OrderItemReturnResource::collection($this->whenLoaded('returns')),
            'attachments' => $attachments,
            'addons' => $this->formatAddons(),
            'addons_total' => $this->resolveAddonsTotal(),
            'formatted_addons_total' => CurrencySnapshotPayload::formatAmount($this->resolveAddonsTotal(), $currencyCode),
            'created_at' => $this->created_at?->format('Y-m-d H:i:s'),
            'updated_at' => $this->updated_at?->format('Y-m-d H:i:s'),
        ];
    }

    /**
     * Flatten `order_item_addons` rows into a scalar-friendly list for the
     * API. Uses the eager-loaded `addons` relation when available and falls
     * back to a lazy load (rare — `OrderService::finalizeOrderCreation()`
     * eager-loads `items.addons`).
     *
     * @return array<int, array<string, mixed>>
     */
    protected function formatAddons(): array
    {
        $addons = $this->resource->relationLoaded('addons')
            ? $this->resource->getRelation('addons')
            : $this->resource->addons()->with(['addonGroup', 'addonItem'])->get();

        if (! $addons || $addons->isEmpty()) {
            return [];
        }

        $currencyCode = $this->order?->currency_code;

        return $addons->map(function ($addon) use ($currencyCode) {
            $group = $addon->addonGroup;
            $item = $addon->addonItem;

            return [
                'id' => $addon->id,
                'uuid' => $addon->uuid,
                'addon_group_id' => $addon->addon_group_id,
                'addon_item_id' => $addon->addon_item_id,
                'price' => (float) $addon->price,
                'formatted_price' => CurrencySnapshotPayload::formatAmount($addon->price, $currencyCode),
                'currency_code' => $currencyCode,
                'group' => $group ? [
                    'id' => $group->id,
                    'title' => $group->title,
                    'selection_type' => $group->selection_type?->value,
                    'is_required' => (bool) $group->is_required,
                ] : null,
                'item' => $item ? [
                    'id' => $item->id,
                    'title' => $item->title,
                    'indicator' => $item->indicator?->value,
                ] : null,
            ];
        })->values()->all();
    }

    /**
     * Line-total contribution of the selected addons for this order item:
     * `quantity × sum(addon.price)`. 0 when no addons are attached.
     */
    protected function resolveAddonsTotal(): float
    {
        $addons = $this->resource->relationLoaded('addons')
            ? $this->resource->getRelation('addons')
            : $this->resource->addons()->get();

        if (! $addons || $addons->isEmpty()) {
            return 0.0;
        }

        return round(((int) $this->quantity) * (float) $addons->sum(fn ($a) => (float) $a->price), 2);
    }
}
