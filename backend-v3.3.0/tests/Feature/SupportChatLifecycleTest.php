<?php

use App\Enums\DefaultSystemRolesEnum;
use App\Enums\GuardNameEnum;
use App\Events\Support\SupportTypingChanged;
use App\Http\Controllers\Admin\SupportController;
use App\Http\Middleware\EnsureSubscriptionFeatureSelected;
use App\Http\Middleware\EnsureSystemVendorTypeSelected;
use App\Http\Middleware\VerifyLicense;
use App\Models\Setting;
use App\Models\SupportThread;
use App\Models\SupportTicket;
use App\Models\SupportTicketMessage;
use App\Models\SupportTicketType;
use App\Models\User;
use App\Services\SettingService;
use App\Services\Support\SupportDashboardService;
use App\Services\Support\SupportPresenter;
use App\Services\Support\SupportService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

beforeEach(function () {
    File::deleteDirectory(storage_path('app/testing-support-chat'));
    File::ensureDirectoryExists(storage_path('app/testing-support-chat'));

    config([
        'broadcasting.default' => 'null',
        'support.availability.enabled' => false,
        'support.attachments_disk' => 'public',
        // Use a real local test disk because Spatie writes streams directly during media attachment tests.
        'filesystems.disks.public' => [
            'driver' => 'local',
            'root' => storage_path('app/testing-support-chat'),
            'url' => '/storage',
            'visibility' => 'public',
            'throw' => false,
        ],
    ]);
    $this->withoutMiddleware([
        VerifyLicense::class,
        EnsureSystemVendorTypeSelected::class,
        EnsureSubscriptionFeatureSelected::class,
    ]);
});

it('keeps typing traffic separate from the callback request limit', function () {
    $customer = User::factory()->create();
    $ticket = app(SupportService::class)->startSession($customer, [
        'topic_id' => SupportTicketType::where('is_active', true)->firstOrFail()->id,
        'message' => 'Please help',
        'client_message_id' => (string) \Illuminate\Support\Str::uuid(),
    ]);
    Sanctum::actingAs($customer);

    foreach (range(1, 6) as $attempt) {
        $this->postJson('/api/user/support/sessions/'.$ticket->slug.'/typing', ['typing' => true])->assertSuccessful();
    }
    foreach (range(1, 5) as $attempt) {
        $this->postJson('/api/user/support/sessions/'.$ticket->slug.'/callbacks')->assertCreated();
    }
    $this->postJson('/api/user/support/sessions/'.$ticket->slug.'/callbacks')
        ->assertStatus(429)->assertHeader('Retry-After');
    $this->postJson('/api/user/support/sessions/'.$ticket->slug.'/messages', [
        'message' => 'I can still send a message',
        'client_message_id' => (string) \Illuminate\Support\Str::uuid(),
    ])->assertCreated();
});

it('uses runtime socket health for the warning and accessible inbox tabs', function () {
    $template = file_get_contents(resource_path('views/admin/support/index.blade.php'));
    $script = file_get_contents(public_path('assets/js/support-chat.js'));

    expect($template)->toContain('id="support-realtime-alert" class="alert alert-warning d-none')
        ->and($template)->not->toContain('@unless($realtimeConfigured)')
        ->and($template)->toContain('support-scope-tabs')
        ->and($template)->toContain('role="tab" aria-selected="true"')
        ->and($script)->toContain('state.realtimeFailures >= 3')
        ->and($script)->toContain('if (!state.socketLive && navigator.onLine) setRealtimeAlert(true)')
        ->and($script)->toContain('if (state.socketLive) markRealtimeHealthy()')
        ->and($script)->toContain("item.setAttribute('aria-selected', active ? 'true' : 'false')");
});

it('repairs quick replies for databases that ran an earlier support migration', function () {
    Schema::table('support_ticket_types', function (Blueprint $table) {
        $table->dropColumn(['customer_quick_replies', 'agent_quick_replies']);
    });

    $migrationPath = glob(database_path('migrations/*_add_quick_replies_to_support_ticket_types.php'))[0];
    $migration = require $migrationPath;
    $migration->up();
    $migration->up();

    expect(Schema::hasColumn('support_ticket_types', 'customer_quick_replies'))->toBeTrue()
        ->and(Schema::hasColumn('support_ticket_types', 'agent_quick_replies'))->toBeTrue()
        ->and(Schema::hasColumn('support_ticket_messages', 'deleted_by'))->toBeTrue()
        ->and(Schema::hasColumn('support_ticket_messages', 'deleted_at'))->toBeTrue()
        ->and(json_decode(DB::table('support_ticket_types')->where('slug', 'delivery-tracking')->value('customer_quick_replies'), true, 512, JSON_THROW_ON_ERROR))
        ->toContain('My order is late');

    $migration->down();
    expect(Schema::hasColumn('support_ticket_types', 'customer_quick_replies'))->toBeFalse()
        ->and(Schema::hasColumn('support_ticket_types', 'agent_quick_replies'))->toBeFalse()
        ->and(Schema::hasColumn('support_ticket_messages', 'deleted_by'))->toBeFalse()
        ->and(Schema::hasColumn('support_ticket_messages', 'deleted_at'))->toBeFalse();

    $migration->up();
    expect(Schema::hasColumn('support_ticket_types', 'customer_quick_replies'))->toBeTrue()
        ->and(Schema::hasColumn('support_ticket_types', 'agent_quick_replies'))->toBeTrue()
        ->and(Schema::hasColumn('support_ticket_messages', 'deleted_by'))->toBeTrue()
        ->and(Schema::hasColumn('support_ticket_messages', 'deleted_at'))->toBeTrue();
});

