<?php

use App\Enums\GuardNameEnum;
use App\Enums\SettingTypeEnum;
use App\Enums\WatchBuy\WatchBuyReelStatusEnum;
use App\Http\Middleware\CheckInstallation;
use App\Http\Middleware\VerifyLicense;
use App\Models\DeliveryZone;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Seller;
use App\Models\Setting;
use App\Models\Store;
use App\Models\StoreProductVariant;
use App\Models\User;
use App\Models\WatchBuyProfile;
use App\Models\WatchBuyReel;
use App\Models\WatchBuyReelLike;
use App\Models\WatchBuyReelProduct;
use App\Models\WatchBuyStatus;
use App\Models\WatchBuyStatusProduct;
use App\Models\WatchBuyStatusView;
use App\Services\SettingService;
use App\Services\WatchBuyStatusService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->withoutMiddleware([
        CheckInstallation::class,
        VerifyLicense::class,
    ]);
    Storage::fake('public');
    config(['media-library.disk_name' => 'public']);
});

it('returns the exact public configuration contract without requiring coordinates or auth', function () {
    $response = $this->getJson(route('watch-and-buy.config'))
        ->assertOk()
        ->assertJsonStructure([
            'success',
            'message',
            'data' => [
                'enabled',
                'title',
                'maxDurationSeconds',
                'maxUploadMb',
                'effectiveMaxUploadMb',
                'maxProductsPerReel',
                'statusesEnabled',
                'maxStatusDurationSeconds',
                'maxProductsPerStatus',
            ],
        ]);

    expect($response->json('data.enabled'))->toBeTrue()
        ->and($response->json('data.statusesEnabled'))->toBeTrue()
        ->and($response->json('data.title'))->toBe('Watch & Buy');
});

it('filters reel product attachments by customer delivery zone and selects effective primary', function () {
    $seller = Seller::factory()->create();
    $profile = WatchBuyProfile::factory()->create(['seller_id' => $seller->id]);

    // Create 2 delivery zones
    $zoneA = DeliveryZone::factory()->create([
        'center_latitude' => 20.0000,
        'center_longitude' => 70.0000,
        'radius_km' => 10,
        'boundary_json' => null,
        'status' => 'active',
    ]);
    $zoneB = DeliveryZone::factory()->create([
        'center_latitude' => 30.0000,
        'center_longitude' => 80.0000,
        'radius_km' => 10,
        'boundary_json' => null,
        'status' => 'active',
    ]);

    // Store A in Zone A
    $storeA = Store::factory()->create([
        'seller_id' => $seller->id,
        'verification_status' => 'approved',
        'visibility_status' => 'visible',
        'latitude' => 20.005,
        'longitude' => 70.0000,
    ]);
    $storeA->deliveryZones()->attach($zoneA->id);

    // Store B in Zone B
    $storeB = Store::factory()->create([
        'seller_id' => $seller->id,
        'verification_status' => 'approved',
        'visibility_status' => 'visible',
        'latitude' => 30.020,
        'longitude' => 80.0000,
    ]);
    $storeB->deliveryZones()->attach($zoneB->id);

    // Product & variants
    $product = Product::create(testProductData($seller->id, ['status' => 'active', 'verification_status' => 'approved']));
    $variantA = ProductVariant::create(testVariantData($product->id, ['availability' => true]));
    $variantB = ProductVariant::create(testVariantData($product->id, ['availability' => true]));

    StoreProductVariant::create([
        'product_variant_id' => $variantA->id,
        'store_id' => $storeA->id,
        'sku' => 'VAR-A',
        'price' => 100,
        'special_price' => 90,
        'cost' => 50,
        'stock' => 5,
    ]);

    StoreProductVariant::create([
        'product_variant_id' => $variantB->id,
        'store_id' => $storeB->id,
        'sku' => 'VAR-B',
        'price' => 200,
        'special_price' => 180,
        'cost' => 90,
        'stock' => 10,
    ]);

    // Reel with variantB as stored primary (is_primary = true), and variantA as secondary
    $reel = WatchBuyReel::factory()->for($profile, 'profile')->create(['status' => 'published', 'published_at' => now()]);
    WatchBuyReelProduct::create([
        'reel_id' => $reel->id,
        'product_id' => $product->id,
        'product_variant_id' => $variantA->id,
        'store_id' => $storeA->id,
        'sort_order' => 0,
        'is_primary' => false,
    ]);
    WatchBuyReelProduct::create([
        'reel_id' => $reel->id,
        'product_id' => $product->id,
        'product_variant_id' => $variantB->id,
        'store_id' => $storeB->id,
        'sort_order' => 1,
        'is_primary' => true,
    ]);

    // Customer at Zone A coordinates (20.0, 70.0) -> variantB is ineligible, so variantA becomes effective primary
    $responseA = $this->getJson(route('watch-and-buy.reels.feed', [
        'latitude' => 20.0000,
        'longitude' => 70.0000,
    ]))->assertOk();

    $reelsA = $responseA->json('data.items');
    expect($reelsA)->toHaveCount(1)
        ->and($reelsA[0]['effective_primary_variant_id'])->toBe($variantA->id)
        ->and($reelsA[0]['products'])->toHaveCount(1)
        ->and($reelsA[0]['products'][0]['is_effective_primary'])->toBeTrue()
        ->and($reelsA[0]['products'][0]['store']['distance_km'])->toBe(0.56)
        ->and($reelsA[0]['products'][0]['store']['distance_label'])->toBe('556 m');

    $profileResponse = $this->getJson(route('watch-and-buy.profiles.show', [
        'username' => $profile->username,
        'latitude' => 20.0000,
        'longitude' => 70.0000,
    ]))->assertOk();

    expect($profileResponse->json('data.reels.0.products'))->toHaveCount(1)
        ->and($profileResponse->json('data.reels.0.effective_primary_variant_id'))->toBe($variantA->id);

    // Customer at Zone B coordinates (30.0, 80.0) -> variantB is eligible and retains primary status
    $responseB = $this->getJson(route('watch-and-buy.reels.feed', [
        'latitude' => 30.0000,
        'longitude' => 80.0000,
    ]))->assertOk();

    $reelsB = $responseB->json('data.items');
    expect($reelsB)->toHaveCount(1)
        ->and($reelsB[0]['effective_primary_variant_id'])->toBe($variantB->id)
        ->and($reelsB[0]['products'])->toHaveCount(1)
        ->and($reelsB[0]['products'][0]['is_effective_primary'])->toBeTrue()
        ->and($reelsB[0]['products'][0]['store']['distance_km'])->toBe(2.22)
        ->and($reelsB[0]['products'][0]['store']['distance_label'])->toBe('2.2 km');
});

