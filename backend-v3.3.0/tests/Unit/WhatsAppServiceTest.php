<?php

use App\Enums\SettingTypeEnum;
use App\Models\Setting;
use App\Models\WhatsAppTemplate;
use App\Services\SettingService;
use App\Services\WhatsAppService;
use App\Support\WhatsAppTemplateCatalog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request as ClientRequest;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

function saveWhatsAppTestSettings(array $overrides = []): void
{
    Setting::updateOrCreate(
        ['variable' => SettingTypeEnum::NOTIFICATION()],
        ['value' => json_encode(array_merge([
            'whatsappEnabled' => true,
            'whatsappPhoneNumberId' => '123456789',
            'whatsappBusinessAccountId' => '987654321',
            'whatsappAccessToken' => 'secret-token',
            'whatsappDefaultLanguage' => 'en_US',
            'enableAbandonedCartNotification' => true,
            'abandonedCartNotificationDelayMinutes' => 60,
            'abandonedCartNotificationChannels' => ['push'],
        ], $overrides))]
    );

    app(SettingService::class)->clearSettingCache(SettingTypeEnum::NOTIFICATION());
}

it('builds template payloads with text body parameters', function () {
    $payload = app(WhatsAppService::class)->templatePayload(
        recipient: '919876543210',
        templateName: 'custom_notification',
        language: 'en_US',
        parameters: ['Title', 'Body', 'https://example.test']
    );

    expect($payload['messaging_product'])->toBe('whatsapp')
        ->and($payload['to'])->toBe('919876543210')
        ->and($payload['template']['name'])->toBe('custom_notification')
        ->and($payload['template']['components'][0]['parameters'])->toHaveCount(3);
});

it('builds template payloads with named body parameters', function () {
    $payload = app(WhatsAppService::class)->templatePayload(
        recipient: '919876543210',
        templateName: 'order_invoice',
        language: 'en_US',
        parameters: ['customer_name' => 'John', 'order_number' => 'ORD-1'],
        parameterFormat: WhatsAppTemplateCatalog::PARAMETER_FORMAT_NAMED
    );

    expect($payload['template']['components'][0]['parameters'][0]['parameter_name'])->toBe('customer_name')
        ->and($payload['template']['components'][0]['parameters'][1]['text'])->toBe('ORD-1');
});

it('resolves positional and named template mappings', function () {
    $service = app(WhatsAppService::class);

    $positional = WhatsAppTemplate::create([
        'module' => WhatsAppTemplateCatalog::CUSTOMER_ORDER_DELIVERED_INVOICE,
        'template_name' => 'order_invoice',
        'language' => 'en_US',
        'parameter_format' => WhatsAppTemplateCatalog::PARAMETER_FORMAT_POSITIONAL,
        'variables' => ['2' => 'order_number', '1' => 'customer_name'],
        'status' => WhatsAppTemplateCatalog::STATUS_APPROVED,
        'is_active' => true,
    ]);

    $named = WhatsAppTemplate::create([
        'module' => WhatsAppTemplateCatalog::SELLER_NEW_ORDER_RECEIVED,
        'template_name' => 'seller_order',
        'language' => 'en_US',
        'parameter_format' => WhatsAppTemplateCatalog::PARAMETER_FORMAT_NAMED,
        'variables' => ['seller_name' => 'seller.name', 'order_number' => 'order.number'],
        'status' => WhatsAppTemplateCatalog::STATUS_APPROVED,
        'is_active' => true,
    ]);

    expect($service->resolveTemplateParameters($positional, [
        'customer_name' => 'John',
        'order_number' => 'ORD-1',
    ]))->toBe(['John', 'ORD-1'])
        ->and($service->resolveTemplateParameters($named, [
            'seller' => ['name' => 'Demo Seller'],
            'order' => ['number' => 'ORD-2'],
        ]))->toBe(['seller_name' => 'Demo Seller', 'order_number' => 'ORD-2']);
});