it('returns predefined topic guidance and quick replies', function () {
    $customer = User::factory()->create();
    Sanctum::actingAs($customer);

    $this->getJson('/api/user/support/topics')
        ->assertOk()
        ->assertJsonPath('data.0.slug', 'delivery-tracking')
        ->assertJsonPath('data.0.quick_replies.0', 'My order is late')
        ->assertJsonPath('data.0.guidance', 'Check the latest tracking status first. If the delivery window has passed, tell us whether it is late, missing, or marked delivered.');
});

it('loads the admin support page with admin-panel users as agents', function () {
    $admin = User::factory()->create(['access_panel' => GuardNameEnum::ADMIN()]);
    User::factory()->create(['access_panel' => GuardNameEnum::WEB()]);

    $view = app(SupportController::class)->index();

    expect($view->getData()['agents']->modelKeys())->toBe([$admin->id])
        ->and($view->getData()['realtimeConfigured'])->toBeFalse()
        ->and($view->getData()['pollingEnabled'])->toBeTrue();
});

it('shows support management controls to the super administrator', function () {
    $admin = User::factory()->create(['access_panel' => GuardNameEnum::ADMIN()]);
    $role = Role::firstOrCreate([
        'name' => DefaultSystemRolesEnum::SUPER_ADMIN(),
        'guard_name' => GuardNameEnum::ADMIN(),
    ]);
    $admin->assignRole($role);
    $this->actingAs($admin);

    $view = app(SupportController::class)->index();
    $template = file_get_contents(resource_path('views/admin/support/index.blade.php'));
    $script = file_get_contents(public_path('assets/js/support-chat.js'));

    expect($view->getData()['canReply'])->toBeTrue()
        ->and($view->getData()['canAssign'])->toBeTrue()
        ->and($view->getData()['canManage'])->toBeTrue()
        ->and($template)->toContain('id="support-reassign"')
        ->and($template)->toContain('id="support-copy-ticket"')
        ->and($template)->toContain('id="support-close"')
        ->and($template)->toContain('id="support-action-modal"')
        ->and($template)->toContain('id="support-topics-modal"')
        ->and($template)->toContain('id="support-image-modal"')
        ->and($template)->toContain('id="support-context-panel-body"')
        ->and($template)->toContain('id="support-context-toggle"')
        ->and($template)->toContain('id="support-progress"')
        ->and($template)->toContain('id="support-quick-replies-manage"')
        ->and($template)->toContain('id="support-scope-unassigned-count"')
        ->and($template)->toContain('id="support-active-filters"')
        ->and($template)->toContain('id="support-customer-presence"')
        ->and($template)->toContain('id="support-typing"')
        ->and($template)->toContain('id="support-fullscreen-toggle"')
        ->and($template)->toContain('id="support-realtime-alert"')
        ->and($template)->toContain('support-scope-tabs')
        ->and($template)->toContain('id="support-topic-guidance" class="form-select support-topic-tags" multiple')
        ->and($template)->toContain('id="support-topic-customer-quick-replies" class="form-select support-topic-tags" multiple')
        ->and($template)->toContain('id="support-topic-agent-quick-replies" class="form-select support-topic-tags" multiple')
        ->and($template)->toContain('Assign to me')
        ->and($template)->toContain("hyperAsset('assets/js/support-chat.js')")
        ->and($script)->toContain("button.setAttribute('aria-current', isSelected ? 'true' : 'false')")
        ->and($script)->toContain('function inboxRowSignature(row)')
        ->and($script)->toContain('support.presence.thread.${uuid}')
        ->and($script)->toContain("endpoint('typingUrl'")
        ->and($script)->toContain(".listen('.support.typing'")
        ->and($script)->toContain(".whisper('typing', {role: 'admin'")
        ->and($script)->toContain(".listenForWhisper('typing'")
        ->and($script)->toContain("node('div', 'col-auto p-0')")
        ->and($script)->toContain("remove.setAttribute('data-bs-target', '#support-action-modal')")
        ->and($script)->not->toContain('text-white-50')
        ->and($script)->toContain('renderTimelineGroup')
        ->and($script)->toContain('window.jQuery?.fn?.modal')
        ->and($script)->toContain('state.inboxSubscribed && (!state.selectedUuid || state.threadSubscribed)')
        ->and($script)->toContain('state.socketLive || state.conversationLoading')
        ->and($script)->toContain('state.realtimeFailures >= 3')
        ->and($script)->toContain('scheduleRealtimeHealthCheck()')
        ->and($script)->toContain('function initSupportTopicTagInputs()')
        ->and($script)->toContain("document.querySelectorAll('select.support-topic-tags')")
        ->and($script)->toContain('createOnBlur: true')
        ->and($script)->toContain("const guidance = guidanceItems.join('\\n')")
        ->and($script)->toContain("event.activity === 'session.created'")
        ->and($script)->toContain('const ticket = currentTicket() || state.thread?.sessions?.at(-1);')
        ->and($script)->toContain('if (pollingEnabled) setInterval(poll, pollInterval)')
        ->and(config('menu.admin.support.route.dashboard.sub_route'))->toBe('admin.support.dashboard');

    $topic = SupportTicketType::query()->firstOrFail();
    $this->putJson(route('admin.support.topics.update', $topic), [
        'title' => $topic->title,
        'context' => 'both',
        'guidance' => 'Check the order status, then choose the closest issue.',
        'customer_quick_replies' => ['My order is late', 'My item is missing'],
        'agent_quick_replies' => ['I am checking that for you.'],
        'is_active' => true,
        'sort_order' => 5,
    ])->assertOk()
        ->assertJsonPath('data.guidance', 'Check the order status, then choose the closest issue.')
        ->assertJsonPath('data.customer_quick_replies.1', 'My item is missing')
        ->assertJsonPath('data.agent_quick_replies.0', 'I am checking that for you.');
});

