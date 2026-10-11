@extends('layouts.admin.app', ['page' => $menuAdmin['settings']['active'] ?? "", 'sub_page' => ($menuAdmin['settings']['route']['pickup'] ?? [])['sub_active'] ?? "" ])


@section('title', __('labels.pickup_settings') ?? 'Self-Pickup Settings')

@section('header_data')
    @php
        $page_title = __('labels.pickup_settings') ?? 'Self-Pickup Settings';
        $page_pretitle = __('labels.admin') . " " . __('labels.settings');
    @endphp
@endsection

@php
    $breadcrumbs = [
        ['title' => __('labels.home'), 'url' => route('admin.dashboard')],
        ['title' => __('labels.settings'), 'url' => route('admin.settings.index')],
        ['title' => __('labels.pickup_settings') ?? 'Self-Pickup Settings', 'url' => null],
    ];
@endphp

@section('admin-content')
    <div class="page-header d-print-none">
        <div class="row g-2 align-items-center">
            <div class="col">
                <h2 class="page-title">{{ __('labels.pickup_settings') ?? 'Self-Pickup Settings' }}</h2>
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
                            <a class="nav-link active" href="#pills-general">{{ __('labels.general') ?? 'General' }}</a>
                        </nav>
                    </div>
                </div>
                <div class="col-sm" data-bs-spy="scroll" data-bs-target="#pills" data-bs-offset="0">
                    <div class="row row-cards">
                        <div class="col-12">
                            <form action="{{ route('admin.settings.store') }}" class="form-submit" method="post" id="pickup-settings-form">
                                @csrf
                                <input type="hidden" name="type" value="pickup">
                                <div class="card mb-4" id="pills-general">
                                    <div class="card-header">
                                        <h4 class="card-title">{{ __('labels.pickup_settings') ?? 'Self-Pickup Settings' }}</h4>
                                    </div>
                                    <div class="card-body">
                                        <div class="mb-3">
                                            <label class="form-check form-switch">
                                                <input type="hidden" name="enabled" value="0">
                                                <input class="form-check-input" type="checkbox" name="enabled" value="1" {{ !empty($settings['enabled']) ? 'checked' : '' }}>
                                                <span class="form-check-label">{{ __('labels.enable_self_pickup') ?? 'Enable Store Self-Pickup globally' }}</span>
                                            </label>
                                            <span class="text-muted d-block mt-1">
                                                {{ __('labels.enable_self_pickup_description') ?? 'Allow customers to pick up orders directly from stores. This bypasses delivery, handling, and per-store drop-off fees while retaining platform fees.' }}
                                            </span>
                                        </div>
                                    </div>
                                </div>
                                <div class="card-footer text-end">
                                    <div class="d-flex">
                                        @can('updateSetting', [\App\Models\Setting::class, 'pickup'])
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

@push('js')
    <script src="{{ asset('assets/js/admin-pickup-settings.js') }}"></script>
@endpush
