@extends('layouts.admin.app',['page' => $menuAdmin['settings']['active'] ?? "", 'sub_page' => 'whatsapp' ])

@section('title', 'WhatsApp Settings')

@section('header_data')
    @php
        $page_title = 'WhatsApp Settings';
        $page_pretitle = __('labels.admin') . " " . __('labels.settings');
    @endphp
@endsection

@php
    $breadcrumbs = [
        ['title' => __('labels.home'), 'url' => route('admin.dashboard')],
        ['title' => __('labels.settings'), 'url' => route('admin.settings.index')],
        ['title' => 'WhatsApp Settings', 'url' => null],
    ];

    $infoButton = fn (string $message) => '<button type="button" class="alert-icon btn ms-1 p-1 rounded-5" data-bs-toggle="tooltip" data-bs-placement="right" data-bs-title="' . e($message) . '">!</button>';
@endphp

@section('admin-content')
    <div class="page-header d-print-none">
        <div class="row g-2 align-items-center">
            <div class="col">
                <h2 class="page-title">WhatsApp Settings</h2>
                <x-breadcrumb :items="$breadcrumbs"/>
            </div>
        </div>
    </div>
    <div class="page-body">
        <div class="container-xl">
            <form action="{{ route('admin.settings.store') }}" class="form-submit" method="post">
                @csrf
                <input type="hidden" name="type" value="notification">
                <input type="hidden" name="firebaseProjectId" value="{{ $settings['firebaseProjectId'] ?? '' }}">
                <input type="hidden" name="vapIdKey" value="{{ $settings['vapIdKey'] ?? '' }}">
                <input type="hidden" name="enableAbandonedCartNotification" value="{{ (int) ($settings['enableAbandonedCartNotification'] ?? true) }}">
                <input type="hidden" name="abandonedCartNotificationDelayMinutes" value="{{ $settings['abandonedCartNotificationDelayMinutes'] ?? 60 }}">
                @foreach(($settings['abandonedCartNotificationChannels'] ?? ['push']) as $channel)
                    <input type="hidden" name="abandonedCartNotificationChannels[]" value="{{ $channel }}">
                @endforeach
                <div class="card mb-4">
                    <div class="card-header">
                        <h4 class="card-title">Meta API Credentials</h4>
                    </div>
                    <div class="card-body">
                        <div class="alert alert-info d-flex align-items-start gap-2" role="alert">
                            <i class="ti ti-info-circle fs-2"></i>
                            <div>
                                Add templates in Meta first, wait for approval, then paste the exact template name here and map each app event to that template. This app sends approved templates; it does not create Meta templates.
                            </div>
                        </div>

                        <div class="mb-4">
                            <label class="row">
                                <span class="col">
                                    Enable WhatsApp
                                    {!! $infoButton('Turns WhatsApp sending on or off for all configured event templates. If disabled, no WhatsApp messages are sent.') !!}
                                    <span class="form-hint">Use approved Meta templates for configured customer, seller, and rider events.</span>
                                </span>
                                <span class="col-auto">
                                    <label class="form-check form-check-single form-switch">
                                        <input type="hidden" name="whatsappEnabled" value="0">
                                        <input class="form-check-input" type="checkbox"
                                               name="whatsappEnabled" value="1"
                                               @checked(old('whatsappEnabled', $settings['whatsappEnabled'] ?? false))>
                                    </label>
                                </span>
                            </label>
                        </div>

                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Phone Number ID {!! $infoButton('Meta Cloud API phone number ID. It identifies which WhatsApp number will send messages.') !!}</label>
                                <input type="text" class="form-control" name="whatsappPhoneNumberId"
                                       value="{{ $settings['whatsappPhoneNumberId'] ?? '' }}">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Business Account ID {!! $infoButton('Your WhatsApp Business Account ID in Meta. Stored for reference and future template sync features.') !!}</label>
                                <input type="text" class="form-control" name="whatsappBusinessAccountId"
                                       value="{{ $settings['whatsappBusinessAccountId'] ?? '' }}">
                            </div>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Access Token {!! $infoButton('Meta API token used to send WhatsApp template messages. Keep it private and rotate it if exposed.') !!}</label>
                            <input type="password" class="form-control" name="whatsappAccessToken"
                                   value="{{ $settings['whatsappAccessToken'] ?? '' }}">
                        </div>

                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Webhook Verify Token {!! $infoButton('A secret text you create here and enter in Meta webhook setup. Meta sends it back during verification.') !!}</label>
                                <input type="password" class="form-control" name="whatsappWebhookVerifyToken"
                                       value="{{ $settings['whatsappWebhookVerifyToken'] ?? '' }}">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Default Language {!! $infoButton('Fallback Meta template language code, for example en_US. Each template row can override it.') !!}</label>
                                <input type="text" class="form-control" name="whatsappDefaultLanguage"
                                       value="{{ $settings['whatsappDefaultLanguage'] ?? 'en_US' }}"
                                       placeholder="en_US">
                            </div>
                        </div>

                        <div class="border rounded p-3">
                            <h5 class="mb-2">Webhook Setup</h5>
                            <div class="row g-3">
                                <div class="col-md-8">
                                    <label class="form-label">Callback URL {!! $infoButton('Paste this URL in Meta webhook configuration so delivery, read, and failed statuses come back to this app.') !!}</label>
                                    <input type="text" class="form-control" value="{{ route('whatsapp.webhook.verify') }}" readonly>
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label">Verify Token {!! $infoButton('Must exactly match the verify token entered above and in Meta. It proves Meta is verifying the correct webhook.') !!}</label>
                                    <input type="text" class="form-control" value="{{ $settings['whatsappWebhookVerifyToken'] ?? '' }}" readonly>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="card-footer text-end">
                        @can('updateSetting', [\App\Models\Setting::class, 'notification'])
                            <button type="submit" class="btn btn-primary">{{ __('labels.submit') }}</button>
                        @endcan
                    </div>
                </div>
            </form>

            <div class="card mb-4">
                <div class="card-header">
                    <div>
                        <h4 class="card-title mb-1">Template Mapping</h4>
                        <div class="text-muted small">Statuses are synced from Meta. Only approved and active templates are used for sending.</div>
                    </div>
                    <div class="card-actions">
                        <button type="button" id="syncWhatsAppTemplatesButton" class="btn btn-outline-secondary"
                                data-endpoint="{{ route('admin.settings.notification.whatsapp-templates.sync') }}">
                            Sync All Configured Templates
                            <span class="spinner-border spinner-border-sm ms-2 d-none" role="status" aria-hidden="true"></span>
                        </button>
                        <button type="button" id="saveWhatsAppTemplatesButton" class="btn btn-outline-primary"
                                data-endpoint="{{ route('admin.settings.notification.whatsapp-templates.save') }}">
                            Save Templates
                        </button>
                    </div>
                </div>
                <div class="card-body">
                    <div class="alert alert-light border d-flex align-items-start gap-2" role="alert">
                        <i class="ti ti-info-circle fs-2 text-primary"></i>
                        <div>
                            <div class="fw-semibold mb-1">Format controls how Meta template variables are filled.</div>
                            <div class="small text-muted">
                                Positional means the Meta template uses variables like <code>@{{1}}</code>, <code>@{{2}}</code>. Named means the template uses variables like <code>@{{customer_name}}</code>, <code>@{{order_number}}</code>. The mapping tells the system which app value should be inserted into each Meta variable.
                            </div>
                        </div>
                    </div>
                    <form id="whatsappTemplatesForm">
                        <div class="table-responsive">
                            <table class="table table-vcenter">
                                <thead>
                                <tr>
                                    <th>Module {!! $infoButton('The app event that can trigger a WhatsApp message, such as order placed or invoice delivered.') !!}</th>
                                    <th>Template {!! $infoButton('Exact approved template name from Meta. Use the template name, not the display title.') !!}</th>
                                    <th>Language {!! $infoButton('Language code used by the Meta template, for example en_US. It must match a language approved in Meta.') !!}</th>
                                    <th>Format {!! $infoButton('Positional maps Meta variables like {{1}} and {{2}}. Named maps variables like {{customer_name}} and {{order_number}}.') !!}</th>
                                    <th>Meta Status {!! $infoButton('Read-only status synced from Meta. Only Approved templates are used for sending.') !!}</th>
                                    <th>Active {!! $infoButton('Enables this module/template mapping in this app. It must be active and approved to send.') !!}</th>
                                    <th>Variables Mapping {!! $infoButton('Maps each Meta variable to an app data field. JSON is stored for flexibility across positional and named templates.') !!}</th>
                                    <th>Sync {!! $infoButton('Fetch latest Meta status and template details for this configured row only.') !!}</th>
                                </tr>
                                </thead>
                                <tbody>
                                @foreach($whatsAppTemplateModules ?? [] as $moduleKey => $moduleConfig)
                                    @php
                                        $template = ($whatsAppTemplates ?? collect())->get($moduleKey);
                                        $format = old("templates.$moduleKey.parameter_format", $template?->parameter_format ?? 'positional');
                                        $variables = old("templates.$moduleKey.variables", json_encode($template?->variables ?: \App\Support\WhatsAppTemplateCatalog::defaultVariables($moduleKey, $format), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
                                    @endphp
                                    <tr>
                                        <td style="min-width: 210px;">
                                            <input type="hidden" name="templates[{{ $moduleKey }}][module]" value="{{ $moduleKey }}">
                                            <div class="fw-semibold">{{ $moduleConfig['label'] }}</div>
                                            <div class="text-muted small">{{ $moduleConfig['audience'] }} · {{ $moduleKey }}</div>
                                            <div class="form-hint">Triggered by this app event.</div>
                                        </td>
                                        <td style="min-width: 190px;">
                                            <input type="text" class="form-control form-control-sm"
                                                   name="templates[{{ $moduleKey }}][template_name]"
                                                   value="{{ old("templates.$moduleKey.template_name", $template?->template_name) }}"
                                                   placeholder="meta_template_name">
                                            <small class="form-hint">Paste from Meta.</small>
                                        </td>
                                        <td style="min-width: 110px;">
                                            <input type="text" class="form-control form-control-sm"
                                                   name="templates[{{ $moduleKey }}][language]"
                                                   value="{{ old("templates.$moduleKey.language", $template?->language ?? ($settings['whatsappDefaultLanguage'] ?? 'en_US')) }}">
                                            <small class="form-hint">Example: en_US.</small>
                                        </td>
                                        <td style="min-width: 135px;">
                                            <select class="form-select form-select-sm" name="templates[{{ $moduleKey }}][parameter_format]">
                                                @foreach($whatsAppParameterFormats ?? ['positional', 'named'] as $parameterFormat)
                                                    <option value="{{ $parameterFormat }}" @selected($format === $parameterFormat)>{{ ucfirst($parameterFormat) }}</option>
                                                @endforeach
                                            </select>
                                            <small class="form-hint">Positional: <code>@{{1}}</code>. Named: <code>@{{name}}</code>.</small>
                                        </td>
                                        <td style="min-width: 190px;">
                                            @php
                                                $status = $template?->status ?? 'draft';
                                                $statusColor = match ($status) {
                                                    'approved' => 'success',
                                                    'rejected' => 'danger',
                                                    'paused' => 'warning',
                                                    'submitted' => 'info',
                                                    default => 'secondary',
                                                };
                                            @endphp
                                            <span class="badge bg-{{ $statusColor }}-lt">{{ ucfirst($status) }}</span>
                                            @if($template?->quality_score)
                                                <div class="small text-muted mt-1">Quality: {{ $template->quality_score }}</div>
                                            @endif
                                            @if($template?->category)
                                                <div class="small text-muted">Category: {{ $template->category }}</div>
                                            @endif
                                            @if($template?->rejected_reason)
                                                <div class="small text-danger">Rejected: {{ $template->rejected_reason }}</div>
                                            @endif
                                            @if($template?->last_synced_at)
                                                <div class="small text-muted">Synced: {{ $template->last_synced_at->diffForHumans() }}</div>
                                            @else
                                                <div class="small text-muted">Not synced yet.</div>
                                            @endif
                                            @if($template?->sync_error)
                                                <div class="small text-danger">{{ \Illuminate\Support\Str::before($template->sync_error, ' Fetched from Meta:') }}</div>
                                            @endif
                                        </td>
                                        <td>
                                            <label class="form-check form-switch">
                                                <input type="hidden" name="templates[{{ $moduleKey }}][is_active]" value="0">
                                                <input class="form-check-input" type="checkbox"
                                                       name="templates[{{ $moduleKey }}][is_active]" value="1"
                                                       @checked(old("templates.$moduleKey.is_active", $template?->is_active ?? false))>
                                            </label>
                                            <small class="form-hint">Use this mapping.</small>
                                        </td>
                                        <td style="min-width: 260px;">
                                            <textarea class="form-control form-control-sm" rows="4"
                                                      name="templates[{{ $moduleKey }}][variables]">{{ $variables }}</textarea>
                                            <small class="text-muted">Allowed app fields: {{ implode(', ', $moduleConfig['variables']) }}</small>
                                        </td>
                                        <td style="min-width: 110px;">
                                            <button type="button"
                                                    class="btn btn-outline-secondary btn-sm js-whatsapp-template-sync"
                                                    data-endpoint="{{ route('admin.settings.notification.whatsapp-templates.sync') }}"
                                                    data-module="{{ $moduleKey }}">
                                                Sync
                                                <span class="spinner-border spinner-border-sm ms-2 d-none" role="status" aria-hidden="true"></span>
                                            </button>
                                        </td>
                                    </tr>
                                @endforeach
                                </tbody>
                            </table>
                        </div>
                    </form>
                </div>
            </div>

            <div class="card">
                <div class="card-header">
                    <h4 class="card-title">Send Test Template {!! $infoButton('Sends the selected approved active module template to the test number with sample values, so credentials and mappings can be verified.') !!}</h4>
                </div>
                <div class="card-body">
                    <div class="row g-2 align-items-end">
                        <div class="col-md-5">
                            <label class="form-label" for="whatsappTestMobile">Test Mobile {!! $infoButton('Recipient number for a test WhatsApp message. Use country code without spaces, for example 919999999999.') !!}</label>
                            <input type="text" id="whatsappTestMobile" class="form-control"
                                   name="whatsappTestMobile"
                                   value="{{ $settings['whatsappTestMobile'] ?? '' }}"
                                   placeholder="919999999999">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label" for="whatsappTestModule">Module {!! $infoButton('Choose which event template mapping to test. The selected module must have an approved active template.') !!}</label>
                            <select id="whatsappTestModule" class="form-select">
                                @foreach($whatsAppTemplateModules ?? [] as $moduleKey => $moduleConfig)
                                    <option value="{{ $moduleKey }}">{{ $moduleConfig['audience'] }} - {{ $moduleConfig['label'] }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-3">
                            <button type="button" id="testWhatsAppButton" class="btn btn-outline-primary w-100"
                                    data-endpoint="{{ route('admin.settings.notification.test-whatsapp') }}">
                                <span>Send Test WhatsApp</span>
                                <span class="spinner-border spinner-border-sm ms-2 d-none" role="status" aria-hidden="true"></span>
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection

@push('scripts')
    <script src="{{ asset('assets/js/notification-settings.js') }}" defer></script>
@endpush