it('excludes reels from the feed when they have no eligible attachments in the resolved zone', function () {
    $seller = Seller::factory()->create();
    $profile = WatchBuyProfile::factory()->create(['seller_id' => $seller->id]);

    $zoneA = DeliveryZone::factory()->create([
        'center_latitude' => 20.0000,
        'center_longitude' => 70.0000,
        'radius_km' => 10,
        'status' => 'active',
    ]);

    $store = Store::factory()->create(['seller_id' => $seller->id, 'verification_status' => 'approved', 'visibility_status' => 'visible']);
    $store->deliveryZones()->attach($zoneA->id);

    $product = Product::create(testProductData($seller->id, ['status' => 'active', 'verification_status' => 'approved']));
    $variant = ProductVariant::create(testVariantData($product->id, ['availability' => true]));

    StoreProductVariant::create([
        'product_variant_id' => $variant->id,
        'store_id' => $store->id,
        'sku' => 'VAR-1',
        'price' => 100,
        'special_price' => 90,
        'cost' => 50,
        'stock' => 5,
    ]);

    $reel = WatchBuyReel::factory()->for($profile, 'profile')->create(['status' => 'published', 'published_at' => now()]);
    WatchBuyReelProduct::create([
        'reel_id' => $reel->id,
        'product_id' => $product->id,
        'product_variant_id' => $variant->id,
        'store_id' => $store->id,
        'sort_order' => 0,
        'is_primary' => true,
    ]);

    // Customer in another coordinate where no zone exists (e.g. 0.0, 0.0) -> Reel is excluded from feed but remains published in DB
    $response = $this->getJson(route('watch-and-buy.reels.feed', [
        'latitude' => 0.0,
        'longitude' => 0.0,
    ]))->assertOk();

    expect($response->json('data.items'))->toBeEmpty()
        ->and($reel->fresh()->status)->toBe(WatchBuyReelStatusEnum::PUBLISHED);
});

