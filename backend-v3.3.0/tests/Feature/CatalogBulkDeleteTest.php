<?php

use App\Enums\DefaultSystemRolesEnum;
use App\Enums\GuardNameEnum;
use App\Enums\Order\OrderItemStatusEnum;
use App\Models\Brand;
use App\Models\Category;
use App\Models\DeliveryZone;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Seller;
use App\Models\Store;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->withoutMiddleware();
});

function catalogBulkDeleteAdmin(): User
{
    $admin = User::factory()->create(['access_panel' => GuardNameEnum::ADMIN->value]);
    Role::findOrCreate(DefaultSystemRolesEnum::SUPER_ADMIN->value, GuardNameEnum::ADMIN->value);
    $admin->assignRole(DefaultSystemRolesEnum::SUPER_ADMIN->value);

    return $admin;
}

function catalogBulkDeleteSeller(): Seller
{
    $seller = Seller::factory()->create();
    $seller->user->update(['access_panel' => GuardNameEnum::SELLER->value]);
    Role::findOrCreate(DefaultSystemRolesEnum::SELLER->value, GuardNameEnum::SELLER->value);
    $seller->user->assignRole(DefaultSystemRolesEnum::SELLER->value);

    return $seller;
}

function catalogBulkDeleteCategory(array $overrides = []): Category
{
    return Category::query()->create(array_merge([
        'uuid' => (string) Str::uuid(),
        'title' => 'Bulk Delete Category '.Str::random(8),
        'status' => 'active',
        'requires_approval' => false,
        'metadata' => [],
    ], $overrides));
}

function catalogBulkDeleteBrand(array $overrides = []): Brand
{
    return Brand::query()->create(array_merge([
        'title' => 'Bulk Delete Brand '.Str::random(8),
        'description' => 'Bulk delete brand description',
        'status' => 'active',
        'metadata' => [],
        'scope_type' => 'global',
    ], $overrides));
}

function catalogBulkDeleteProduct(int $sellerId, array $overrides = []): Product
{
    return Product::forceCreate(testProductData($sellerId, array_merge([
        'title' => 'Bulk Delete Product '.Str::random(8),
    ], $overrides)));
}

it('bulk deletes selected admin categories and skips categories with products', function () {
    $admin = catalogBulkDeleteAdmin();
    $seller = Seller::factory()->create();

    $deletable = catalogBulkDeleteCategory();
    $blocked = catalogBulkDeleteCategory();
    catalogBulkDeleteProduct($seller->id, ['category_id' => $blocked->id]);

    $response = $this
        ->actingAs($admin)
        ->postJson(route('admin.categories.bulk-delete'), [
            'ids' => [$deletable->id, $blocked->id],
        ]);

    $response
        ->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonPath('data.deleted_count', 1)
        ->assertJsonCount(1, 'data.skipped');

    $this->assertSoftDeleted('categories', ['id' => $deletable->id]);
    $this->assertDatabaseHas('categories', ['id' => $blocked->id, 'deleted_at' => null]);
});

it('bulk skips parent categories whose direct child has products', function () {
    $admin = catalogBulkDeleteAdmin();
    $seller = Seller::factory()->create();

    $parent = catalogBulkDeleteCategory();
    $child = catalogBulkDeleteCategory(['parent_id' => $parent->id]);
    catalogBulkDeleteProduct($seller->id, ['category_id' => $child->id]);

    $response = $this
        ->actingAs($admin)
        ->postJson(route('admin.categories.bulk-delete'), [
            'ids' => [$parent->id],
        ]);

    $response
        ->assertOk()
        ->assertJsonPath('data.deleted_count', 0)
        ->assertJsonCount(1, 'data.skipped');

    $this->assertDatabaseHas('categories', ['id' => $parent->id, 'deleted_at' => null]);
});

it('bulk deletes selected admin brands', function () {
    $admin = catalogBulkDeleteAdmin();
    $first = catalogBulkDeleteBrand();
    $second = catalogBulkDeleteBrand();

    $response = $this
        ->actingAs($admin)
        ->postJson(route('admin.brands.bulk-delete'), [
            'ids' => [$first->id, $second->id],
        ]);

    $response
        ->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonPath('data.deleted_count', 2);

    $this->assertDatabaseMissing('brands', ['id' => $first->id]);
    $this->assertDatabaseMissing('brands', ['id' => $second->id]);
});

