@php use App\Enums\PoliciesEnum; @endphp
@extends('layouts.admin.app', ['page' => $menuAdmin['settings']['active'] ?? "", 'sub_page' => $menuAdmin['settings']['route']['seller']['sub_active'] ?? "" ])

@section('title', __('labels.seller_settings') ?? 'Seller Settings')

@section('header_data')
    @php
        $page_title = __('labels.seller_settings') ?? 'Seller Settings';
        $page_pretitle = __('labels.admin') . " " . __('labels.settings');
    @endphp
@endsection

@php
    $breadcrumbs = [
        ['title' => __('labels.home'), 'url' => route('admin.dashboard')],
        ['title' => __('labels.settings'), 'url' => route('admin.settings.index')],
        ['title' => __('labels.seller_settings') ?? 'Seller Settings', 'url' => null],
    ];
@endphp

@section('admin-content')
    <div class="page-header d-print-none">
        <div class="row g-2 align-items-center">
            <div class="col">
                <h2 class="page-title">{{ __('labels.seller_settings') ?? 'Seller Settings' }}</h2>
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
                            <a class="nav-link" href="#pills-policies">{{ __('labels.seller_policies') ?? 'Seller Policies' }}</a>
                            <a class="nav-link" href="#pills-orders">{{ __('labels.seller_order_settings') ?? 'Seller Order Settings' }}</a>
                            <a class="nav-link" href="#pills-returns">Seller Return SLA</a>
                        </nav>
                    </div>
                </div>
                <div class="col-sm" data-bs-spy="scroll" data-bs-target="#pills" data-bs-offset="0">
                    <div class="row row-cards">
                        <div class="col-12">
                            <form action="{{ route('admin.settings.store') }}" class="form-submit" method="post">
                                @csrf
                                <input type="hidden" name="type" value="seller">
                                <div class="card mb-4" id="pills-policies">
                                    <div class="card-header">
                                        <h4 class="card-title">{{ __('labels.seller_policies') ?? 'Seller Policies' }}</h4>
                                    </div>
                                    <div class="card-body">
                                        <div class="mb-3">
                                            <label class="form-label">{{ __('labels.seller_terms_condition') ?? 'Seller Terms & Conditions' }}
                                                <a href="{{ route('policies.show', PoliciesEnum::SELLER_TERMS()) }}" target="_blank">
                                                    <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="icon icon-tabler icons-tabler-outline icon-tabler-eye">
                                                        <path stroke="none" d="M0 0h24v24H0z" fill="none"/>
                                                        <path d="M10 12a2 2 0 1 0 4 0a2 2 0 0 0 -4 0"/>
                                                        <path d="M21 12c-2.4 4 -5.4 6 -9 6c-3.6 0 -6.6 -2 -9 -6c2.4 -4 5.4 -6 9 -6c3.6 0 6.6 2 9 6"/>
                                                    </svg>
                                                </a>
                                            </label>
                                            <textarea class="hugerte-mytextarea" name="termsCondition" rows="8"
                                                      placeholder="{{ __('labels.seller_terms_condition_placeholder') ?? 'Enter seller terms & conditions' }}">{{ $settings['termsCondition'] ?? '' }}</textarea>
                                        </div>
                                        <div class="mb-3">
                                            <label class="form-label">{{ __('labels.seller_privacy_policy') ?? 'Seller Privacy Policy' }}
                                                <a href="{{ route('policies.show', PoliciesEnum::SELLER_PRIVACY()) }}" target="_blank">
                                                    <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="icon icon-tabler icons-tabler-outline icon-tabler-eye">
                                                        <path stroke="none" d="M0 0h24v24H0z" fill="none"/>
                                                        <path d="M10 12a2 2 0 1 0 4 0a2 2 0 0 0 -4 0"/>
                                                        <path d="M21 12c-2.4 4 -5.4 6 -9 6c-3.6 0 -6.6 -2 -9 -6c2.4 -4 5.4 -6 9 -6c3.6 0 6.6 2 9 6"/>
                                                    </svg>
                                                </a>
                                            </label>
                                            <textarea class="hugerte-mytextarea" name="privacyPolicy" rows="8"
                                                      placeholder="{{ __('labels.seller_privacy_policy_placeholder') ?? 'Enter seller privacy policy' }}">{{ $settings['privacyPolicy'] ?? '' }}</textarea>
                                        </div>
                                    </div>
                                </div>

                                <div class="card mb-4" id="pills-orders">
                                    <div class="card-header">
                                        <h4 class="card-title">{{ __('labels.seller_order_settings') ?? 'Seller Order Settings' }}</h4>
                                    </div>
                                    <div class="card-body">
                                        <!-- Regular order acceptance timeout is stored in minutes and enforced by the auto-reject scheduler. -->
                                        <div class="mb-3">
                                            <label class="row">
                                                <span class="col">{{ __('labels.enable_regular_order_acceptance_timeout') }}</span>
                                                <span class="col-auto">
                                                    <label class="form-check form-check-single form-switch">
                                                        <input class="form-check-input" type="checkbox"
                                                            name="regularOrderAcceptanceTimeoutEnabled" value="1"
                                                            {{ ($settings['regularOrderAcceptanceTimeoutEnabled'] ?? true) ? 'checked' : '' }} />
                                                    </label>
                                                </span>
                                            </label>
                                            <small class="form-hint text-secondary">{{ __('labels.enable_regular_order_acceptance_timeout_help') }}</small>
                                        </div>
                                        <div class="mb-3">
                                            <label class="form-label required">{{ __('labels.regular_order_acceptance_timeout_minutes') ?? 'Regular Order Acceptance Timeout (Minutes)' }}</label>
                                            <input type="number" min="1" max="1440" class="form-control"
                                                name="regularOrderAcceptanceTimeoutMinutes"
                                                placeholder="10"
                                                value="{{ $settings['regularOrderAcceptanceTimeoutMinutes'] ?? '10' }}" required />
                                            <small class="form-hint text-secondary">{{ __('labels.regular_order_acceptance_timeout_help') ?? 'Normal orders are auto-rejected when sellers do not accept within this many minutes.' }}</small>
                                        </div>
                                    </div>
                                </div>

                                <div class="card mb-4" id="pills-returns">
                                    <div class="card-header">
                                        <h4 class="card-title">Seller Return Settings</h4>
                                    </div>
                                    <div class="card-body">
                                        <div class="mb-3">
                                            <label class="form-label required">Seller Return Inspection SLA (Hours)</label>
                                            <input type="number" min="1" class="form-control" name="return_seller_inspection_sla_hours"
                                                placeholder="Enter hours (e.g. 48)"
                                                value="{{$settings['return_seller_inspection_sla_hours'] ?? '48'}}" required />
                                            <small class="form-hint text-secondary">The duration in hours that a seller has to inspect and confirm a returned product before it is auto-approved in favor of the customer.</small>
                                        </div>
                                    </div>
                                </div>
                                <div class="card-footer text-end">
                                    <div class="d-flex">
                                        @can('updateSetting', [\App\Models\Setting::class, 'seller'])
                                            <button type="submit" class="btn btn-primary ms-auto">{{ __('labels.submit') }}</button>
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
