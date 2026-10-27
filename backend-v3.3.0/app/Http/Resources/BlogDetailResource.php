<?php

namespace App\Http\Resources;

use App\Models\Address;
use App\Models\Product;
use App\Services\DeliveryZoneService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class BlogDetailResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $author = $this->authorProfile;
        $seo = is_array($this->metadata) ? $this->metadata : [];

        $latitude = $request->input('latitude');
        $longitude = $request->input('longitude');
        $addressId = $request->input('address_id');

        if ($addressId && $request->user('sanctum')) {
            $address = Address::query()
                ->whereKey($addressId)
                ->where('user_id', $request->user('sanctum')->id)
                ->first();
            if ($address) {
                $latitude = $address->latitude;
                $longitude = $address->longitude;
            }
        }

        $linkedProducts = $this->resolveLinkedProducts($latitude !== null ? (float) $latitude : null, $longitude !== null ? (float) $longitude : null);
        $categories = $this->categories;
        $primaryCategory = $categories->first();
        $relatedBlogs = $this->getRelatedBlogs(4);

        return [
            'id' => $this->id,
            'title' => $this->title,
            'slug' => $this->slug,
            'short_description' => $this->short_description,
            'content' => $this->content,
            'image' => $this->image,
            'is_editors_pick' => (bool) $this->is_editors_pick,
            'published_at' => $this->published_at?->toIso8601String(),
            'views_count' => (int) $this->views_count,
            'categories' => $categories->map(fn ($cat): array => [
                'id' => $cat->id,
                'name' => $cat->name,
                'slug' => $cat->slug,
                'image' => $cat->image,
            ])->values()->all(),
            'category' => $primaryCategory ? [
                'id' => $primaryCategory->id,
                'name' => $primaryCategory->name,
                'slug' => $primaryCategory->slug,
                'image' => $primaryCategory->image,
            ] : null,
            'tags' => (array) ($this->tags ?? []),
            'seo' => [
                'title' => $seo['seo_title'] ?? $this->title,
                'description' => $seo['seo_description'] ?? $this->short_description,
                'keywords' => $seo['seo_keywords'] ?? [],
            ],
            'author' => $author ? [
                'id' => $author->id,
                'username' => $author->username,
                'bio' => $author->bio,
                'photo_url' => $author->photo_url,
            ] : null,
            'linked_products' => $linkedProducts,
            'related_blogs' => BlogResource::collection($relatedBlogs),
        ];
    }

    /**
     * Resolve linked products with live pricing, stock, and location-based delivery status.
     */
    protected function resolveLinkedProducts(?float $latitude, ?float $longitude): array
    {
        $productIds = array_values(array_filter(array_map('intval', (array) ($this->product_ids ?? []))));

        if ($productIds === []) {
            return [];
        }

        $products = Product::query()
            ->with([
                'media',
                'category',
                'brand',
                'variants.media',
                'variants.storeProductVariants.store.deliveryZones',
            ])
            ->whereIn('id', $productIds)
            ->where('status', 'active')
            ->get();

        return $products->map(function (Product $product) use ($latitude, $longitude): array {
            $storeVariants = $product->variants
                ->flatMap(fn ($v) => $v->storeProductVariants ?? collect());

            $cheapestStoreVariant = $storeVariants->sortBy('price')->first();

            if ($cheapestStoreVariant) {
                $minPrice = (float) $cheapestStoreVariant->price;
                $specialPrice = ((float) $cheapestStoreVariant->special_price > 0 && (float) $cheapestStoreVariant->special_price < $minPrice)
                    ? (float) $cheapestStoreVariant->special_price
                    : null;
            } else {
                $minVariant = $product->variants->sortBy('price')->first();
                $minPrice = $minVariant ? (float) $minVariant->price : 0.0;
                $specialPrice = ($minVariant && (float) $minVariant->special_price > 0 && (float) $minVariant->special_price < $minPrice)
                    ? (float) $minVariant->special_price
                    : null;
            }

            $isDeliverable = null;
            if ($latitude !== null && $longitude !== null) {
                $stores = $product->variants
                    ->flatMap(fn ($v) => $v->storeProductVariants ?? collect())
                    ->pluck('store')
                    ->filter()
                    ->unique('id');

                $isDeliverable = $stores->contains(function ($store) use ($latitude, $longitude): bool {
                    return $store->deliveryZones->contains(
                        fn ($zone): bool => DeliveryZoneService::containsPoint($zone, $latitude, $longitude)
                    );
                });
            }

            return [
                'id' => $product->id,
                'title' => $product->title,
                'slug' => $product->slug,
                'short_description' => $product->short_description,
                'main_image' => $product->main_image,
                'category_name' => $product->category?->title,
                'brand_name' => $product->brand?->title,
                'price' => $minPrice,
                'special_price' => $specialPrice,
                'is_available' => $product->status === 'active',
                'is_deliverable' => $isDeliverable,
            ];
        })->values()->all();
    }
}
