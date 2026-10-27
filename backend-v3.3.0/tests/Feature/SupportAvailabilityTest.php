<?php

use App\Enums\GuardNameEnum;
use App\Events\Support\SupportActivityCreated;
use App\Http\Controllers\Admin\SupportController;
use App\Http\Middleware\EnsureSystemVendorTypeSelected;
use App\Http\Middleware\EnsureSubscriptionFeatureSelected;
use App\Http\Middleware\VerifyLicense;
use App\Models\Setting;
use App\Models\SupportTicketType;
use App\Models\User;
use App\Notifications\SupportAdminMessageReceived;
use App\Notifications\SupportMessageReceived;
use App\Services\SettingService;
use App\Services\Support\SupportAvailabilityService;
use App\Services\Support\SupportPresenter;
use App\Services\Support\SupportService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Notification;
use Illuminate\View\ComponentAttributeBag;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;

uses(RefreshDatabase::class);

beforeEach(function () {
    config(['broadcasting.default' => 'null', 'app.timezone' => 'Asia/Kolkata']);
    $this->travelTo(CarbonImmutable::parse('2026-09-07 20:00:00', 'Asia/Kolkata'));
    $this->withoutMiddleware([
        VerifyLicense::class,
        EnsureSystemVendorTypeSelected::class,
        EnsureSubscriptionFeatureSelected::class,
    ]);
    Notification::fake();
    Event::fake([SupportActivityCreated::class]);
    $this->support = app(SupportService::class);
    $this->customer = User::factory()->create();
    $this->agent = User::factory()->create(['access_panel' => GuardNameEnum::ADMIN()]);
    foreach (['support.view', 'support.manage'] as $permission) {
        $this->agent->givePermissionTo(Permission::findOrCreate($permission, GuardNameEnum::ADMIN()));
    }
    $this->sessionData = ['topic_id' => SupportTicketType::where('is_active', true)->firstOrFail()->id, 'message' => 'Please check my order', 'client_message_id' => fake()->uuid()];
});

it('provides visible effective defaults without a stored setting', function () {
    $config = app(SupportAvailabilityService::class)->configuration();
    expect($config['enabled'])->toBeTrue()
        ->and($config['days'])->toBe(['monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday'])
        ->and($config['hours']['monday']['start_time'])->toBe('09:00')
        ->and($config['hours']['saturday']['end_time'])->toBe('18:00')
        ->and($config['message'])->not->toBeEmpty()
        ->and(Setting::where('variable', 'support')->exists())->toBeFalse();
    $this->actingAs($this->agent);
    $view = app(SupportController::class)->index();
    expect($view->getData()['availability'])->toBe($config);
    $html = view('components.weekly-schedule', ['schedule' => $config, 'attributes' => new ComponentAttributeBag])->render();
    expect($html)->toContain('Asia/Kolkata', '09:00', '18:00', 'Always available (24/7)');
});

it('persists availability and preserves other settings and the schedule in 24/7 mode', function () {
    app(SettingService::class)->saveSetting('support', ['other' => 'preserved']);
    $data = ['enabled' => true, 'days' => ['monday', 'sunday'], 'hours' => [
        'monday' => ['start_time' => '09:00', 'end_time' => '17:00'],
        'sunday' => ['start_time' => '10:30', 'end_time' => '16:30'],
    ], 'message' => 'Our team will reply when available.'];
    $this->actingAs($this->agent)->putJson(route('admin.support.availability.update'), $data)
        ->assertOk()->assertJsonPath('success', true)->assertJsonPath('data.days', ['monday', 'sunday']);
    app(SettingService::class)->clearSettingCache('support');
    expect(app(SupportAvailabilityService::class)->configuration()['hours']['sunday'])->toBe($data['hours']['sunday'])
        ->and(app(SupportAvailabilityService::class)->configuration()['hours']['monday'])->toBe($data['hours']['monday'])
        ->and(app(SettingService::class)->getSettingValues('support')['other'])->toBe('preserved');
    $this->putJson(route('admin.support.availability.update'), ['enabled' => false])
        ->assertOk()->assertJsonPath('data.enabled', false)->assertJsonPath('data.hours.sunday.start_time', '10:30');
});

it('rejects availability updates without manage permission', function () {
    $this->agent->revokePermissionTo('support.manage');
    $this->actingAs($this->agent)->putJson(route('admin.support.availability.update'), ['enabled' => false])->assertForbidden();
});

