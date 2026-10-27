<?php

use App\Enums\WatchBuy\WatchBuyProfileStatusEnum;
use App\Enums\WatchBuy\WatchBuyReelStatusEnum;
use App\Enums\DefaultSystemRolesEnum;
use App\Enums\GuardNameEnum;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Store;
use App\Models\StoreProductVariant;
use App\Models\WatchBuyModerationLog;
use App\Models\WatchBuyProfile;
use App\Models\WatchBuyReel;
use App\Models\WatchBuyStatus;
use App\Models\User;
use App\Services\WatchBuyMediaInspector;
use App\Services\WatchBuyService;
use App\Services\WatchBuyStatusService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->withoutMiddleware([
        \App\Http\Middleware\CheckInstallation::class,
        \App\Http\Middleware\VerifyLicense::class,
    ]);
    Storage::fake('public');
    config(['media-library.disk_name' => 'public']);
    Notification::fake();
});

it('creates reels published immediately and preserves seller behalf origin on seller edits', function () {
    $profile = milestoneTwoProfileWithPhoto();
    $variant = milestoneTwoEligibleVariant($profile->seller_id);
    $inspector = $this->mock(WatchBuyMediaInspector::class);
    $inspector->shouldReceive('inspect')->twice()->andReturn([
        'duration_ms' => 12_000,
        'video_width' => 1080,
        'video_height' => 1920,
        'video_size_bytes' => 2048,
    ]);
    $service = app(WatchBuyService::class);

    $reel = $service->saveReel(new WatchBuyReel, $profile, [
        'source' => 'seller_behalf',
        'caption' => 'Created by admin',
        'attachment_ids' => [$variant->id],
        'primary_attachment_id' => $variant->id,
    ], UploadedFile::fake()->create('reel.mp4', 2, 'video/mp4'), null, $profile->created_by);

    expect($reel->status)->toBe(WatchBuyReelStatusEnum::PUBLISHED)
        ->and($reel->published_at)->not->toBeNull()
        ->and($reel->products)->toHaveCount(1)
        ->and($reel->products->first()->is_primary)->toBeTrue();

    $updated = $service->saveReel($reel, $profile, [
        'source' => 'seller',
        'caption' => 'Edited by seller',
        'attachment_ids' => [$variant->id],
        'primary_attachment_id' => $variant->id,
    ], UploadedFile::fake()->create('replacement.mp4', 2, 'video/mp4'), null, $profile->created_by);

    expect($updated->source->value)->toBe('seller_behalf')
        ->and($updated->caption)->toBe('Edited by seller');

    $otherVariant = milestoneTwoEligibleVariant(WatchBuyProfile::factory()->create()->seller_id);
    expect(fn () => $service->saveReel($updated, $profile, [
        'source' => 'seller',
        'attachment_ids' => [$otherVariant->id],
        'primary_attachment_id' => $otherVariant->id,
    ], null, null, $profile->created_by))->toThrow(ValidationException::class);
});

it('blocks and restores reel state while retaining moderation history after deletion', function () {
    $profile = WatchBuyProfile::factory()->create();
    $reel = WatchBuyReel::factory()->for($profile, 'profile')->create(['status' => 'published']);
    $service = app(WatchBuyService::class);

    $service->block($reel, 'Policy violation', $profile->created_by);
    expect($reel->refresh()->status)->toBe(WatchBuyReelStatusEnum::BLOCKED)
        ->and($reel->status_before_block)->toBe(WatchBuyReelStatusEnum::PUBLISHED);

    $service->unblock($reel, $profile->created_by);
    expect($reel->refresh()->status)->toBe(WatchBuyReelStatusEnum::PUBLISHED);

    $service->deleteReel($reel, $profile->created_by);
    expect(WatchBuyReel::find($reel->id))->toBeNull()
        ->and(WatchBuyModerationLog::where('target_id', $reel->id)->get()->pluck('action')->map->value->all())
        ->toContain('block', 'unblock', 'delete');
});