it('paginates reel feed deterministically using session_id and encrypted cursor', function () {
    $seller = Seller::factory()->create();
    $profile = WatchBuyProfile::factory()->create(['seller_id' => $seller->id]);

    $zone = DeliveryZone::factory()->create([
        'center_latitude' => 20.0000,
        'center_longitude' => 70.0000,
        'radius_km' => 50,
        'boundary_json' => null,
        'status' => 'active',
    ]);
    $store = Store::factory()->create(['seller_id' => $seller->id, 'verification_status' => 'approved', 'visibility_status' => 'visible']);
    $store->deliveryZones()->attach($zone->id);

    $product = Product::create(testProductData($seller->id, ['status' => 'active', 'verification_status' => 'approved']));

    // Create 5 reels
    for ($i = 0; $i < 5; $i++) {
        $variant = ProductVariant::create(testVariantData($product->id, ['availability' => true]));
        StoreProductVariant::create([
            'product_variant_id' => $variant->id,
            'store_id' => $store->id,
            'sku' => "VAR-PAG-{$i}",
            'price' => 100,
            'special_price' => 90,
            'cost' => 50,
            'stock' => 5,
        ]);
        $reel = WatchBuyReel::factory()->for($profile, 'profile')->create(['status' => 'published', 'published_at' => now()]);
        WatchBuyReelProduct::create([
            'reel_id' => $reel->id,
            'product_id' => $product->id,
            'product_variant_id' => $variant->id,
            'store_id' => $store->id,
            'sort_order' => 0,
            'is_primary' => true,
        ]);
    }

    $sessionId = (string) Str::uuid();

    // Page 1: per_page = 2
    $page1 = $this->getJson(route('watch-and-buy.reels.feed', [
        'latitude' => 20.0000,
        'longitude' => 70.0000,
        'session_id' => $sessionId,
        'per_page' => 2,
    ]))->assertOk();

    expect($page1->json('data.items'))->toHaveCount(2)
        ->and($page1->json('data.meta.next_cursor'))->not->toBeNull();

    $cursor1 = $page1->json('data.meta.next_cursor');

    // Page 2: with cursor
    $page2 = $this->getJson(route('watch-and-buy.reels.feed', [
        'latitude' => 20.0000,
        'longitude' => 70.0000,
        'session_id' => $sessionId,
        'cursor' => $cursor1,
        'per_page' => 2,
    ]))->assertOk();

    expect($page2->json('data.items'))->toHaveCount(2)
        ->and($page2->json('data.meta.next_cursor'))->not->toBeNull();

    // Ensure no overlap between page 1 and page 2
    $page1Ids = collect($page1->json('data.items'))->pluck('id')->all();
    $page2Ids = collect($page2->json('data.items'))->pluck('id')->all();
    expect(array_intersect($page1Ids, $page2Ids))->toBeEmpty();

    // Reusing cursor with different session ID fails with 400
    $this->getJson(route('watch-and-buy.reels.feed', [
        'latitude' => 20.0000,
        'longitude' => 70.0000,
        'session_id' => (string) Str::uuid(),
        'cursor' => $cursor1,
    ]))->assertStatus(400);
});

