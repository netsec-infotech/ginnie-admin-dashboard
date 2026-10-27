<?php

use App\Enums\AdminPermissionEnum;
use App\Enums\DefaultSystemRolesEnum;
use App\Enums\GuardNameEnum;
use App\Enums\SellerPermissionEnum;
use App\Enums\SettingTypeEnum;
use App\Enums\SpatieMediaCollectionName;
use App\Enums\WatchBuy\WatchBuySourceEnum;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Setting;
use App\Models\Store;
use App\Models\User;
use App\Models\WatchBuyModerationLog;
use App\Models\WatchBuyProfile;
use App\Models\WatchBuyReel;
use App\Models\WatchBuyReelLike;
use App\Models\WatchBuyReelProduct;
use App\Models\WatchBuyStatus;
use App\Models\WatchBuyStatusView;
use App\Services\SettingService;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

it('installs the watch and buy schema and initial defaults', function () {
    $profile = WatchBuyProfile::where('profile_type', 'platform')->firstOrFail();
    $settings = app(SettingService::class)->getSettingValues(SettingTypeEnum::WATCH_AND_BUY());

    expect($profile->username)->toBe('hyperlocal')
        ->and($settings)->toMatchArray([
            'enabled' => true,
            'title' => 'Watch & Buy',
            'maxDurationSeconds' => 60,
            'maxUploadMb' => 50,
            'maxProductsPerReel' => 10,
            'statusesEnabled' => true,
            'maxStatusDurationSeconds' => 15,
            'maxProductsPerStatus' => 5,
        ]);
});

it('initializes the platform profile and settings idempotently', function () {
    $migration = require database_path('migrations/2026_08_26_000003_initialize_watch_buy_data.php');

    $migration->up();
    $migration->up();

    expect(WatchBuyProfile::where('profile_type', 'platform')->where('username', 'hyperlocal')->count())->toBe(1)
        ->and(Setting::where('variable', SettingTypeEnum::WATCH_AND_BUY())->count())->toBe(1);
});

it('merges missing watch and buy setting keys with defaults without overwriting stored values', function () {
    Setting::where('variable', SettingTypeEnum::WATCH_AND_BUY())->update([
        'value' => json_encode(['title' => 'Local Picks', 'maxUploadMb' => 75], JSON_THROW_ON_ERROR),
    ]);
    app(SettingService::class)->clearSettingCache(SettingTypeEnum::WATCH_AND_BUY());

    $settings = app(SettingService::class)->getSettingValues(SettingTypeEnum::WATCH_AND_BUY());

    expect($settings['title'])->toBe('Local Picks')
        ->and($settings['maxUploadMb'])->toBe(75)
        ->and($settings['enabled'])->toBeTrue()
        ->and($settings['statusesEnabled'])->toBeTrue()
        ->and($settings['maxProductsPerStatus'])->toBe(5);
});

it('renders and saves the authorized watch and buy settings page', function () {
    $admin = User::factory()->create(['access_panel' => GuardNameEnum::ADMIN->value]);
    $role = Role::findOrCreate(DefaultSystemRolesEnum::SUPER_ADMIN->value, GuardNameEnum::ADMIN->value);
    $admin->assignRole($role);

    $this->withoutMiddleware()
        ->actingAs($admin, GuardNameEnum::ADMIN->value)
        ->get(route('admin.settings.show', ['setting' => SettingTypeEnum::WATCH_AND_BUY()]))
        ->assertOk()
        ->assertSee(__('labels.watch_buy_settings'))
        ->assertSee(
            'href="'.route('admin.settings.show', ['setting' => SettingTypeEnum::WATCH_AND_BUY()]).'"',
            false,
        );

    $this->withoutMiddleware()
        ->actingAs($admin, GuardNameEnum::ADMIN->value)
        ->postJson(route('admin.settings.store'), [
            'type' => SettingTypeEnum::WATCH_AND_BUY(),
            'enabled' => false,
            'title' => 'Watch Local',
            'maxDurationSeconds' => 90,
            'maxUploadMb' => 75,
            'maxProductsPerReel' => 8,
            'statusesEnabled' => true,
            'maxStatusDurationSeconds' => 20,
            'maxProductsPerStatus' => 4,
        ])
        ->assertOk()
        ->assertJsonPath('success', true);

    $saved = Setting::where('variable', SettingTypeEnum::WATCH_AND_BUY())->firstOrFail()->value;
    expect($saved)->toMatchArray([
        'enabled' => false,
        'title' => 'Watch Local',
        'maxDurationSeconds' => 90,
        'maxUploadMb' => 75,
        'maxProductsPerReel' => 8,
        'statusesEnabled' => true,
        'maxStatusDurationSeconds' => 20,
        'maxProductsPerStatus' => 4,
    ]);
});