it('suspends seller profiles without changing content state and protects the official profile', function () {
    $profile = WatchBuyProfile::factory()->create();
    $reel = WatchBuyReel::factory()->for($profile, 'profile')->create(['status' => 'published']);
    $service = app(WatchBuyService::class);

    $service->suspend($profile, 'Account review', $profile->created_by);
    expect($profile->refresh()->status)->toBe(WatchBuyProfileStatusEnum::SUSPENDED)
        ->and($reel->refresh()->status)->toBe(WatchBuyReelStatusEnum::PUBLISHED)
        ->and(WatchBuyReel::publiclyVisible()->whereKey($reel->id)->exists())->toBeFalse();

    $service->unsuspend($profile, $profile->created_by);
    expect(WatchBuyReel::publiclyVisible()->whereKey($reel->id)->exists())->toBeTrue();

    $official = WatchBuyProfile::where('profile_type', 'platform')->firstOrFail();
    $this->expectException(\Symfony\Component\HttpKernel\Exception\HttpException::class);
    $service->suspend($official, 'Invalid operation', $profile->created_by);
});

it('creates optional-product stories and permanently cleans expired records and media', function () {
    $profile = milestoneTwoProfileWithPhoto();
    $service = app(WatchBuyStatusService::class);
    $story = $service->save($profile, ['source' => 'seller', 'text' => 'Available today'], UploadedFile::fake()->image('story.jpg', 600, 900), $profile->created_by);

    expect($story->products)->toBeEmpty()
        ->and($story->content_type->value)->toBe('image')
        ->and(abs((int) $story->expires_at->diffInHours($story->created_at)))->toBe(24);

    $story->update(['expires_at' => now()->subSecond()]);
    expect(WatchBuyStatus::active()->whereKey($story->id)->exists())->toBeFalse()
        ->and($service->deleteExpired())->toBe(1)
        ->and(WatchBuyStatus::find($story->id))->toBeNull();
});

it('registers the complete admin and seller management navigation', function () {
    expect(config('menu.admin.watch_and_buy.route'))->toHaveKeys(['stories', 'reels', 'profiles', 'official_profile'])
        ->and(config('menu.seller.watch_and_buy.route'))->toHaveKeys(['stories', 'reels', 'profile']);
});

it('never treats the official profile as owned by an account without a seller record', function () {
    $user = User::factory()->create(['access_panel' => GuardNameEnum::SELLER->value]);
    $user->assignRole(Role::findOrCreate(DefaultSystemRolesEnum::SELLER(), GuardNameEnum::SELLER()));
    $official = WatchBuyProfile::where('profile_type', 'platform')->firstOrFail();
    $reel = WatchBuyReel::factory()->for($official, 'profile')->create(['source' => 'admin_direct']);
    Sanctum::actingAs($user);

    $this->getJson(route('seller.api.watch-and-buy.reels.show', $reel))->assertNotFound();
});

function milestoneTwoProfileWithPhoto(): WatchBuyProfile
{
    $profile = WatchBuyProfile::factory()->create();
    $profile->addMedia(UploadedFile::fake()->image('profile.jpg'))->toMediaCollection('watch_buy_profile_photo');

    return $profile->refresh();
}

function milestoneTwoEligibleVariant(int $sellerId): ProductVariant
{
    $product = Product::create(testProductData($sellerId, [
        'status' => 'active',
        'verification_status' => 'approved',
    ]));
    $variant = ProductVariant::create(testVariantData($product->id));
    $store = Store::factory()->create([
        'seller_id' => $sellerId,
        'verification_status' => 'approved',
        'visibility_status' => 'visible',
    ]);
    StoreProductVariant::create([
        'product_variant_id' => $variant->id,
        'store_id' => $store->id,
        'sku' => 'WATCH-BUY-'.$variant->id,
        'price' => 100,
        'special_price' => 90,
        'cost' => 50,
        'stock' => 0,
    ]);

    return $variant->refresh();
}

