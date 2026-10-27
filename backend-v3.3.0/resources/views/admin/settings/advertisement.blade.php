@extends('layouts.admin.app', ['page' => $menuAdmin['settings']['active'] ?? "", 'sub_page' => $menuAdmin['settings']['route']['advertisement']['sub_active'] ?? "advertisement" ])

@section('title', __('labels.advertisement_settings'))

@section('header_data')
    @php
        $page_title = __('labels.advertisement_settings');
        $page_pretitle = __('labels.admin') . " " . __('labels.settings');
    @endphp
@endsection

@php
    $breadcrumbs = [
        ['title' => __('labels.home'), 'url' => route('admin.dashboard')],
        ['title' => __('labels.settings'), 'url' => route('admin.settings.index')],
        ['title' => __('labels.advertisement_settings'), 'url' => null],
    ];
@endphp

@section('admin-content')
    <div class="page-header d-print-none">
        <div class="row g-2 align-items-center">
            <div class="col">
                <h2 class="page-title">{{ __('labels.advertisement_settings') }}</h2>
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
                            <a class="nav-link" href="#pills-general">{{ __('labels.general') }}</a>
                            <a class="nav-link" href="#pills-pricing">{{ __('labels.ad_pricing') }}</a>
                        </nav>
                    </div>
                </div>
                <div class="col-sm" data-bs-spy="scroll" data-bs-target="#pills" data-bs-offset="0">
                    <div class="row row-cards">
                        <div class="col-12">
                            <form action="{{ route('admin.settings.store') }}" class="form-submit" method="post">
                                @csrf
                                <input type="hidden" name="type" value="advertisement">

                                {{-- General --}}
                                <div class="card mb-4" id="pills-general">
                                    <div class="card-header">
                                        <h4 class="card-title">{{ __('labels.general') }}</h4>
                                    </div>
                                    <div class="card-body">
                                        <div class="mb-3">
                                            <label class="form-check form-switch">
                                                <input type="hidden" name="featureEnabled" value="0">
                                                <input class="form-check-input" type="checkbox" name="featureEnabled" value="1" {{ ($settings['featureEnabled'] ?? false) ? 'checked' : '' }}>
                                                <span class="form-check-label">
                                                    {{ __('labels.ad_feature_enabled_label') }}
                                                    <x-form-info-tooltip :title="__('labels.ad_feature_enabled_hint')" />
                                                </span>
                                            </label>
                                        </div>
                                        <div class="mb-3">
                                            <label class="form-label">
                                                {{ __('labels.ad_disable_behavior') }}
                                                <x-form-info-tooltip :title="__('labels.ad_disable_behavior_hint')" />
                                            </label>
                                            <select name="disableBehavior" class="form-select">
                                                <option value="keep_running" {{ ($settings['disableBehavior'] ?? 'keep_running') === 'keep_running' ? 'selected' : '' }}>
                                                    {{ __('labels.ad_disable_behavior_keep_running') }}
                                                </option>
                                                <option value="pause_all" {{ ($settings['disableBehavior'] ?? '') === 'pause_all' ? 'selected' : '' }}>
                                                    {{ __('labels.ad_disable_behavior_pause_all') }}
                                                </option>
                                            </select>
                                        </div>
                                    </div>
                                </div>

                                {{-- Pricing --}}
                                <div class="card mb-4" id="pills-pricing">
                                    <div class="card-header">
                                        <h4 class="card-title">{{ __('labels.ad_pricing') }}</h4>
                                    </div>
                                    <div class="card-body">
                                        <div class="row">
                                            <div class="col-md-6 mb-3">
                                                <label class="form-label">
                                                    {{ __('labels.ad_cpc_rate') }}
                                                    <x-form-info-tooltip :title="__('labels.ad_cpc_rate_hint')" />
                                                </label>
                                                <input type="number" step="0.0001" min="0" class="form-control" name="cpcRate"
                                                       value="{{ $settings['cpcRate'] ?? '' }}"
                                                       placeholder="{{ __('labels.ad_cpc_rate_placeholder') }}">
                                            </div>
                                            <div class="col-md-6 mb-3">
                                                <label class="form-label">
                                                    {{ __('labels.ad_wallet_min_topup') }}
                                                    <x-form-info-tooltip :title="__('labels.ad_wallet_min_topup_hint')" />
                                                </label>
                                                <input type="number" step="0.01" min="0" class="form-control" name="walletMinTopup"
                                                       value="{{ $settings['walletMinTopup'] ?? '' }}"
                                                       placeholder="{{ __('labels.ad_wallet_min_topup_placeholder') }}">
                                            </div>
                                        </div>

                                        <div class="row">
                                            <div class="col-md-6 mb-3">
                                                <label class="form-label">
                                                    {{ __('labels.ad_impression_multiplier_min') }}
                                                    <x-form-info-tooltip :title="__('labels.ad_impression_multiplier_min_tooltip')" />
                                                </label>
                                                <input type="number" min="1" max="1000" class="form-control"
                                                       name="impressionMultiplierMin"
                                                       value="{{ $settings['impressionMultiplierMin'] ?? 12 }}"
                                                       placeholder="12">
                                            </div>
                                            <div class="col-md-6 mb-3">
                                                <label class="form-label">
                                                    {{ __('labels.ad_impression_multiplier_max') }}
                                                    <x-form-info-tooltip :title="__('labels.ad_impression_multiplier_max_tooltip')" />
                                                </label>
                                                <input type="number" min="1" max="1000" class="form-control"
                                                       name="impressionMultiplierMax"
                                                       value="{{ $settings['impressionMultiplierMax'] ?? 20 }}"
                                                       placeholder="20">
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <div class="card-footer text-end">
                                    <div class="d-flex">
                                        @can('updateSetting', [\App\Models\Setting::class, 'advertisement'])
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