it('returns inbox counts for unassigned mine and all scopes', function () {
    $admin = User::factory()->create(['access_panel' => GuardNameEnum::ADMIN()]);
    $admin->assignRole(Role::firstOrCreate([
        'name' => DefaultSystemRolesEnum::SUPER_ADMIN(),
        'guard_name' => GuardNameEnum::ADMIN(),
    ]));
    $otherAgent = User::factory()->create(['access_panel' => GuardNameEnum::ADMIN()]);
    $topic = SupportTicketType::where('is_active', true)->firstOrFail();
    $tickets = collect(range(1, 3))->map(fn () => app(SupportService::class)->startSession(User::factory()->create(), [
        'topic_id' => $topic->id,
        'message' => 'I need help.',
        'client_message_id' => fake()->uuid(),
    ]));
    app(SupportService::class)->assign($tickets[1], $admin, $admin);
    app(SupportService::class)->assign($tickets[2], $otherAgent, $admin);
    $this->actingAs($admin);

    $this->getJson(route('admin.support.inbox', ['scope' => 'all']))
        ->assertOk()
        ->assertJsonPath('meta.scope_counts.unassigned', 1)
        ->assertJsonPath('meta.scope_counts.mine', 1)
        ->assertJsonPath('meta.scope_counts.all', 3);
});

it('shows the latest message and requires assignment before an admin reply', function () {
    $admin = User::factory()->create(['access_panel' => GuardNameEnum::ADMIN()]);
    $admin->assignRole(Role::firstOrCreate([
        'name' => DefaultSystemRolesEnum::SUPER_ADMIN(),
        'guard_name' => GuardNameEnum::ADMIN(),
    ]));
    $customer = User::factory()->create();
    $topic = SupportTicketType::where('is_active', true)->firstOrFail();
    $ticket = app(SupportService::class)->startSession($customer, [
        'topic_id' => $topic->id,
        'message' => 'First message',
        'client_message_id' => fake()->uuid(),
    ]);
    app(SupportService::class)->addMessage($ticket, $customer, 'user', [
        'message' => 'Latest customer message',
        'client_message_id' => fake()->uuid(),
    ]);
    $this->actingAs($admin);

    $this->getJson(route('admin.support.inbox', ['scope' => 'all']))
        ->assertOk()
        ->assertJsonPath('data.0.preview', 'Latest customer message');

    $this->postJson(route('admin.support.messages.store', $ticket), [
        'message' => 'This must be blocked.',
        'client_message_id' => fake()->uuid(),
    ])->assertConflict()
        ->assertJsonPath('message', 'Assign this conversation to yourself before replying.');

    $this->postJson(route('admin.support.tickets.assign', $ticket), ['agent_id' => $admin->id])->assertOk();
    $this->postJson(route('admin.support.messages.store', $ticket), [
        'message' => 'Assigned reply',
        'client_message_id' => fake()->uuid(),
    ])->assertCreated();
});

it('returns admin attachments to the owning customer with downloadable urls', function () {
    $admin = User::factory()->create(['access_panel' => GuardNameEnum::ADMIN()]);
    $admin->assignRole(Role::firstOrCreate([
        'name' => DefaultSystemRolesEnum::SUPER_ADMIN(),
        'guard_name' => GuardNameEnum::ADMIN(),
    ]));
    $customer = User::factory()->create();
    $ticket = app(SupportService::class)->startSession($customer, [
        'topic_id' => SupportTicketType::where('is_active', true)->firstOrFail()->id,
        'message' => 'Need help with this order',
        'client_message_id' => fake()->uuid(),
    ]);
    app(SupportService::class)->assign($ticket, $admin, $admin);
    $cursor = SupportTicketMessage::max('id');

    $sent = $this->actingAs($admin)->post(route('admin.support.messages.store', $ticket), [
        'message' => 'Please check the attached proof.',
        'client_message_id' => fake()->uuid(),
        'attachments' => [UploadedFile::fake()->image('agent-proof.jpg', 80, 60)],
    ], ['Accept' => 'application/json'])->assertCreated();

    $mediaId = $sent->json('data.attachments.0.id');
    $media = Spatie\MediaLibrary\MediaCollections\Models\Media::findOrFail($mediaId);

    expect($media->disk)->toBe('public')
        ->and($media->getPathRelativeToRoot())->toStartWith($media->getKey().'/')
        ->and($media->getPathRelativeToRoot())->not->toContain('support_attachments');

    $sent->assertJsonPath('data.attachments.0.name', 'agent-proof.jpg')
        ->assertJsonPath('data.attachments.0.is_image', true)
        ->assertJsonPath('data.attachments.0.url', $sent->json('data.attachments.0.preview_url'));
    expect($sent->json('data.attachments.0.preview_url'))->toContain('/storage/'.$mediaId.'/agent-proof.jpg');

    $this->actingAs($admin)
        ->get(route('admin.support.attachments.show', $mediaId))
        ->assertOk()
        ->assertHeader('content-type', 'image/jpeg');
    $this->actingAs($admin)
        ->get(route('admin.support.attachments.show', ['media' => $mediaId, 'download' => 1]))
        ->assertOk();

    Sanctum::actingAs($customer);
    $this->getJson('/api/user/support/thread/updates?after_message_id='.$cursor)
        ->assertOk()
        ->assertJsonCount(1, 'data.messages')
        ->assertJsonPath('data.messages.0.attachments.0.id', $mediaId)
        ->assertJsonPath('data.messages.0.attachments.0.name', 'agent-proof.jpg')
        ->assertJsonPath('data.messages.0.attachments.0.is_image', true);
    $this->get('/api/user/support/attachments/'.$mediaId.'?inline=1')
        ->assertOk()
        ->assertHeader('content-type', 'image/jpeg');
    $this->get('/api/user/support/attachments/'.$mediaId)
        ->assertOk()
        ->assertHeader('content-type', 'image/jpeg');
    $this->get('/api/user/support/attachments/'.$mediaId.'?download=1')->assertOk();
});

