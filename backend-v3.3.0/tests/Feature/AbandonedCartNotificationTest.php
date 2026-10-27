<?php

use App\Broadcasting\FirebaseChannel;
use App\Enums\NotificationTypeEnum;
use App\Models\Cart;
use App\Models\CartItem;
use App\Models\Product;
use App\Models\Setting;
use App\Models\Store;
use App\Models\User;
use App\Notifications\AbandonedCartNotification;
use App\Notifications\Channels\CustomSmsChannel;
use App\Notifications\Channels\RoleAwareDatabaseChannel;
use App\Services\AbandonedCartSettings;
use App\Services\CartActivityService;
use App\Services\SettingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification as NotificationFacade;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

function abandonedCartFor(User $user, bool $saveForLater = false): Cart
{
    $store = Store::factory()->create();
    $product = Product::factory()->create(['seller_id' => $store->seller_id]);
    $variantId = DB::table('product_variants')->insertGetId([
        'uuid' => fake()->uuid(),
        'product_id' => $product->id,
        'title' => 'Default Variant',
        'slug' => 'abandoned-'.Str::random(12),
        'weight' => 1,
        'height' => 1,
        'breadth' => 1,
        'length' => 1,
        'availability' => true,
        'provider' => 'self',
        'barcode' => Str::random(12),
        'visibility' => 'published',
        'is_default' => true,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $cart = Cart::query()->create([
        'uuid' => (string) Str::uuid(),
        'user_id' => $user->id,
    ]);

    CartItem::query()->create([
        'cart_id' => $cart->id,
        'product_id' => $product->id,
        'product_variant_id' => $variantId,
        'store_id' => $store->id,
        'quantity' => 1,
        'save_for_later' => $saveForLater,
    ]);

    return $cart;
}

function enableAbandonedCartNotifications(array $overrides = []): void
{
    Setting::query()->updateOrCreate(
        ['variable' => 'notification'],
        ['value' => json_encode(array_merge([
            'enableAbandonedCartNotification' => true,
            'abandonedCartNotificationDelayMinutes' => 60,
            'abandonedCartNotificationChannels' => ['push'],
        ], $overrides))]
    );

    app(SettingService::class)->clearSettingCache('notification');
}

it('starts and resets the abandonment cycle when active cart contents change', function () {
    $cart = abandonedCartFor(User::factory()->create());
    $activity = app(CartActivityService::class);
    $cart->refresh();

    expect($cart->last_activity_at)->toBeNull()
        ->and($cart->activity_version)->toBe(0);

    $activity->record($cart);
    $cart->refresh();

    expect($cart->last_activity_at)->not->toBeNull()
        ->and($cart->activity_version)->toBe(1)
        ->and($cart->abandoned_cart_notified_at)->toBeNull();

    $cart->forceFill(['abandoned_cart_notified_at' => now()])->save();
    $activity->record($cart);
    $cart->refresh();

    expect($cart->activity_version)->toBe(2)
        ->and($cart->abandoned_cart_notified_at)->toBeNull();
});

it('does not start an abandonment timer for a save-for-later-only cart', function () {
    $cart = abandonedCartFor(User::factory()->create(), saveForLater: true);

    app(CartActivityService::class)->record($cart);

    expect($cart->fresh()->last_activity_at)->toBeNull();
});

it('queues only one reminder for an unchanged eligible cart', function () {
    NotificationFacade::fake();
    enableAbandonedCartNotifications();

    $user = User::factory()->create();
    $cart = abandonedCartFor($user);
    $cart->forceFill([
        'last_activity_at' => now()->subMinutes(61),
        'activity_version' => 1,
    ])->save();

    $this->artisan('carts:send-abandoned-notifications')->assertSuccessful();
    $this->artisan('carts:send-abandoned-notifications')->assertSuccessful();

    NotificationFacade::assertSentToTimes($user, AbandonedCartNotification::class, 1);
    expect($cart->fresh()->abandoned_cart_notified_at)->not->toBeNull();
});

it('excludes old untouched carts and carts inside the configured delay', function () {
    NotificationFacade::fake();
    enableAbandonedCartNotifications();

    $untouchedUser = User::factory()->create();
    abandonedCartFor($untouchedUser);

    $recentUser = User::factory()->create();
    $recentCart = abandonedCartFor($recentUser);
    $recentCart->forceFill([
        'last_activity_at' => now()->subMinutes(59),
        'activity_version' => 1,
    ])->save();

    NotificationFacade::fake();

    $this->artisan('carts:send-abandoned-notifications')->assertSuccessful();

    NotificationFacade::assertNothingSent();
});

it('suppresses a queued reminder after the cart cycle changes', function () {
    enableAbandonedCartNotifications();

    $user = User::factory()->create();
    $cart = abandonedCartFor($user);
    $cart->forceFill([
        'last_activity_at' => now()->subMinutes(61),
        'activity_version' => 3,
        'abandoned_cart_notified_at' => now(),
    ])->save();

    $notification = new AbandonedCartNotification($cart->id, $cart->uuid, 3);
    expect($notification->shouldSend($user, RoleAwareDatabaseChannel::class))->toBeTrue();

    app(CartActivityService::class)->record($cart);

    expect($notification->shouldSend($user, RoleAwareDatabaseChannel::class))->toBeFalse();
});

it('always delivers the in-app notification when optional channels are empty', function () {
    enableAbandonedCartNotifications([
        'abandonedCartNotificationChannels' => [],
    ]);

    $user = User::factory()->create();
    $cart = abandonedCartFor($user);
    $cart->forceFill([
        'last_activity_at' => now()->subMinutes(61),
        'activity_version' => 2,
        'abandoned_cart_notified_at' => now(),
    ])->save();

    $notification = new AbandonedCartNotification($cart->id, $cart->uuid, 2);

    expect($notification->via($user))->toBe([RoleAwareDatabaseChannel::class]);

    NotificationFacade::sendNow($user, $notification);

    $storedNotification = $user->notifications()->sole();

    expect($storedNotification->data)
        ->toMatchArray([
            'type' => NotificationTypeEnum::ABANDONED_CART(),
            'sent_to' => 'customer',
            'user_id' => $user->id,
        ])
        ->and($storedNotification->data['metadata'])
        ->toMatchArray([
            'cart_id' => (string) $cart->id,
            'cart_uuid' => $cart->uuid,
            'activity_version' => '2',
            'click_action' => 'cart',
        ]);
});

it('suppresses queued delivery when the feature or customer is disabled', function () {
    enableAbandonedCartNotifications();

    $user = User::factory()->create();
    $cart = abandonedCartFor($user);
    $cart->forceFill([
        'last_activity_at' => now()->subMinutes(61),
        'activity_version' => 1,
        'abandoned_cart_notified_at' => now(),
    ])->save();

    $notification = new AbandonedCartNotification($cart->id, $cart->uuid, 1);

    enableAbandonedCartNotifications(['enableAbandonedCartNotification' => false]);
    expect($notification->shouldSend($user, RoleAwareDatabaseChannel::class))->toBeFalse();

    enableAbandonedCartNotifications();
    User::query()->whereKey($user->id)->update(['status' => 'inactive']);

    expect($notification->shouldSend($user->fresh(), RoleAwareDatabaseChannel::class))->toBeFalse();
});

it('uses all configured channels but requires verified email and mobile contacts', function () {
    $settings = new class extends AbandonedCartSettings
    {
        public function __construct() {}

        public function hasChannel(string $channel): bool
        {
            return true;
        }

        public function channelIsAvailable(string $channel): bool
        {
            return true;
        }
    };
    app()->instance(AbandonedCartSettings::class, $settings);

    $notification = new AbandonedCartNotification(1, (string) Str::uuid(), 1);
    $verified = User::factory()->make();
    $unverified = User::factory()->unverified()->make();

    expect($notification->via($verified))->toBe([
        RoleAwareDatabaseChannel::class,
        FirebaseChannel::class,
        'mail',
        CustomSmsChannel::class,
    ])->and($notification->via($unverified))->toBe([
        RoleAwareDatabaseChannel::class,
        FirebaseChannel::class,
    ]);
});

it('builds the customer abandoned cart payload', function () {
    $settings = new class extends AbandonedCartSettings
    {
        public function __construct() {}

        public function customerWebCartUrl(): ?string
        {
            return 'https://shop.example/cart';
        }

        public function customerAppCartDeepLink(): ?string
        {
            return 'hypercommerce://cart';
        }
    };
    app()->instance(AbandonedCartSettings::class, $settings);

    $user = User::factory()->make(['id' => 7]);
    $notification = new AbandonedCartNotification(10, 'cart-uuid', 4);
    $payload = $notification->toDatabase($user);

    expect($payload['type'])->toBe(NotificationTypeEnum::ABANDONED_CART())
        ->and($payload['sent_to'])->toBe('customer')
        ->and($payload['metadata'])->toMatchArray([
            'type' => NotificationTypeEnum::ABANDONED_CART(),
            'cart_id' => '10',
            'cart_uuid' => 'cart-uuid',
            'activity_version' => '4',
            'click_action' => 'cart',
            'deep_link' => 'hypercommerce://cart',
            'web_url' => 'https://shop.example/cart',
        ]);
});

it('retries complete push delivery failures without retrying partial delivery', function () {
    $notification = new AbandonedCartNotification(10, 'cart-uuid', 4);
    $user = User::factory()->make();

    expect(fn () => $notification->afterSending($user, FirebaseChannel::class, [
        'success' => 0,
        'failure' => 1,
        'error' => 'Firebase unavailable',
    ]))->toThrow(RuntimeException::class, 'Firebase unavailable');

    $notification->afterSending($user, FirebaseChannel::class, [
        'success' => 1,
        'failure' => 1,
    ]);

    expect(true)->toBeTrue();
});
