<?php

namespace App\Services;

use App\Models\Badge;
use App\Models\Product;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class BadgeService
{
    /**
     * Create a new badge.
     */
    public function create(array $data): Badge
    {
        return Badge::create($data);
    }

    /**
     * Update an existing badge.
     */
    public function update(Badge $badge, array $data): Badge
    {
        $badge->update($data);

        return $badge->fresh();
    }

    /**
     * Delete a badge and unlink it from any products.
     */
    public function delete(Badge $badge): void
    {
        // nullOnDelete constraint handles unlinking in the DB, but explicit for clarity
        Product::where('badge_id', $badge->id)->update(['badge_id' => null]);
        $badge->delete();
    }

    /**
     * Assign a badge to a single product.
     */
    public function assignToProduct(Product $product, ?int $badgeId): void
    {
        $product->badge_id = $badgeId;
        $product->save();
    }

    /**
     * Assign a badge to multiple products in bulk.
     */
    public function bulkAssign(array $productIds, int $badgeId): int
    {
        if ($badgeId <= 0) {
            return $this->bulkRemove($productIds);
        }

        return Product::whereIn('id', $productIds)->update(['badge_id' => $badgeId]);
    }

    /**
     * Remove the badge from multiple products in bulk.
     */
    public function bulkRemove(array $productIds): int
    {
        return Product::whereIn('id', $productIds)->update(['badge_id' => null]);
    }

    /**
     * Return all badges as an array suitable for select dropdowns.
     */
    public function allForSelect(): array
    {
        return Badge::orderBy('name')->get(['id', 'name', 'label', 'bg_color', 'text_color', 'border_color'])->toArray();
    }
}
