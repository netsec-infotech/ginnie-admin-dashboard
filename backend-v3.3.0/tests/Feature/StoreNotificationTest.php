<?php

use App\Console\Commands\SendStoreOpeningNotificationsCommand;
use App\Enums\Store\StoreStatusEnum;
use App\Jobs\SendStoreBackOnlineNotificationJob;
use App\Models\AvailabilityNotification;
use App\Models\DeliveryZone;
use App\Models\Seller;
use App\Models\Store;
use App\Models\User;
use App\Notifications\StoreBackOnlineNotification;
use Illuminate\Contracts\Notifications\Dispatcher;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->withoutMiddleware([\App\Http\Middleware\VerifyLicense::class]);

    $this->user = User::factory()->create();
    $this->seller = Seller::factory()->create();

    $this->store = Store::factory()->create([
        'seller_id' => $this->seller->id,
        'latitude' => 23.2420,
        'longitude' => 69.6669,
        'status' => StoreStatusEnum::OFFLINE(),
        'verification_status' => \App\Enums\Store\StoreVerificationStatusEnum::APPROVED(),
        'visibility_status' => \App\Enums\Store\StoreVisibilityStatusEnum::VISIBLE(),
    ]);

    $zone = DeliveryZone::factory()->create([
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
});

it('rejects unauthenticated store notify-me requests with 401', function () {
    $responsePost = $this->postJson("/api/stores/{$this->store->id}/notify-me");
    $responsePost->assertStatus(401);

    $responseGet = $this->getJson("/api/stores/{$this->store->slug}?latitude=23.2420&longitude=69.6669&is_notify=1");
    $responseGet->assertStatus(401);
});

it('subscribes and unsubscribes via POST /api/stores/{id}/notify-me for authenticated users', function () {
    // 1. Subscribe
    $responseSubscribe = $this->actingAs($this->user, 'sanctum')
        ->postJson("/api/stores/{$this->store->id}/notify-me", ['is_notify' => true]);

    $responseSubscribe->assertStatus(200)
        ->assertJson([
            'success' => true,
            'data' => [
                'store_id' => $this->store->id,
                'is_notify' => true,
            ],
        ]);

    $exists = AvailabilityNotification::where('user_id', $this->user->id)
        ->where('subscribable_type', 'store')
        ->where('subscribable_id', $this->store->id)
        ->where('status', 'pending')
        ->exists();
    expect($exists)->toBeTrue();

    $this->actingAs($this->user, 'sanctum')
        ->postJson("/api/stores/{$this->store->id}/notify-me", ['is_notify' => true])
        ->assertSuccessful();

    expect(AvailabilityNotification::query()
        ->where('user_id', $this->user->id)
        ->where('subscribable_type', 'store')
        ->where('subscribable_id', $this->store->id)
        ->count())->toBe(1);

    // Verify StoreResource returns is_notify: true
    $responseShow = $this->actingAs($this->user, 'sanctum')
        ->getJson("/api/stores/{$this->store->slug}?latitude=23.2420&longitude=69.6669");
    $responseShow->assertStatus(200);
    expect($responseShow->json('data.is_notify'))->toBeTrue();

    // 2. Unsubscribe explicitly
    $responseUnsubscribe = $this->actingAs($this->user, 'sanctum')
        ->postJson("/api/stores/{$this->store->id}/notify-me", ['is_notify' => false]);

    $responseUnsubscribe->assertStatus(200)
        ->assertJson([
            'success' => true,
            'data' => [
                'store_id' => $this->store->id,
                'is_notify' => false,
            ],
        ]);

    $existsAfter = AvailabilityNotification::where('user_id', $this->user->id)
        ->where('subscribable_type', 'store')
        ->where('subscribable_id', $this->store->id)
        ->where('status', 'pending')
        ->exists();
    expect($existsAfter)->toBeFalse();
});

it('rejects subscription with 422 if store is already online and open', function () {
    $this->store->update([
        'status' => StoreStatusEnum::ONLINE(),
    ]);

    $response = $this->actingAs($this->user, 'sanctum')
        ->postJson("/api/stores/{$this->store->id}/notify-me", ['is_notify' => true]);

    $response->assertStatus(422)->assertJsonValidationErrors(['is_notify']);
});

it('requires an explicit valid notification state for POST requests', function (mixed $value) {
    $response = $this->actingAs($this->user, 'sanctum')
        ->postJson("/api/stores/{$this->store->id}/notify-me", ['is_notify' => $value]);

    $response->assertStatus(422)->assertJsonValidationErrors(['is_notify']);
})->with([null, 'invalid', 2]);

