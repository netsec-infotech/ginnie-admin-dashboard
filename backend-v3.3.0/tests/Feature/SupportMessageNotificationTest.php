<?php

use App\Broadcasting\FirebaseChannel;
use App\Enums\AdminPermissionEnum;
use App\Enums\DefaultSystemRolesEnum;
use App\Enums\DeviceTypeEnum;
use App\Enums\GuardNameEnum;
use App\Enums\Notification\NotificationRoleTypeEnum;
use App\Events\Support\SupportActivityCreated;
use App\Http\Resources\NotificationResource;
use App\Models\Notification as DatabaseNotification;
use App\Models\SupportTicketMessage;
use App\Models\SupportTicketType;
use App\Models\User;
use App\Models\UserFcmToken;
use App\Notifications\SupportAdminMessageReceived;
use App\Notifications\SupportMessageReceived;
use App\Services\FirebaseService;
use App\Services\Support\SupportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

it('notifies the customer once for a newly committed agent message', function () {
    Notification::fake();

    $customer = User::factory()->create();
    $agent = User::factory()->create(['access_panel' => GuardNameEnum::ADMIN()]);
    $topic = SupportTicketType::where('is_active', true)->firstOrFail();
    $support = app(SupportService::class);
    $ticket = $support->startSession($customer, [
        'topic_id' => $topic->id,
        'message' => 'I need help.',
        'client_message_id' => fake()->uuid(),
    ]);
    $support->assign($ticket, $agent, $agent);
    $clientMessageId = fake()->uuid();

    $message = $support->addMessage($ticket->fresh(), $agent, 'admin', [
        'message' => 'We are checking this for you.',
        'client_message_id' => $clientMessageId,
    ]);

    Notification::assertSentTo(
        $customer,
        SupportMessageReceived::class,
        function (SupportMessageReceived $notification) use ($customer, $message, $ticket): bool {
            $payload = $notification->toFirebase($customer);

            return $payload['data']['type'] === 'support_message'
                && $payload['data']['role_type'] === 'customer'
                && $payload['data']['thread_uuid'] === $ticket->thread->uuid
                && $payload['data']['ticket_slug'] === $ticket->slug
                && $payload['data']['message_id'] === (string) $message->id;
        },
    );

    $support->addMessage($ticket->fresh(), $agent, 'admin', [
        'message' => 'Duplicate retry must not notify.',
        'client_message_id' => $clientMessageId,
    ]);
    Notification::assertSentTimes(SupportMessageReceived::class, 1);
});

it('does not send a customer support push for customer messages', function () {
    Notification::fake();

    $customer = User::factory()->create();
    $topic = SupportTicketType::where('is_active', true)->firstOrFail();
    $support = app(SupportService::class);
    $ticket = $support->startSession($customer, [
        'topic_id' => $topic->id,
        'message' => 'Initial question.',
        'client_message_id' => fake()->uuid(),
    ]);

    $support->addMessage($ticket, $customer, 'user', [
        'message' => 'More information.',
        'client_message_id' => fake()->uuid(),
    ]);

    Notification::assertNothingSent();
});

