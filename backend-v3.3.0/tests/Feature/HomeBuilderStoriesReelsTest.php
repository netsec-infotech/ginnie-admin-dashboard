<?php

use App\Enums\HomePage\HomeSectionTypeEnum;
use App\Enums\HomePage\ReelSourceEnum;
use App\Enums\HomePage\ReelStyleEnum;
use App\Enums\HomePage\StoryStyleEnum;
use App\Enums\SettingTypeEnum;
use App\Http\Middleware\CheckInstallation;
use App\Http\Middleware\VerifyLicense;
use App\Http\Requests\HomeBuilder\SaveHomeLayoutRequest;
use App\Http\Resources\WatchBuy\Customer\CustomerWatchBuyReelResource;
use App\Models\DeliveryZone;
use App\Models\HomeLayout;
use App\Models\HomeSection;
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
use App\Services\HomeBuilderOptions;
use App\Services\HomeBuilderService;
use App\Services\HomeLayoutResolver;
use App\Services\SettingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->withoutMiddleware([CheckInstallation::class, VerifyLicense::class]);
    Storage::fake('public');
    config(['media-library.disk_name' => 'public']);

    Setting::query()->updateOrCreate(
        ['variable' => SettingTypeEnum::WATCH_AND_BUY()],
        ['value' => json_encode([
            'enabled' => true,
            'title' => 'Watch & Buy',
            'maxDurationSeconds' => 60,
            'maxUploadMb' => 50,
            'maxProductsPerReel' => 10,
            'statusesEnabled' => true,
            'maxStatusDurationSeconds' => 15,
            'maxProductsPerStatus' => 5,
        ], JSON_THROW_ON_ERROR)],
    );
    app(SettingService::class)->clearSettingCache(SettingTypeEnum::WATCH_AND_BUY());
});

function homeBuilderZone(string $name, float $latitude, float $longitude): DeliveryZone
{
    return DeliveryZone::factory()->create([
        'name' => $name,
        'center_latitude' => $latitude,
        'center_longitude' => $longitude,
        'radius_km' => 10,
        'boundary_json' => null,
        'status' => 'active',
    ]);
}