it('attributes support activity to the actor for admin and customer audiences', function () {
    $customer = User::factory()->create(['name' => 'Meet Ahir']);
    $admin = User::factory()->create(['name' => 'Super admin', 'access_panel' => GuardNameEnum::ADMIN()]);
    $topic = SupportTicketType::where('is_active', true)->firstOrFail();
    $ticket = app(SupportService::class)->startSession($customer, [
        'topic_id' => $topic->id,
        'message' => 'Please call me.',
        'client_message_id' => fake()->uuid(),
    ]);

    app(SupportService::class)->requestCallback($ticket, $customer, []);
    $callback = $ticket->messages()->where('type', 'callback')->reorder()->latest('id')->firstOrFail();

    expect(SupportPresenter::message($callback, true)['text'])->toBe('Call requested by Meet Ahir')
        ->and(SupportPresenter::message($callback)['text'])->toBe('Call requested by you');

    app(SupportService::class)->logCall($ticket, $admin, ['status' => 'attempted']);
    $callUpdate = $ticket->messages()->where('type', 'callback')->reorder()->latest('id')->firstOrFail();

    expect(SupportPresenter::message($callUpdate, true)['text'])->toBe('Call record updated by Super admin')
        ->and(SupportPresenter::message($callUpdate)['text'])->toBe('Call record updated by Super admin');

    app(SupportService::class)->resolve($ticket, $customer, 'customer', null);
    $closure = $ticket->messages()->where('type', 'closure')->reorder()->latest('id')->firstOrFail();

    expect(SupportPresenter::message($closure, true)['text'])->toBe('This conversation has been closed by Meet Ahir')
        ->and(SupportPresenter::message($closure)['text'])->toBe('This conversation has been closed by you');
});

it('moves a waiting conversation back to in progress', function () {
    $admin = User::factory()->create(['access_panel' => GuardNameEnum::ADMIN()]);
    $admin->assignRole(Role::firstOrCreate([
        'name' => DefaultSystemRolesEnum::SUPER_ADMIN(),
        'guard_name' => GuardNameEnum::ADMIN(),
    ]));
    $topic = SupportTicketType::where('is_active', true)->firstOrFail();
    $ticket = app(SupportService::class)->startSession(User::factory()->create(), [
        'topic_id' => $topic->id,
        'message' => 'I need help.',
        'client_message_id' => fake()->uuid(),
    ]);
    app(SupportService::class)->updateStatus($ticket, $admin, 'waiting_customer');
    $this->actingAs($admin);

    $this->postJson(route('admin.support.tickets.status', $ticket), ['status' => 'in_progress'])
        ->assertOk()
        ->assertJsonPath('data.status', 'in_progress')
        ->assertJsonPath('data.topic.quick_replies.0', $topic->agent_quick_replies[0]);
});

it('resolves admin support thread routes by uuid', function () {
    $customer = User::factory()->create();
    $thread = app(SupportService::class)->threadFor($customer);

    $resolved = (new SupportThread)->resolveRouteBinding($thread->uuid);

    expect($resolved?->is($thread))->toBeTrue()
        ->and((new SupportThread)->resolveRouteBinding((string) $thread->id))->toBeNull();
});

it('keeps one permanent thread and one active session per customer', function () {
    $customer = User::factory()->create();
    $agent = User::factory()->create(['access_panel' => GuardNameEnum::ADMIN()]);
    $topic = SupportTicketType::where('is_active', true)->firstOrFail();
    Sanctum::actingAs($customer);

    $first = $this->postJson('/api/user/support/sessions', [
        'topic_id' => $topic->id,
        'message' => 'My item is damaged.',
        'client_message_id' => fake()->uuid(),
    ])->assertCreated();

    $this->postJson('/api/user/support/sessions', [
        'topic_id' => $topic->id,
        'message' => 'A second ticket must not open.',
        'client_message_id' => fake()->uuid(),
    ])->assertConflict();

    $slug = $first->json('data.slug');
    app(SupportService::class)->assign(SupportTicket::where('slug', $slug)->firstOrFail(), $agent, $agent);
    $this->postJson("/api/user/support/sessions/{$slug}/resolve", [])
        ->assertOk()
        ->assertJsonPath('data.status', 'resolved');

    $second = $this->postJson('/api/user/support/sessions', [
        'topic_id' => $topic->id,
        'message' => 'I still need help.',
        'client_message_id' => fake()->uuid(),
    ])->assertCreated();

    expect($second->json('data.parent_ticket_id'))->toBe($first->json('data.id'))
        ->and($second->json('data.assignee'))->toBeNull();
    $this->assertDatabaseHas('support_tickets', [
        'id' => $second->json('data.id'),
        'assigned_to' => null,
        'status' => 'open',
    ]);
    $this->getJson('/api/user/support/thread')
        ->assertOk()
        ->assertJsonCount(2, 'data.thread.sessions');
});

