<?php

namespace App\Services;

use App\Enums\SettingTypeEnum;
use App\Exceptions\SharedCartMutationException;
use App\Http\Resources\User\CartResource;
use App\Models\Address;
use App\Models\Cart;
use App\Models\SharedCart;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class SharedCartService
{
    public function __construct(
        private readonly CartService $cartService,
        private readonly SharedCartValidationService $validationService,
        private readonly SettingService $settingService,
    ) {}

    /** @return array<string, mixed> */
    public function create(User $user, ?string $title = null): array
    {
        $cart = Cart::query()
            ->where('user_id', $user->id)
            ->with([
                'items' => fn ($query) => $query->where(function ($activeItems): void {
                    $activeItems->where('save_for_later', '0')->orWhereNull('save_for_later');
                }),
                'items.addons',
            ])
            ->first();

        if (! $cart || $cart->items->isEmpty()) {
            return $this->result(false, __('labels.cannot_share_empty_cart'));
        }

        $snapshot = $cart->items->map(fn ($item): array => [
            'store_id' => (int) $item->store_id,
            'product_id' => (int) $item->product_id,
            'product_variant_id' => (int) $item->product_variant_id,
            'quantity' => (int) $item->quantity,
            'addons' => $item->addons
                ->map(fn ($addon): array => [
                    'addon_group_id' => (int) $addon->addon_group_id,
                    'addon_item_id' => (int) $addon->addon_item_id,
                ])
                ->sortBy([['addon_group_id', 'asc'], ['addon_item_id', 'asc']])
                ->values()
                ->all(),
        ])->values()->all();

        $token = SharedCart::generateToken();
        $sharedCart = SharedCart::query()->create([
            'token_hash' => SharedCart::hashToken($token),
            'user_id' => $user->id,
            'title' => $title,
            'cart_data' => $snapshot,
            'expires_at' => now()->addDays(7),
        ]);

        return $this->result(true, __('labels.cart_shared_successfully'), [
            'share_code' => $token,
            'share_url' => $this->shareUrl($token),
            'title' => $sharedCart->title,
            'items_count' => count($snapshot),
            'expires_at' => $sharedCart->expires_at->toIso8601String(),
        ]);
    }

    /** @return array<string, mixed> */
    public function preview(
        string $token,
        ?User $user = null,
        ?float $latitude = null,
        ?float $longitude = null,
        ?int $addressId = null,
    ): array {
        $sharedCart = SharedCart::findByToken($token);

        if (! $sharedCart) {
            return $this->result(false, __('labels.shared_cart_not_found'), status: 404);
        }

        if ($sharedCart->isExpired()) {
            return $this->result(false, __('labels.shared_cart_expired'), ['is_expired' => true], 410);
        }

        if ($addressId !== null && $user) {
            $address = Address::query()
                ->whereKey($addressId)
                ->where('user_id', $user->id)
                ->first();
            $latitude = $address?->latitude !== null ? (float) $address->latitude : null;
            $longitude = $address?->longitude !== null ? (float) $address->longitude : null;
        }

        $validatedItems = $this->validationService->validate(
            $sharedCart->cart_data,
            $user,
            'merge',
            $latitude,
            $longitude,
        );
        $items = [];
        $availableCount = 0;
        $estimatedTotal = 0.0;

        foreach ($validatedItems as $validatedItem) {
            $snapshot = $validatedItem['snapshot'];
            $storeVariant = $validatedItem['store_product_variant'];
            $store = $storeVariant?->store;
            $variant = $storeVariant?->productVariant;
            $product = $variant?->product;
            $price = (float) ($storeVariant?->price ?? 0);
            $specialPrice = (float) ($storeVariant?->special_price ?? 0);
            $effectivePrice = $specialPrice > 0 ? $specialPrice : $price;
            $addonsUnitPrice = (float) collect($validatedItem['resolved_addons'])->sum('price');
            $lineTotal = ($effectivePrice + $addonsUnitPrice) * $snapshot['quantity'];

            if ($validatedItem['success']) {
                $availableCount++;
                $estimatedTotal += $lineTotal;
            }

            $items[] = [
                'store_id' => $snapshot['store_id'],
                'store_name' => $store?->name,
                'is_store_online' => $store?->isOnline() ?? false,
                'store_is_accepting_orders' => $store?->isAcceptingOrders() ?? false,
                'is_deliverable' => $validatedItem['is_deliverable'],
                'product_id' => $product?->id ?? $snapshot['product_id'],
                'product_name' => $product?->title,
                'product_slug' => $product?->slug,
                'product_image' => $product?->main_image,
                'product_variant_id' => $snapshot['product_variant_id'],
                'variant_title' => $variant?->title,
                'shared_quantity' => $snapshot['quantity'],
                'unit_price' => $price,
                'special_price' => $specialPrice > 0 ? $specialPrice : null,
                'effective_unit_price' => $effectivePrice + $addonsUnitPrice,
                'total_price' => round($lineTotal, 2),
                'available_stock' => (int) ($storeVariant?->stock ?? 0),
                'addons' => $validatedItem['resolved_addons'],
                'is_available' => (bool) $validatedItem['success'],
                'unavailable_reason' => $validatedItem['message'],
                'already_in_user_cart' => (bool) $validatedItem['already_in_user_cart'],
            ];
        }

        return $this->result(true, __('labels.shared_cart_preview_retrieved_successfully'), [
            'share_code' => $token,
            'share_url' => $this->shareUrl($token),
            'title' => $sharedCart->title,
            'expires_at' => $sharedCart->expires_at->toIso8601String(),
            'is_expired' => false,
            'items' => $items,
            'summary' => [
                'total_items' => count($items),
                'available_items' => $availableCount,
                'unavailable_items' => count($items) - $availableCount,
                'estimated_items_total' => round($estimatedTotal, 2),
            ],
        ]);
    }

    /** @return array<string, mixed> */
    public function import(User $recipient, string $token, string $action): array
    {
        $sharedCart = SharedCart::findByToken($token);

        if (! $sharedCart) {
            return $this->result(false, __('labels.shared_cart_not_found'), status: 404);
        }

        if ($sharedCart->isExpired()) {
            return $this->result(false, __('labels.shared_cart_expired'), ['is_expired' => true], 410);
        }

        if ($action === 'replace') {
            $prevalidated = $this->validationService->validate($sharedCart->cart_data, $recipient, 'replace');
            if (collect($prevalidated)->where('success', true)->isEmpty()) {
                return $this->withCurrentCart(
                    $this->importResult($action, [], [], $this->skippedItems($prevalidated), false),
                    $recipient,
                );
            }
        }

        try {
            $result = DB::transaction(function () use ($action, $recipient, $sharedCart): array {
                User::query()->whereKey($recipient->id)->lockForUpdate()->firstOrFail();
                Cart::query()->where('user_id', $recipient->id)->lockForUpdate()->first();

                $lockedShare = SharedCart::query()->whereKey($sharedCart->id)->lockForUpdate()->first();
                if (! $lockedShare) {
                    return $this->result(false, __('labels.shared_cart_not_found'), status: 404);
                }

                if ($lockedShare->isExpired()) {
                    return $this->result(false, __('labels.shared_cart_expired'), ['is_expired' => true], 410);
                }

                $validatedItems = $this->validationService->validate(
                    $lockedShare->cart_data,
                    $recipient,
                    $action,
                );
                $validItems = collect($validatedItems)->where('success', true)->values();
                $skippedItems = $this->skippedItems($validatedItems);

                if ($validItems->isEmpty()) {
                    return $this->importResult($action, [], [], $skippedItems, false);
                }

                if ($action === 'replace') {
                    $clearResult = $this->cartService->clearCart($recipient);
                    if (! $clearResult['success']) {
                        throw new SharedCartMutationException($this->importResult(
                            $action,
                            [],
                            [],
                            $skippedItems,
                            false,
                            $clearResult['message'],
                        ));
                    }
                }

                $imported = [];
                $updated = [];
                $unchanged = [];

                foreach ($validItems as $validatedItem) {
                    $snapshot = $validatedItem['snapshot'];

                    if ($action === 'merge' && ! $validatedItem['requires_mutation']) {
                        $unchanged[] = $this->summaryItem($validatedItem);
                        continue;
                    }

                    $addResult = $this->cartService->addToCart($recipient, [
                        'store_id' => $snapshot['store_id'],
                        'product_variant_id' => $snapshot['product_variant_id'],
                        'quantity' => $validatedItem['target_quantity'],
                        'addons' => $snapshot['addons'],
                        'replace_quantity' => true,
                    ]);

                    if (! $addResult['success']) {
                        $failedItem = $this->skippedItem($validatedItem, $addResult['message']);

                        if ($action === 'replace') {
                            throw new SharedCartMutationException($this->importResult(
                                $action,
                                [],
                                [],
                                [...$skippedItems, $failedItem],
                                false,
                                $addResult['message'],
                            ));
                        }

                        $skippedItems[] = $failedItem;
                        continue;
                    }

                    if ($validatedItem['already_in_user_cart'] && $action === 'merge') {
                        $updated[] = $this->summaryItem($validatedItem);
                    } else {
                        $imported[] = $this->summaryItem($validatedItem);
                    }
                }

                return $this->importResult(
                    $action,
                    $imported,
                    $updated,
                    $skippedItems,
                    $imported !== [] || $updated !== [] || $unchanged !== [],
                    unchanged: $unchanged,
                );
            }, attempts: 3);
        } catch (SharedCartMutationException $exception) {
            $result = $exception->result;
        }

        return $this->withCurrentCart($result, $recipient);
    }

    private function shareUrl(string $token): ?string
    {
        $webSettings = $this->settingService->getSettingValues(SettingTypeEnum::WEB());
        $baseUrl = trim((string) ($webSettings['customerWebUrl'] ?? ''));

        if ($baseUrl === '') {
            $baseUrl = trim((string) config('app.frontendUrl', ''));
        }

        return $baseUrl !== '' ? rtrim($baseUrl, '/').'/cart/shared/'.$token : null;
    }

    /** @param array<int, array<string, mixed>> $validatedItems */
    private function skippedItems(array $validatedItems): array
    {
        return collect($validatedItems)
            ->where('success', false)
            ->map(fn (array $item): array => $this->skippedItem($item))
            ->values()
            ->all();
    }

    /** @param array<string, mixed> $validatedItem */
    private function skippedItem(array $validatedItem, ?string $message = null): array
    {
        return [
            ...$this->summaryItem($validatedItem),
            'reason' => $message ?? $validatedItem['message'],
            'details' => $validatedItem['details'] ?? [],
        ];
    }

    /** @param array<string, mixed> $validatedItem */
    private function summaryItem(array $validatedItem): array
    {
        $snapshot = $validatedItem['snapshot'];
        $product = $validatedItem['store_product_variant']?->productVariant?->product;

        return [
            'store_id' => $snapshot['store_id'],
            'product_id' => $product?->id ?? $snapshot['product_id'],
            'product_name' => $product?->title,
            'product_variant_id' => $snapshot['product_variant_id'],
            'quantity' => $validatedItem['target_quantity'],
            'addons' => $snapshot['addons'],
        ];
    }

    /** @return array<string, mixed> */
    private function importResult(
        string $action,
        array $imported,
        array $updated,
        array $skipped,
        bool $success,
        ?string $message = null,
        array $unchanged = [],
    ): array {
        return $this->result(
            $success,
            $message ?? ($success ? __('labels.shared_cart_imported_successfully') : __('labels.shared_cart_import_failed')),
            [
                'action' => $action,
                'cart' => null,
                'import_summary' => [
                    'imported_count' => count($imported),
                    'updated_count' => count($updated),
                    'unchanged_count' => count($unchanged),
                    'skipped_count' => count($skipped),
                    'imported_items' => $imported,
                    'updated_items' => $updated,
                    'unchanged_items' => $unchanged,
                    'skipped_items' => $skipped,
                ],
            ],
        );
    }

    /** @param array<string, mixed> $result */
    private function withCurrentCart(array $result, User $user): array
    {
        if (($result['status'] ?? 200) !== 200) {
            return $result;
        }

        $cart = $this->cartService->getUserCart($user);
        $result['data']['cart'] = $cart ? new CartResource($cart) : null;

        return $result;
    }

    /** @return array<string, mixed> */
    private function result(bool $success, string $message, array $data = [], int $status = 200): array
    {
        return compact('success', 'message', 'data', 'status');
    }
}
