<?php

use App\Enums\SettingTypeEnum;
use App\Jobs\SendWhatsAppMessageJob;
use App\Models\DeliveryZone;
use App\Models\Order;
use App\Models\Setting;
use App\Models\User;
use App\Models\WhatsAppMessage;
use App\Models\WhatsAppTemplate;
use App\Services\SettingService;
use App\Support\WhatsAppTemplateCatalog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->withoutMiddleware();
    Gate::before(fn () => true);
    Bus::fake();
    saveWhatsAppFeatureSettings();
});

function saveWhatsAppFeatureSettings(array $overrides = []): void
{
    Setting::updateOrCreate(
        ['variable' => SettingTypeEnum::NOTIFICATION()],
        ['value' => json_encode(array_merge([
            'whatsappEnabled' => true,
            'whatsappPhoneNumberId' => '123456789',
            'whatsappBusinessAccountId' => '987654321',
            'whatsappAccessToken' => 'secret-token',
            'whatsappWebhookVerifyToken' => 'verify-token',
            'whatsappDefaultLanguage' => 'en_US',
            'enableAbandonedCartNotification' => true,
            'abandonedCartNotificationDelayMinutes' => 60,
            'abandonedCartNotificationChannels' => ['push'],
        ], $overrides))]
    );

    app(SettingService::class)->clearSettingCache(SettingTypeEnum::NOTIFICATION());
}

function createApprovedWhatsAppTemplate(array $overrides = []): WhatsAppTemplate
{
    return WhatsAppTemplate::create(array_merge([
        'module' => WhatsAppTemplateCatalog::CUSTOMER_ORDER_DELIVERED_INVOICE,
        'template_name' => 'order_invoice',
        'language' => 'en_US',
        'parameter_format' => WhatsAppTemplateCatalog::PARAMETER_FORMAT_POSITIONAL,
        'variables' => [
            '1' => 'customer_name',
            '2' => 'order_number',
            '3' => 'total_amount',
            '4' => 'invoice_url',
        ],
        'status' => WhatsAppTemplateCatalog::STATUS_APPROVED,
        'is_active' => true,
    ], $overrides));
}

it('sends the configured whatsapp test template', function () {
    createApprovedWhatsAppTemplate();

    Http::fake([
        'graph.facebook.com/*' => Http::response(['messages' => [['id' => 'wamid.test']]], 200),
    ]);

    $this->actingAs(User::factory()->create(['access_panel' => 'admin']))
        ->postJson(route('admin.settings.notification.test-whatsapp'), [
            'test_mobile' => '+919876543210',
            'module' => WhatsAppTemplateCatalog::CUSTOMER_ORDER_DELIVERED_INVOICE,
        ])
        ->assertOk()
        ->assertJsonPath('success', true);
});

it('queues an invoice whatsapp message for an authorized order', function () {
    createApprovedWhatsAppTemplate();
    $user = User::factory()->create(['mobile' => '+919876543210']);
    $admin = User::factory()->create(['access_panel' => 'admin']);
    $order = createWhatsAppInvoiceOrder($user);

    $this->actingAs($admin)
        ->postJson(route('admin.orders.invoice.whatsapp'), ['id' => $order->uuid])
        ->assertOk()
        ->assertJsonPath('success', true);

    $message = WhatsAppMessage::where('order_id', $order->id)->first();

    expect($message)->not->toBeNull()
        ->and($message->module)->toBe(WhatsAppTemplateCatalog::CUSTOMER_ORDER_DELIVERED_INVOICE)
        ->and($message->template_name)->toBe('order_invoice');

    Bus::assertDispatched(SendWhatsAppMessageJob::class);
});

it('skips invoice whatsapp when no approved active template exists', function () {
    $user = User::factory()->create(['mobile' => '+919876543210']);
    $admin = User::factory()->create(['access_panel' => 'admin']);
    $order = createWhatsAppInvoiceOrder($user);

    $this->actingAs($admin)
        ->postJson(route('admin.orders.invoice.whatsapp'), ['id' => $order->uuid])
        ->assertStatus(422)
        ->assertJsonPath('success', false);

    expect(WhatsAppMessage::where('order_id', $order->id)->first()?->status)->toBe('skipped');
});