it('subscribes and unsubscribes via GET /api/stores/{slug}?is_notify=1/0', function () {
    // 1. Subscribe via GET query param
    $response1 = $this->actingAs($this->user, 'sanctum')
        ->getJson("/api/stores/{$this->store->slug}?latitude=23.2420&longitude=69.6669&is_notify=1");

    $response1->assertStatus(200);
    expect($response1->json('data.is_notify'))->toBeTrue();

    $exists = AvailabilityNotification::where('user_id', $this->user->id)
        ->where('subscribable_type', 'store')
        ->where('subscribable_id', $this->store->id)
        ->where('status', 'pending')
        ->exists();
    expect($exists)->toBeTrue();

    // 2. Unsubscribe via GET is_notify=0
    $response2 = $this->actingAs($this->user, 'sanctum')
        ->getJson("/api/stores/{$this->store->slug}?latitude=23.2420&longitude=69.6669&is_notify=0");

    $response2->assertStatus(200);
    expect($response2->json('data.is_notify'))->toBeFalse();

    $existsAfter = AvailabilityNotification::where('user_id', $this->user->id)
        ->where('subscribable_type', 'store')
        ->where('subscribable_id', $this->store->id)
        ->where('status', 'pending')
        ->exists();
    expect($existsAfter)->toBeFalse();
});

it('rejects invalid legacy GET values without changing the subscription', function () {
    AvailabilityNotification::create([
        'user_id' => $this->user->id,
        'subscribable_type' => 'store',
        'subscribable_id' => $this->store->id,
        'store_id' => $this->store->id,
        'status' => 'pending',
    ]);

    $this->actingAs($this->user, 'sanctum')
        ->getJson("/api/stores/{$this->store->slug}?is_notify=invalid")
        ->assertStatus(422)
        ->assertJsonValidationErrors(['is_notify']);

    expect(AvailabilityNotification::query()
        ->where('user_id', $this->user->id)
        ->where('subscribable_type', 'store')
        ->where('subscribable_id', $this->store->id)
        ->exists())->toBeTrue();
});

it('triggers SendStoreBackOnlineNotificationJob and delivers notifications when store status changes to online', function () {
    Notification::fake();
    Queue::fake();

    AvailabilityNotification::create([
        'user_id' => $this->user->id,
        'subscribable_type' => 'store',
        'subscribable_id' => $this->store->id,
        'store_id' => $this->store->id,
        'status' => 'pending',
    ]);

    // Update store status to online -> Observer triggers job
    $this->store->update([
        'status' => StoreStatusEnum::ONLINE(),
    ]);

    Queue::assertPushed(SendStoreBackOnlineNotificationJob::class, fn ($job) => $job->storeId === $this->store->id);

    (new SendStoreBackOnlineNotificationJob($this->store->id))->handle();

    Notification::assertSentTo(
        $this->user,
        StoreBackOnlineNotification::class,
        function ($notification) {
            return $notification->store->id === $this->store->id;
        }
    );

    $remaining = AvailabilityNotification::where('user_id', $this->user->id)
        ->where('subscribable_type', 'store')
        ->where('subscribable_id', $this->store->id)
        ->first();

    expect($remaining)->toBeNull();
});

it('dispatches back-online jobs via stores:check-opening-notifications console command', function () {
    Notification::fake();
    Queue::fake();

    Store::withoutEvents(fn () => $this->store->update([
        'status' => StoreStatusEnum::ONLINE(),
    ]));

    AvailabilityNotification::create([
        'user_id' => $this->user->id,
        'subscribable_type' => 'store',
        'subscribable_id' => $this->store->id,
        'store_id' => $this->store->id,
        'status' => 'pending',
    ]);

    $this->artisan('stores:check-opening-notifications')
        ->assertSuccessful();

    Queue::assertPushed(SendStoreBackOnlineNotificationJob::class, fn ($job) => $job->storeId === $this->store->id);
});

it('accepts subscriptions while an online store is temporarily closed', function () {
    Store::withoutEvents(fn () => $this->store->update([
        'status' => StoreStatusEnum::ONLINE(),
        'is_temporarily_closed' => true,
    ]));

    $this->actingAs($this->user, 'sanctum')
        ->postJson("/api/stores/{$this->store->id}/notify-me", ['is_notify' => true])
        ->assertSuccessful();

    expect($this->store->fresh()->isAcceptingOrders())->toBeFalse()
        ->and(AvailabilityNotification::query()->where('user_id', $this->user->id)->exists())->toBeTrue();
});

it('does not dispatch while a store is temporarily closed or outside operating hours', function () {
    Queue::fake();

    AvailabilityNotification::create([
        'user_id' => $this->user->id,
        'subscribable_type' => 'store',
        'subscribable_id' => $this->store->id,
        'store_id' => $this->store->id,
        'status' => 'pending',
    ]);

    Store::withoutEvents(fn () => $this->store->update([
        'status' => StoreStatusEnum::ONLINE(),
        'is_temporarily_closed' => true,
    ]));

    $this->artisan('stores:check-opening-notifications')->assertSuccessful();
    Queue::assertNothingPushed();

    Store::withoutEvents(fn () => $this->store->update([
        'is_temporarily_closed' => false,
        'metadata' => [
            'operating_hours' => [
                strtolower(now()->format('l')) => ['is_open' => false],
            ],
        ],
    ]));

    $this->artisan('stores:check-opening-notifications')->assertSuccessful();
    Queue::assertNotPushed(SendStoreBackOnlineNotificationJob::class);
});