it('starts every follow-up support session unassigned', function () {
    $customer = User::factory()->create();
    $agent = User::factory()->create(['access_panel' => GuardNameEnum::ADMIN()]);
    $topic = SupportTicketType::where('is_active', true)->firstOrFail();
    $support = app(SupportService::class);
    $first = $support->startSession($customer, [
        'topic_id' => $topic->id,
        'message' => 'First issue.',
        'client_message_id' => fake()->uuid(),
    ]);

    $support->assign($first, $agent, $agent);
    $support->resolve($first->fresh(), $customer, 'customer', null);
    $second = $support->startSession($customer, [
        'topic_id' => $topic->id,
        'message' => 'Follow-up issue.',
        'client_message_id' => fake()->uuid(),
    ]);

    expect($first->fresh()->assigned_to)->toBe($agent->id)
        ->and($second->parent_ticket_id)->toBe($first->id)
        ->and($second->assigned_to)->toBeNull()
        ->and($second->status)->toBe('open');
});

it('deduplicates retried messages by client id', function () {
    $customer = User::factory()->create();
    $topic = SupportTicketType::where('is_active', true)->firstOrFail();
    Sanctum::actingAs($customer);
    $session = $this->postJson('/api/user/support/sessions', [
        'topic_id' => $topic->id,
        'message' => 'Hello',
        'client_message_id' => fake()->uuid(),
    ])->json('data');
    $clientId = fake()->uuid();

    $first = $this->postJson("/api/user/support/sessions/{$session['slug']}/messages", [
        'message' => '<script>alert(1)</script>',
        'client_message_id' => $clientId,
    ])->assertCreated()
        ->assertJsonStructure(['data' => ['sender_avatar']]);
    $second = $this->postJson("/api/user/support/sessions/{$session['slug']}/messages", [
        'message' => 'A retry must not overwrite content.',
        'client_message_id' => $clientId,
    ])->assertCreated();

    expect($second->json('data.id'))->toBe($first->json('data.id'))
        ->and($second->json('data.text'))->toBe('<script>alert(1)</script>')
        ->and(SupportTicketMessage::where('client_message_id', $clientId)->count())->toBe(1);
});

it('presents support sessions and messages from oldest to newest', function () {
    $customer = User::factory()->create();
    $topic = SupportTicketType::where('is_active', true)->firstOrFail();
    $ticket = app(SupportService::class)->startSession($customer, [
        'topic_id' => $topic->id,
        'message' => 'First message',
        'client_message_id' => fake()->uuid(),
    ]);
    $second = app(SupportService::class)->addMessage($ticket, $customer, 'user', [
        'message' => 'Second message',
        'client_message_id' => fake()->uuid(),
    ]);
    $third = app(SupportService::class)->addMessage($ticket, $customer, 'user', [
        'message' => 'Third message',
        'client_message_id' => fake()->uuid(),
    ]);
    $ticket->setRelation('messages', SupportTicketMessage::query()
        ->where('ticket_id', $ticket->id)
        ->orderByDesc('id')
        ->get());

    $messages = SupportPresenter::ticket($ticket)['messages'];

    expect($messages->pluck('id')->all())->toBe([
        $ticket->messages()->first()->id,
        $second->id,
        $third->id,
    ]);
});

it('accepts one rating only after resolution and allows updating it', function () {
    $customer = User::factory()->create();
    $topic = SupportTicketType::where('is_active', true)->firstOrFail();
    Sanctum::actingAs($customer);
    $slug = $this->postJson('/api/user/support/sessions', [
        'topic_id' => $topic->id,
        'message' => 'Payment is pending.',
        'client_message_id' => fake()->uuid(),
    ])->json('data.slug');

    $this->postJson("/api/user/support/sessions/{$slug}/rating", ['score' => 2])->assertConflict();
    $this->postJson("/api/user/support/sessions/{$slug}/resolve", [])->assertOk();
    $this->postJson("/api/user/support/sessions/{$slug}/rating", ['score' => 2, 'feedback' => 'Slow'])->assertOk();
    $this->postJson("/api/user/support/sessions/{$slug}/rating", ['score' => 4, 'feedback' => 'Recovered'])->assertOk()
        ->assertJsonPath('data.score', 4);

    $this->assertDatabaseCount('support_ticket_ratings', 1);
});

it('attributes a customer rating to the assigned support agent', function () {
    $customer = User::factory()->create();
    $agent = User::factory()->create(['access_panel' => GuardNameEnum::ADMIN()]);
    $topic = SupportTicketType::where('is_active', true)->firstOrFail();
    Sanctum::actingAs($customer);
    $ticketId = $this->postJson('/api/user/support/sessions', [
        'topic_id' => $topic->id,
        'message' => 'I need support.',
        'client_message_id' => fake()->uuid(),
    ])->assertCreated()->json('data.id');
    $ticket = SupportTicket::findOrFail($ticketId);
    app(SupportService::class)->assign($ticket, $agent, $agent);
    app(SupportService::class)->resolve($ticket->fresh(), $agent, 'admin', 'Issue fixed.');

    $this->postJson("/api/user/support/sessions/{$ticket->slug}/rating", ['score' => 5])
        ->assertOk()
        ->assertJsonPath('data.agent.id', $agent->id)
        ->assertJsonPath('data.agent.name', $agent->name);

    $this->assertDatabaseHas('support_ticket_ratings', [
        'ticket_id' => $ticket->id,
        'agent_id' => $agent->id,
        'score' => 5,
    ]);
});