it('notifies only the assigned support executive for a customer message', function () {
    Notification::fake();

    $customer = User::factory()->create();
    $topic = SupportTicketType::where('is_active', true)->firstOrFail();
    $support = app(SupportService::class);
    $ticket = $support->startSession($customer, [
        'topic_id' => $topic->id,
        'message' => 'Initial question.',
        'client_message_id' => fake()->uuid(),
    ]);
    $permission = Permission::findOrCreate(AdminPermissionEnum::SUPPORT_VIEW(), GuardNameEnum::ADMIN());
    $assigned = User::factory()->create(['access_panel' => GuardNameEnum::ADMIN()]);
    $assigned->givePermissionTo($permission);
    $other = User::factory()->create(['access_panel' => GuardNameEnum::ADMIN()]);
    $other->givePermissionTo($permission);
    $support->assign($ticket, $assigned, $assigned);
    $clientMessageId = fake()->uuid();

    $message = $support->addMessage($ticket->fresh(), $customer, 'user', [
        'message' => 'This should reach my assigned executive.',
        'client_message_id' => $clientMessageId,
    ]);

    Notification::assertSentTo(
        $assigned,
        SupportAdminMessageReceived::class,
        function (SupportAdminMessageReceived $notification) use ($assigned, $message, $ticket): bool {
            $firebase = $notification->toFirebase($assigned);
            $database = $notification->toDatabase($assigned);
            $target = route('admin.support.index', ['thread' => $ticket->thread->uuid, 'scope' => 'all']);

            return $firebase['data']['role_type'] === NotificationRoleTypeEnum::ADMIN()
                && $firebase['data']['message_id'] === (string) $message->id
                && $firebase['data']['assigned_to'] === (string) $assigned->id
                && $firebase['data']['click_action'] === $target
                && $database['metadata']['click_action'] === $target;
        },
    );
    Notification::assertNotSentTo($other, SupportAdminMessageReceived::class);
    Notification::assertNotSentTo($customer, SupportAdminMessageReceived::class);
    expect((new SupportActivityCreated(
        $ticket->thread_id,
        $ticket->id,
        'message.created',
        $message->id,
    ))->broadcastWith()['assigned_to'])->toBe($assigned->id);

    $support->addMessage($ticket->fresh(), $customer, 'user', [
        'message' => 'An idempotent retry.',
        'client_message_id' => $clientMessageId,
    ]);

    Notification::assertSentTimes(SupportAdminMessageReceived::class, 1);
});

it('notifies every eligible active support executive for an unassigned session', function () {
    Notification::fake();

    $permission = Permission::findOrCreate(AdminPermissionEnum::SUPPORT_VIEW(), GuardNameEnum::ADMIN());
    $supportAgent = User::factory()->create(['access_panel' => GuardNameEnum::ADMIN()]);
    $supportAgent->givePermissionTo($permission);
    $superAdmin = User::factory()->create(['access_panel' => GuardNameEnum::ADMIN()]);
    $superAdmin->assignRole(Role::findOrCreate(DefaultSystemRolesEnum::SUPER_ADMIN(), GuardNameEnum::ADMIN()));
    $inactiveAgent = User::factory()->create(['access_panel' => GuardNameEnum::ADMIN(), 'status' => 'inactive']);
    $inactiveAgent->givePermissionTo($permission);
    $unauthorizedAdmin = User::factory()->create(['access_panel' => GuardNameEnum::ADMIN()]);
    $seller = User::factory()->create(['access_panel' => GuardNameEnum::SELLER()]);
    $customer = User::factory()->create();
    $topic = SupportTicketType::where('is_active', true)->firstOrFail();

    app(SupportService::class)->startSession($customer, [
        'topic_id' => $topic->id,
        'message' => 'An unassigned issue.',
        'client_message_id' => fake()->uuid(),
    ]);

    Notification::assertSentTo($supportAgent, SupportAdminMessageReceived::class);
    Notification::assertSentTo($superAdmin, SupportAdminMessageReceived::class);
    Notification::assertNotSentTo($inactiveAgent, SupportAdminMessageReceived::class);
    Notification::assertNotSentTo($unauthorizedAdmin, SupportAdminMessageReceived::class);
    Notification::assertNotSentTo($seller, SupportAdminMessageReceived::class);
    Notification::assertNotSentTo($customer, SupportAdminMessageReceived::class);
    Notification::assertSentTimes(SupportAdminMessageReceived::class, 2);
});

it('resolves an admin support notification to its exact conversation', function () {
    $customer = User::factory()->create();
    $topic = SupportTicketType::where('is_active', true)->firstOrFail();
    $ticket = app(SupportService::class)->startSession($customer, [
        'topic_id' => $topic->id,
        'message' => 'Open this conversation.',
        'client_message_id' => fake()->uuid(),
    ]);
    $notification = new DatabaseNotification([
        'data' => [
            'type' => 'support_message',
            'sent_to' => 'admin',
            'role_type' => 'admin',
            'metadata' => ['thread_uuid' => $ticket->thread->uuid],
        ],
    ]);

    $target = route('admin.support.index', ['thread' => $ticket->thread->uuid, 'scope' => 'all']);

    expect($notification->getRedirectUrlForPanel('admin'))->toBe($target)
        ->and((new NotificationResource($notification))->toArray(request())['redirect_url'])->toBe($target);
});

