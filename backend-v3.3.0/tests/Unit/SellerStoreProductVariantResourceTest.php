<?php

use App\Http\Resources\Product\SellerStoreProductVariantResource;
use App\Models\StoreProductVariant;

it('returns editable raw prices separately from display prices', function () {
    $storeVariant = new class extends stdClass
    {
        public int $id = 1;
        public int $store_id = 10;
        public string $sku = 'SELLER-EDIT-001';
        public float $price_exclude_tax = 100.0;
        public float $special_price_exclude_tax = 80.0;
        public float $price = 110.0;
        public float $special_price = 88.0;
        public float $cost = 50.0;
        public int $stock = 5;

        public function relationLoaded(string $relation): bool
        {
            return false;
        }

        public function whenLoaded(string $relation, callable $callback): mixed
        {
            return null;
        }
    };

    $data = (new SellerStoreProductVariantResource($storeVariant))->toArray(request());

    expect($data['price'])->toBe(100.0)
        ->and($data['special_price'])->toBe(80.0)
        ->and($data['display_price'])->toBe(110.0)
        ->and($data['display_special_price'])->toBe(88.0);
});

it('preserves decimal raw editable prices on store product variants', function () {
    $storeVariant = new StoreProductVariant([
        'price' => 100.99,
        'special_price' => 80.75,
    ]);

    expect($storeVariant->price_exclude_tax)->toBe(100.99)
        ->and($storeVariant->special_price_exclude_tax)->toBe(80.75);
});
