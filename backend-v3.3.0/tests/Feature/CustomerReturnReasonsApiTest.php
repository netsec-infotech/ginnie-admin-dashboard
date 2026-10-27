<?php

use App\Enums\Order\OrderItemStatusEnum;
use App\Models\DeliveryZone;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\ReturnReason;
use App\Models\Seller;
use App\Models\Store;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->withoutMiddleware();
});

function createTestOrderForItem($user) {
    return Order::forceCreate([
        'uuid'                  => (string) Str::uuid(),
        'user_id'               => $user->id,
        'delivery_zone_id'      => DeliveryZone::first()?->id ?? DeliveryZone::factory()->create()->id,
        'slug'                  => 'order-test-' . uniqid(),
        'email'                 => $user->email,
        'ip_address'            => '127.0.0.1',
        'currency_code'         => 'USD',
        'currency_rate'         => 1,
        'payment_method'        => 'cod',
        'payment_status'        => 'completed',
        'fulfillment_type'      => 'hyperlocal',
        'wallet_balance'        => 0,
        'subtotal'              => 100.0,
        'total_payable'         => 100.0,
        'final_total'           => 100.0,
        'status'                => 'delivered',
        'billing_name'          => $user->name,
        'billing_address_1'     => '123 Test St',
        'billing_landmark'      => 'Test Landmark',
        'billing_zip'           => '10001',
        'billing_phone'         => '1234567890',
        'billing_address_type'  => 'home',
        'billing_latitude'      => 0,
        'billing_longitude'     => 0,
        'billing_city'          => 'City',
        'billing_state'         => 'State',
        'billing_country'       => 'Country',
        'billing_country_code'  => 'US',
        'shipping_name'         => $user->name,
        'shipping_address_1'    => '123 Test St',
        'shipping_landmark'     => 'Test Landmark',
        'shipping_zip'          => '10001',
        'shipping_phone'        => '1234567890',
        'shipping_address_type' => 'home',
        'shipping_latitude'     => 0,
        'shipping_longitude'    => 0,
        'shipping_city'         => 'City',
        'shipping_state'        => 'State',
        'shipping_country'      => 'Country',
        'shipping_country_code' => 'US',
    ]);
}

test('customer can get applicable return reasons for an order item with global fallback', function () {
    $user = User::factory()->create();
    $seller = Seller::factory()->create();
    $product = Product::forceCreate(testProductData($seller->id));
    $store = Store::factory()->create(['seller_id' => $seller->id]);
    $variant = ProductVariant::forceCreate(testVariantData($product->id));

    $globalReason = ReturnReason::create([
        'seller_id' => null,
        'title' => 'Global Defective Product',
        'proof_required' => true,
        'otp_required' => true,
        'status' => 'active',
    ]);

    $order = createTestOrderForItem($user);
    $orderItem = OrderItem::forceCreate([
        'order_id' => $order->id,
        'product_id' => $product->id,
        'product_variant_id' => $variant->id,
        'store_id' => $store->id,
        'title' => $product->title ?? 'Product Title',
        'variant_title' => 'Default Variant',
        'sku' => 'SKU-TEST-001',
        'quantity' => 1,
        'price' => 50.00,
        'subtotal' => 50.00,
        'status' => OrderItemStatusEnum::DELIVERED(),
    ]);

    $response = $this->actingAs($user, 'sanctum')
        ->getJson("/api/user/orders/items/{$orderItem->id}/return-reasons");

    $response->assertStatus(200)
        ->assertJsonPath('success', true)
        ->assertJsonFragment([
            'id' => $globalReason->id,
            'title' => 'Global Defective Product',
            'proof_required' => true,
            'otp_required' => true,
        ]);
});

test('customer gets seller custom return reasons when assigned to product', function () {
    $user = User::factory()->create();
    $seller = Seller::factory()->create();
    $product = Product::forceCreate(testProductData($seller->id));
    $store = Store::factory()->create(['seller_id' => $seller->id]);
    $variant = ProductVariant::forceCreate(testVariantData($product->id));

    $sellerReason = ReturnReason::create([
        'seller_id' => $seller->id,
        'title' => 'Custom Product Return Reason',
        'proof_required' => false,
        'otp_required' => true,
        'status' => 'active',
    ]);

    $product->returnReasons()->attach($sellerReason->id);

    $order = createTestOrderForItem($user);
    $orderItem = OrderItem::forceCreate([
        'order_id' => $order->id,
        'product_id' => $product->id,
        'product_variant_id' => $variant->id,
        'store_id' => $store->id,
        'title' => $product->title ?? 'Product Title',
        'variant_title' => 'Default Variant',
        'sku' => 'SKU-TEST-001',
        'quantity' => 1,
        'price' => 50.00,
        'subtotal' => 50.00,
        'status' => OrderItemStatusEnum::DELIVERED(),
    ]);

    $response = $this->actingAs($user, 'sanctum')
        ->getJson("/api/user/orders/items/{$orderItem->id}/return-reasons");

    $response->assertStatus(200)
        ->assertJsonPath('success', true)
        ->assertJsonFragment([
            'id' => $sellerReason->id,
            'title' => 'Custom Product Return Reason',
            'proof_required' => false,
            'otp_required' => true,
        ]);
});
