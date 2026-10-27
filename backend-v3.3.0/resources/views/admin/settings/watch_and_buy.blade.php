@extends('layouts.admin.app', [
    'page' => $menuAdmin['settings']['active'] ?? '',
    'sub_page' => $menuAdmin['settings']['route']['watch_and_buy']['sub_active'] ?? 'watch_and_buy',
])

@section('title', __('labels.watch_buy_settings'))

@section('header_data')
    @php
        $page_title = __('labels.watch_buy_settings');
        $page_pretitle = __('labels.settings');
    @endphp
@endsection

@php
    $breadcrumbs = [
        ['title' => __('labels.home'), 'url' => route('admin.dashboard')],
        ['title' => __('labels.settings'), 'url' => route('admin.settings.index')],
        ['title' => __('labels.watch_buy'), 'url' => null],
    ];
@endphp

@section('admin-content')
    <div class="row row-cards">
        <div class="col-12">
            <form action="{{ route('admin.settings.store') }}" class="form-submit" method="post">
                @csrf
                <input type="hidden" name="type" value="watch_and_buy">
                <div class="card">
                    <div class="card-header">
                        <div>
                            <h3 class="card-title">{{ __('labels.watch_buy_settings') }}</h3>
                            <x-breadcrumb :items="$breadcrumbs"/>
                        </div>
                    </div>
                    <div class="card-body">
                        <div class="alert alert-info">{{ __('labels.watch_buy_settings_description') }}</div>
                        <div class="mb-3">
                            <label class="form-check form-switch">
                                <input type="hidden" name="enabled" value="0">
                                <input class="form-check-input" type="checkbox" name="enabled" value="1" @checked(old('enabled', $settings['enabled'] ?? true))>
                                <span class="form-check-label">{{ __('labels.watch_buy_enable') }}</span>
                            </label>
                        </div>
                        <div class="mb-3">
                            <label class="form-check form-switch">
                                <input type="hidden" name="statusesEnabled" value="0">
                                <input class="form-check-input" type="checkbox" name="statusesEnabled" value="1" @checked(old('statusesEnabled', $settings['statusesEnabled'] ?? true))>
                                <span class="form-check-label">{{ __('labels.watch_buy_enable_statuses') }}</span>
                            </label>
                            <small class="form-hint">{{ __('labels.watch_buy_status_setting_hint') }}</small>
                        </div>
                        <div class="mb-3">
                            <label class="form-label required">{{ __('labels.title') }}</label>
                            <input class="form-control" name="title" maxlength="100" value="{{ old('title', $settings['title'] ?? __('labels.watch_buy')) }}" required>
                        </div>
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label required">{{ __('labels.watch_buy_status_maximum_duration') }}</label>
                                    <div class="input-group">
                                        <input class="form-control" type="number" name="maxStatusDurationSeconds" min="5" max="60" value="{{ old('maxStatusDurationSeconds', $settings['maxStatusDurationSeconds'] ?? 15) }}" required>
                                        <span class="input-group-text">{{ __('labels.seconds') }}</span>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label required">{{ __('labels.watch_buy_max_products_per_status') }}</label>
                                    <input class="form-control" type="number" name="maxProductsPerStatus" min="1" max="10" value="{{ old('maxProductsPerStatus', $settings['maxProductsPerStatus'] ?? 5) }}" required>
                                </div>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-4">
                                <div class="mb-3">
                                    <label class="form-label required">{{ __('labels.watch_buy_maximum_duration') }}</label>
                                    <div class="input-group">
                                        <input class="form-control" type="number" name="maxDurationSeconds" min="5" max="180" value="{{ old('maxDurationSeconds', $settings['maxDurationSeconds'] ?? 60) }}" required>
                                        <span class="input-group-text">{{ __('labels.seconds') }}</span>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="mb-3">
                                    <label class="form-label required">{{ __('labels.watch_buy_maximum_upload') }}</label>
                                    <div class="input-group">
                                        <input class="form-control" type="number" name="maxUploadMb" min="5" max="500" value="{{ old('maxUploadMb', $settings['maxUploadMb'] ?? 50) }}" required>
                                        <span class="input-group-text">{{ __('labels.watch_buy_megabytes') }}</span>
                                    </div>
                                    <small class="form-hint">{{ __('labels.watch_buy_upload_limit_hint') }}</small>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="mb-3">
                                    <label class="form-label required">{{ __('labels.watch_buy_max_products_per_reel') }}</label>
                                    <input class="form-control" type="number" name="maxProductsPerReel" min="1" max="20" value="{{ old('maxProductsPerReel', $settings['maxProductsPerReel'] ?? 10) }}" required>
                                </div>
                            </div>
                        </div>
                    </div>
                    @can('updateSetting', [\App\Models\Setting::class, 'watch_and_buy'])
                        <div class="card-footer text-end">
                            <button type="submit" class="btn btn-primary">{{ __('labels.save_settings') }}</button>
                        </div>
                    @endcan
                </div>
            </form>
        </div>
    </div>
@endsection