/** @return array{product: Product, variant: ProductVariant, store: Store} */
function homeBuilderEligibleProduct(Seller $seller, DeliveryZone $zone, bool $available = true, float $latitude = 20.0, float $longitude = 70.0): array
{
    $store = Store::factory()->create([
        'seller_id' => $seller->id,
        'status' => 'online',
        'verification_status' => 'approved',
        'visibility_status' => 'visible',
        'latitude' => $latitude,
        'longitude' => $longitude,
    ]);
    $store->deliveryZones()->attach($zone->id);

    $product = Product::forceCreate(testProductData($seller->id, [
        'status' => 'active',
        'verification_status' => 'approved',
    ]));
    $variant = ProductVariant::forceCreate(testVariantData($product->id, [
        'availability' => $available,
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

    return compact('product', 'variant', 'store');
}

function attachHomeBuilderProduct(WatchBuyReel $reel, array $productData, int $sortOrder, bool $primary = false): WatchBuyReelProduct
{
    return WatchBuyReelProduct::create([
        'reel_id' => $reel->id,
        'product_id' => $productData['product']->id,
        'product_variant_id' => $productData['variant']->id,
        'store_id' => $productData['store']->id,
        'sort_order' => $sortOrder,
        'is_primary' => $primary,
    ]);
}

it('registers stories and reels as supported Home Builder sections', function () {
    $options = app(HomeBuilderOptions::class);

    expect($options->sectionTypes('app'))->toContain('stories', 'reels')
        ->and($options->stylesFor('app', 'stories'))->toBe(StoryStyleEnum::values())
        ->and($options->stylesFor('app', 'reels'))->toBe(ReelStyleEnum::values())
        ->and($options->configValues('app', 'reels', 'source'))->toBe(ReelSourceEnum::values());
});

it('returns only eligible in-zone reel attachments and excludes empty reels', function () {
    $zone = homeBuilderZone('Zone 1', 20.0, 70.0);
    $otherZone = homeBuilderZone('Zone 2', 24.0, 74.0);
    $seller = Seller::factory()->create();
    $profile = WatchBuyProfile::factory()->create(['seller_id' => $seller->id]);

    $eligible = homeBuilderEligibleProduct($seller, $zone);
    $outOfZone = homeBuilderEligibleProduct($seller, $otherZone);
    $unavailable = homeBuilderEligibleProduct($seller, $zone, false);

    $mixedReel = WatchBuyReel::factory()->for($profile, 'profile')->create([
        'source' => 'seller',
        'like_count' => 50,
        'published_at' => now(),
    ]);
    attachHomeBuilderProduct($mixedReel, $outOfZone, 0, true);
    $eligibleAttachment = attachHomeBuilderProduct($mixedReel, $eligible, 1);
    attachHomeBuilderProduct($mixedReel, $unavailable, 2);

    $outOfZoneReel = WatchBuyReel::factory()->for($profile, 'profile')->create([
        'source' => 'admin_direct',
        'like_count' => 100,
        'published_at' => now(),
    ]);
    attachHomeBuilderProduct($outOfZoneReel, $outOfZone, 0, true);

    $section = new HomeSection([
        'type' => HomeSectionTypeEnum::REELS(),
        'style' => ReelStyleEnum::CARD_SLIDER(),
        'config' => ['source' => ReelSourceEnum::TRENDING(), 'limit' => 8],
    ]);

    $reels = app(HomeLayoutResolver::class)->resolveReels($section, $zone, 20.0, 70.0);

    expect($reels)->toHaveCount(1)
        ->and($reels->first()->is($mixedReel))->toBeTrue()
        ->and($reels->first()->products)->toHaveCount(1)
        ->and($reels->first()->products->first()->id)->toBe($eligibleAttachment->id)
        ->and($reels->first()->products->first()->is_effective_primary)->toBeTrue();
});

it('orders latest separately while trending aliases most liked', function () {
    $zone = homeBuilderZone('Zone 1', 20.0, 70.0);
    $seller = Seller::factory()->create();
    $profile = WatchBuyProfile::factory()->create(['seller_id' => $seller->id]);
    $product = homeBuilderEligibleProduct($seller, $zone);

    $olderPopular = WatchBuyReel::factory()->for($profile, 'profile')->create(['like_count' => 100, 'published_at' => now()->subHour()]);
    attachHomeBuilderProduct($olderPopular, $product, 0, true);
    $newer = WatchBuyReel::factory()->for($profile, 'profile')->create(['like_count' => 10, 'published_at' => now()]);
    attachHomeBuilderProduct($newer, $product, 0, true);

    $resolver = app(HomeLayoutResolver::class);
    $section = new HomeSection(['type' => 'reels', 'config' => ['source' => 'latest', 'limit' => 10]]);
    expect($resolver->resolveReels($section, $zone)->pluck('id')->all())->toBe([$newer->id, $olderPopular->id]);

    $section->config = ['source' => 'trending', 'limit' => 10];
    expect($resolver->resolveReels($section, $zone)->pluck('id')->all())->toBe([$olderPopular->id, $newer->id]);

    $section->config = ['source' => 'most_liked', 'limit' => 10];
    expect($resolver->resolveReels($section, $zone)->pluck('id')->all())->toBe([$olderPopular->id, $newer->id]);
});

it('uses the customer reel resource without moderation or view fields', function () {
    $zone = homeBuilderZone('Zone 1', 20.0, 70.0);
    $seller = Seller::factory()->create();
    $profile = WatchBuyProfile::factory()->create(['seller_id' => $seller->id]);
    $product = homeBuilderEligibleProduct($seller, $zone, true, 20.005, 70.0);
    $customer = User::factory()->create(['access_panel' => 'web']);

    $reel = WatchBuyReel::factory()->for($profile, 'profile')->create([
        'like_count' => 1,
        'published_at' => now(),
        'block_reason' => 'internal-only',
    ]);
    attachHomeBuilderProduct($reel, $product, 0, true);
    WatchBuyReelLike::create(['reel_id' => $reel->id, 'user_id' => $customer->id]);

    $section = new HomeSection(['type' => 'reels', 'config' => ['source' => 'latest', 'limit' => 10]]);
    $resolved = app(HomeLayoutResolver::class)->resolveReels($section, $zone, 20.0, 70.0, $customer);
    $request = Request::create('/api/home-layout', 'GET', ['latitude' => 20.0, 'longitude' => 70.0]);
    $request->setUserResolver(fn () => $customer);
    $payload = (new CustomerWatchBuyReelResource($resolved->first()))->toArray($request);

    expect($payload)->toHaveKeys(['liked_by_me', 'effective_primary_variant_id', 'products'])
        ->and($payload['liked_by_me'])->toBeTrue()
        ->and($payload)->not->toHaveKeys(['block_reason', 'video_size_bytes', 'view_count', 'views_count']);
});

it('returns customer-safe eligible reels through the Home Layout API', function () {
    $zone = homeBuilderZone('Zone 1', 20.0, 70.0);
    $seller = Seller::factory()->create();
    $profile = WatchBuyProfile::factory()->create(['seller_id' => $seller->id]);
    $product = homeBuilderEligibleProduct($seller, $zone);
    $customer = User::factory()->create(['access_panel' => 'web']);
    Sanctum::actingAs($customer);

    $reel = WatchBuyReel::factory()->for($profile, 'profile')->create(['published_at' => now()]);
    attachHomeBuilderProduct($reel, $product, 0, true);
    WatchBuyReelLike::create(['reel_id' => $reel->id, 'user_id' => $customer->id]);

    $layout = HomeLayout::query()->create([
        'platform' => 'app',
        'scope_type' => 'global',
        'is_active' => true,
    ]);
    $layout->sections()->create([
        'type' => 'reels',
        'style' => 'card_slider',
        'config' => ['source' => 'latest', 'limit' => 8, 'show_views' => true],
        'sort_order' => 0,
        'is_active' => true,
    ]);

    $response = $this->getJson(route('home-layout', [
        'platform' => 'app',
        'latitude' => 20.0,
        'longitude' => 70.0,
    ]))->assertOk();

    $response->assertJsonPath('data.sections.0.content.reels.0.id', $reel->id)
        ->assertJsonPath('data.sections.0.content.reels.0.liked_by_me', true)
        ->assertJsonPath('data.sections.0.content.reels.0.effective_primary_variant_id', $product['variant']->id)
        ->assertJsonPath('data.sections.0.content.reels.0.products.0.store.distance_label', '0 m')
        ->assertJsonMissingPath('data.sections.0.content.reels.0.block_reason')
        ->assertJsonMissingPath('data.sections.0.content.reels.0.video_size_bytes')
        ->assertJsonMissingPath('data.sections.0.content.reels.0.view_count')
        ->assertJsonMissingPath('data.sections.0.config.show_views');
});

it('uses the established story tray ordering and active-status rules', function () {
    $platformProfile = WatchBuyProfile::query()->where('profile_type', 'platform')->firstOrFail();
    $sellerProfile = WatchBuyProfile::factory()->create();

    WatchBuyStatus::factory()->for($platformProfile, 'profile')->create(['expires_at' => now()->addDay()]);
    WatchBuyStatus::factory()->for($sellerProfile, 'profile')->create(['expires_at' => now()->addDay()]);

    $section = new HomeSection([
        'type' => HomeSectionTypeEnum::STORIES(),
        'style' => StoryStyleEnum::AVATAR_CIRCLE(),
        'config' => ['limit' => 10],
    ]);
    $stories = app(HomeLayoutResolver::class)->resolveStories($section, null);

    expect($stories)->toHaveCount(2)
        ->and($stories->first()['profile']->id)->toBe($platformProfile->id)
        ->and($stories->pluck('profile.id')->all())->toContain($sellerProfile->id);
});

it('does not expose published reels without a publication timestamp', function () {
    $profile = WatchBuyProfile::factory()->create();
    $reel = WatchBuyReel::factory()->for($profile, 'profile')->create(['status' => 'published', 'published_at' => null]);

    expect(WatchBuyReel::publiclyVisible()->whereKey($reel->id)->exists())->toBeFalse();
});

it('validates story and reel configuration on the server', function () {
    $payload = [
        'platform' => 'app',
        'scope_type' => 'global',
        'is_active' => true,
        'sections' => [
            [
                'type' => 'stories',
                'style' => 'avatar_circle',
                'config' => [
                    'limit' => 0,
                    'ring_color' => 'red',
                    'show_profile_name' => 'yes',
                ],
            ],
            [
                'type' => 'reels',
                'style' => 'unknown',
                'config' => [
                    'source' => 'unknown',
                    'limit' => 51,
                    'show_likes' => 'yes',
                    'show_title' => 'no',
                ],
            ],
        ],
    ];
    $request = SaveHomeLayoutRequest::create('/admin/home-builder/save', 'POST', $payload);
    $request->setContainer(app());
    $validator = Validator::make($request->all(), $request->rules());
    $request->withValidator($validator);

    expect($validator->fails())->toBeTrue()
        ->and($validator->errors()->keys())->toContain(
            'sections.0.config.limit',
            'sections.0.config.ring_color',
            'sections.0.config.show_profile_name',
            'sections.1.style',
            'sections.1.config.source',
            'sections.1.config.limit',
            'sections.1.config.show_likes',
            'sections.1.config.show_title',
        );
});

it('drops deprecated view configuration while preserving unrelated config', function () {
    $layout = app(HomeBuilderService::class)->save([
        'platform' => 'app',
        'scope_type' => 'global',
        'is_active' => true,
        'sections' => [[
            'type' => 'reels',
            'style' => 'card_slider',
            'config' => [
                'source' => 'latest',
                'limit' => 8,
                'show_views' => true,
                'future_option' => 'preserved',
            ],
        ]],
    ]);
    $config = $layout->sections->first()->config;

    expect($config)->not->toHaveKey('show_views')
        ->and($config['future_option'])->toBe('preserved');
});

it('returns empty Home Builder content when Watch and Buy is disabled', function () {
    Setting::query()->where('variable', SettingTypeEnum::WATCH_AND_BUY())->update([
        'value' => json_encode(['enabled' => false, 'statusesEnabled' => true], JSON_THROW_ON_ERROR),
    ]);
    app(SettingService::class)->clearSettingCache(SettingTypeEnum::WATCH_AND_BUY());

    $resolver = app(HomeLayoutResolver::class);
    $stories = $resolver->resolveStories(new HomeSection(['type' => 'stories', 'config' => ['limit' => 10]]), null);
    $reels = $resolver->resolveReels(new HomeSection(['type' => 'reels', 'config' => ['limit' => 10]]), null);

    expect($stories)->toBeEmpty()->and($reels)->toBeEmpty();
});

it('keeps expired stories active in demo mode', function () {
    $systemSetting = Setting::query()->firstOrCreate(
        ['variable' => SettingTypeEnum::SYSTEM()],
        ['value' => json_encode([], JSON_THROW_ON_ERROR)],
    );
    $systemValues = $systemSetting->value ?? [];
    $systemValues['demoMode'] = true;
    $systemSetting->update(['value' => json_encode($systemValues, JSON_THROW_ON_ERROR)]);
    app(SettingService::class)->clearSettingCache(SettingTypeEnum::SYSTEM());

    $platformProfile = WatchBuyProfile::query()->where('profile_type', 'platform')->firstOrFail();
    WatchBuyStatus::factory()->for($platformProfile, 'profile')->create(['expires_at' => now()->subDay()]);

    $stories = app(HomeLayoutResolver::class)->resolveStories(
        new HomeSection(['type' => 'stories', 'config' => ['limit' => 10]]),
        null,
    );

    expect($stories->pluck('profile.id')->all())->toContain($platformProfile->id);
});