it('rejects invalid watch and buy settings without changing stored values', function () {
    $admin = User::factory()->create(['access_panel' => GuardNameEnum::ADMIN->value]);
    $role = Role::findOrCreate(DefaultSystemRolesEnum::SUPER_ADMIN->value, GuardNameEnum::ADMIN->value);
    $admin->assignRole($role);
    $original = Setting::where('variable', SettingTypeEnum::WATCH_AND_BUY())->firstOrFail()->value;

    $this->withoutMiddleware()
        ->actingAs($admin, GuardNameEnum::ADMIN->value)
        ->postJson(route('admin.settings.store'), [
            'type' => SettingTypeEnum::WATCH_AND_BUY(),
            'enabled' => true,
            'title' => '',
            'maxDurationSeconds' => 4,
            'maxUploadMb' => 501,
            'maxProductsPerReel' => 0,
            'statusesEnabled' => true,
            'maxStatusDurationSeconds' => 61,
            'maxProductsPerStatus' => 11,
        ])
        ->assertOk()
        ->assertJsonPath('success', false)
        ->assertJsonStructure([
            'data' => [
                'title',
                'maxDurationSeconds',
                'maxUploadMb',
                'maxProductsPerReel',
                'maxStatusDurationSeconds',
                'maxProductsPerStatus',
            ],
        ]);

    expect(Setting::where('variable', SettingTypeEnum::WATCH_AND_BUY())->firstOrFail()->value)->toBe($original);
});

it('registers reference-compatible permissions without reel or story viewer permissions', function () {
    expect(AdminPermissionEnum::SETTING_WATCH_AND_BUY_VIEW())->toBe('setting.watch_and_buy.view')
        ->and(AdminPermissionEnum::WATCH_BUY_REEL_VIEW())->toBe('watch_buy.reel.view')
        ->and(SellerPermissionEnum::WATCH_BUY_REEL_PUBLISH())->toBe('watch_buy.reel.publish')
        ->and(SellerPermissionEnum::WATCH_BUY_STATUS_VIEW())->toBe('watch_buy.status.view')
        ->and(WatchBuySourceEnum::SELLER_BEHALF())->toBe('seller_behalf')
        ->and(AdminPermissionEnum::groupedPermissions())->toHaveKeys([
            'setting_watch_and_buy',
            'watch_buy_profile',
            'watch_buy_reel',
            'watch_buy_status',
        ])
        ->and(SellerPermissionEnum::groupedPermissions())->toHaveKeys([
            'watch_buy_profile',
            'watch_buy_reel',
            'watch_buy_status',
        ]);
});

it('registers the permission-aware watch and buy settings sidebar entry', function () {
    expect(config('menu.admin.settings.route.watch_and_buy'))->toBe([
        'sub_active' => 'watch_and_buy',
        'sub_route' => 'admin.settings.show',
        'route_param' => ['setting' => 'watch_and_buy'],
        'sub_title' => 'labels.watch_buy_settings',
        'permission' => 'setting.watch_and_buy.view',
    ]);
});

it('creates reel and status identifiers and exposes their relationships', function () {
    $profile = WatchBuyProfile::factory()->create();
    $reel = WatchBuyReel::factory()->for($profile, 'profile')->create();
    $status = WatchBuyStatus::factory()->for($profile, 'profile')->create();

    expect(Str::isUuid($reel->uuid))->toBeTrue()
        ->and($reel->slug)->toHaveLength(12)
        ->and(Str::isUuid($status->uuid))->toBeTrue()
        ->and($profile->reels()->whereKey($reel->id)->exists())->toBeTrue()
        ->and($profile->statuses()->whereKey($status->id)->exists())->toBeTrue()
        ->and($profile->seller->watchBuyProfile->is($profile))->toBeTrue();
});

it('enforces unique profiles likes and viewer identities', function () {
    $profile = WatchBuyProfile::factory()->create();
    $reel = WatchBuyReel::factory()->for($profile, 'profile')->create();
    $status = WatchBuyStatus::factory()->for($profile, 'profile')->create();
    $viewer = User::factory()->create();
    $viewerKey = hash('sha256', 'viewer:test');

    WatchBuyReelLike::create(['reel_id' => $reel->id, 'user_id' => $viewer->id]);
    expect(fn () => WatchBuyReelLike::create(['reel_id' => $reel->id, 'user_id' => $viewer->id]))
        ->toThrow(QueryException::class);

    WatchBuyStatusView::create([
        'status_id' => $status->id,
        'user_id' => $viewer->id,
        'seen_at' => now(),
    ]);
    expect(fn () => WatchBuyStatusView::create([
        'status_id' => $status->id,
        'user_id' => $viewer->id,
        'seen_at' => now(),
    ]))->toThrow(QueryException::class);

    expect(fn () => WatchBuyProfile::factory()->create(['seller_id' => $profile->seller_id]))
        ->toThrow(QueryException::class);
});

