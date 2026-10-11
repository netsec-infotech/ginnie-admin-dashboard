@extends('layouts.admin.app', ['page' => $menuAdmin['home_builder']['active'] ?? 'home-builder'])

@section('title', __('labels.home_builder'))

@section('header_data')
    @php
        $page_title = __('labels.home_builder');
        $page_pretitle = __('labels.menu_section_marketing');
    @endphp
@endsection

@push('styles')
    <link rel="stylesheet" href="{{ asset('assets/vendor/devices-css/devices.min.css') }}">
    <link rel="stylesheet"
        href="{{ asset('assets/css/home-builder.css') }}?v={{ filemtime(public_path('assets/css/home-builder.css')) }}">
@endpush

@section('admin-content')
    <div id="home-builder-container" class="hb-container">
        <div class="card card-sm rounded-3 mb-3 hb-tabs-card" data-role="hb-tabs-card">
            <div class="card-body py-2 px-3">
                <ul class="nav nav-pills gap-1" id="hb-tabs" role="tablist">
                    <li class="nav-item" role="presentation">
                        <a href="#" class="nav-link active rounded-pill px-3 py-2" data-pane="zones" role="tab">
                            <i class="ti ti-map-pins me-2"></i>{{ __('labels.hb_zone_layout') }}
                        </a>
                    </li>
                    <li class="nav-item" role="presentation">
                        <a href="#" class="nav-link rounded-pill px-3 py-2" data-pane="navbar-header" role="tab">
                            <i class="ti ti-layout-navbar me-2"></i>{{ __('labels.hb_navbar_header_layout') }}
                        </a>
                    </li>
                    <li class="nav-item" role="presentation">
                        <a href="#" class="nav-link rounded-pill px-3 py-2" data-pane="app" role="tab">
                            <i class="ti ti-device-mobile me-2"></i>{{ __('labels.hb_app_layout') }}
                        </a>
                    </li>
                    <li class="nav-item" role="presentation">
                        <a href="#" class="nav-link rounded-pill px-3 py-2" data-pane="web" role="tab">
                            <i class="ti ti-device-desktop me-2"></i>{{ __('labels.hb_web_layout') }}
                        </a>
                    </li>
                </ul>
            </div>
        </div>

        <div id="hb-pane-zones">
            @include('admin.home-builder.partials.zones-table')
        </div>

        <div id="hb-pane-navbar-header" class="d-none">
            @include('admin.home-builder.partials.navbar-header')
        </div>

        <div id="hb-pane-app" class="d-none">
            @include('admin.home-builder.partials.builder', [
                'platform' => 'app',
                'options' => $appOptions,
                'templates' => $appTemplates,
                'themes' => $appThemes,
            ])
        </div>

        <div id="hb-pane-web" class="d-none">
            @include('admin.home-builder.partials.builder', [
                'platform' => 'web',
                'options' => $webOptions,
                'templates' => $webTemplates,
                'themes' => $webThemes,
            ])
        </div>

        @include('admin.home-builder.partials.confirm-modal')
        @include('admin.home-builder.partials.image-modal')
        @include('admin.home-builder.partials.templates-modal')
        @include('admin.home-builder.partials.save-preset-modal')
        @include('admin.home-builder.partials.revisions-modal')
    </div>
@endsection

@push('scripts')
    <script src="https://unpkg.com/@lottiefiles/lottie-player@latest/dist/lottie-player.js"></script>
    <script src="{{ asset('assets/vendor/sortablejs/sortable.min.js') }}" defer></script>
    <script src="{{ asset('assets/js/navbar-header.js') }}?v={{ filemtime(public_path('assets/js/navbar-header.js')) }}"
        defer></script>
    <script src="{{ asset('assets/js/home-builder.js') }}?v={{ filemtime(public_path('assets/js/home-builder.js')) }}"
        defer></script>
@endpush
