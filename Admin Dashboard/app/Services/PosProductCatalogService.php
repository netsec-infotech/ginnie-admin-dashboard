<?php

namespace App\Services;

use App\Enums\Addon\AddonGroupStatusEnum;
use App\Enums\CategoryStatusEnum;
use App\Enums\Product\ProductStatusEnum;
use App\Enums\Product\ProductVarificationStatusEnum;
use App\Models\AddonGroup;
use App\Models\AddonItem;
use App\Models\Category;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Store;
use App\Models\StoreAddonItem;
use App\Models\StoreProductVariant;
use App\Models\StoreProductVariantAddon;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

/**
 * Builds the product-centric POS catalog payload.
 *
 * The seller-panel + Sanctum API both share this so the cart UI's data shape
 * is identical regardless of authentication transport.
 *
 * Hot-path queries:
 *  - Variants are pulled in one whereIn call grouped by product
 *  - Addon attachments are pulled with a single whereIn over the full variant
 *    set, then bucketed by variant in PHP (cheaper than N+1 round trips)
 */
class PosProductCatalogService
{
    /**
     * Search products in a single store, returning a paginator of Product
     * models with the variant + addon data attached as transient properties
     * for the resource to render.
     */
    public function searchInStore(Store $store, ?string $q, int $perPage, bool $includeOutOfStock, ?int $categoryId = null): LengthAwarePaginator
    {
        $productQuery = Product::query()
            ->where('status', ProductStatusEnum::ACTIVE())
            ->where('verification_status', ProductVarificationStatusEnum::APPROVED())
            ->whereHas('variants', function ($qq) use ($store, $includeOutOfStock) {
                $qq->where('availability', true)
                    // No dedicated ProductVisibilityStatusEnum exists; raw string retained.
                    ->where('visibility', 'published')
                    ->whereHas('storeProductVariants', function ($svq) use ($store, $includeOutOfStock) {
                        $svq->where('store_id', $store->id);
                        if (! $includeOutOfStock) {
                            $svq->where('stock', '>', 0);
                        }
                    });
            });

        if ($q !== null && $q !== '') {
            $productQuery->where('title', 'like', "%{$q}%");
        }

        if ($categoryId) {
            $ids = $this->categoryWithDescendantIds($categoryId);
            $productQuery->whereIn('category_id', $ids);
        }

        $productQuery->orderByDesc('featured')->orderByDesc('id');

        $paginator = $productQuery->paginate($perPage);

        $products = collect($paginator->items());
        $this->attachVariantData($store, $products, $includeOutOfStock);

        return $paginator;
    }

    private function attachVariantData(Store $store, Collection $products, bool $includeOutOfStock): void
    {
        if ($products->isEmpty()) {
            return;
        }

        $productIds = $products->pluck('id')->all();

        $svQuery = StoreProductVariant::query()
            ->where('store_id', $store->id)
            ->whereHas('productVariant', function ($qq) use ($productIds) {
                $qq->whereIn('product_id', $productIds)
                    ->where('availability', true)
                   // No dedicated ProductVisibilityStatusEnum exists; raw string retained.
                    ->where('visibility', 'published');
            })
            ->with(['productVariant']);
        if (! $includeOutOfStock) {
            $svQuery->where('stock', '>', 0);
        }

        /** @var Collection<int,StoreProductVariant> $svRows */
        $svRows = $svQuery->get();
        $svByProduct = $svRows->groupBy(fn ($sv) => $sv->productVariant?->product_id);
        $variantIds = $svRows->pluck('product_variant_id')->unique()->values()->all();

        /** @var Collection $addonLinks */
        $addonLinks = $variantIds
            ? StoreProductVariantAddon::where('store_id', $store->id)
                ->whereIn('product_variant_id', $variantIds)
                ->get()
            : collect();

        $groupIds = $addonLinks->pluck('addon_group_id')->unique()->values()->all();
        $itemIds = $addonLinks->pluck('addon_item_id')->unique()->values()->all();

        $groups = $groupIds
            ? AddonGroup::whereIn('id', $groupIds)->where('status', AddonGroupStatusEnum::ACTIVE())->get()->keyBy('id')
            : collect();
        // No AddonItemStatusEnum exists; addon items use is_available boolean.
        $items = $itemIds
            ? AddonItem::whereIn('id', $itemIds)->where('is_available', true)->get()->keyBy('id')
            : collect();
        $storeItems = $itemIds
            ? StoreAddonItem::where('store_id', $store->id)->whereIn('addon_item_id', $itemIds)->get()->keyBy('addon_item_id')
            : collect();

        $addonsByVariant = collect();
        foreach ($addonLinks as $link) {
            $group = $groups->get($link->addon_group_id);
            $item = $items->get($link->addon_item_id);
            if (! $group || ! $item) {
                continue;
            }

            $bucket = $addonsByVariant->get($link->product_variant_id) ?? collect();
            $existing = $bucket->get($group->id);
            if (! $existing) {
                $existing = ['group' => $group, 'items' => []];
            }
            $existing['items'][] = [
                'item' => $item,
                'store_addon_item' => $storeItems->get($item->id),
            ];
            $bucket->put($group->id, $existing);
            $addonsByVariant->put($link->product_variant_id, $bucket);
        }

        foreach ($products as $product) {
            $product->setAttribute('pos_store_variants', ($svByProduct->get($product->id) ?? collect())->values());

            $perVariant = collect();
            foreach (($svByProduct->get($product->id) ?? collect()) as $sv) {
                $variantId = $sv->product_variant_id;
                $groups = ($addonsByVariant->get($variantId) ?? collect())
                    ->sortBy(fn ($g) => (int) ($g['group']->sort_order ?? 0))
                    ->values();
                $perVariant->put($variantId, $groups);
            }
            $product->setAttribute('pos_addons_by_variant', $perVariant);
        }
    }