it('prioritizes a shared reel slug on page 1 and returns slug_not_found when unavailable', function () {
    $seller = Seller::factory()->create();
    $profile = WatchBuyProfile::factory()->create(['seller_id' => $seller->id]);

    $zone = DeliveryZone::factory()->create([
        'center_latitude' => 20.0000,
        'center_longitude' => 70.0000,
        'radius_km' => 50,
        'boundary_json' => null,
        'status' => 'active',
    ]);
    $store = Store::factory()->create(['seller_id' => $seller->id, 'verification_status' => 'approved', 'visibility_status' => 'visible']);
    $store->deliveryZones()->attach($zone->id);

    $product = Product::create(testProductData($seller->id, ['status' => 'active', 'verification_status' => 'approved']));
    $variant = ProductVariant::create(testVariantData($product->id, ['availability' => true]));
    StoreProductVariant::create([
        'product_variant_id' => $variant->id,
        'store_id' => $store->id,
        'sku' => 'VAR-SLUG',
        'price' => 100,
        'special_price' => 90,
        'cost' => 50,
        'stock' => 5,
    ]);

    $sharedReel = WatchBuyReel::factory()->for($profile, 'profile')->create([
        'slug' => 'shared-reel-123',
        'status' => 'published',
        'published_at' => now(),
    ]);
    WatchBuyReelProduct::create([
        'reel_id' => $sharedReel->id,
        'product_id' => $product->id,
        'product_variant_id' => $variant->id,
        'store_id' => $store->id,
        'sort_order' => 0,
        'is_primary' => true,
    ]);

    $randomReel = WatchBuyReel::factory()->for($profile, 'profile')->create([
        'status' => 'published',
        'published_at' => now(),
    ]);
    WatchBuyReelProduct::create([
        'reel_id' => $randomReel->id,
        'product_id' => $product->id,
        'product_variant_id' => $variant->id,
        'store_id' => $store->id,
        'sort_order' => 0,
        'is_primary' => true,
    ]);

    $response = $this->getJson(route('watch-and-buy.reels.feed', [
        'latitude' => 20.0000,
        'longitude' => 70.0000,
        'slug' => 'shared-reel-123',
        'per_page' => 1,
    ]))->assertOk();

    expect($response->json('data.items.0.slug'))->toBe('shared-reel-123')
        ->and($response->json('data.items'))->toHaveCount(1)
        ->and($response->json('data.meta.slug_not_found'))->toBeFalse();

    // Invalid slug
    $invalidResponse = $this->getJson(route('watch-and-buy.reels.feed', [
        'latitude' => 20.0000,
        'longitude' => 70.0000,
        'slug' => 'non-existent-slug',
    ]))->assertOk();

    expect($invalidResponse->json('data.meta.slug_not_found'))->toBeTrue();
});

it('prioritizes official profile and groups unseen story profiles first in the Stories Tray', function () {
    $adminUser = User::factory()->create();
    $official = WatchBuyProfile::where('profile_type', 'platform')->firstOrFail();
    $seller = Seller::factory()->create();
    $sellerProfile = WatchBuyProfile::factory()->create(['seller_id' => $seller->id]);

    $statusService = app(WatchBuyStatusService::class);

    // Create 1 story for official
    $officialStory = $statusService->save($official, ['source' => 'admin_direct', 'text' => 'Official announcement'], UploadedFile::fake()->image('story1.jpg', 600, 900), $adminUser->id);

    // Create 1 story for seller
    $sellerStory = $statusService->save($sellerProfile, ['source' => 'seller', 'text' => 'Seller story'], UploadedFile::fake()->image('story2.jpg', 600, 900), $seller->user_id);

    // Guest request: official profile is always first
    $guestResponse = $this->getJson(route('watch-and-buy.statuses.feed'))->assertOk();
    $guestTray = $guestResponse->json('data.items');

    expect($guestTray)->toHaveCount(2)
        ->and($guestTray[0]['profile']['type'])->toBe('platform')
        ->and($guestTray[0]['status_count'])->toBe(1)
        ->and($guestTray[0]['profile']['has_unseen_status'])->toBeTrue()
        ->and($guestTray[0])->not->toHaveKey('statuses');

    // Authenticated user marks official story seen
    $user = User::factory()->create();
    Sanctum::actingAs($user);

    $this->putJson(route('watch-and-buy.statuses.seen.sync'), [
        'status_ids' => [$officialStory->id],
    ])->assertOk();

    // Now official is fully seen, but seller is unseen -> seller should come first
    $userResponse = $this->getJson(route('watch-and-buy.statuses.feed'))->assertOk();
    $userTray = $userResponse->json('data.items');

    expect($userTray[0]['profile']['id'])->toBe($sellerProfile->id)
        ->and($userTray[0]['profile']['has_unseen_status'])->toBeTrue()
        ->and($userTray[1]['profile']['type'])->toBe('platform')
        ->and($userTray[1]['profile']['has_unseen_status'])->toBeFalse();
});

it('returns profile stories in oldest-to-newest chronological order', function () {
    $seller = Seller::factory()->create();
    $profile = WatchBuyProfile::factory()->create(['seller_id' => $seller->id]);
    $statusService = app(WatchBuyStatusService::class);

    $story1 = $statusService->save($profile, ['source' => 'seller', 'text' => 'First story'], UploadedFile::fake()->image('s1.jpg', 600, 900), $profile->created_by);
    $story2 = $statusService->save($profile, ['source' => 'seller', 'text' => 'Second story'], UploadedFile::fake()->image('s2.jpg', 600, 900), $profile->created_by);

    $response = $this->getJson('/api/watch-and-buy/profiles/'.$profile->username.'/statuses')
        ->assertOk();

    $statuses = $response->json('data.items');
    expect($statuses)->toHaveCount(2)
        ->and($statuses[0]['id'])->toBe($story1->id)
        ->and($statuses[1]['id'])->toBe($story2->id);
});