it('saves 24/7 with no submitted days and renders its retained schedule hidden after reload', function () {
    $this->actingAs($this->agent)->post(route('admin.support.availability.update'), [
        '_method' => 'PUT', 'enabled' => '0',
    ], ['Accept' => 'application/json'])->assertOk()->assertJsonPath('data.enabled', false);

    app(SettingService::class)->clearSettingCache('support');
    $availability = app(SupportAvailabilityService::class)->configuration();
    $html = view('components.weekly-schedule', [
        'schedule' => $availability, 'attributes' => new ComponentAttributeBag,
    ])->render();
    expect($availability['enabled'])->toBeFalse()
        ->and($availability['hours']['monday']['start_time'])->toBe('09:00')
        ->and($html)->toContain('data-schedule-fields class="mb-0 d-none" disabled')
        ->toContain('Your saved hours are kept for later.');
});

it('accepts the global form submit payload and preserves disabled day hours', function () {
    $this->actingAs($this->agent)->post(route('admin.support.availability.update'), [
        '_method' => 'PUT',
        'enabled' => '1',
        'days' => ['tuesday'],
        'hours' => ['tuesday' => ['start_time' => '12:00', 'end_time' => '20:00']],
        'message' => 'We will be back soon.',
    ], ['Accept' => 'application/json'])->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonPath('data.enabled', true)
        ->assertJsonPath('data.hours.tuesday.start_time', '12:00')
        ->assertJsonPath('data.hours.monday.start_time', '09:00');

    $this->post(route('admin.support.availability.update'), [
        '_method' => 'PUT', 'enabled' => '0',
    ], ['Accept' => 'application/json'])->assertOk()
        ->assertJsonPath('data.enabled', false)
        ->assertJsonPath('data.hours.tuesday.end_time', '20:00');
});

it('expands saved shared times without changing existing settings until saved', function () {
    $legacy = ['enabled' => true, 'days' => ['monday', 'friday'], 'start_time' => '10:15', 'end_time' => '19:45', 'message' => 'Legacy away message'];
    app(SettingService::class)->saveSetting('support', ['availability' => $legacy]);
    $availability = app(SupportAvailabilityService::class);

    expect($availability->configuration()['hours']['monday'])->toBe(['start_time' => '10:15', 'end_time' => '19:45'])
        ->and($availability->configuration()['hours']['friday'])->toBe(['start_time' => '10:15', 'end_time' => '19:45'])
        ->and($availability->configuration()['days'])->toBe($legacy['days'])
        ->and(app(SettingService::class)->getSettingValues('support')['availability'])->toBe($legacy);

    $availability->save(['enabled' => true, 'hours' => ['friday' => ['start_time' => '11:00', 'end_time' => '16:00']]]);
    expect($availability->configuration()['hours']['monday']['end_time'])->toBe('19:45')
        ->and($availability->configuration()['hours']['friday']['end_time'])->toBe('16:00')
        ->and($availability->configuration()['message'])->toBe('Legacy away message');
});

it('accepts an already opened legacy admin form during rollout', function () {
    $this->actingAs($this->agent)->putJson(route('admin.support.availability.update'), [
        'enabled' => true, 'days' => ['monday', 'tuesday'],
        'start_time' => '11:00', 'end_time' => '19:00', 'message' => 'Away',
    ])->assertOk()->assertJsonPath('data.hours.monday.start_time', '11:00')
        ->assertJsonPath('data.hours.tuesday.end_time', '19:00');
});

it('uses the global form handler and named fields instead of a bootstrap save callback', function () {
    $template = file_get_contents(resource_path('views/admin/support/index.blade.php'));
    $script = file_get_contents(public_path('assets/js/support-chat.js'));
    $html = view('components.weekly-schedule', [
        'name' => '', 'schedule' => app(SupportAvailabilityService::class)->configuration(),
        'attributes' => new ComponentAttributeBag,
    ])->render();

    expect($template)->toContain('id="support-availability-form" class="modal-content form-submit"')
        ->toContain('@method(\'PUT\')', 'name="message"')
        ->and($script)->not->toContain("availabilityForm?.addEventListener('submit'", "bootstrap.Modal.getInstance(el('support-availability-modal'))")
        ->and($html)->toContain('name="enabled"', 'name="days[]"', 'name="hours[monday][start_time]"', 'name="hours[sunday][end_time]"')
        ->not->toContain('The same hours apply to every selected day.');
});