it('cascades status seen records when a customer account is removed', function () {
    $reel = WatchBuyReel::factory()->create();
    $status = WatchBuyStatus::factory()->for($reel->profile, 'profile')->create();
    $viewer = User::factory()->create();

    $statusView = WatchBuyStatusView::create([
        'status_id' => $status->id,
        'user_id' => $viewer->id,
        'seen_at' => now(),
    ]);

    $viewer->forceDelete();

    expect(WatchBuyStatusView::where('id', $statusView->id)->exists())->toBeFalse();
});

it('restricts creator profile deletion while preserving attachments after store deletion', function () {
    $reel = WatchBuyReel::factory()->create();
    $product = Product::factory()->create();
    $variant = ProductVariant::create([
        'uuid' => (string) Str::uuid(),
        'product_id' => $product->id,
        'title' => 'Default',
        'barcode' => Str::random(12),
        'availability' => true,
        'visibility' => 'published',
        'is_default' => true,
    ]);
    $store = Store::factory()->create(['seller_id' => $product->seller_id]);
    $attachment = WatchBuyReelProduct::create([
        'reel_id' => $reel->id,
        'product_id' => $product->id,
        'product_variant_id' => $variant->id,
        'store_id' => $store->id,
        'sort_order' => 1,
        'is_primary' => true,
    ]);

    expect(fn () => $reel->profile->delete())->toThrow(QueryException::class);

    $store->forceDelete();

    expect($attachment->fresh()->store_id)->toBeNull();
});

it('cascades reel and status dependants without removing moderation history', function () {
    $reel = WatchBuyReel::factory()->create();
    $status = WatchBuyStatus::factory()->for($reel->profile, 'profile')->create();
    $viewer = User::factory()->create();

    WatchBuyReelLike::create(['reel_id' => $reel->id, 'user_id' => $viewer->id]);
    WatchBuyStatusView::create([
        'status_id' => $status->id,
        'user_id' => $viewer->id,
        'seen_at' => now(),
    ]);
    $moderationLog = $reel->moderationLogs()->create([
        'action' => 'delete',
        'previous_status' => 'published',
        'metadata' => ['slug' => $reel->slug],
    ]);

    expect($moderationLog->target->is($reel))->toBeTrue();

    $reel->delete();
    $status->delete();

    expect(WatchBuyReelLike::count())->toBe(0)
        ->and(WatchBuyStatusView::count())->toBe(0)
        ->and(WatchBuyModerationLog::count())->toBe(1);
});

it('registers single file watch and buy media collections', function () {
    $profileCollections = (new WatchBuyProfile)->getRegisteredMediaCollections()->keyBy('name');
    $reelCollections = (new WatchBuyReel)->getRegisteredMediaCollections()->keyBy('name');
    $statusCollections = (new WatchBuyStatus)->getRegisteredMediaCollections()->keyBy('name');

    expect($profileCollections)->toHaveKey(SpatieMediaCollectionName::WATCH_BUY_PROFILE_PHOTO())
        ->and($profileCollections[SpatieMediaCollectionName::WATCH_BUY_PROFILE_PHOTO()]->singleFile)->toBeTrue()
        ->and($reelCollections)->toHaveKeys([
            SpatieMediaCollectionName::WATCH_BUY_REEL_VIDEO(),
            SpatieMediaCollectionName::WATCH_BUY_REEL_COVER(),
        ])
        ->and($statusCollections)->toHaveKey(SpatieMediaCollectionName::WATCH_BUY_STATUS_MEDIA());
});

it('exposes management routes as well as customer watch and buy routes', function () {
    expect(Route::has('watch-and-buy.config'))->toBeTrue()
        ->and(Route::has('watch-and-buy.reels.feed'))->toBeTrue()
        ->and(Route::has('watch-and-buy.statuses.feed'))->toBeTrue()
        ->and(Route::has('admin.watch-and-buy.reels.index'))->toBeTrue()
        ->and(Route::has('admin.watch-and-buy.statuses.index'))->toBeTrue()
        ->and(Route::has('seller.watch-and-buy.reels.index'))->toBeTrue()
        ->and(Route::has('seller.api.watch-and-buy.reels.index'))->toBeTrue();
});

it('declares getid3 as a production dependency', function () {
    expect(composerInstalled('james-heinrich/getid3'))->toBeTrue()
        ->and(class_exists(getID3::class))->toBeTrue();
});

function composerInstalled(string $package): bool
{
    $composer = json_decode(file_get_contents(base_path('composer.json')), true, flags: JSON_THROW_ON_ERROR);

    return array_key_exists($package, $composer['require'] ?? []);
}
