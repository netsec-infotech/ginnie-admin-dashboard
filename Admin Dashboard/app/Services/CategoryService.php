<?php

namespace App\Services;

use App\Enums\CategoryStatusEnum;
use App\Enums\Product\ProductStatusEnum;
use App\Enums\Product\ProductVarificationStatusEnum;
use App\Models\Category;
use App\Models\Product;
use App\Traits\HasZoneAvailability;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class CategoryService
{
    use HasZoneAvailability;
    public static function getCategoriesWithParent()
    {
        return Category::select('id', 'parent_id', 'title', 'requires_approval')->where('status', CategoryStatusEnum::ACTIVE())->get()->map(function ($category) {
            return [
                'id' => (string) $category->id,
                'parent' => $category->parent_id ? (string) $category->parent_id : '#',
                'text' => $category->title . ($category->requires_approval ? ' <small class="text-azure">(Requires Admin Approval)</small>' : ''),
            ];
        });
    }

    /**
     * Build base category query constrained by active status and availability in given stores.
     *
     * A category is considered available if it has at least one approved and active product
     * that has variants available in any of the specified stores. This also includes products
     * from child categories.
     */
    protected function baseAvailabilityQuery(array $storeIds): Builder
    {
        return Category::query()
            ->where('status', CategoryStatusEnum::ACTIVE())
            ->distinct()
            ->whereHas('products', function ($productQuery) use ($storeIds) {
                $productQuery->where('verification_status', ProductVarificationStatusEnum::APPROVED())
                    ->where('status', ProductStatusEnum::ACTIVE())
                    ->whereHas('variants.storeProductVariants', function ($variantQuery) use ($storeIds) {
                        $variantQuery->whereIn('store_id', $storeIds);
                    });
            });
    }

    /**
     * Canonical zone-aware category query.
     *
     * Returns active categories with a `products_count` attribute reflecting how many
     * active+approved products with variants in the given stores they have. Caller
     * adds parent_id / is_home_category / ordering / pagination.
     *
     * Mirrors CategoryApiController::applyProductsCount so the customer + admin
     * preview share one source of truth.
     */
    public function availableInStoresQuery(array $storeIds): Builder
    {
        return Category::query()
            ->where('status', CategoryStatusEnum::ACTIVE())
            ->withCount([
                'products as products_count' => function (Builder $q) use ($storeIds) {
                    if (empty($storeIds)) {
                        $q->whereRaw('1 = 0');
                        return;
                    }
                    $q->where('verification_status', ProductVarificationStatusEnum::APPROVED())
                        ->where('status', ProductStatusEnum::ACTIVE())
                        ->whereHas(
                            'variants.storeProductVariants',
                            fn(Builder $sq) => $sq->whereIn('store_id', $storeIds),
                        );
                },
            ]);
    }

    /**
     * For each category in the collection, add the immediate children's product counts.
     *
     * Mirrors CategoryApiController::aggregateImmediateChildrenProducts so a parent
     * category that has zero direct products but stocked subcategories still surfaces.
     */
    public function aggregateImmediateChildrenProducts(Collection $categories, array $storeIds): Collection
    {
        return $categories->map(function (Category $cat) use ($storeIds) {
            $childIds = Category::query()
                ->where('parent_id', $cat->id)
                ->where('status', CategoryStatusEnum::ACTIVE())
                ->pluck('id');
            if ($childIds->isEmpty()) {
                return $cat;
            }

            $additional = empty($storeIds)
                ? 0
                : Product::query()
                    ->whereIn('category_id', $childIds)
                    ->where('verification_status', ProductVarificationStatusEnum::APPROVED())
                    ->where('status', ProductStatusEnum::ACTIVE())
                    ->whereHas('variants.storeProductVariants', fn(Builder $sq) => $sq->whereIn('store_id', $storeIds))
                    ->count();

            $cat->products_count = ($cat->products_count ?? 0) + $additional;
            return $cat;
        });
    }
}