it('returns store distance for story products when coordinates are provided and null otherwise', function () {
    $seller = Seller::factory()->create();
    $profile = WatchBuyProfile::factory()->create(['seller_id' => $seller->id]);
    $zone = DeliveryZone::factory()->create([
        'center_latitude' => 20.0000,
        'center_longitude' => 70.0000,
        'radius_km' => 10,
        'boundary_json' => null,
        'status' => 'active',
    ]);
    $store = Store::factory()->create([
        'seller_id' => $seller->id,
        'verification_status' => 'approved',
        'visibility_status' => 'visible',
        'latitude' => 20.005,
        'longitude' => 70.0000,
    ]);
    $store->deliveryZones()->attach($zone->id);

    $product = Product::create(testProductData($seller->id, [
        'status' => 'active',
        'verification_status' => 'approved',
    ]));
    $variant = ProductVariant::create(testVariantData($product->id, [
        'availability' => true,
        'visibility' => 'published',
    ]));
    StoreProductVariant::create([
        'product_variant_id' => $variant->id,
        'store_id' => $store->id,
        'sku' => 'STORY-DISTANCE',
        'price' => 100,
        'special_price' => 90,
        'cost' => 50,
        'stock' => 5,
    ]);

    $status = WatchBuyStatus::factory()->for($profile, 'profile')->create();
    WatchBuyStatusProduct::create([
        'status_id' => $status->id,
        'product_id' => $product->id,
        'product_variant_id' => $variant->id,
        'store_id' => $store->id,
        'sort_order' => 0,
    ]);

    $this->getJson(route('watch-and-buy.profiles.statuses', [
        'username' => $profile->username,
        'latitude' => 20.0000,
        'longitude' => 70.0000,
    ]))
        ->assertOk()
        ->assertJsonPath('data.items.0.products.0.store.distance_km', 0.56)
        ->assertJsonPath('data.items.0.products.0.store.distance_label', '556 m');

    $this->getJson(route('watch-and-buy.profiles.statuses', [
        'username' => $profile->username,
    ]))
        ->assertOk()
        ->assertJsonPath('data.items.0.products.0.store.distance_km', null)
        ->assertJsonPath('data.items.0.products.0.store.distance_label', null);
});

it('synchronizes bulk likes transactionally and idempotently with non-negative counter safeguards', function () {
    $user = User::factory()->create();
    Sanctum::actingAs($user);

    $seller = Seller::factory()->create();
    $profile = WatchBuyProfile::factory()->create(['seller_id' => $seller->id]);

    $zone = DeliveryZone::factory()->create([
        'center_latitude' => 20.0000,
        'center_longitude' => 70.0000,
        'radius_km' => 50,
        'boundary_json' => null,
        'status' => 'active',
    ]);
    $store = Store::factory()->create(['seller_id' => $seller->id, 'verification_status' => 'approved', 'visibility_status' => 'visible']);
    $store->deliveryZones()->attach($zone->id);

    $product = Product::create(testProductData($seller->id, ['status' => 'active', 'verification_status' => 'approved']));
    $variant = ProductVariant::create(testVariantData($product->id, ['availability' => true]));
    StoreProductVariant::create([
        'product_variant_id' => $variant->id,
        'store_id' => $store->id,
        'sku' => 'VAR-LIKE',
        'price' => 100,
        'special_price' => 90,
        'cost' => 50,
        'stock' => 5,
    ]);

    $reel1 = WatchBuyReel::factory()->for($profile, 'profile')->create(['status' => 'published', 'published_at' => now(), 'like_count' => 0]);
    WatchBuyReelProduct::create([
        'reel_id' => $reel1->id,
        'product_id' => $product->id,
        'product_variant_id' => $variant->id,
        'store_id' => $store->id,
        'sort_order' => 0,
        'is_primary' => true,
    ]);

    $reel2 = WatchBuyReel::factory()->for($profile, 'profile')->create(['status' => 'published', 'published_at' => now(), 'like_count' => 5]);
    WatchBuyReelProduct::create([
        'reel_id' => $reel2->id,
        'product_id' => $product->id,
        'product_variant_id' => $variant->id,
        'store_id' => $store->id,
        'sort_order' => 0,
        'is_primary' => true,
    ]);
    WatchBuyReelLike::create(['reel_id' => $reel2->id, 'user_id' => $user->id]);

    // 1. Like reel1 and unlike reel2
    $syncResponse = $this->putJson(route('watch-and-buy.reels.likes.sync'), [
        'latitude' => 20.0000,
        'longitude' => 70.0000,
        'items' => [
            ['reel_id' => $reel1->id, 'liked' => true],
            ['reel_id' => $reel2->id, 'liked' => false],
        ],
    ])->assertOk();

    expect($reel1->fresh()->like_count)->toBe(1)
        ->and($reel2->fresh()->like_count)->toBe(4)
        ->and($syncResponse->json('data.0.liked_by_me'))->toBeTrue()
        ->and($syncResponse->json('data.1.liked_by_me'))->toBeFalse();

    // 2. Liking reel1 again is idempotent and does not increase like_count
    $this->putJson(route('watch-and-buy.reels.likes.sync'), [
        'latitude' => 20.0000,
        'longitude' => 70.0000,
        'items' => [
            ['reel_id' => $reel1->id, 'liked' => true],
        ],
    ])->assertOk();

    expect($reel1->fresh()->like_count)->toBe(1);
});