it('maps meta template statuses to local statuses', function () {
    $service = app(WhatsAppService::class);

    expect($service->metaStatusToLocalStatus('APPROVED'))->toBe(WhatsAppTemplateCatalog::STATUS_APPROVED)
        ->and($service->metaStatusToLocalStatus('ACTIVE'))->toBe(WhatsAppTemplateCatalog::STATUS_APPROVED)
        ->and($service->metaStatusToLocalStatus('PENDING'))->toBe(WhatsAppTemplateCatalog::STATUS_SUBMITTED)
        ->and($service->metaStatusToLocalStatus('IN_APPEAL'))->toBe(WhatsAppTemplateCatalog::STATUS_SUBMITTED)
        ->and($service->metaStatusToLocalStatus('REJECTED'))->toBe(WhatsAppTemplateCatalog::STATUS_REJECTED)
        ->and($service->metaStatusToLocalStatus('PAUSED'))->toBe(WhatsAppTemplateCatalog::STATUS_PAUSED)
        ->and($service->metaStatusToLocalStatus('DISABLED'))->toBe(WhatsAppTemplateCatalog::STATUS_PAUSED)
        ->and($service->metaStatusToLocalStatus('UNKNOWN'))->toBe(WhatsAppTemplateCatalog::STATUS_DRAFT);
});

it('syncs only configured meta templates by exact name and language', function () {
    saveWhatsAppTestSettings();

    $template = WhatsAppTemplate::create([
        'module' => WhatsAppTemplateCatalog::CUSTOMER_ORDER_DELIVERED_INVOICE,
        'template_name' => 'order_invoice',
        'language' => 'en_US',
        'parameter_format' => WhatsAppTemplateCatalog::PARAMETER_FORMAT_POSITIONAL,
        'variables' => ['1' => 'customer_name'],
        'status' => WhatsAppTemplateCatalog::STATUS_DRAFT,
        'is_active' => true,
    ]);

    Http::fake([
        'graph.facebook.com/v20.0/987654321/message_templates*' => Http::response([
            'data' => [
                [
                    'id' => 'tpl_unrelated',
                    'name' => 'not_used',
                    'language' => 'en_US',
                    'status' => 'APPROVED',
                ],
                [
                    'id' => 'tpl_invoice',
                    'name' => 'order_invoice',
                    'language' => 'en_US',
                    'status' => 'APPROVED',
                    'category' => 'UTILITY',
                    'parameter_format' => 'POSITIONAL',
                    'quality_score' => ['score' => 'GREEN'],
                    'components' => [['type' => 'BODY', 'text' => 'Hi {{1}}']],
                ],
            ],
        ], 200),
    ]);

    $result = app(WhatsAppService::class)->syncConfiguredTemplates();
    $template->refresh();

    expect($result['success'])->toBeTrue()
        ->and($template->meta_template_id)->toBe('tpl_invoice')
        ->and($template->status)->toBe(WhatsAppTemplateCatalog::STATUS_APPROVED)
        ->and($template->category)->toBe('UTILITY')
        ->and($template->quality_score)->toBe('GREEN')
        ->and($template->components)->toHaveCount(1)
        ->and($template->last_synced_at)->not->toBeNull();
});

it('syncs a unique matching template even when meta returns a different english language code', function () {
    saveWhatsAppTestSettings();

    $template = WhatsAppTemplate::create([
        'module' => WhatsAppTemplateCatalog::CUSTOMER_ORDER_PLACED,
        'template_name' => 'customer_order_placed',
        'language' => 'en_US',
        'parameter_format' => WhatsAppTemplateCatalog::PARAMETER_FORMAT_POSITIONAL,
        'variables' => ['1' => 'customer_name'],
        'status' => WhatsAppTemplateCatalog::STATUS_DRAFT,
        'is_active' => true,
    ]);

    Http::fake([
        'graph.facebook.com/v20.0/987654321/message_templates*' => Http::response([
            'data' => [
                [
                    'id' => 'tpl_customer_order_placed',
                    'name' => ' Customer Order Placed ',
                    'language' => 'en',
                    'status' => 'ACTIVE',
                    'category' => 'UTILITY',
                    'parameter_format' => 'POSITIONAL',
                ],
            ],
        ], 200),
    ]);

    $result = app(WhatsAppService::class)->syncConfiguredTemplates(WhatsAppTemplateCatalog::CUSTOMER_ORDER_PLACED);
    $template->refresh();

    expect($result['success'])->toBeTrue()
        ->and($template->meta_template_id)->toBe('tpl_customer_order_placed')
        ->and($template->language)->toBe('en')
        ->and($template->status)->toBe(WhatsAppTemplateCatalog::STATUS_APPROVED);
});

