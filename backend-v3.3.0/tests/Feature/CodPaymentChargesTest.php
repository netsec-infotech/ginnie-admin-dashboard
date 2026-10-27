<?php

use App\Enums\ActiveInactiveStatusEnum;
use App\Enums\Product\ProductStatusEnum;
use App\Enums\Product\ProductVarificationStatusEnum;
use App\Enums\Store\StoreVerificationStatusEnum;
use App\Enums\Store\StoreVisibilityStatusEnum;
use App\Http\Middleware\VerifyLicense;
use App\Models\Address;
use App\Models\Cart;
use App\Models\CartItem;
use App\Models\Category;
use App\Models\DeliveryZone;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Seller;
use App\Models\Setting;
use App\Models\Store;
use App\Models\StoreProductVariant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->withoutMiddleware([VerifyLicense::class]);

    $this->user = User::factory()->create();
    $seller = Seller::factory()->create();

    // Create system setting
    Setting::create([
        'variable' => 'system',
        'value' => json_encode([
            'minimumCartAmount' => 1,
            'currency' => 'USD',
            'checkoutType' => 'single_store',
        ]),
    ]);

    // Create payment setting with COD enabled and COD charges set to 25.00
    Setting::create([
        'variable' => 'payment',
        'value' => json_encode([
            'cod' => true,
            'codCharges' => '25.00',
        ]),
    ]);

    // Setup Store, Zone, Product, Variant, Cart
    $this->store = Store::factory()->create([
        'seller_id' => $seller->id,
        'latitude' => 23.5,
        'longitude' => 69.5,
        'status' => 'online',
        'verification_status' => StoreVerificationStatusEnum::APPROVED(),
        'visibility_status' => StoreVisibilityStatusEnum::VISIBLE(),
    ]);

    $this->zone = DeliveryZone::factory()->create([
        'center_latitude' => 23.5,
        'center_longitude' => 69.5,
        'radius_km' => 100.0,
        'boundary_json' => [
            ['lat' => 23.0, 'lng' => 69.0],
            ['lat' => 23.0, 'lng' => 70.0],
            ['lat' => 24.0, 'lng' => 70.0],
            ['lat' => 24.0, 'lng' => 69.0],
        ],
        'regular_delivery_charges' => 10,
        'handling_charges' => 5,
        'per_store_drop_off_fee' => 0,
        'status' => ActiveInactiveStatusEnum::ACTIVE(),
    ]);

    DB::table('store_zone')->insert([
        'store_id' => $this->store->id,
        'zone_id' => $this->zone->id,
    ]);

    $category = Category::create([
        'uuid' => (string) Str::uuid(),
        'title' => 'General',
        'slug' => 'general',
        'status' => 1,
    ]);

    $this->product = Product::factory()->create([
        'seller_id' => $seller->id,
        'category_id' => $category->id,
        'status' => ProductStatusEnum::ACTIVE(),
        'verification_status' => ProductVarificationStatusEnum::APPROVED(),
    ]);

    $this->variant = ProductVariant::create([
        'uuid' => (string) Str::uuid(),
        'product_id' => $this->product->id,
        'title' => 'Default Variant',
        'slug' => 'default-variant',
        'barcode' => '12345678',
        'visibility' => 'visible',
        'is_default' => 1,
        'availability' => 1,
    ]);

    $this->spv = StoreProductVariant::create([
        'product_variant_id' => $this->variant->id,
        'store_id' => $this->store->id,
        'sku' => 'SKU-123',
        'price' => 100.00,
        'special_price' => 0,
        'cost' => 0,
        'stock' => 10,
    ]);

    $this->cart = Cart::create([
        'uuid' => (string) Str::uuid(),
        'user_id' => $this->user->id,
    ]);
    CartItem::create([
        'cart_id' => $this->cart->id,
        'product_variant_id' => $this->variant->id,
        'store_product_variant_id' => $this->spv->id,
        'store_id' => $this->store->id,
        'product_id' => $this->product->id,
        'quantity' => 1,
    ]);
});

test('cart API returns cod_charges in payment summary', function () {
    $response = $this->actingAs($this->user, 'sanctum')
        ->getJson('/api/user/cart?latitude=23.5&longitude=69.5');

    $response->assertStatus(200);
    expect((float) $response->json('data.payment_summary.cod_charges'))->toEqual(25.0);
});

test('placing order with COD payment method includes cod_charges in final payable total', function () {
    $address = Address::create([
        'user_id' => $this->user->id,
        'name' => 'John Doe',
        'mobile' => '1234567890',
        'address_line1' => 'Street 1',
        'city' => 'City',
        'state' => 'State',
        'country' => 'Country',
        'country_code' => 'US',
        'zipcode' => '123456',
        'latitude' => 23.5,
        'longitude' => 69.5,
        'address_type' => 'home',
    ]);

    $response = $this->actingAs($this->user, 'sanctum')
        ->postJson('/api/user/orders', [
            'payment_type' => 'cod',
            'address_id' => $address->id,
            'delivery_type' => 'delivery',
            'latitude' => 23.5,
            'longitude' => 69.5,
        ]);

    $response->assertStatus(200);
    // Subtotal 100 + Handling 5 + Delivery 10 + COD charges 25 = 140
    expect((float) $response->json('data.total_payable'))->toEqual(140.0);
});
