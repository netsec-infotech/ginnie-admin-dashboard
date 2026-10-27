@extends('layouts.admin.app', ['page' => $menuAdmin['settings']['active'] ?? "", 'sub_page' => $menuAdmin['settings']['route']['email']['sub_active'] ?? "" ])

@section('title', __('labels.email_settings'))

@section('header_data')
    @php
        $page_title = __('labels.email_settings');
        $page_pretitle = __('labels.admin') . " " . __('labels.settings');
    @endphp
@endsection

@php
    $breadcrumbs = [
        ['title' => __('labels.home'), 'url' => route('admin.dashboard')],
        ['title' => __('labels.settings'), 'url' => route('admin.settings.index')],
        ['title' => __('labels.email_settings'), 'url' => null],
    ];
@endphp

@section('admin-content')
    <div class="page-header d-print-none">
        <div class="row g-2 align-items-center">
            <div class="col">
                <h2 class="page-title">{{ __('labels.email_settings') }}</h2>
                <x-breadcrumb :items="$breadcrumbs"/>
            </div>
        </div>
    </div>
    <div class="page-body">
        <div class="container-xl">
            <div class="row g-5">
                <div class="col-sm-2 d-none d-lg-block">
                    <div class="sticky-top">
                        <h3>{{ __('labels.menu') }}</h3>
                        <nav class="nav nav-vertical nav-pills" id="pills">
                            <a class="nav-link" href="#pills-smtp">{{ __('labels.smtp') }}</a>
                        </nav>
                    </div>
                </div>
                <div class="col-sm" data-bs-spy="scroll" data-bs-target="#pills" data-bs-offset="0">
                    <div class="row row-cards">
                        <div class="col-12">
                            <form action="{{ route('admin.settings.store') }}" class="form-submit" method="post">
                                @csrf
                                <input type="hidden" name="type" value="email">
                                <div class="card mb-4" id="pills-smtp">
                                    <div class="card-header">
                                        <h4 class="card-title">{{ __('labels.smtp') }}</h4>
                                    </div>
                                    <div class="card-body">
                                        <div class="mb-3">
                                            <label class="form-label required">{{ __('labels.smtp_host') }}</label>
                                            <input type="text" class="form-control" name="smtpHost"
                                                   placeholder="{{ __('labels.smtp_host_placeholder') }}"
                                                   value="{{ $settings['smtpHost'] ?? '' }}" required/>
                                        </div>
                                        <div class="mb-3">
                                            <label class="form-label required">{{ __('labels.smtp_port') }}</label>
                                            <input type="number" class="form-control" name="smtpPort"
                                                   placeholder="{{ __('labels.smtp_port_placeholder') }}"
                                                   value="{{ $settings['smtpPort'] ?? '' }}" required/>
                                        </div>
                                        <div class="mb-3">
                                            <label class="form-label required">{{ __('labels.smtp_email') }}</label>
                                            <input type="email" class="form-control" name="smtpEmail"
                                                   placeholder="{{ __('labels.smtp_email_placeholder') }}"
                                                   value="{{ $isDemoUser ? Str::mask(($settings['smtpEmail'] ?? '****'), '****', 3, 8) : ($settings['smtpEmail'] ?? '') }}" required/>
                                        </div>
                                        <div class="mb-3">
                                            <label class="form-label required">{{ __('labels.smtp_password') }}</label>
                                            <input type="password" class="form-control" name="smtpPassword"
                                                   placeholder="{{ __('labels.smtp_password_placeholder') }}"
                                                   value="{{ $isDemoUser ? Str::mask(($settings['smtpPassword'] ?? '****'), '****', 3, 8) : ($settings['smtpPassword'] ?? '') }}" required/>
                                        </div>
                                        <div class="mb-3">
                                            <label
                                                class="form-label required">{{ __('labels.smtp_encryption') }}</label>
                                            <select class="form-select" name="smtpEncryption" required>
                                                <option value=""
                                                        disabled {{ !isset($settings['smtpEncryption']) ? 'selected' : '' }}>{{ __('labels.smtp_encryption_placeholder') }}</option>
                                                <option
                                                    value="tls" {{ isset($settings['smtpEncryption']) && $settings['smtpEncryption'] === 'tls' ? 'selected' : '' }}>
                                                    TLS
                                                </option>
                                                <option
                                                    value="ssl" {{ isset($settings['smtpEncryption']) && $settings['smtpEncryption'] === 'ssl' ? 'selected' : '' }}>
                                                    SSL
                                                </option>
                                            </select>
                                        </div>
                                        <div class="mb-3">
                                            <label
                                                class="form-label required">{{ __('labels.smtp_content_type') }}</label>
                                            <select class="form-select" name="smtpContentType" required>
                                                <option value=""
                                                        disabled {{ !isset($settings['smtpContentType']) ? 'selected' : '' }}>{{ __('labels.smtp_content_type_placeholder') }}</option>
                                                <option
                                                    value="text" {{ isset($settings['smtpContentType']) && $settings['smtpContentType'] === 'text' ? 'selected' : '' }}>
                                                    Text
                                                </option>
                                                <option
                                                    value="html" {{ isset($settings['smtpContentType']) && $settings['smtpContentType'] === 'html' ? 'selected' : '' }}>
                                                    HTML
                                                </option>
                                            </select>
                                        </div>
                                        <hr class="my-4">
                                        <div class="mb-2">
                                            <h5 class="mb-1">{{ __('labels.mail_sender_section_title') }}</h5>
                                            <small class="text-muted d-block">{{ __('labels.mail_sender_section_hint') }}</small>
                                        </div>
                                        <div class="mb-3">
                                            <label class="form-label required">{{ __('labels.mail_from_address') }}</label>
                                            <input type="email" class="form-control" name="mailFromAddress"
                                                   placeholder="{{ __('labels.mail_from_address_placeholder') }}"
                                                   value="{{ $isDemoUser ? Str::mask(($settings['mailFromAddress'] ?? ($settings['smtpEmail'] ?? '****')), '****', 3, 8) : ($settings['mailFromAddress'] ?? ($settings['smtpEmail'] ?? '')) }}" required/>
                                        </div>
                                        <div class="mb-3">
                                            <label class="form-label">{{ __('labels.mail_from_name') }}</label>
                                            <input type="text" class="form-control" name="mailFromName"
                                                   placeholder="{{ __('labels.mail_from_name_placeholder') }}"
                                                   value="{{ $settings['mailFromName'] ?? '' }}"/>
                                        </div>
                                        <hr class="my-4">
                                        <div class="mb-2">
                                            <h5 class="mb-1">{{ __('labels.test_email_section_title') }}</h5>
                                            <small class="text-muted d-block">{{ __('labels.test_email_section_hint') }}</small>
                                        </div>
                                        <div class="row g-2 align-items-end">
                                            <div class="col-md-7">
                                                <label class="form-label" for="testEmailRecipient">{{ __('labels.test_email_recipient_label') }}</label>
                                                <input type="email" id="testEmailRecipient" class="form-control"
                                                       placeholder="{{ __('labels.test_email_recipient_placeholder') }}"/>
                                            </div>
                                            <div class="col-md-5">
                                                <button type="button" id="testEmailButton" class="btn btn-outline-primary w-100"
                                                        data-endpoint="{{ route('admin.settings.email.test') }}"
                                                        data-missing-recipient="{{ __('labels.test_email_recipient_required') }}"
                                                        data-missing-smtp="{{ __('labels.test_email_smtp_incomplete') }}"
                                                        data-success-title="{{ __('labels.test_email_success') }}"
                                                        data-failed-title="{{ __('labels.test_email_failed') }}">
                                                    <span>{{ __('labels.test_email_send_button') }}</span>
                                                    <span class="spinner-border spinner-border-sm ms-2 d-none" role="status" aria-hidden="true"></span>
                                                </button>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <div class="card-footer text-end">
                                    <div class="d-flex">
                                        @can('updateSetting', [\App\Models\Setting::class, 'email'])
                                            <button type="submit"
                                                    class="btn btn-primary ms-auto">{{ __('labels.submit') }}</button>
                                        @endcan
                                    </div>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection

@push('scripts')
    <script src="{{ asset('assets/js/email-settings.js') }}" defer></script>
@endpush
