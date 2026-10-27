<?php

namespace App\Services;

use App\Enums\CategoryStatusEnum;
use App\Enums\Product\ProductStatusEnum;
use App\Enums\Product\ProductVarificationStatusEnum;
use App\Helpers\TranslationHelper;
use App\Models\Category;
use App\Models\CategorySearchLabel;
use App\Models\Product;
use App\Traits\HasZoneAvailability;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class CategoryService
{
    use HasZoneAvailability;

    /**
     * Persist a category from a normalized payload. Bulk import uses this so
     * category defaults stay in one place instead of living in the importer.
     */
    public function createCategory(array $attributes, array $searchLabels = []): Category
    {
        $attributes = $this->normaliseAttributes($attributes);
        if (array_key_exists('description', $attributes) && $attributes['description'] === null) {
            unset($attributes['description']);
        }

        $category = Category::create($attributes);
        $this->syncSearchLabels($category, $searchLabels);

        return $category->refresh();
    }

    /**
     * Persist only the intended category changes. Bulk update mode passes a
     * diff, so empty CSV cells can preserve the existing model values.
     */
    public function updateCategory(Category $category, array $attributes, ?array $searchLabels = null): Category
    {
        if (! empty($attributes)) {
            foreach (['title', 'description'] as $attribute) {
                if (! array_key_exists($attribute, $attributes)) {
                    continue;
                }

                $this->forgetBlankTranslations($category, $attribute, $attributes[$attribute]);
                $attributes[$attribute] = $this->mergeTranslatableAttribute(
                    $category->getTranslations($attribute) ?: [],
                    $attributes[$attribute],
                    $attribute === 'description',
                );
            }

            $attributes = $this->normaliseAttributes($attributes, $category);
            $clearDescription = array_key_exists('description', $attributes) && $attributes['description'] === null;
            if ($clearDescription) {
                unset($attributes['description']);
            }

            if ($attributes !== []) {
                $category->update($attributes);
            }

            if ($clearDescription) {
                $category->setRawAttributes(array_merge($category->getAttributes(), ['description' => null]));
                $category->save();
            }
        }

        if ($searchLabels !== null) {
            $this->syncSearchLabels($category, $searchLabels);
        }

        return $category->refresh();
    }

    /**
     * Apply category defaults and flatten CSV color fields into metadata.
     */
    protected function normaliseAttributes(array $attributes, ?Category $category = null): array
    {
        if (array_key_exists('title', $attributes)) {
            $attributes['title'] = TranslationHelper::normalize($attributes['title'], 'en');
        }
        if (array_key_exists('description', $attributes)) {
            $attributes['description'] = TranslationHelper::normalize($attributes['description'], 'en');
        }

        if (array_key_exists('status', $attributes) && empty($attributes['status'])) {
            $attributes['status'] = CategoryStatusEnum::INACTIVE();
        }
        if (array_key_exists('requires_approval', $attributes)) {
            $attributes['requires_approval'] = filter_var($attributes['requires_approval'], FILTER_VALIDATE_BOOLEAN);
        }
        if (array_key_exists('background_type', $attributes) && empty($attributes['background_type'])) {
            $attributes['background_type'] = null;
            $attributes['background_color'] = null;
        }
        $backgroundType = $attributes['background_type'] ?? $this->backgroundTypeValue($category);
        if ($backgroundType === 'color'
            || array_key_exists('background_color_web', $attributes)
            || array_key_exists('background_color_app', $attributes)) {
            $metadata = is_array($attributes['metadata'] ?? null) ? $attributes['metadata'] : ($category?->metadata ?? []);
            foreach (['background_color_web', 'background_color_app'] as $colorKey) {
                if (array_key_exists($colorKey, $attributes)) {
                    $metadata[$colorKey] = $attributes[$colorKey];
                    unset($attributes[$colorKey]);
                }
            }
            $attributes['metadata'] = $metadata;
            if ($backgroundType === 'color') {
                $attributes['background_color'] = $attributes['background_color']
                    ?? ($metadata['background_color_web'] ?? ($metadata['background_color_app'] ?? null));
            }
        }

        unset($attributes['background_color_web'], $attributes['background_color_app']);

        return $attributes;
    }

    protected function backgroundTypeValue(?Category $category): ?string
    {
        if (! $category) {
            return null;
        }

        return is_string($category->background_type)
            ? $category->background_type
            : ($category->background_type?->value ?? null);
    }

    /**
     * Keep per-category search labels synced with the web form behavior.
     */
    protected function syncSearchLabels(Category $category, array $searchLabels): void
    {
        $normalizedSearchLabels = collect($searchLabels)
            ->filter(fn ($label) => is_string($label))
            ->map(fn ($label) => trim($label))
            ->filter()
            ->unique()
            ->values()
            ->all();

        if (empty($normalizedSearchLabels)) {
            $category->searchLabelSetting()->delete();

            return;
        }

        CategorySearchLabel::updateOrCreate(
            ['category_id' => $category->id],
            ['search_labels' => $normalizedSearchLabels],
        );
    }

    public static function getCategoriesWithParent()
    {
        return Category::select('id', 'parent_id', 'title', 'requires_approval')->where('status', CategoryStatusEnum::ACTIVE())->get()->map(function ($category) {
            $title = $category->getTranslation('title', 'en', false);

            return [
                'id' => (string) $category->id,
                'parent' => $category->parent_id ? (string) $category->parent_id : '#',
                'text' => $title.($category->requires_approval ? ' <small class="text-azure">(Requires Admin Approval)</small>' : ''),
            ];
        });
    }

    protected function applySearch(Builder $query, string $search): void
    {
        $query->whereLocalizedTextLike($search);
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

    protected function mergeTranslatableAttribute(array $existing, mixed $incoming, bool $allowBlankEnglish): ?array
    {
        if (is_string($incoming)) {
            $incoming = ['en' => $incoming];
        }

        if ($incoming === null) {
            return $allowBlankEnglish ? null : $existing;
        }

        if (! is_array($incoming)) {
            return $existing;
        }

        foreach ($incoming as $locale => $value) {
            $locale = (string) $locale;
            $translation = trim((string) ($value ?? ''));
            if ($translation === '') {
                if ($locale !== 'en' || $allowBlankEnglish) {
                    unset($existing[$locale]);
                }

                continue;
            }

            $existing[$locale] = $translation;
        }

        return $existing === [] ? null : $existing;
    }

    protected function forgetBlankTranslations(Category $category, string $attribute, mixed $incoming): void
    {
        if (! is_array($incoming)) {
            return;
        }

        foreach ($incoming as $locale => $value) {
            $mayRemoveEnglish = $attribute === 'description';
            if (trim((string) ($value ?? '')) === '' && ((string) $locale !== 'en' || $mayRemoveEnglish)) {
                $category->forgetTranslation($attribute, (string) $locale);
            }
        }
    }
}