it('does not dispatch an observer job when manual online status is outside operating hours', function () {
    Queue::fake();

    $this->store->update([
        'metadata' => [
            'operating_hours' => [
                strtolower(now()->format('l')) => ['is_open' => false],
            ],
        ],
        'status' => StoreStatusEnum::ONLINE(),
    ]);

    Queue::assertNotPushed(SendStoreBackOnlineNotificationJob::class);
});

it('dispatches when a temporary closure is lifted', function () {
    Queue::fake();

    Store::withoutEvents(fn () => $this->store->update([
        'status' => StoreStatusEnum::ONLINE(),
        'is_temporarily_closed' => true,
    ]));

    $this->store->update(['is_temporarily_closed' => false]);

    Queue::assertPushed(SendStoreBackOnlineNotificationJob::class, fn ($job) => $job->storeId === $this->store->id);
});

it('uses a unique queue identity per store', function () {
    $job = new SendStoreBackOnlineNotificationJob($this->store->id);

    expect($job)->toBeInstanceOf(\Illuminate\Contracts\Queue\ShouldBeUnique::class)
        ->and($job->uniqueId())->toBe((string) $this->store->id);
});

it('retains the subscription for a queued retry when notification handoff fails', function () {
    Store::withoutEvents(fn () => $this->store->update(['status' => StoreStatusEnum::ONLINE()]));

    $subscription = AvailabilityNotification::create([
        'user_id' => $this->user->id,
        'subscribable_type' => 'store',
        'subscribable_id' => $this->store->id,
        'store_id' => $this->store->id,
        'status' => 'pending',
    ]);

    $dispatcher = \Mockery::mock(Dispatcher::class);
    $dispatcher->shouldReceive('send')->once()->andThrow(new RuntimeException('Queue unavailable'));
    app()->instance(Dispatcher::class, $dispatcher);

    expect(fn () => (new SendStoreBackOnlineNotificationJob($this->store->id))->handle())
        ->toThrow(RuntimeException::class, 'Queue unavailable');

    expect($subscription->fresh())->not->toBeNull();
});

it('suppresses queued delivery if the store is no longer accepting orders', function () {
    Store::withoutEvents(fn () => $this->store->update(['status' => StoreStatusEnum::ONLINE()]));
    $notification = new StoreBackOnlineNotification($this->store->fresh());

    Store::withoutEvents(fn () => $this->store->update(['status' => StoreStatusEnum::OFFLINE()]));

    expect($notification->shouldSend($this->user, \App\Notifications\Channels\RoleAwareDatabaseChannel::class))->toBeFalse();
});

it('skips inactive customers', function () {
    Notification::fake();
    Store::withoutEvents(fn () => $this->store->update(['status' => StoreStatusEnum::ONLINE()]));
    User::query()->whereKey($this->user->id)->update(['status' => 'inactive']);

    AvailabilityNotification::create([
        'user_id' => $this->user->id,
        'subscribable_type' => 'store',
        'subscribable_id' => $this->store->id,
        'store_id' => $this->store->id,
        'status' => 'pending',
    ]);

    (new SendStoreBackOnlineNotificationJob($this->store->id))->handle();

    Notification::assertNothingSent();
    expect(AvailabilityNotification::query()->where('user_id', $this->user->id)->exists())->toBeFalse();
});

it('builds store navigation metadata for database and push delivery', function () {
    $notification = new StoreBackOnlineNotification($this->store);
    $databasePayload = $notification->toDatabase($this->user);
    $pushPayload = $notification->toFirebase($this->user);

    expect($databasePayload['type'])->toBe(\App\Enums\NotificationTypeEnum::STORE_BACK_ONLINE())
        ->and($databasePayload['role_type'])->toBe(\App\Enums\Notification\NotificationRoleTypeEnum::CUSTOMER->value)
        ->and($databasePayload['metadata'])->toMatchArray([
            'store_id' => $this->store->id,
            'store_slug' => $this->store->slug,
            'click_action' => 'store',
        ])
        ->and($pushPayload['data'])->toMatchArray([
            'store_id' => (string) $this->store->id,
            'store_slug' => (string) $this->store->slug,
            'click_action' => 'store',
        ]);
});

it('loads store subscription state without per-resource availability queries', function () {
    Store::factory()->count(3)->create([
        'seller_id' => $this->seller->id,
        'status' => StoreStatusEnum::OFFLINE(),
        'verification_status' => \App\Enums\Store\StoreVerificationStatusEnum::APPROVED(),
        'visibility_status' => \App\Enums\Store\StoreVisibilityStatusEnum::VISIBLE(),
    ]);

    DB::flushQueryLog();
    DB::enableQueryLog();

    $this->actingAs($this->user, 'sanctum')->getJson('/api/stores')->assertSuccessful();

    $availabilityQueries = collect(DB::getQueryLog())->filter(
        fn (array $query): bool => str_contains(strtolower($query['query']), 'availability_notifications')
    );

    expect($availabilityQueries)->toHaveCount(1);
});