it('delivers support pushes to customer native tokens only', function () {
    $customer = User::factory()->create();
    $topic = SupportTicketType::where('is_active', true)->firstOrFail();
    $ticket = app(SupportService::class)->startSession($customer, [
        'topic_id' => $topic->id,
        'message' => 'Initial question.',
        'client_message_id' => fake()->uuid(),
    ]);
    $message = SupportTicketMessage::create([
        'ticket_id' => $ticket->id,
        'user_id' => $customer->id,
        'send_by' => 'admin',
        'type' => 'text',
        'message' => 'A support reply.',
        'client_message_id' => fake()->uuid(),
    ]);
    UserFcmToken::create([
        'user_id' => $customer->id,
        'fcm_token' => 'customer-android-token',
        'device_type' => DeviceTypeEnum::ANDROID,
        'role_type' => NotificationRoleTypeEnum::CUSTOMER,
    ]);
    UserFcmToken::create([
        'user_id' => $customer->id,
        'fcm_token' => 'customer-web-token',
        'device_type' => DeviceTypeEnum::WEB,
        'role_type' => NotificationRoleTypeEnum::CUSTOMER,
    ]);

    $firebase = new class extends FirebaseService
    {
        /** @var array<int, array<int, string>> */
        public array $sentTokenGroups = [];

        public function __construct() {}

        public function sendBulkNotification(
            array $tokens,
            string $title,
            string $body,
            ?string $image = null,
            array $data = [],
            int $chunkSize = 50,
            ?array $android = null,
            ?array $apns = null,
        ): array {
            $this->sentTokenGroups[] = $tokens;

            return ['success' => count($tokens), 'failure' => 0];
        }
    };

    (new FirebaseChannel($firebase))->send(
        $customer,
        new SupportMessageReceived($message),
    );

    expect($firebase->sentTokenGroups)->toBe([['customer-android-token']]);
});

it('delivers admin support pushes only to admin browser tokens', function () {
    $customer = User::factory()->create();
    $admin = User::factory()->create(['access_panel' => GuardNameEnum::ADMIN()]);
    $topic = SupportTicketType::where('is_active', true)->firstOrFail();
    $ticket = app(SupportService::class)->startSession($customer, [
        'topic_id' => $topic->id,
        'message' => 'Initial question.',
        'client_message_id' => fake()->uuid(),
    ]);
    $message = $ticket->messages()->latest('id')->firstOrFail();
    UserFcmToken::create([
        'user_id' => $admin->id,
        'fcm_token' => 'support-admin-web-token',
        'device_type' => DeviceTypeEnum::WEB,
        'role_type' => NotificationRoleTypeEnum::ADMIN,
    ]);
    UserFcmToken::create([
        'user_id' => $admin->id,
        'fcm_token' => 'seller-web-token',
        'device_type' => DeviceTypeEnum::WEB,
        'role_type' => NotificationRoleTypeEnum::SELLER,
    ]);

    $firebase = new class extends FirebaseService
    {
        public array $sentTokenGroups = [];

        public function __construct() {}

        public function sendBulkNotification(
            array $tokens,
            string $title,
            string $body,
            ?string $image = null,
            array $data = [],
            int $chunkSize = 50,
            ?array $android = null,
            ?array $apns = null,
        ): array {
            $this->sentTokenGroups[] = $tokens;

            return ['success' => count($tokens), 'failure' => 0];
        }
    };

    (new FirebaseChannel($firebase))->send(
        $admin,
        new SupportAdminMessageReceived($message, null),
    );

    expect($firebase->sentTokenGroups)->toBe([['support-admin-web-token']]);
});