it('rejects an entire like batch when any requested like is outside the customer zone', function () {
    $customer = User::factory()->create(['access_panel' => GuardNameEnum::WEB->value]);
    Sanctum::actingAs($customer);
    $eligible = createMilestoneThreeReelFixture(20.0, 70.0);
    $outsideZone = createMilestoneThreeReelFixture(30.0, 80.0);

    $this->putJson(route('watch-and-buy.reels.likes.sync'), [
        'latitude' => 20.0,
        'longitude' => 70.0,
        'items' => [
            ['reel_id' => $eligible['reel']->id, 'liked' => true],
            ['reel_id' => $outsideZone['reel']->id, 'liked' => true],
        ],
    ])->assertNotFound();

    expect(WatchBuyReelLike::query()->count())->toBe(0)
        ->and($eligible['reel']->fresh()->like_count)->toBe(0)
        ->and($outsideZone['reel']->fresh()->like_count)->toBe(0);
});

it('allows only customer accounts to mutate likes and story seen state', function () {
    $admin = User::factory()->create(['access_panel' => GuardNameEnum::ADMIN->value]);
    Sanctum::actingAs($admin);
    $fixture = createMilestoneThreeReelFixture(20.0, 70.0);

    $this->putJson(route('watch-and-buy.reels.likes.sync'), [
        'latitude' => 20.0,
        'longitude' => 70.0,
        'items' => [['reel_id' => $fixture['reel']->id, 'liked' => true]],
    ])->assertForbidden();

    $this->putJson(route('watch-and-buy.statuses.seen.sync'), [
        'status_ids' => [999999],
    ])->assertForbidden();

    expect(WatchBuyReelLike::query()->count())->toBe(0)
        ->and(WatchBuyStatusView::query()->count())->toBe(0);
});

it('rejects an entire story seen batch when any story is inactive', function () {
    $customer = User::factory()->create(['access_panel' => GuardNameEnum::WEB->value]);
    Sanctum::actingAs($customer);
    $seller = Seller::factory()->create();
    $profile = WatchBuyProfile::factory()->create(['seller_id' => $seller->id]);
    $active = WatchBuyStatus::factory()->for($profile, 'profile')->create(['expires_at' => now()->addHour()]);
    $expired = WatchBuyStatus::factory()->for($profile, 'profile')->create(['expires_at' => now()->subMinute()]);

    $this->putJson(route('watch-and-buy.statuses.seen.sync'), [
        'status_ids' => [$active->id, $expired->id],
    ])->assertNotFound();

    expect(WatchBuyStatusView::query()->count())->toBe(0);
});

