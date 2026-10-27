@php use App\Enums\PoliciesEnum; @endphp
@extends('layouts.admin.app', ['page' => $menuAdmin['settings']['active'] ?? "", 'sub_page' => $menuAdmin['settings']['route']['delivery_boy']['sub_active'] ?? "" ])

@section('title', __('labels.delivery_boy_settings'))

@section('header_data')
    @php
        $page_title = __('labels.delivery_boy_settings');
        $page_pretitle = __('labels.admin') . " " . __('labels.settings');
    @endphp
@endsection

@php
    $breadcrumbs = [
        ['title' => __('labels.home'), 'url' => route('admin.dashboard')],
        ['title' => __('labels.settings'), 'url' => route('admin.settings.index')],
        ['title' => __('labels.delivery_boy_settings'), 'url' => null],
    ];
@endphp

@section('admin-content')
    <div class="page-header d-print-none">
        <div class="row g-2 align-items-center">
            <div class="col">
                <h2 class="page-title">{{ __('labels.delivery_boy_settings') }}</h2>
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
                            <a class="nav-link" href="#pills-policies">{{ __('labels.delivery_boy_policies') }}</a>
                            <a class="nav-link" href="#pills-refer-earn">{{ __('labels.db_refer_and_earn') }}</a>
                            <a class="nav-link" href="#pills-cash-limit">{{ __('labels.cash_collection_limit') }}</a>
                            <a class="nav-link" href="#pills-rider-tips">{{ __('labels.rider_tips') ?? 'Rider Tips' }}</a>
                        </nav>
                    </div>
                </div>
                <div class="col-sm" data-bs-spy="scroll" data-bs-target="#pills" data-bs-offset="0">
                    <div class="row row-cards">
                        <div class="col-12">
                            <form action="{{ route('admin.settings.store') }}" class="form-submit" method="post">
                                @csrf
                                <input type="hidden" name="type" value="delivery_boy">
                                <div class="card mb-4" id="pills-policies">
                                    <div class="card-header">
                                        <h4 class="card-title">{{ __('labels.delivery_boy_policies') }}</h4>
                                    </div>
                                    <div class="card-body">
                                        <div class="mb-3">
                                            <label
                                                class="form-label">{{ __('labels.delivery_boy_terms_condition') }}
                                                <a href="{{ route('policies.show', PoliciesEnum::DELIVERY_TERMS()) }}"
                                                   target="_blank">
                                                    <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24"
                                                         viewBox="0 0 24 24" fill="none" stroke="currentColor"
                                                         stroke-width="2" stroke-linecap="round" stroke-linejoin="round"
                                                         class="icon icon-tabler icons-tabler-outline icon-tabler-eye">
                                                        <path stroke="none" d="M0 0h24v24H0z" fill="none"/>
                                                        <path d="M10 12a2 2 0 1 0 4 0a2 2 0 0 0 -4 0"/>
                                                        <path
                                                            d="M21 12c-2.4 4 -5.4 6 -9 6c-3.6 0 -6.6 -2 -9 -6c2.4 -4 5.4 -6 9 -6c3.6 0 6.6 2 9 6"/>
                                                    </svg>
                                                </a></label>
                                            <textarea class="hugerte-mytextarea" name="termsCondition" rows="8"
                                                      placeholder="{{ __('labels.delivery_boy_terms_condition_placeholder') }}">{{ $settings['termsCondition'] ?? '' }}</textarea>
                                        </div>
                                        <div class="mb-3">
                                            <label
                                                class="form-label">{{ __('labels.delivery_boy_privacy_policy') }}
                                                <a href="{{ route('policies.show', PoliciesEnum::DELIVERY_PRIVACY()) }}"
                                                   target="_blank">
                                                    <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24"
                                                         viewBox="0 0 24 24" fill="none" stroke="currentColor"
                                                         stroke-width="2" stroke-linecap="round" stroke-linejoin="round"
                                                         class="icon icon-tabler icons-tabler-outline icon-tabler-eye">
                                                        <path stroke="none" d="M0 0h24v24H0z" fill="none"/>
                                                        <path d="M10 12a2 2 0 1 0 4 0a2 2 0 0 0 -4 0"/>
                                                        <path
                                                            d="M21 12c-2.4 4 -5.4 6 -9 6c-3.6 0 -6.6 -2 -9 -6c2.4 -4 5.4 -6 9 -6c3.6 0 6.6 2 9 6"/>
                                                    </svg>
                                                </a></label>
                                            <textarea class="hugerte-mytextarea" name="privacyPolicy" rows="8"
                                                      placeholder="{{ __('labels.delivery_boy_privacy_policy_placeholder') }}">{{ $settings['privacyPolicy'] ?? '' }}</textarea>
                                        </div>
                                    </div>
                                </div>

                                {{-- Refer & Earn card --}}
                                <div class="card mb-4" id="pills-refer-earn">
                                    <div class="card-header">
                                        <h4 class="card-title">{{ __('labels.db_refer_and_earn') }}</h4>
                                    </div>
                                    <div class="card-body">
                                        <div class="mb-4">
                                            <label class="row">
                                                <span class="col">
                                                    <span class="form-label">{{ __('labels.refer_earn_status') }}</span>
                                                    <small class="form-hint d-block">{{ __('labels.db_refer_earn_status_hint') }}</small>
                                                </span>
                                                <span class="col-auto">
                                                    <label class="form-check form-check-single form-switch">
                                                        <input class="form-check-input" type="checkbox"
                                                               id="deliveryBoyReferEarnStatus"
                                                               name="deliveryBoyReferEarnStatus"
                                                               value="1"
                                                               {{ (isset($settings['deliveryBoyReferEarnStatus']) && $settings['deliveryBoyReferEarnStatus']) ? 'checked' : '' }}>
                                                    </label>
                                                </span>
                                            </label>
                                        </div>
                                        <div id="db-refer-earn-fields" class="{{ (isset($settings['deliveryBoyReferEarnStatus']) && $settings['deliveryBoyReferEarnStatus']) ? '' : 'd-none' }}">
                                            <div class="row g-3">
                                                <div class="col-md-6">
                                                    <label class="form-label" for="deliveryBoyReferEarnBonusReferral">
                                                        {{ __('labels.db_bonus_referral') }}
                                                        <small class="text-muted">({{ __('labels.db_referrer_hint') }})</small>
                                                    </label>
                                                    {{-- @dd(getCurrencySymbol()) --}}
                                                    <div class="input-group">
                                                        <span class="input-group-text">{{ getCurrencySymbol() }}</span>
                                                        <input type="number" step="0.01" min="0"
                                                               class="form-control"
                                                               name="deliveryBoyReferEarnBonusReferral"
                                                               id="deliveryBoyReferEarnBonusReferral"
                                                               value="{{ $settings['deliveryBoyReferEarnBonusReferral'] ?? '0' }}">
                                                    </div>
                                                </div>
                                                <div class="col-md-6">
                                                    <label class="form-label" for="deliveryBoyReferEarnBonusReferee">
                                                        {{ __('labels.db_bonus_referee') }}
                                                        <small class="text-muted">({{ __('labels.db_referee_hint') }})</small>
                                                    </label>
                                                    <div class="input-group">
                                                        <span class="input-group-text">{{ getCurrencySymbol() }}</span>
                                                        <input type="number" step="0.01" min="0"
                                                               class="form-control"
                                                               name="deliveryBoyReferEarnBonusReferee"
                                                               id="deliveryBoyReferEarnBonusReferee"
                                                               value="{{ $settings['deliveryBoyReferEarnBonusReferee'] ?? '0' }}">
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                {{-- Cash Collection Limit card --}}
                                <div class="card mb-4" id="pills-cash-limit">
                                    <div class="card-header">
                                        <h4 class="card-title">{{ __('labels.cash_collection_limit') }}</h4>
                                    </div>
                                    <div class="card-body">
                                        <div class="mb-4">
                                            <label class="row">
                                                <span class="col">
                                                    <span class="form-label">{{ __('labels.cash_collection_limit_status') }}</span>
                                                    <small class="form-hint d-block">{{ __('labels.cash_collection_limit_status_hint') }}</small>
                                                </span>
                                                <span class="col-auto">
                                                    <label class="form-check form-check-single form-switch">
                                                        <input class="form-check-input" type="checkbox"
                                                               id="cashCollectionLimitStatus"
                                                               name="cashCollectionLimitStatus"
                                                               value="1"
                                                               {{ (isset($settings['cashCollectionLimitStatus']) && $settings['cashCollectionLimitStatus']) ? 'checked' : '' }}>
                                                    </label>
                                                </span>
                                            </label>
                                        </div>
                                        <div id="db-cash-limit-fields" class="{{ (isset($settings['cashCollectionLimitStatus']) && $settings['cashCollectionLimitStatus']) ? '' : 'd-none' }}">
                                            <div class="row g-3">
                                                <div class="col-md-6">
                                                    <label class="form-label" for="cashCollectionLimitAmount">
                                                        {{ __('labels.cash_collection_limit_amount') }}
                                                    </label>
                                                    <div class="input-group">
                                                        <span class="input-group-text">{{ getCurrencySymbol() }}</span>
                                                        <input type="number" step="0.01" min="0"
                                                               class="form-control"
                                                               name="cashCollectionLimitAmount"
                                                               id="cashCollectionLimitAmount"
                                                               value="{{ $settings['cashCollectionLimitAmount'] ?? '0' }}">
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                {{-- Rider Tips Settings Card --}}
                                <div class="card mb-4" id="pills-rider-tips">
                                    <div class="card-header">
                                        <h4 class="card-title">{{ __('labels.rider_tips') ?? 'Rider Tips' }}</h4>
                                    </div>
                                    <div class="card-body">
                                        <div class="mb-4">
                                            <label class="row">
                                                <span class="col">{{ __('labels.enable_rider_tips') ?? 'Enable Rider Tips' }}</span>
                                                <span class="col-auto">
                                                    <label class="form-check form-check-single form-switch">
                                                        <input class="form-check-input" type="checkbox"
                                                               id="enableRiderTips"
                                                               name="enableRiderTips"
                                                               value="1"
                                                            {{ !empty($settings['enableRiderTips']) ? 'checked' : '' }}>
                                                    </label>
                                                </span>
                                            </label>
                                        </div>

                                        <div id="db-rider-tips-fields" class="{{ empty($settings['enableRiderTips']) ? 'd-none' : '' }}">
                                            <div class="row">
                                                <div class="col-md-6 mb-3">
                                                    <label class="form-label">{{ __('labels.min_tip_amount') ?? 'Minimum Tip Amount' }}</label>
                                                    <div class="input-group">
                                                        <span class="input-group-text">{{ getCurrencySymbol() }}</span>
                                                        <input type="number" step="0.01" min="0"
                                                               class="form-control"
                                                               name="minTipAmount"
                                                               id="minTipAmount"
                                                               value="{{ $settings['minTipAmount'] ?? '5' }}">
                                                    </div>
                                                </div>
                                                <div class="col-md-6 mb-3">
                                                    <label class="form-label">{{ __('labels.max_tip_amount') ?? 'Maximum Tip Amount' }}</label>
                                                    <div class="input-group">
                                                        <span class="input-group-text">{{ getCurrencySymbol() }}</span>
                                                        <input type="number" step="0.01" min="0"
                                                               class="form-control"
                                                               name="maxTipAmount"
                                                               id="maxTipAmount"
                                                               value="{{ $settings['maxTipAmount'] ?? '500' }}">
                                                    </div>
                                                </div>
                                            </div>

                                            <div class="row">
                                                <div class="col-md-4 mb-3">
                                                    <label class="form-label">{{ __('labels.preset_1') ?? 'Preset 1 Amount' }}</label>
                                                    <div class="input-group">
                                                        <span class="input-group-text">{{ getCurrencySymbol() }}</span>
                                                        <input type="number" step="0.01" min="0"
                                                               class="form-control"
                                                               name="preset1"
                                                               id="preset1"
                                                               value="{{ $settings['preset1'] ?? '10' }}">
                                                    </div>
                                                </div>
                                                <div class="col-md-4 mb-3">
                                                    <label class="form-label">{{ __('labels.preset_2') ?? 'Preset 2 Amount' }}</label>
                                                    <div class="input-group">
                                                        <span class="input-group-text">{{ getCurrencySymbol() }}</span>
                                                        <input type="number" step="0.01" min="0"
                                                               class="form-control"
                                                               name="preset2"
                                                               id="preset2"
                                                               value="{{ $settings['preset2'] ?? '20' }}">
                                                    </div>
                                                </div>
                                                <div class="col-md-4 mb-3">
                                                    <label class="form-label">{{ __('labels.preset_3') ?? 'Preset 3 Amount' }}</label>
                                                    <div class="input-group">
                                                        <span class="input-group-text">{{ getCurrencySymbol() }}</span>
                                                        <input type="number" step="0.01" min="0"
                                                               class="form-control"
                                                               name="preset3"
                                                               id="preset3"
                                                               value="{{ $settings['preset3'] ?? '50' }}">
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <div class="card-footer text-end">
                                    <div class="d-flex">
                                        @can('updateSetting', [\App\Models\Setting::class, 'delivery_boy'])
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
<script>
    document.addEventListener('DOMContentLoaded', function () {
        const toggle = document.getElementById('deliveryBoyReferEarnStatus');
        const fields = document.getElementById('db-refer-earn-fields');
        if (toggle && fields) {
            toggle.addEventListener('change', function () {
                fields.classList.toggle('d-none', !this.checked);
            });
        }

        // [CHANGE] Toggle Cash Collection Limit fields visibility
        const limitToggle = document.getElementById('cashCollectionLimitStatus');
        const limitFields = document.getElementById('db-cash-limit-fields');
        if (limitToggle && limitFields) {
            limitToggle.addEventListener('change', function () {
                limitFields.classList.toggle('d-none', !this.checked);
            });
        }

        // [CHANGE] Toggle Rider Tips fields visibility
        const tipToggle = document.getElementById('enableRiderTips');
        const tipFields = document.getElementById('db-rider-tips-fields');
        if (tipToggle && tipFields) {
            tipToggle.addEventListener('change', function () {
                tipFields.classList.toggle('d-none', !this.checked);
            });
        }
    });
</script>
@endpush