    /**
     * Top-level categories that have at least one published product in
     * this store. Powers the cashier's category-chip filter row.
     */
    public function topLevelCategoriesForStore(Store $store): Collection
    {
        $directIds = Product::query()
            ->where('status', ProductStatusEnum::ACTIVE())
            ->where('verification_status', ProductVarificationStatusEnum::APPROVED())
            ->whereHas('variants', function ($qq) use ($store) {
                $qq->where('availability', true)
                    ->where('visibility', 'published')
                    ->whereHas('storeProductVariants', fn ($svq) => $svq->where('store_id', $store->id));
            })
            ->pluck('category_id')
            ->filter()
            ->unique()
            ->values()
            ->all();

        if (empty($directIds)) {
            return collect();
        }

        $rootIds = [];
        $byId = Category::whereIn('id', $directIds)->get()->keyBy('id');
        foreach ($directIds as $id) {
            $node = $byId->get($id);
            $guard = 0;
            while ($node && $node->parent_id && $guard < 10) {
                $node = Category::find($node->parent_id);
                $guard++;
            }
            if ($node) {
                $rootIds[$node->id] = true;
            }
        }

        return Category::whereIn('id', array_keys($rootIds))
            ->where('status', CategoryStatusEnum::ACTIVE())
            ->orderBy('sort_order')
            ->orderBy('title')
            ->get(['id', 'title', 'sort_order']);
    }

    /**
     * Resolve a scanned barcode (or SKU) to a single product in this store.
     */
    public function findByBarcode(Store $store, string $code): ?Product
    {
        $code = trim($code);
        if ($code === '') {
            return null;
        }

        $variant = ProductVariant::where('barcode', $code)
            ->where('availability', true)
            ->where('visibility', 'published')
            ->whereHas('storeProductVariants', fn ($q) => $q->where('store_id', $store->id))
            ->first();
        if ($variant) {
            $product = Product::where('id', $variant->product_id)
                ->where('status', ProductStatusEnum::ACTIVE())
                ->where('verification_status', ProductVarificationStatusEnum::APPROVED())
                ->first();
            if ($product) {
                $this->attachVariantData($store, collect([$product]), false);

                return $product;
            }
        }

        $sv = StoreProductVariant::where('store_id', $store->id)
            ->where('sku', $code)
            ->whereHas('productVariant', fn ($q) => $q->where('availability', true)->where('visibility', 'published'))
            ->with('productVariant')
            ->first();
        if ($sv) {
            $product = Product::where('id', $sv->productVariant?->product_id)
                ->where('status', ProductStatusEnum::ACTIVE())
                ->where('verification_status', ProductVarificationStatusEnum::APPROVED())
                ->first();
            if ($product) {
                $this->attachVariantData($store, collect([$product]), false);

                return $product;
            }
        }

        return null;
    }

    private function categoryWithDescendantIds(int $rootId): array
    {
        $ids = [$rootId];
        $frontier = [$rootId];
        $guard = 0;
        while (! empty($frontier) && $guard < 10) {
            $children = Category::whereIn('parent_id', $frontier)->pluck('id')->all();
            if (empty($children)) {
                break;
            }
            $ids = array_merge($ids, $children);
            $frontier = $children;
            $guard++;
        }

        return array_values(array_unique($ids));
    }
}
