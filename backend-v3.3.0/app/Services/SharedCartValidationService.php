<?php

namespace App\Services;

use App\Enums\Addon\AddonGroupSelectionTypeEnum;
use App\Enums\Addon\AddonGroupStatusEnum;
use App\Enums\Product\ProductStatusEnum;
use App\Enums\Product\ProductVarificationStatusEnum;
use App\Enums\SettingTypeEnum;
use App\Models\Cart;
use App\Models\CartItem;
use App\Models\StoreAddonItem;
use App\Models\StoreProductVariant;
use App\Models\StoreProductVariantAddon;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class SharedCartValidationService
{
    public function __construct(private readonly SettingService $settingService) {}

    /**
     * Validate a cart snapshot without modifying the recipient's cart.
     *
     * @param  array<int, array<string, mixed>>  $cartData
     * @return array<int, array<string, mixed>>
     */
    public function validate(
        array $cartData,
        ?User $user = null,
        string $action = 'merge',
        ?float $latitude = null,
        ?float $longitude = null,
    ): array {
        $items = $this->normalizeItems($cartData);

        if ($items->isEmpty()) {
            return [];
        }

        $pairConstraint = function (Builder $query) use ($items): void {
            foreach ($items->unique(fn (array $item): string => $this->pairKey($item)) as $item) {
                $query->orWhere(function (Builder $pair) use ($item): void {
                    $pair->where('store_id', $item['store_id'])
                        ->where('product_variant_id', $item['product_variant_id']);
                });
            }
        };

        $storeVariants = StoreProductVariant::query()
            ->with([
                'store.deliveryZones',
                'store.media',
                'productVariant.media',
                'productVariant.product.media',
                'productVariant.product.category',
                'productVariant.product.brand',
                'productVariant.product.taxClasses.taxRates',
            ])
            ->where($pairConstraint)
            ->get()
            ->keyBy(fn (StoreProductVariant $variant): string => $variant->store_id.':'.$variant->product_variant_id);

        $attachments = StoreProductVariantAddon::query()
            ->with(['addonGroup', 'addonItem'])
            ->where($pairConstraint)
            ->get()
            ->groupBy(fn (StoreProductVariantAddon $attachment): string => $attachment->store_id.':'.$attachment->product_variant_id);

        $storeIds = $items->pluck('store_id')->filter()->unique()->values();
        $addonItemIds = $items->pluck('addons')->flatten(1)->pluck('addon_item_id')->filter()->unique()->values();
        $inventories = StoreAddonItem::withTrashed()
            ->whereIn('store_id', $storeIds)
            ->whereIn('addon_item_id', $addonItemIds)
            ->get()
            ->keyBy(fn (StoreAddonItem $inventory): string => $inventory->store_id.':'.$inventory->addon_item_id);

        $cart = $user
            ? Cart::query()->with(['items.addons'])->where('user_id', $user->id)->first()
            : null;
        $virtualLines = collect();
        $activeStoreIds = collect();
        $activeLineCount = 0;

        foreach ($cart?->items ?? [] as $cartItem) {
            if ($cartItem->save_for_later || $action === 'replace') {
                continue;
            }

            $signature = $cartItem->addon_signature
                ?? CartItem::buildAddonSignature($cartItem->addons->pluck('addon_item_id')->all());
            $virtualLines->put($this->lineKey(
                (int) $cartItem->store_id,
                (int) $cartItem->product_variant_id,
                $signature,
            ), (int) $cartItem->quantity);
            $activeStoreIds->push((int) $cartItem->store_id);
            $activeLineCount++;
        }

        $settings = $this->settingService->getSettingValues(SettingTypeEnum::SYSTEM());
        $singleStoreCheckout = ($settings['checkoutType'] ?? 'multi_store') === 'single_store';
        $maximumLines = max(1, (int) ($settings['maximumItemsAllowedInCart'] ?? 100));
        $results = [];

        foreach ($items as $item) {
            $pairKey = $this->pairKey($item);
            $storeVariant = $storeVariants->get($pairKey);
            $variant = $storeVariant?->productVariant;
            $product = $variant?->product;
            $store = $storeVariant?->store;
            $failure = null;
            $resolvedAddons = [];

            if (! $storeVariant || ! $variant || ! $product || ! $store) {
                $failure = $this->failure(__('messages.product_variant_not_available_in_store'));
            } elseif ((int) $product->id !== $item['product_id']) {
                $failure = $this->failure(__('messages.product_variant_not_available_in_store'));
            } elseif (! $variant->availability) {
                $failure = $this->failure(__('labels.shared_cart_variant_unavailable'));
            } elseif (
                $product->status !== ProductStatusEnum::ACTIVE()
                || $product->verification_status !== ProductVarificationStatusEnum::APPROVED()
            ) {
                $failure = $this->failure(__('labels.shared_cart_product_unavailable'));
            } elseif (! $store->isAcceptingOrders()) {
                $failure = $this->failure(__('labels.shared_cart_store_not_accepting_orders'));
            }

            if ($failure === null) {
                $addonResult = $this->resolveAddons(
                    $attachments->get($pairKey, collect()),
                    $inventories,
                    $item['store_id'],
                    $item['addons'],
                );

                if (! $addonResult['success']) {
                    $failure = $addonResult['failure'];
                } else {
                    $resolvedAddons = $addonResult['addons'];
                }
            }

            $signature = CartItem::buildAddonSignature(array_column($resolvedAddons, 'addon_item_id'));
            $lineKey = $this->lineKey($item['store_id'], $item['product_variant_id'], $signature);
            $existingQuantity = (int) $virtualLines->get($lineKey, 0);
            $alreadyInCart = $existingQuantity > 0;
            $targetQuantity = $action === 'merge'
                ? max($existingQuantity, $item['quantity'])
                : $item['quantity'];
            $requiresMutation = ! $alreadyInCart || $targetQuantity > $existingQuantity || $action === 'replace';
            $isNewLine = ! $alreadyInCart;

            if (
                $failure === null
                && $singleStoreCheckout
                && $activeStoreIds->isNotEmpty()
                && ! $activeStoreIds->contains($item['store_id'])
            ) {
                $failure = $this->failure(__('labels.checkout_type_single_store_error'));
            }

            if ($failure === null && $isNewLine && $activeLineCount >= $maximumLines) {
                $failure = $this->failure(__('messages.maximum_items_allowed_in_cart_reached'));
            }

            if ($failure === null && $requiresMutation) {
                $failure = $this->validateQuantity($targetQuantity, $storeVariant);
            }

            if ($failure === null && $requiresMutation && $storeVariant->stock < $targetQuantity) {
                $failure = $this->failure(__('messages.insufficient_stock_available'), [
                    'available_stock' => (int) $storeVariant->stock,
                ]);
            }

            if ($failure === null && $requiresMutation) {
                foreach ($resolvedAddons as $addon) {
                    if ($addon['stock'] !== null && $addon['stock'] < $targetQuantity) {
                        $failure = $this->failure(__('messages.addon_item_insufficient_stock'), [
                            'addon_item_id' => $addon['addon_item_id'],
                            'available_stock' => $addon['stock'],
                            'requested' => $targetQuantity,
                        ]);
                        break;
                    }
                }
            }

            $isDeliverable = null;
            if ($store && $latitude !== null && $longitude !== null) {
                $isDeliverable = $store->deliveryZones->contains(
                    fn ($zone): bool => DeliveryZoneService::containsPoint($zone, $latitude, $longitude)
                );
            }

            if ($failure === null) {
                $virtualLines->put($lineKey, $targetQuantity);
                if ($isNewLine) {
                    $activeLineCount++;
                }
                $activeStoreIds->push($item['store_id']);
                $activeStoreIds = $activeStoreIds->unique()->values();
            }

            $results[] = [
                'snapshot' => $item,
                'success' => $failure === null,
                'message' => $failure['message'] ?? null,
                'details' => $failure['details'] ?? [],
                'store_product_variant' => $storeVariant,
                'resolved_addons' => $resolvedAddons,
                'already_in_user_cart' => $alreadyInCart,
                'existing_quantity' => $existingQuantity,
                'target_quantity' => $targetQuantity,
                'requires_mutation' => $requiresMutation,
                'is_deliverable' => $isDeliverable,
            ];
        }

        return $results;
    }

    /** @param array<int, array<string, mixed>> $cartData */
    private function normalizeItems(array $cartData): Collection
    {
        return collect($cartData)->map(function (array $item): array {
            $addons = collect(is_array($item['addons'] ?? null) ? $item['addons'] : [])
                ->map(fn (array $addon): array => [
                    'addon_group_id' => (int) ($addon['addon_group_id'] ?? 0),
                    'addon_item_id' => (int) ($addon['addon_item_id'] ?? 0),
                ])
                ->filter(fn (array $addon): bool => $addon['addon_group_id'] > 0 && $addon['addon_item_id'] > 0)
                ->unique(fn (array $addon): string => $addon['addon_group_id'].':'.$addon['addon_item_id'])
                ->sortBy([['addon_group_id', 'asc'], ['addon_item_id', 'asc']])
                ->values()
                ->all();

            return [
                'store_id' => (int) ($item['store_id'] ?? 0),
                'product_id' => (int) ($item['product_id'] ?? 0),
                'product_variant_id' => (int) ($item['product_variant_id'] ?? 0),
                'quantity' => max(1, (int) ($item['quantity'] ?? 1)),
                'addons' => $addons,
            ];
        })->values();
    }

    /**
     * @param Collection<int, StoreProductVariantAddon> $attachments
     * @param Collection<string, StoreAddonItem> $inventories
     * @param array<int, array{addon_group_id:int, addon_item_id:int}> $selectedAddons
     * @return array<string, mixed>
     */
    private function resolveAddons(
        Collection $attachments,
        Collection $inventories,
        int $storeId,
        array $selectedAddons,
    ): array {
        $attachmentMap = $attachments->keyBy(
            fn (StoreProductVariantAddon $attachment): string => $attachment->addon_group_id.':'.$attachment->addon_item_id
        );
        $groups = $attachments->pluck('addonGroup')->filter()->unique('id')->keyBy('id');
        $selected = collect($selectedAddons);
        $selectedByGroup = $selected->groupBy('addon_group_id');

        foreach ($groups as $groupId => $group) {
            if ($group->status !== AddonGroupStatusEnum::ACTIVE) {
                continue;
            }

            if ($group->is_required && ! $selectedByGroup->has((int) $groupId)) {
                return ['success' => false, 'failure' => $this->failure(
                    __('messages.addon_group_required_missing', ['group' => $group->title]),
                    ['addon_group_id' => (int) $groupId],
                )];
            }
        }

        foreach ($selectedByGroup as $groupId => $selectedItems) {
            $group = $groups->get((int) $groupId);
            if (
                $group
                && $group->selection_type === AddonGroupSelectionTypeEnum::SINGLE
                && $selectedItems->count() > 1
            ) {
                return ['success' => false, 'failure' => $this->failure(
                    __('messages.addon_group_single_selection_required', ['group' => $group->title]),
                    ['addon_group_id' => (int) $groupId],
                )];
            }
        }

        $resolved = [];
        foreach ($selected as $addon) {
            $attachment = $attachmentMap->get($addon['addon_group_id'].':'.$addon['addon_item_id']);
            $group = $attachment?->addonGroup;
            $catalogItem = $attachment?->addonItem;

            if (
                ! $attachment
                || ! $group
                || ! $catalogItem
                || $group->status !== AddonGroupStatusEnum::ACTIVE
                || $catalogItem->status !== AddonGroupStatusEnum::ACTIVE
                || ! $catalogItem->is_available
            ) {
                return ['success' => false, 'failure' => $this->failure(
                    __('messages.addon_not_available_for_variant'),
                    $addon,
                )];
            }

            $inventory = $inventories->get($storeId.':'.$addon['addon_item_id']);
            if ($inventory?->trashed() || ($inventory && ! $inventory->is_available)) {
                return ['success' => false, 'failure' => $this->failure(
                    __('messages.addon_item_unavailable'),
                    ['addon_item_id' => $addon['addon_item_id']],
                )];
            }

            $resolved[] = [
                'addon_group_id' => $addon['addon_group_id'],
                'addon_group_name' => $group->title,
                'addon_item_id' => $addon['addon_item_id'],
                'addon_item_name' => $catalogItem->title,
                'price' => (float) ($inventory?->price ?? $catalogItem->price ?? 0),
                'stock' => $inventory ? (int) $inventory->stock : null,
            ];
        }

        return ['success' => true, 'addons' => $resolved];
    }

    /** @return array{message:string, details:array<string, mixed>}|null */
    private function validateQuantity(int $quantity, StoreProductVariant $storeVariant): ?array
    {
        $product = $storeVariant->productVariant->product;
        $minimum = max(1, (int) ($product->minimum_order_quantity ?? 1));
        $step = max(1, (int) ($product->quantity_step_size ?? 1));
        $maximum = max(0, (int) ($product->total_allowed_quantity ?? 0));

        if ($quantity < $minimum) {
            return $this->failure(
                __('messages.quantity_must_be_at_least_minimum_order_quantity', ['min' => $minimum]),
                ['minimum_order_quantity' => $minimum, 'attempted_quantity' => $quantity],
            );
        }

        if ($quantity % $step !== 0) {
            return $this->failure(
                __('messages.quantity_must_be_multiple_of_step_size', ['step' => $step]),
                ['step_size' => $step, 'attempted_quantity' => $quantity],
            );
        }

        if ($maximum > 0 && $quantity > $maximum) {
            return $this->failure(
                __('messages.quantity_must_not_exceed_total_allowed_quantity', ['max' => $maximum]),
                ['total_allowed_quantity' => $maximum, 'attempted_quantity' => $quantity],
            );
        }

        return null;
    }

    /** @return array{message:string, details:array<string, mixed>} */
    private function failure(string $message, array $details = []): array
    {
        return ['message' => $message, 'details' => $details];
    }

    /** @param array<string, mixed> $item */
    private function pairKey(array $item): string
    {
        return $item['store_id'].':'.$item['product_variant_id'];
    }

    private function lineKey(int $storeId, int $variantId, ?string $signature): string
    {
        return $storeId.':'.$variantId.':'.($signature ?? 'none');
    }
}