it('sends an away reply based on the current weekday rather than a shared time', function () {
    app(SupportAvailabilityService::class)->save([
        'enabled' => true, 'days' => ['monday', 'tuesday'],
        'hours' => [
            'monday' => ['start_time' => '09:00', 'end_time' => '17:00'],
            'tuesday' => ['start_time' => '12:00', 'end_time' => '20:00'],
        ],
    ]);
    $this->travelTo(CarbonImmutable::parse('2026-09-07 18:00:00', 'Asia/Kolkata'));
    $monday = $this->support->startSession($this->customer, $this->sessionData);
    expect($monday->messages()->where('type', 'auto_reply')->count())->toBe(1);
    $this->support->resolve($monday, $this->customer, 'user', null);

    $this->travelTo(CarbonImmutable::parse('2026-09-08 18:00:00', 'Asia/Kolkata'));
    $tuesday = $this->support->startSession($this->customer, $this->sessionData);
    expect($tuesday->messages()->where('type', 'auto_reply')->exists())->toBeFalse();
    $this->travelTo(CarbonImmutable::parse('2026-09-08 20:00:00', 'Asia/Kolkata'));
    $this->support->addMessage($tuesday, $this->customer, 'user', ['message' => 'Any updates?']);
    expect($tuesday->messages()->where('type', 'auto_reply')->count())->toBe(1);
});

it('validates the schedule before saving', function (array $invalid, string $field) {
    $data = array_replace(config('support.availability'), $invalid);
    $res = $this->actingAs($this->agent)->putJson(route('admin.support.availability.update'), $data);
    $res->assertUnprocessable()->assertJsonValidationErrors($field);
    expect(Setting::where('variable', 'support')->exists())->toBeFalse();
})->with([
    'no days' => [['days' => []], 'days'],
    'unknown day' => [['days' => ['holiday']], 'days.0'],
    'duplicate day' => [['days' => ['monday', 'monday']], 'days.0'],
    'equal times' => [['hours' => ['monday' => ['start_time' => '09:00', 'end_time' => '09:00']]], 'hours.monday.end_time'],
    'overnight' => [['hours' => ['monday' => ['start_time' => '09:00', 'end_time' => '08:00']]], 'hours.monday.end_time'],
    'invalid time' => [['hours' => ['monday' => ['start_time' => '25:00', 'end_time' => '18:00']]], 'hours.monday.start_time'],
    'missing selected day' => [['hours' => ['tuesday' => ['start_time' => '09:00', 'end_time' => '18:00']]], 'hours.monday.start_time'],
    'unknown hours day' => [['hours' => ['holiday' => ['start_time' => '09:00', 'end_time' => '18:00']]], 'hours'],
    'no message' => [['message' => ''], 'message'],
    'long message' => [['message' => str_repeat('a', 2001)], 'message'],
]);

it('creates and broadcasts a support bubble without replacing the customer notification or preview', function () {
    $ticket = $this->support->startSession($this->customer, $this->sessionData);
    $messages = $ticket->messages()->orderBy('id')->get();
    expect($messages)->toHaveCount(2)
        ->and($messages[0]->send_by)->toBe('user')
        ->and($messages[1]->type)->toBe('auto_reply');
    $presented = SupportPresenter::message($messages[1]);
    expect($presented['sender_role'])->toBe('system')
        ->and($presented['sender_name'])->toBe('Customer Support')
        ->and($presented['sender_avatar'])->toBeNull()
        ->and($presented['text'])->toBe(config('support.availability.message'))
        ->and($presented['metadata'])->toBe(['automated' => true, 'source' => 'support_availability']);
    Event::assertDispatched(SupportActivityCreated::class, fn ($event) => $event->activity === 'session.created' && $event->messageId === $messages[0]->id);
    Event::assertDispatched(SupportActivityCreated::class, fn ($event) => $event->activity === 'message.created' && $event->messageId === $messages[1]->id);
    Notification::assertSentTo($this->agent, SupportAdminMessageReceived::class, fn ($notification) => $notification->message->id === $messages[0]->id);
    Notification::assertNotSentTo($this->customer, SupportMessageReceived::class);
    $this->actingAs($this->agent)->getJson(route('admin.support.inbox'))
        ->assertOk()->assertJsonPath('data.0.preview', $this->sessionData['message'])->assertJsonPath('data.0.unread_count', 1);
    Sanctum::actingAs($this->customer);
    $this->getJson('/api/user/support/thread/updates?after_message_id='.$messages[0]->id)
        ->assertOk()->assertJsonPath('data.messages.0.type', 'auto_reply');
});