it('saves whatsapp template mappings from notification settings', function () {
    $this->actingAs(User::factory()->create(['access_panel' => 'admin']))
        ->postJson(route('admin.settings.notification.whatsapp-templates.save'), [
            'templates' => [
                WhatsAppTemplateCatalog::SELLER_NEW_ORDER_RECEIVED => [
                    'module' => WhatsAppTemplateCatalog::SELLER_NEW_ORDER_RECEIVED,
                    'template_name' => 'seller_new_order',
                    'language' => 'en_US',
                    'parameter_format' => WhatsAppTemplateCatalog::PARAMETER_FORMAT_NAMED,
                    'is_active' => true,
                    'variables' => json_encode(['seller_name' => 'seller_name', 'order_number' => 'order_number']),
                ],
            ],
        ])
        ->assertOk()
        ->assertJsonPath('success', true);

    $template = WhatsAppTemplate::where('module', WhatsAppTemplateCatalog::SELLER_NEW_ORDER_RECEIVED)->first();

    expect($template)->not->toBeNull()
        ->and($template->parameter_format)->toBe(WhatsAppTemplateCatalog::PARAMETER_FORMAT_NAMED)
        ->and($template->is_active)->toBeTrue();
});

it('syncs one configured whatsapp template from meta', function () {
    $template = createApprovedWhatsAppTemplate([
        'status' => WhatsAppTemplateCatalog::STATUS_DRAFT,
        'meta_template_id' => null,
    ]);

    Http::fake([
        'graph.facebook.com/v20.0/987654321/message_templates*' => Http::response([
            'data' => [[
                'id' => 'tpl_invoice',
                'name' => 'order_invoice',
                'language' => 'en_US',
                'status' => 'APPROVED',
                'category' => 'UTILITY',
                'parameter_format' => 'POSITIONAL',
                'quality_score' => ['score' => 'GREEN'],
                'components' => [['type' => 'BODY', 'text' => 'Hi {{1}}']],
            ]],
        ], 200),
    ]);

    $this->actingAs(User::factory()->create(['access_panel' => 'admin']))
        ->postJson(route('admin.settings.notification.whatsapp-templates.sync'), [
            'module' => WhatsAppTemplateCatalog::CUSTOMER_ORDER_DELIVERED_INVOICE,
        ])
        ->assertOk()
        ->assertJsonPath('success', true);

    $template->refresh();

    expect($template->meta_template_id)->toBe('tpl_invoice')
        ->and($template->status)->toBe(WhatsAppTemplateCatalog::STATUS_APPROVED)
        ->and($template->quality_score)->toBe('GREEN')
        ->and($template->last_synced_at)->not->toBeNull();
});

it('syncs all configured whatsapp templates and ignores unrelated meta templates', function () {
    createApprovedWhatsAppTemplate([
        'status' => WhatsAppTemplateCatalog::STATUS_DRAFT,
        'meta_template_id' => null,
    ]);

    WhatsAppTemplate::create([
        'module' => WhatsAppTemplateCatalog::SELLER_NEW_ORDER_RECEIVED,
        'template_name' => 'seller_new_order',
        'language' => 'en_US',
        'parameter_format' => WhatsAppTemplateCatalog::PARAMETER_FORMAT_NAMED,
        'variables' => ['seller_name' => 'seller_name'],
        'status' => WhatsAppTemplateCatalog::STATUS_DRAFT,
        'is_active' => true,
    ]);

    Http::fake([
        'graph.facebook.com/v20.0/987654321/message_templates*' => Http::response([
            'data' => [
                ['id' => 'tpl_unrelated', 'name' => 'unrelated_template', 'language' => 'en_US', 'status' => 'APPROVED'],
                ['id' => 'tpl_invoice', 'name' => 'order_invoice', 'language' => 'en_US', 'status' => 'APPROVED', 'parameter_format' => 'POSITIONAL'],
                ['id' => 'tpl_seller', 'name' => 'seller_new_order', 'language' => 'en_US', 'status' => 'PENDING', 'parameter_format' => 'NAMED'],
            ],
        ], 200),
    ]);

    $this->actingAs(User::factory()->create(['access_panel' => 'admin']))
        ->postJson(route('admin.settings.notification.whatsapp-templates.sync'))
        ->assertOk()
        ->assertJsonPath('success', true);

    expect(WhatsAppTemplate::where('meta_template_id', 'tpl_unrelated')->exists())->toBeFalse()
        ->and(WhatsAppTemplate::where('module', WhatsAppTemplateCatalog::CUSTOMER_ORDER_DELIVERED_INVOICE)->first()->status)->toBe(WhatsAppTemplateCatalog::STATUS_APPROVED)
        ->and(WhatsAppTemplate::where('module', WhatsAppTemplateCatalog::SELLER_NEW_ORDER_RECEIVED)->first()->status)->toBe(WhatsAppTemplateCatalog::STATUS_SUBMITTED);
});