it('shows configured app deeplink details only for related support queries', function () {
    Setting::updateOrCreate(['variable' => 'app'], ['value' => json_encode([
        'customerAppScheme' => 'hypercommerce',
        'customerPlaystoreLink' => 'https://play.google.com/store/apps/details?id=test',
    ])]);
    app(SettingService::class)->clearSettingCache('app');
    $customer = User::factory()->create();
    $topic = SupportTicketType::where('is_active', true)->firstOrFail();
    $ticket = app(SupportService::class)->startSession($customer, [
        'topic_id' => $topic->id,
        'message' => 'The deep link hypercommerce://orders/42 does not open in app.',
        'client_message_id' => fake()->uuid(),
    ]);

    $deeplink = SupportPresenter::ticket($ticket, true)['deeplink'];

    expect($deeplink['customer']['scheme'])->toBe('hypercommerce')
        ->and($deeplink['reported_links'])->toContain('hypercommerce://orders/42');
});

it('renders the support dashboard with agent csat attribution', function () {
    $admin = User::factory()->create(['access_panel' => GuardNameEnum::ADMIN()]);
    $admin->assignRole(Role::firstOrCreate([
        'name' => DefaultSystemRolesEnum::SUPER_ADMIN(),
        'guard_name' => GuardNameEnum::ADMIN(),
    ]));
    $this->actingAs($admin);

    $this->get(route('admin.support.dashboard'))
        ->assertOk()
        ->assertSee('Support performance')
        ->assertSee('Support team')
        ->assertSee('Ratings feedback')
        ->assertSee('Customers waiting')
        ->assertSee('Late replies')
        ->assertSee('id="support-team-table"', false)
        ->assertSee('id="support-ratings-table"', false)
        ->assertSee('data-support-queue="waiting"', false)
        ->assertSee('data-support-queue="late"', false)
        ->assertSee('data-late-reply-filter', false)
        ->assertSee(route('admin.support.dashboard.team', ['days' => 30]), false)
        ->assertSee(route('admin.support.dashboard.ratings', ['days' => 30]), false)
        ->assertSee('id="support-dashboard-realtime-alert"', false)
        ->assertSee(hyperAsset('assets/js/support-dashboard.js'), false);

    $refresh = $this->getJson(route('admin.support.dashboard.data', ['days' => 7]))->assertOk();
    expect($refresh->json('data.html'))->toContain('Customers waiting')
        ->and($refresh->json('data.generated_at'))->not->toBeEmpty();

    $this->getJson(route('admin.support.dashboard.team', ['days' => 7, 'draw' => 1]))
        ->assertOk()
        ->assertJsonStructure(['draw', 'recordsTotal', 'recordsFiltered', 'data']);
    $this->getJson(route('admin.support.dashboard.ratings', ['days' => 7, 'draw' => 1]))
        ->assertOk()
        ->assertJsonStructure(['draw', 'recordsTotal', 'recordsFiltered', 'data']);
});

it('uses actionable callback wait feedback and assignment response metrics on the support dashboard', function () {
    $support = app(SupportService::class);
    $topic = SupportTicketType::where('is_active', true)->firstOrFail();
    $agent = User::factory()->create(['access_panel' => GuardNameEnum::ADMIN()]);
    $agent->assignRole(Role::firstOrCreate([
        'name' => DefaultSystemRolesEnum::SUPER_ADMIN(),
        'guard_name' => GuardNameEnum::ADMIN(),
    ]));
    $this->actingAs($agent);

    $waiting = $support->startSession(User::factory()->create(), [
        'topic_id' => $topic->id,
        'message' => 'I am still waiting for help.',
        'client_message_id' => fake()->uuid(),
    ]);
    $waiting->messages()->where('send_by', 'user')->update([
        'created_at' => now()->subMinutes(20),
        'updated_at' => now()->subMinutes(20),
    ]);
    $support->assign($waiting, $agent, $agent);
    $support->requestCallback($waiting, $waiting->user, []);

    $recentWaiting = $support->startSession(User::factory()->create(), [
        'topic_id' => $topic->id,
        'message' => 'This customer has only just started waiting.',
        'client_message_id' => fake()->uuid(),
    ]);

    $closedWithCallback = $support->startSession(User::factory()->create(), [
        'topic_id' => $topic->id,
        'message' => 'Please call me.',
        'client_message_id' => fake()->uuid(),
    ]);
    $support->requestCallback($closedWithCallback, $closedWithCallback->user, []);
    $support->resolve($closedWithCallback, $agent, 'admin', 'Closed without a call.');

    $responded = $support->startSession(User::factory()->create(), [
        'topic_id' => $topic->id,
        'message' => 'Please reply.',
        'client_message_id' => fake()->uuid(),
    ]);
    $support->assign($responded, $agent, $agent);
    $assignmentAt = $responded->messages()->where('type', 'assignment')->latest('id')->value('created_at');
    $this->travel(2)->minutes();
    $support->addMessage($responded, $agent, 'admin', [
        'message' => 'I am checking this now.',
        'client_message_id' => fake()->uuid(),
    ]);
    $this->travelBack();

    $rated = $support->startSession(User::factory()->create(), [
        'topic_id' => $topic->id,
        'message' => 'Rating test.',
        'client_message_id' => fake()->uuid(),
    ]);
    $support->assign($rated, $agent, $agent);
    $support->resolve($rated, $agent, 'admin', 'Resolved.');
    $support->rate($rated->fresh(), $rated->user, 4, 'Helpful and clear.');

    $dashboard = app(SupportDashboardService::class);
    $data = $dashboard->data(30);
    $agentMetrics = $dashboard->team(30)->firstWhere('id', $agent->id);
    $ratingFeedback = $dashboard->ratings(30)->first();
    $waitingPage = $dashboard->waitingPage(1, null, 1);
    $teamResponse = $this->getJson(route('admin.support.dashboard.team', ['days' => 30, 'draw' => 2]))->assertOk();
    $ratingResponse = $this->getJson(route('admin.support.dashboard.ratings', ['days' => 30, 'draw' => 3]))->assertOk();
    $lateResponse = $this->getJson(route('admin.support.dashboard.queue', [
        'queue' => 'late',
        'late_minutes' => 15,
    ]))->assertOk();
    $laterResponse = $this->getJson(route('admin.support.dashboard.queue', [
        'queue' => 'late',
        'late_minutes' => 30,
    ]))->assertOk();

    expect($assignmentAt)->not->toBeNull()
        ->and($data['metrics']['callbacks'])->toBe(1)
        ->and($data['metrics']['waiting'])->toBe(2)
        ->and($data['metrics']['late'])->toBe(1)
        ->and($data['waitingCustomers']->pluck('ticket_id'))->toContain($waiting->id)
        ->and($data['waitingCustomers']->pluck('ticket_id'))->toContain($recentWaiting->id)
        ->and($data['lateReplies']->pluck('ticket_id'))->toContain($waiting->id)
        ->and($waitingPage->total())->toBe(2)
        ->and($waitingPage->hasMorePages())->toBeTrue()
        ->and($ratingFeedback?->feedback)->toBe('Helpful and clear.')
        ->and($agentMetrics['response_count'])->toBe(1)
        ->and($agentMetrics['response_average_seconds'])->toBeGreaterThanOrEqual(119)
        ->and($agentMetrics['response_average_seconds'])->toBeLessThanOrEqual(121)
        ->and($teamResponse->json('draw'))->toBe(2)
        ->and(collect($teamResponse->json('data'))->pluck('executive')->implode(' '))->toContain($agent->name)
        ->and($ratingResponse->json('draw'))->toBe(3)
        ->and($ratingResponse->json('recordsFiltered'))->toBe(1)
        ->and($ratingResponse->json('data.0.feedback'))->toContain('Helpful and clear.')
        ->and($ratingResponse->json('data.0.executive'))->toBe($agent->name)
        ->and($lateResponse->json('data.total'))->toBe(1)
        ->and($lateResponse->json('data.html'))->toContain($waiting->slug)
        ->and($laterResponse->json('data.total'))->toBe(0);
});

