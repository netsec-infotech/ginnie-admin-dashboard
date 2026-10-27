@extends('layouts.admin.app', ['page' => $menuAdmin['design_appearance']['active'] ?? 'design_appearance', 'sub_page' => $menuAdmin['design_appearance']['route']['home_builder']['sub_active'] ?? 'home-builder'])

@section('title', __('labels.home_builder'))

@section('header_data')
    @php
        $page_title = __('labels.home_builder');
        $page_pretitle = __('labels.menu_section_marketing');
    @endphp
@endsection

@push('styles')
    <link rel="stylesheet" href="{{ asset('assets/vendor/devices-css/devices.min.css') }}">
    <!-- SwiperJS styles for Play Image slider carousels -->
    <link rel="stylesheet" href="{{ asset('assets/vendor/swiperjs/swiper-bundle.min.css') }}">
    <link rel="stylesheet" href="{{ asset('assets/vendor/cropperjs/css/cropper.min.css') }}">
    <link rel="stylesheet"
        href="{{ asset('assets/css/home-builder.css') }}?v={{ filemtime(public_path('assets/css/home-builder.css')) }}">
    <link rel="stylesheet"
        href="{{ asset('assets/css/home-builder-play-image.css') }}?v={{ filemtime(public_path('assets/css/home-builder-play-image.css')) }}">
@endpush

@section('admin-content')
    <div id="home-builder-container" class="hb-container">
        @if ($canEdit)
            <div id="hb-legacy-migration-card" class="alert alert-info d-none mb-3"
                data-status-url="{{ route('admin.home-builder.legacy-migration.status') }}"
                data-run-url="{{ route('admin.home-builder.legacy-migration.run') }}"
                data-home-banner-status-url="{{ route('admin.home-builder.legacy-migration.home-banners.status') }}"
                data-home-banner-run-url="{{ route('admin.home-builder.legacy-migration.home-banners.run') }}">
                <div class="d-flex align-items-center justify-content-between gap-3 flex-wrap d-none"
                    data-role="legacy-migration-row">
                    <div>
                        <div class="fw-bold">Legacy home content available</div>
                        <div class="small" data-role="legacy-migration-summary">
                            Featured sections and banners can be moved into Home Builder.
                        </div>
                    </div>
                    <button type="button" class="btn btn-info" data-role="run-legacy-migration">
                        <i class="ti ti-transfer-in me-1"></i>Migrate legacy content
                    </button>
                </div>
                <div class="border-top border-info-subtle mt-3 pt-3 d-none" data-role="home-banner-migration-row">
                    <div class="d-flex align-items-center justify-content-between gap-3 flex-wrap">
                        <div>
                            <div class="fw-semibold">Existing Home Builder banners available</div>
                            <div class="small" data-role="home-banner-migration-summary">
                                Convert only banner sections already saved in Home Builder.
                            </div>
                        </div>
                        <button type="button" class="btn btn-outline-info" data-role="run-home-banner-migration">
                            <i class="ti ti-photo-up me-1"></i>Convert Home Builder banners only
                        </button>
                    </div>
                </div>
            </div>
        @endif

        <div class="card card-sm rounded-3 mb-3 hb-tabs-card" data-role="hb-tabs-card">
            <div class="card-body py-2 px-3 d-flex align-items-center justify-content-between">
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
                {{-- Fullscreen Mode Toggle Button --}}
                <button type="button" class="btn shadow-none" data-role="toggle-fullscreen"
                    title="Toggle Fullscreen Canvas">
                    <i class="ti ti-maximize fs-2" data-role="fs-icon-enter"></i>
                    <i class="ti ti-minimize fs-2 d-none" data-role="fs-icon-exit"></i>
                </button>
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
    <!-- SwiperJS library for Play Image slider carousels -->
    <script src="{{ asset('assets/vendor/swiperjs/swiper-bundle.min.js') }}" defer></script>
    <script src="{{ asset('assets/vendor/cropperjs/js/cropper.min.js') }}" defer></script>
    <script src="{{ asset('assets/js/navbar-header.js') }}?v={{ filemtime(public_path('assets/js/navbar-header.js')) }}"
        defer></script>
    <script src="{{ asset('assets/js/home-builder.js') }}?v={{ filemtime(public_path('assets/js/home-builder.js')) }}"
        defer></script>
@endpush