it('returns a clear failure when meta sync credentials are missing', function () {
    saveWhatsAppFeatureSettings(['whatsappBusinessAccountId' => '', 'whatsappAccessToken' => '']);
    createApprovedWhatsAppTemplate();

    $this->actingAs(User::factory()->create(['access_panel' => 'admin']))
        ->postJson(route('admin.settings.notification.whatsapp-templates.sync'))
        ->assertStatus(422)
        ->assertJsonPath('success', false);
});

it('does not send rejected templates even when active', function () {
    $user = User::factory()->create(['mobile' => '+919876543210']);
    $admin = User::factory()->create(['access_panel' => 'admin']);
    $order = createWhatsAppInvoiceOrder($user);

    createApprovedWhatsAppTemplate([
        'status' => WhatsAppTemplateCatalog::STATUS_REJECTED,
        'is_active' => true,
    ]);

    $this->actingAs($admin)
        ->postJson(route('admin.orders.invoice.whatsapp'), ['id' => $order->uuid])
        ->assertStatus(422)
        ->assertJsonPath('success', false);

    expect(WhatsAppMessage::where('order_id', $order->id)->first()?->status)->toBe('skipped');
    Bus::assertNotDispatched(SendWhatsAppMessageJob::class);
});

it('does not render the custom notification whatsapp option', function () {
    $contents = file_get_contents(resource_path('views/admin/app_notifications/index.blade.php'));

    expect($contents)->not->toContain('send_whatsapp');
});

it('rejects invoice whatsapp when the order does not exist', function () {
    $this->actingAs(User::factory()->create(['access_panel' => 'admin']))
        ->postJson(route('admin.orders.invoice.whatsapp'), ['id' => 'missing-order'])
        ->assertNotFound();
});

it('verifies and applies whatsapp webhook statuses', function () {
    $message = WhatsAppMessage::create([
        'module' => 'invoice',
        'recipient_mobile' => '919876543210',
        'template_name' => 'order_invoice',
        'language' => 'en_US',
        'provider_message_id' => 'wamid.test',
        'status' => 'sent',
    ]);

    $this->get('/api/whatsapp/webhook?hub_mode=subscribe&hub_verify_token=verify-token&hub_challenge=abc123')
        ->assertOk()
        ->assertSee('abc123');

    $this->postJson('/api/whatsapp/webhook', [
        'entry' => [[
            'changes' => [[
                'value' => [
                    'statuses' => [[
                        'id' => 'wamid.test',
                        'status' => 'delivered',
                        'timestamp' => (string) now()->timestamp,
                    ]],
                ],
            ]],
        ]],
    ])->assertOk();

    expect($message->fresh()->status)->toBe('delivered')
        ->and($message->fresh()->delivered_at)->not->toBeNull();
});

function createWhatsAppInvoiceOrder(User $user): Order
{
    $zone = DeliveryZone::factory()->create();

    return Order::create([
        'uuid' => (string) Str::uuid(),
        'user_id' => $user->id,
        'slug' => 'order-' . Str::lower(Str::random(10)),
        'email' => $user->email,
        'ip_address' => '127.0.0.1',
        'currency_code' => 'USD',
        'currency_rate' => 1,
        'payment_method' => 'cod',
        'payment_status' => 'pending',
        'delivery_zone_id' => $zone->id,
        'wallet_balance' => 0,
        'subtotal' => 100,
        'total_payable' => 100,
        'final_total' => 100,
        'billing_name' => $user->name,
        'billing_address_1' => 'Billing street',
        'billing_landmark' => 'Near park',
        'billing_zip' => '12345',
        'billing_phone' => '+919876543210',
        'billing_address_type' => 'home',
        'billing_latitude' => 12.97159888,
        'billing_longitude' => 77.59456666,
        'billing_city' => 'City',
        'billing_state' => 'State',
        'billing_country' => 'India',
        'billing_country_code' => 'IN',
        'shipping_name' => $user->name,
        'shipping_address_1' => 'Shipping street',
        'shipping_landmark' => 'Near park',
        'shipping_zip' => '12345',
        'shipping_phone' => '+919876543210',
        'shipping_address_type' => 'home',
        'shipping_latitude' => 12.97159888,
        'shipping_longitude' => 77.59456666,
        'shipping_city' => 'City',
        'shipping_state' => 'State',
        'shipping_country' => 'India',
        'shipping_country_code' => 'IN',
    ]);
}
