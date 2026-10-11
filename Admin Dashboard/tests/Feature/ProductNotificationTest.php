<?php

use App\Jobs\SendBackInStockNotificationJob;
use App\Models\Product;
use App\Models\ProductNotification;
use App\Models\ProductVariant;
use App\Models\Store;
use App\Models\StoreProductVariant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->withoutMiddleware([\App\Http\Middleware\VerifyLicense::class]);

    $this->user = User::factory()->create();
    $seller = \App\Models\Seller::factory()->create();

    $this->store = Store::factory()->create([
        'seller_id' => $seller->id,
        'latitude' => 23.2420,
        'longitude' => 69.6669,
        'status' => 'online',
        'verification_status' => \App\Enums\Store\StoreVerificationStatusEnum::APPROVED(),
        'visibility_status' => \App\Enums\Store\StoreVisibilityStatusEnum::VISIBLE(),
    ]);

    $zone = \App\Models\DeliveryZone::factory()->create([
        'center_latitude' => 23.2420,
        'center_longitude' => 69.6669,
        'radius_km' => 100.0,
        'boundary_json' => null,
        'status' => \App\Enums\ActiveInactiveStatusEnum::ACTIVE(),
    ]);

    \Illuminate\Support\Facades\DB::table('store_zone')->insert([
        'store_id' => $this->store->id,
        'zone_id' => $zone->id,
    ]);

    $category = \App\Models\Category::create(['uuid' => (string) \Illuminate\Support\Str::uuid(), 'title' => 'Tech', 'slug' => 'tech', 'status' => 1]);

    $this->product = Product::factory()->create([
        'seller_id' => $seller->id,
        'category_id' => $category->id,
        'status' => \App\Enums\Product\ProductStatusEnum::ACTIVE(),
        'verification_status' => \App\Enums\Product\ProductVarificationStatusEnum::APPROVED(),
    ]);

    $this->variant = ProductVariant::create([
        'uuid' => (string) \Illuminate\Support\Str::uuid(),
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
        'price' => 500,
        'special_price' => 0,
        'cost' => 0,
        'stock' => 0,
    ]);
});

it('rejects unauthenticated is_notify requests with 401', function () {
    $response = $this->getJson("/api/products/{$this->product->slug}?latitude=23.2420&longitude=69.6669&is_notify=1&variant_id={$this->variant->id}");

    $response->assertStatus(401)
        ->assertJson([
            'success' => false,
        ]);
});

it('toggles subscription on GET /api/products/{slug}?is_notify=1 for authenticated users', function () {
    // 1. Subscribe
    $response1 = $this->actingAs($this->user, 'sanctum')
        ->getJson("/api/products/{$this->product->slug}?latitude=23.2420&longitude=69.6669&is_notify=1&variant_id={$this->variant->id}");

    $response1->assertStatus(200);
    expect($response1->json('data.variants.0.is_notify'))->toBeTrue()
        ->and($response1->json('data.notification_set'))->toBeTrue();

    $notificationExists = ProductNotification::where('user_id', $this->user->id)
        ->where('product_variant_id', $this->variant->id)
        ->where('status', 'pending')
        ->exists();
    expect($notificationExists)->toBeTrue();

    // 2. Unsubscribe / Cancel Notification (is_notify=0)
    $response2 = $this->actingAs($this->user, 'sanctum')
        ->getJson("/api/products/{$this->product->slug}?latitude=23.2420&longitude=69.6669&is_notify=0&variant_id={$this->variant->id}");

    $response2->assertStatus(200);
    expect($response2->json('data.variants.0.is_notify'))->toBeFalse()
        ->and($response2->json('data.notification_set'))->toBeFalse();

    $notificationExistsAfterToggle = ProductNotification::where('user_id', $this->user->id)
        ->where('product_variant_id', $this->variant->id)
        ->where('status', 'pending')
        ->exists();
    expect($notificationExistsAfterToggle)->toBeFalse();
});

it('accepts is_notifiy typo and product_variant_id parameter aliases', function () {
    $response = $this->actingAs($this->user, 'sanctum')
        ->getJson("/api/products/{$this->product->slug}?latitude=23.2420&longitude=69.6669&is_notifiy=1&product_variant_id={$this->variant->id}");

    $response->assertStatus(200);
    expect($response->json('data.variants.0.is_notify'))->toBeTrue()
        ->and($response->json('data.notification_set'))->toBeTrue();

    $notificationExists = ProductNotification::where('user_id', $this->user->id)
        ->where('product_variant_id', $this->variant->id)
        ->where('status', 'pending')
        ->exists();
    expect($notificationExists)->toBeTrue();
});

it('executes instant back in stock notification when stock increases from 0 to > 0', function () {
    ProductNotification::create([
        'user_id' => $this->user->id,
        'product_id' => $this->product->id,
        'product_variant_id' => $this->variant->id,
        'store_id' => $this->store->id,
        'status' => 'pending',
    ]);

    $this->spv->update(['stock' => 10]);

    $notification = ProductNotification::where('user_id', $this->user->id)
        ->where('product_variant_id', $this->variant->id)
        ->first();

    expect($notification)->toBeNull();
});

it('executes back in stock notification job and deletes entry upon successful dispatch', function () {
    ProductNotification::create([
        'user_id' => $this->user->id,
        'product_id' => $this->product->id,
        'product_variant_id' => $this->variant->id,
        'store_id' => $this->store->id,
        'status' => 'pending',
    ]);

    $job = new SendBackInStockNotificationJob($this->variant->id, $this->store->id);
    $job->handle();

    $notification = ProductNotification::where('user_id', $this->user->id)
        ->where('product_variant_id', $this->variant->id)
        ->first();

    expect($notification)->toBeNull();
});