it('keeps stories active and skips expired cleanup while demo mode is enabled', function () {
    $seller = Seller::factory()->create();
    $profile = WatchBuyProfile::factory()->create(['seller_id' => $seller->id]);
    $status = WatchBuyStatus::factory()->for($profile, 'profile')->create([
        'expires_at' => now()->subDay(),
    ]);

    app(SettingService::class)->saveSetting(SettingTypeEnum::SYSTEM(), [
        'demoMode' => true,
    ]);

    expect(WatchBuyStatus::query()->active()->whereKey($status)->exists())->toBeTrue()
        ->and(app(WatchBuyStatusService::class)->deleteExpired())->toBe(0)
        ->and(WatchBuyStatus::find($status->id))->not->toBeNull();

    $this->getJson(route('watch-and-buy.profiles.statuses', [
        'username' => $profile->username,
    ]))
        ->assertOk()
        ->assertJsonPath('data.pagination.total', 1)
        ->assertJsonPath('data.items.0.expires_at', null);

    $status->load(['creator', 'products.variant.product']);
    $statusCards = view('watch_buy._status_cards', [
        'statuses' => collect([$status]),
        'profile' => $profile,
        'canDelete' => false,
    ])->render();

    expect($statusCards)->toContain(__('labels.watch_buy_status_never_expires_demo'));

    app(SettingService::class)->saveSetting(SettingTypeEnum::SYSTEM(), [
        'demoMode' => false,
    ]);

    expect(WatchBuyStatus::query()->active()->whereKey($status)->exists())->toBeFalse()
        ->and(app(WatchBuyStatusService::class)->deleteExpired())->toBe(1)
        ->and(WatchBuyStatus::find($status->id))->toBeNull();
});

it('requires story coordinates to be supplied as a pair', function () {
    $this->getJson(route('watch-and-buy.statuses.feed', ['latitude' => 20.0]))
        ->assertUnprocessable()
        ->assertJsonValidationErrors('longitude');

    $profile = WatchBuyProfile::factory()->create();
    $this->getJson(route('watch-and-buy.profiles.statuses', [
        'username' => $profile->username,
        'longitude' => 70.0,
    ]))->assertUnprocessable()->assertJsonValidationErrors('latitude');
});

it('keeps only authenticated story seen storage and removes all reel view storage', function () {
    $columns = collect(Schema::getColumns('watch_buy_status_views'))->keyBy('name');

    expect(Schema::hasTable('watch_buy_reel_views'))->toBeFalse()
        ->and(Schema::hasColumn('watch_buy_reels', 'view_count'))->toBeFalse()
        ->and(Schema::hasColumn('watch_buy_statuses', 'view_count'))->toBeFalse()
        ->and(Schema::hasColumn('watch_buy_status_views', 'viewer_key'))->toBeFalse()
        ->and($columns->get('user_id')['nullable'])->toBeFalse();
});

it('handles disabled switches gracefully for global feature and stories-only switch', function () {
    $settings = app(SettingService::class);

    // 1. Disable stories only
    Setting::where('variable', SettingTypeEnum::WATCH_AND_BUY())->update([
        'value' => [
            'enabled' => true,
            'title' => 'Watch & Buy',
            'maxDurationSeconds' => 60,
            'maxUploadMb' => 50,
            'maxProductsPerReel' => 10,
            'statusesEnabled' => false,
            'maxStatusDurationSeconds' => 15,
            'maxProductsPerStatus' => 5,
        ],
    ]);
    $settings->clearSettingCache(SettingTypeEnum::WATCH_AND_BUY());

    $this->getJson(route('watch-and-buy.statuses.feed'))->assertOk()->assertJsonPath('data.items', []);
    $this->getJson(route('watch-and-buy.profiles.statuses', 'hyperlocal'))->assertNotFound();

    // Reels still work
    $this->getJson(route('watch-and-buy.reels.feed', [
        'latitude' => 20.0,
        'longitude' => 70.0,
    ]))->assertOk();

    // 2. Disable entire feature
    Setting::where('variable', SettingTypeEnum::WATCH_AND_BUY())->update([
        'value' => [
            'enabled' => false,
            'title' => 'Watch & Buy',
            'maxDurationSeconds' => 60,
            'maxUploadMb' => 50,
            'maxProductsPerReel' => 10,
            'statusesEnabled' => false,
            'maxStatusDurationSeconds' => 15,
            'maxProductsPerStatus' => 5,
        ],
    ]);
    $settings->clearSettingCache(SettingTypeEnum::WATCH_AND_BUY());

    $config = $this->getJson(route('watch-and-buy.config'))->assertOk();
    expect($config->json('data.enabled'))->toBeFalse();

    $this->getJson(route('watch-and-buy.reels.feed', [
        'latitude' => 20.0,
        'longitude' => 70.0,
    ]))->assertOk()->assertJsonPath('data.items', []);

    $this->getJson(route('watch-and-buy.profiles.show', [
        'username' => 'hyperlocal',
        'latitude' => 20.0,
        'longitude' => 70.0,
    ]))->assertNotFound();
});