it('bulk delete reports unauthorized admin rows without deleting them', function () {
    $admin = User::factory()->create(['access_panel' => GuardNameEnum::ADMIN->value]);
    $brand = catalogBulkDeleteBrand();

    $response = $this
        ->actingAs($admin)
        ->postJson(route('admin.brands.bulk-delete'), [
            'ids' => [$brand->id],
        ]);

    $response
        ->assertOk()
        ->assertJsonPath('data.deleted_count', 0)
        ->assertJsonCount(1, 'data.skipped');

    $this->assertDatabaseHas('brands', ['id' => $brand->id]);
});

it('bulk deletes products for admin and skips products with pending orders', function () {
    $admin = catalogBulkDeleteAdmin();
    $seller = Seller::factory()->create();
    $deletable = catalogBulkDeleteProduct($seller->id);
    $blocked = catalogBulkDeleteProduct($seller->id);
    $variant = ProductVariant::forceCreate(testVariantData($blocked->id));
    $store = Store::forceCreate(testStoreData($seller->id));
    $customer = User::factory()->create();
    $deliveryZone = DeliveryZone::factory()->create();
    $order = Order::forceCreate([
        'uuid' => (string) Str::uuid(),
        'user_id' => $customer->id,
        'delivery_zone_id' => $deliveryZone->id,
        'slug' => 'bulk-delete-order-'.Str::random(10),
        'email' => $customer->email,
        'ip_address' => '127.0.0.1',
        'currency_code' => 'USD',
        'currency_rate' => 1,
        'payment_method' => 'cod',
        'payment_status' => 'pending',
        'wallet_balance' => 0,
        'subtotal' => 10,
        'total_payable' => 10,
        'final_total' => 10,
        'billing_name' => 'Test Customer',
        'billing_address_1' => 'Address',
        'billing_landmark' => 'Landmark',
        'billing_zip' => '12345',
        'billing_phone' => '9999999999',
        'billing_address_type' => 'home',
        'billing_latitude' => 10,
        'billing_longitude' => 10,
        'billing_city' => 'City',
        'billing_state' => 'State',
        'billing_country' => 'India',
        'billing_country_code' => 'IN',
        'shipping_name' => 'Test Customer',
        'shipping_address_1' => 'Address',
        'shipping_landmark' => 'Landmark',
        'shipping_zip' => '12345',
        'shipping_phone' => '9999999999',
        'shipping_address_type' => 'home',
        'shipping_latitude' => 10,
        'shipping_longitude' => 10,
        'shipping_city' => 'City',
        'shipping_state' => 'State',
        'shipping_country' => 'India',
        'shipping_country_code' => 'IN',
    ]);
    OrderItem::forceCreate([
        'order_id' => $order->id,
        'product_id' => $blocked->id,
        'product_variant_id' => $variant->id,
        'store_id' => $store->id,
        'title' => $blocked->title,
        'variant_title' => $variant->title,
        'sku' => 'SKU-'.Str::random(8),
        'quantity' => 1,
        'price' => 10,
        'subtotal' => 10,
        'status' => OrderItemStatusEnum::PENDING(),
    ]);

    $response = $this
        ->actingAs($admin)
        ->postJson(route('admin.products.bulk-delete'), [
            'ids' => [$deletable->id, $blocked->id],
        ]);

    $response
        ->assertOk()
        ->assertJsonPath('data.deleted_count', 1)
        ->assertJsonCount(1, 'data.skipped');

    $this->assertSoftDeleted('products', ['id' => $deletable->id]);
    $this->assertDatabaseHas('products', ['id' => $blocked->id, 'deleted_at' => null]);
});

it('seller bulk product delete is scoped to the authenticated seller', function () {
    $seller = catalogBulkDeleteSeller();
    $otherSeller = Seller::factory()->create();
    $ownProduct = catalogBulkDeleteProduct($seller->id);
    $otherProduct = catalogBulkDeleteProduct($otherSeller->id);

    $response = $this
        ->actingAs($seller->user)
        ->postJson(route('seller.products.bulk-delete'), [
            'ids' => [$ownProduct->id, $otherProduct->id],
        ]);

    $response
        ->assertOk()
        ->assertJsonPath('data.deleted_count', 1)
        ->assertJsonCount(1, 'data.skipped');

    $this->assertSoftDeleted('products', ['id' => $ownProduct->id]);
    $this->assertDatabaseHas('products', ['id' => $otherProduct->id, 'deleted_at' => null]);
});