it('exposes the support dashboard realtime refresh contract', function () {
    $template = file_get_contents(resource_path('views/admin/support/dashboard.blade.php'));
    $dashboardScript = file_get_contents(public_path('assets/js/support-dashboard.js'));
    $notificationScript = file_get_contents(public_path('assets/js/support-notifications.js'));

    expect($template)->toContain('data-refresh-url="{{ route(\'admin.support.dashboard.data\') }}"')
        ->and($template)->toContain('id="support-dashboard-connection"')
        ->and($template)->toContain('id="support-team-table"')
        ->and($template)->toContain('id="support-ratings-table"')
        ->and($template)->toContain('data-queue-url="{{ route(\'admin.support.dashboard.queue\') }}"')
        ->and($dashboardScript)->toContain("window.addEventListener('support:activity'")
        ->and($dashboardScript)->toContain('DataTable().ajax.reload(null, false)')
        ->and($dashboardScript)->toContain('async function loadQueuePage')
        ->and($dashboardScript)->toContain("queueElement.addEventListener('scroll'")
        ->and($dashboardScript)->toContain("lateFilter.addEventListener('change'")
        ->and($dashboardScript)->toContain('if (!state.live) refreshDashboard()')
        ->and($notificationScript)->toContain("new CustomEvent('support:realtime'")
        ->and($notificationScript)->toContain("new CustomEvent('support:activity'")
        ->and($notificationScript)->toContain("echo.private('support.admin.inbox')");
});

it('returns immutable cursor updates with public attachment urls', function () {
    $customer = User::factory()->create();
    $otherCustomer = User::factory()->create();
    $topic = SupportTicketType::where('is_active', true)->firstOrFail();
    Sanctum::actingAs($customer);
    $slug = $this->postJson('/api/user/support/sessions', [
        'topic_id' => $topic->id,
        'message' => 'Initial issue',
        'client_message_id' => fake()->uuid(),
    ])->assertCreated()->json('data.slug');
    $cursor = SupportTicketMessage::max('id');

    $sent = $this->post('/api/user/support/sessions/'.$slug.'/messages', [
        'message' => 'Evidence attached',
        'client_message_id' => fake()->uuid(),
        'attachments' => [UploadedFile::fake()->image('evidence.jpg')],
    ], ['Accept' => 'application/json'])->assertCreated();
    $messageId = $sent->json('data.id');
    $mediaId = $sent->json('data.attachments.0.id');
    Spatie\MediaLibrary\MediaCollections\Models\Media::findOrFail($mediaId)
        ->forceFill(['mime_type' => 'application/octet-stream'])
        ->save();

    $updates = $this->getJson('/api/user/support/thread/updates?after_message_id='.$cursor)
        ->assertOk()
        ->assertJsonCount(1, 'data.messages')
        ->assertJsonPath('data.messages.0.id', $messageId)
        ->assertJsonPath('data.messages.0.attachments.0.is_image', true)
        ->assertJsonPath('data.messages.0.attachments.0.mime_type', 'image/jpeg')
        ->assertJsonPath('data.message_cursor', $messageId);

    $publicPreviewUrl = $updates->json('data.messages.0.attachments.0.preview_url');
    expect($publicPreviewUrl)->toStartWith('http')
        ->and($publicPreviewUrl)->toContain('/storage/'.$mediaId.'/evidence.jpg')
        ->and($publicPreviewUrl)->not->toContain('support_attachments')
        ->and($publicPreviewUrl)->not->toContain('signature=');

    $this->get('/api/user/support/attachments/'.$mediaId)
        ->assertOk()
        ->assertHeader('content-type', 'image/jpeg');

    $adminPayload = SupportPresenter::thread(SupportTicket::where('slug', $slug)->firstOrFail()->thread, true, 50);
    $adminAttachment = $adminPayload['sessions'][0]['messages'][1]['attachments'][0];

    expect($adminAttachment['is_image'])->toBeTrue()
        ->and($adminAttachment['mime_type'])->toBe('image/jpeg')
        ->and($adminAttachment['url'])->toContain('/storage/'.$mediaId.'/evidence.jpg')
        ->and(array_key_exists('preview_data_url', $adminAttachment))->toBeFalse();

    Sanctum::actingAs($otherCustomer);
    $this->get('/api/user/support/attachments/'.$mediaId)->assertNotFound();
    $this->postJson('/api/user/support/sessions/'.$slug.'/resolve')->assertNotFound();
});