/**
 * @return array{reel: WatchBuyReel, profile: WatchBuyProfile, zone: DeliveryZone}
 */
function createMilestoneThreeReelFixture(float $latitude, float $longitude): array
{
    $seller = Seller::factory()->create();
    $profile = WatchBuyProfile::factory()->create(['seller_id' => $seller->id]);
    $zone = DeliveryZone::factory()->create([
        'center_latitude' => $latitude,
        'center_longitude' => $longitude,
        'radius_km' => 10,
        'boundary_json' => null,
        'status' => 'active',
    ]);
    $store = Store::factory()->create([
        'seller_id' => $seller->id,
        'verification_status' => 'approved',
        'visibility_status' => 'visible',
    ]);
    $store->deliveryZones()->attach($zone->id);
    $product = Product::create(testProductData($seller->id, [
        'status' => 'active',
        'verification_status' => 'approved',
    ]));
    $variant = ProductVariant::create(testVariantData($product->id, [
        'availability' => true,
        'visibility' => 'published',
    ]));
    StoreProductVariant::create([
        'product_variant_id' => $variant->id,
        'store_id' => $store->id,
        'sku' => (string) Str::uuid(),
        'price' => 100,
        'special_price' => 90,
        'cost' => 50,
        'stock' => 5,
    ]);
    $reel = WatchBuyReel::factory()->for($profile, 'profile')->create([
        'status' => 'published',
        'published_at' => now(),
        'like_count' => 0,
    ]);
    WatchBuyReelProduct::create([
        'reel_id' => $reel->id,
        'product_id' => $product->id,
        'product_variant_id' => $variant->id,
        'store_id' => $store->id,
        'sort_order' => 0,
        'is_primary' => true,
    ]);

    return compact('reel', 'profile', 'zone');
}

it('fetches story tray filtered by store_id and store_slug when story creator belongs to the seller', function () {
    $seller = Seller::factory()->create();
    $store = Store::factory()->create(['seller_id' => $seller->id, 'slug' => 'test-store-slug']);
    $profile = WatchBuyProfile::factory()->create(['seller_id' => $seller->id, 'status' => 'active']);

    $otherSeller = Seller::factory()->create();
    $otherStore = Store::factory()->create(['seller_id' => $otherSeller->id, 'slug' => 'other-store-slug']);
    $otherProfile = WatchBuyProfile::factory()->create(['seller_id' => $otherSeller->id, 'status' => 'active']);

    $statusService = app(WatchBuyStatusService::class);
    // Story without attached products from target seller
    $statusService->save($profile, ['source' => 'seller', 'text' => 'My story without products'], UploadedFile::fake()->image('story1.jpg', 600, 900), $seller->user_id);
    // Story from other seller
    $statusService->save($otherProfile, ['source' => 'seller', 'text' => 'Other story'], UploadedFile::fake()->image('story2.jpg', 600, 900), $otherSeller->user_id);

    // 1. Query by store_id
    $responseById = $this->getJson(route('watch-and-buy.statuses.feed', ['store_id' => $store->id]))
        ->assertOk();
    $itemsById = $responseById->json('data.items');
    expect($itemsById)->toHaveCount(1)
        ->and($itemsById[0]['profile']['id'])->toBe($profile->id)
        ->and($itemsById[0]['status_count'])->toBe(1);

    // 2. Query by store_slug
    $responseBySlug = $this->getJson(route('watch-and-buy.statuses.feed', ['store_slug' => $store->slug]))
        ->assertOk();
    $itemsBySlug = $responseBySlug->json('data.items');
    expect($itemsBySlug)->toHaveCount(1)
        ->and($itemsBySlug[0]['profile']['id'])->toBe($profile->id);

    // 3. Query with both store_id and store_slug
    $responseBoth = $this->getJson(route('watch-and-buy.statuses.feed', ['store_id' => $store->id, 'store_slug' => $store->slug]))
        ->assertOk();
    $itemsBoth = $responseBoth->json('data.items');
    expect($itemsBoth)->toHaveCount(1)
        ->and($itemsBoth[0]['profile']['id'])->toBe($profile->id);
});