it('refreshes synced templates by meta template id', function () {
    saveWhatsAppTestSettings();

    $template = WhatsAppTemplate::create([
        'module' => WhatsAppTemplateCatalog::CUSTOMER_ORDER_DELIVERED_INVOICE,
        'meta_template_id' => 'tpl_invoice',
        'template_name' => 'order_invoice',
        'language' => 'en_US',
        'parameter_format' => WhatsAppTemplateCatalog::PARAMETER_FORMAT_POSITIONAL,
        'variables' => ['1' => 'customer_name'],
        'status' => WhatsAppTemplateCatalog::STATUS_APPROVED,
        'is_active' => true,
    ]);

    Http::fake([
        'graph.facebook.com/v20.0/tpl_invoice*' => Http::response([
            'id' => 'tpl_invoice',
            'name' => 'order_invoice',
            'language' => 'en_US',
            'status' => 'REJECTED',
            'parameter_format' => 'POSITIONAL',
            'rejected_reason' => 'INVALID_FORMAT',
        ], 200),
        'graph.facebook.com/v20.0/987654321/message_templates*' => Http::response(['data' => []], 200),
    ]);

    $result = app(WhatsAppService::class)->syncConfiguredTemplates(WhatsAppTemplateCatalog::CUSTOMER_ORDER_DELIVERED_INVOICE);
    $template->refresh();

    expect($result['success'])->toBeTrue()
        ->and($template->status)->toBe(WhatsAppTemplateCatalog::STATUS_REJECTED)
        ->and($template->rejected_reason)->toBe('INVALID_FORMAT');

    Http::assertNotSent(fn (ClientRequest $request) => str_contains($request->url(), '/987654321/message_templates'));
});

it('stores meta sync errors without exposing tokens', function () {
    saveWhatsAppTestSettings();

    $template = WhatsAppTemplate::create([
        'module' => WhatsAppTemplateCatalog::CUSTOMER_ORDER_DELIVERED_INVOICE,
        'template_name' => 'order_invoice',
        'language' => 'en_US',
        'parameter_format' => WhatsAppTemplateCatalog::PARAMETER_FORMAT_POSITIONAL,
        'variables' => ['1' => 'customer_name'],
        'status' => WhatsAppTemplateCatalog::STATUS_DRAFT,
        'is_active' => true,
    ]);

    Http::fake([
        'graph.facebook.com/*' => Http::response([
            'error' => ['message' => 'Invalid OAuth access token', 'code' => 190],
        ], 401),
    ]);

    $result = app(WhatsAppService::class)->syncConfiguredTemplates();

    expect($result['success'])->toBeFalse()
        ->and($template->fresh()->sync_error)->toBe('Invalid OAuth access token');
});

it('normalizes mobile numbers for cloud api recipients', function () {
    $service = app(WhatsAppService::class);

    expect($service->normalizeMobile('+91 98765 43210'))->toBe('919876543210')
        ->and($service->normalizeMobile('abc'))->toBeNull();
});

it('sends template messages through the meta endpoint', function () {
    saveWhatsAppTestSettings();
    Http::fake([
        'graph.facebook.com/*' => Http::response([
            'messages' => [['id' => 'wamid.test']],
        ], 200),
    ]);

    $result = app(WhatsAppService::class)->sendTemplate(
        mobile: '+919876543210',
        templateName: 'custom_notification',
        parameters: ['Title', 'Body', 'Link']
    );

    expect($result['success'])->toBeTrue()
        ->and($result['provider_message_id'])->toBe('wamid.test');

    Http::assertSent(fn (ClientRequest $request) => $request->url() === 'https://graph.facebook.com/v20.0/123456789/messages'
        && $request->hasHeader('Authorization', 'Bearer secret-token')
        && $request['to'] === '919876543210'
        && $request['template']['name'] === 'custom_notification');
});

it('does not send when whatsapp is disabled', function () {
    saveWhatsAppTestSettings(['whatsappEnabled' => false]);
    Http::fake();

    $result = app(WhatsAppService::class)->sendTemplate(
        mobile: '+919876543210',
        templateName: 'custom_notification',
        parameters: ['Title']
    );

    expect($result['success'])->toBeFalse();
    Http::assertNothingSent();
});

it('masks sensitive keys in nested provider payloads', function () {
    $masked = app(WhatsAppService::class)->maskSensitiveData([
        'access_token' => 'secret',
        'nested' => ['token' => 'secret'],
    ]);

    expect($masked['access_token'])->toBe('***')
        ->and($masked['nested']['token'])->toBe('***');
});