it('authorizes only the owning customer on private thread channels', function () {
    $customer = User::factory()->create();
    $otherCustomer = User::factory()->create();
    $thread = app(SupportService::class)->threadFor($customer);

    Sanctum::actingAs($customer);
    $this->postJson('/api/broadcasting/auth', [
        'socket_id' => '1234.5678',
        'channel_name' => 'private-support.thread.'.$thread->uuid,
    ])->assertOk();
    $this->postJson('/api/broadcasting/auth', [
        'socket_id' => '1234.5679',
        'channel_name' => 'private-support.user.'.$customer->id,
    ])->assertOk();

    Sanctum::actingAs($otherCustomer);
    $this->postJson('/api/broadcasting/auth', [
        'socket_id' => '1234.9999',
        'channel_name' => 'private-support.thread.'.$thread->uuid,
    ])->assertForbidden();
    $this->postJson('/api/broadcasting/auth', [
        'socket_id' => '1234.9998',
        'channel_name' => 'private-support.user.'.$customer->id,
    ])->assertForbidden();
});

it('broadcasts typing through the authenticated server event', function () {
    Event::fake([SupportTypingChanged::class]);
    $customer = User::factory()->create();
    $topic = SupportTicketType::where('is_active', true)->firstOrFail();
    $ticket = app(SupportService::class)->startSession($customer, [
        'topic_id' => $topic->id,
        'message' => 'I need help.',
        'client_message_id' => fake()->uuid(),
    ]);
    Sanctum::actingAs($customer);

    $this->postJson("/api/user/support/sessions/{$ticket->slug}/typing", ['typing' => true])
        ->assertStatus(202)
        ->assertJsonPath('data.typing', true);

    Event::assertDispatched(SupportTypingChanged::class, fn (SupportTypingChanged $event) => $event->threadId === $ticket->thread_id
        && $event->userId === $customer->id
        && $event->role === 'customer'
        && $event->typing);
});

it('paginates older messages across the permanent support timeline', function () {
    $customer = User::factory()->create();
    $topic = SupportTicketType::where('is_active', true)->firstOrFail();
    $ticket = app(SupportService::class)->startSession($customer, [
        'topic_id' => $topic->id,
        'message' => 'Message 0',
        'client_message_id' => fake()->uuid(),
    ]);
    foreach (range(1, 60) as $index) {
        app(SupportService::class)->addMessage($ticket, $customer, 'user', [
            'message' => 'Message '.$index,
            'client_message_id' => fake()->uuid(),
        ]);
    }
    Sanctum::actingAs($customer);

    $threadResponse = $this->getJson('/api/user/support/thread')->assertOk();
    $messages = collect($threadResponse->json('data.thread.sessions'))->flatMap(fn (array $session) => $session['messages']);
    expect($messages)->toHaveCount(50);
    $threadResponse->assertJsonPath('data.thread.message_pagination.has_more', true);

    $this->getJson('/api/user/support/thread/messages?before_message_id='.$messages->min('id'))
        ->assertOk()
        ->assertJsonCount(11, 'data.messages')
        ->assertJsonPath('data.has_more', false);
});

it('lets support managers delete one message or clear a thread while preserving ticket records', function () {
    $admin = User::factory()->create(['access_panel' => GuardNameEnum::ADMIN()]);
    $admin->assignRole(Role::firstOrCreate([
        'name' => DefaultSystemRolesEnum::SUPER_ADMIN(),
        'guard_name' => GuardNameEnum::ADMIN(),
    ]));
    $customer = User::factory()->create();
    $topic = SupportTicketType::where('is_active', true)->firstOrFail();
    $ticket = app(SupportService::class)->startSession($customer, [
        'topic_id' => $topic->id,
        'message' => 'Delete this message',
        'client_message_id' => fake()->uuid(),
    ]);
    $firstMessage = $ticket->messages()->firstOrFail();
    app(SupportService::class)->addMessage($ticket, $customer, 'user', [
        'message' => 'Clear this message',
        'client_message_id' => fake()->uuid(),
    ]);
    $this->actingAs($admin);
    app(SupportService::class)->resolve($ticket, $admin, 'admin', 'Completed before history cleanup.');

    expect($ticket->thread()->value('active_ticket_id'))->toBeNull();

    $this->deleteJson(route('admin.support.messages.destroy', $firstMessage))->assertOk();
    $this->assertSoftDeleted('support_ticket_messages', ['id' => $firstMessage->id, 'deleted_by' => $admin->id]);

    $this->deleteJson(route('admin.support.threads.messages.clear', $ticket->thread))->assertOk();
    expect($ticket->messages()->count())->toBe(0)
        ->and(SupportTicket::whereKey($ticket->id)->exists())->toBeTrue();
});