it('deduplicates away replies during the cooldown including retries and deleted replies but allows the next session', function () {
    $ticket = $this->support->startSession($this->customer, $this->sessionData);
    $data = ['message' => 'More details', 'client_message_id' => fake()->uuid()];
    $first = $this->support->addMessage($ticket, $this->customer, 'user', $data);
    $retry = $this->support->addMessage($ticket, $this->customer, 'user', $data);
    expect($retry->id)->toBe($first->id)
        ->and($ticket->messages()->where('type', 'auto_reply')->count())->toBe(1);
    $ticket->messages()->where('type', 'auto_reply')->delete();
    $this->support->addMessage($ticket, $this->customer, 'user', ['message' => 'One more detail']);
    expect($ticket->messages()->withTrashed()->where('type', 'auto_reply')->count())->toBe(1);
    $this->support->resolve($ticket, $this->customer, 'user', null);
    $next = $this->support->startSession($this->customer, $this->sessionData);
    expect($next->assigned_to)->toBeNull()
        ->and($next->messages()->where('type', 'auto_reply')->count())->toBe(1);
});

it('sends another outside-hours reply in an ongoing chat after the cooldown', function () {
    $ticket = $this->support->startSession($this->customer, $this->sessionData);
    $this->support->assign($ticket, $this->agent, $this->agent);
    $data = ['message' => 'Are there any updates?', 'client_message_id' => fake()->uuid()];
    $this->travel(29)->minutes();
    $this->support->addMessage($ticket, $this->customer, 'user', $data);
    expect($ticket->messages()->where('type', 'auto_reply')->count())->toBe(1);

    $this->travel(1)->minutes();
    $this->support->addMessage($ticket, $this->customer, 'user', $data);
    expect($ticket->messages()->where('type', 'auto_reply')->count())->toBe(1);

    Sanctum::actingAs($this->customer);
    $sent = $this->postJson('/api/user/support/sessions/'.$ticket->slug.'/messages', [
        'message' => 'Please check again', 'client_message_id' => fake()->uuid(),
    ])->assertCreated()->json('data');
    $away = $ticket->messages()->where('type', 'auto_reply')->reorder('id', 'desc')->firstOrFail();
    expect($ticket->messages()->where('type', 'auto_reply')->count())->toBe(2);
    Event::assertDispatched(SupportActivityCreated::class, fn ($event) => $event->messageId === $away->id && $event->activity === 'message.created');
    $this->getJson('/api/user/support/thread/updates?after_message_id='.$sent['id'])
        ->assertOk()->assertJsonPath('data.messages.0.type', 'auto_reply');

    $this->support->addMessage($ticket, $this->customer, 'user', ['message' => 'An extra detail']);
    expect($ticket->messages()->where('type', 'auto_reply')->count())->toBe(2);
});

it('sends outside-hours reminders on a later day without needing a new ticket', function () {
    $ticket = $this->support->startSession($this->customer, $this->sessionData);
    $this->travelTo(CarbonImmutable::parse('2026-09-08 12:00:00', 'Asia/Kolkata'));
    $this->support->addMessage($ticket, $this->customer, 'user', ['message' => 'Hello during office hours']);
    expect($ticket->messages()->where('type', 'auto_reply')->count())->toBe(1);

    $this->travelTo(CarbonImmutable::parse('2026-09-08 20:00:00', 'Asia/Kolkata'));
    $this->support->addMessage($ticket, $this->customer, 'user', ['message' => 'Following up tonight']);
    expect($ticket->messages()->where('type', 'auto_reply')->count())->toBe(2)
        ->and($ticket->fresh()->status)->toBe('open');
});

it('waits for the first outside-hours customer message and never responds to an agent', function () {
    $this->travelTo(CarbonImmutable::parse('2026-09-07 10:00:00', 'Asia/Kolkata'));
    $ticket = $this->support->startSession($this->customer, $this->sessionData);
    expect($ticket->messages()->count())->toBe(1);
    $this->support->assign($ticket, $this->agent, $this->agent);
    $this->travelTo(CarbonImmutable::parse('2026-09-07 18:00:00', 'Asia/Kolkata'));
    $this->support->addMessage($ticket, $this->agent, 'admin', ['message' => 'We are checking']);
    expect($ticket->messages()->where('type', 'auto_reply')->exists())->toBeFalse();
    $this->support->addMessage($ticket, $this->customer, 'user', ['message' => 'Any news?']);
    expect($ticket->messages()->where('type', 'auto_reply')->count())->toBe(1);
});

it('does not send away replies when always available', function () {
    app(SupportAvailabilityService::class)->save(['enabled' => false]);
    $ticket = $this->support->startSession($this->customer, $this->sessionData);
    $this->support->addMessage($ticket, $this->customer, 'user', ['message' => 'Hello again']);
    expect($ticket->messages()->where('type', 'auto_reply')->exists())->toBeFalse();
});
