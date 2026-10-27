@extends('layouts.admin.app', ['page' => $menuAdmin['design_appearance']['active'] ?? 'design_appearance', 'sub_page' => $menuAdmin['design_appearance']['route']['onboarding_screens']['sub_active'] ?? 'onboarding-screens'])

@section('title', __('labels.onboarding_screens'))

@section('header_data')
    @php
        $page_title = __('labels.onboarding_screens');
        $page_pretitle = __('labels.admin') . ' ' . __('labels.management');
    @endphp
@endsection

@php
    $selectedApp = request('app_type', \App\Enums\OnboardingAppTypeEnum::CUSTOMER());
    $breadcrumbs = [
        ['title' => __('labels.home'), 'url' => route('admin.dashboard')],
        ['title' => __('labels.design_appearance'), 'url' => null],
        ['title' => __('labels.onboarding_screens'), 'url' => null],
    ];
@endphp

@push('styles')
    <link rel="stylesheet" href="{{ asset('assets/vendor/devices-css/devices.min.css') }}">
    <link rel="stylesheet" href="{{ hyperAsset('assets/css/onboarding-screens.css') }}">
@endpush

@section('admin-content')
    <div class="onboarding-admin"
        data-onboarding-admin
        data-selected-app="{{ $selectedApp }}"
        data-max-screens="{{ \App\Models\OnboardingScreen::MAX_PER_APP }}"
        data-datatable-url="{{ route('admin.onboarding-screens.datatable') }}"
        data-toggle-url-template="{{ url('admin/onboarding-screens/settings/__APP__/toggle') }}">
        <div class="onboarding-page-heading">
            <div class="d-flex align-items-center gap-3">
                <div class="onboarding-heading-icon">
                    <i class="ti ti-device-mobile"></i>
                </div>
                <div>
                    <h2 class="onboarding-page-title">{{ __('labels.onboarding_screens') }}</h2>
                    <p class="onboarding-page-subtitle">Manage onboarding screens for your applications.</p>
                </div>
            </div>
        </div>

        <div class="card onboarding-selector-card">
            <div class="card-body">
                <div class="onboarding-toolbar">
                    <div class="onboarding-app-tabs" role="group" aria-label="{{ __('labels.app_type') }}">
                        @foreach ($appTypes as $appType)
                            @php
                                $appIcon = match ($appType->value) {
                                    \App\Enums\OnboardingAppTypeEnum::SELLER() => 'ti-building-store',
                                    \App\Enums\OnboardingAppTypeEnum::RIDER() => 'ti-bike',
                                    default => 'ti-user',
                                };
                            @endphp
                            <button type="button"
                                class="btn onboarding-app-tab {{ $selectedApp === $appType->value ? 'active' : '' }}"
                                data-onboarding-app-tab="{{ $appType->value }}"
                                data-onboarding-app-label="{{ $appType->label() }}">
                                <i class="ti {{ $appIcon }}"></i>
                                {{ $appType->label() }}
                            </button>
                        @endforeach
                    </div>

                    <div class="onboarding-toggle-card">
                        @foreach ($appTypes as $appType)
                            <label class="form-check form-switch onboarding-app-toggle {{ $selectedApp === $appType->value ? '' : 'd-none' }}"
                                data-onboarding-toggle-wrap="{{ $appType->value }}">
                                <span class="form-check-label fw-medium me-2">
                                    {{ __('labels.enable') ?? 'Enable' }} {{ __('labels.onboarding_screens') }}
                                    <span class="text-secondary">({{ $appType->label() }})</span>
                                </span>
                                <input class="form-check-input m-0" type="checkbox"
                                    data-onboarding-toggle="{{ $appType->value }}"
                                    @checked($settings[$appType->value] ?? true)>
                            </label>
                        @endforeach
                    </div>
                </div>
            </div>
        </div>

        <div class="onboarding-builder-workspace">
            <div class="onboarding-preview-canvas">
                <div class="card onboarding-live-preview-card">
                    <div class="card-header border-bottom p-3 d-flex flex-column gap-3">
                        <div class="d-flex align-items-center justify-content-between w-100">
                            <div class="d-flex align-items-center gap-2 min-w-0">
                                <div class="onboarding-preview-title-avatar">
                                    <i class="ti ti-device-mobile"></i>
                                </div>
                                <div>
                                    <h3 class="card-title m-0 fw-bold fs-3 lh-sm">{{ __('labels.preview') }}</h3>
                                    <div class="text-muted small">Preview how the selected onboarding screen will appear in the app.</div>
                                </div>
                            </div>
                            <span class="text-secondary small" data-onboarding-preview-count>0 / 0</span>
                        </div>

                        <div class="d-flex align-items-center justify-content-between w-100 flex-wrap gap-2">
                            <div class="btn-group btn-group-sm onboarding-device-switcher" role="group" data-onboarding-device-switcher>
                                <button type="button" class="btn" data-onboarding-device="android" title="Android preview">
                                    <i class="ti ti-brand-android"></i> Android
                                </button>
                                <button type="button" class="btn active" data-onboarding-device="ios" title="iOS preview">
                                    <i class="ti ti-brand-apple"></i> iOS
                                </button>
                            </div>

                            <button type="button" class="btn btn-outline-primary btn-sm" data-onboarding-preview-app>
                                <i class="ti ti-eye"></i>
                                Full Preview
                            </button>
                        </div>
                    </div>
                    <div class="card-body onboarding-preview-canvas-body">
                        @include('admin.onboarding-screens.partials.preview', [
                            'screen' => null,
                            'selectedApp' => $selectedApp,
                            'framed' => false,
                        ])
                    </div>
                </div>
            </div>

            <aside class="onboarding-builder-inspector">
                <div class="card onboarding-builder-card mb-3">
                    <div class="card-header justify-content-between">
                        <div class="d-flex align-items-center gap-2 min-w-0">
                            <i class="ti ti-stack-2 text-primary fs-2"></i>
                            <div class="min-w-0">
                                <h3 class="card-title text-truncate mb-0">Screen Sequence</h3>
                                <p class="card-subtitle mb-0">
                                    <span data-onboarding-screen-count>0</span> / {{ \App\Models\OnboardingScreen::MAX_PER_APP }} screens configured
                                </p>
                            </div>
                        </div>
                        @if ($createPermission)
                            <button type="button" class="btn btn-outline-primary btn-sm" data-onboarding-create>
                                <i class="ti ti-plus"></i>
                                Add Screen
                            </button>
                        @endif
                    </div>
                    <div class="card-body">
                        <div class="onboarding-builder-list" data-onboarding-builder-list>
                            <div class="onboarding-builder-empty">
                                <i class="ti ti-device-mobile-plus"></i>
                                <span>Add up to {{ \App\Models\OnboardingScreen::MAX_PER_APP }} onboarding screens for this app.</span>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="card onboarding-editor-card d-none" data-onboarding-editor-card>
                    <div class="card-body" data-onboarding-inline-editor>
                    </div>
                </div>

                <div class="card onboarding-table-card d-none" aria-hidden="true">
                    <div class="card-header justify-content-between">
                        <div>
                            <h3 class="card-title" data-onboarding-table-title>Detailed Records</h3>
                            <p class="card-subtitle" data-onboarding-table-subtitle>{{ __('labels.onboarding_screens') }}</p>
                        </div>
                        <button class="btn btn-icon btn-outline-primary refresh-table" type="button" title="{{ __('labels.refresh') }}">
                            <i class="ti ti-refresh"></i>
                        </button>
                    </div>
                    <div class="card-body">
                        <x-datatable id="onboarding-screens-table"
                            :columns="$columns"
                            route="{{ route('admin.onboarding-screens.datatable', ['app_type' => $selectedApp]) }}"
                            :options="['order' => [[0, 'asc']], 'pageLength' => 10]" />
                    </div>
                </div>
            </aside>
        </div>
    </div>

    <div class="modal fade" id="onboarding-screen-form-modal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-centered">
            <form class="modal-content form-submit"
                action="{{ route('admin.onboarding-screens.store') }}"
                method="POST"
                enctype="multipart/form-data"
                data-onboarding-form
                data-store-url="{{ route('admin.onboarding-screens.store') }}">
                @csrf
                <input type="hidden" name="stay_on_page" value="1">
                <input type="hidden" name="app_type" value="{{ $selectedApp }}" data-onboarding-form-app>

                <div class="modal-header">
                    <h5 class="modal-title" data-onboarding-form-title>{{ __('labels.create_onboarding_screen') }}</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" data-dismiss="modal" data-onboarding-editor-cancel aria-label="{{ __('labels.close') }}"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">{{ __('labels.title') }}</label>
                        <input type="text" class="form-control" name="title"
                            maxlength="30"
                            placeholder="{{ __('labels.enter_title') }}"
                            data-onboarding-form-field="title"
                            data-onboarding-character-field
                            data-onboarding-character-max="30">
                        <div class="d-flex justify-content-between mt-1 small">
                            <span class="text-danger d-none" data-onboarding-character-message></span>
                            <span class="text-secondary ms-auto" data-onboarding-character-counter>0/30</span>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">{{ __('labels.subtitle') }}</label>
                        <textarea class="form-control" name="subtitle" rows="3"
                            maxlength="70"
                            placeholder="{{ __('labels.enter_subtitle') }}"
                            data-onboarding-form-field="subtitle"
                            data-onboarding-character-field
                            data-onboarding-character-max="70"></textarea>
                        <div class="d-flex justify-content-between mt-1 small">
                            <span class="text-danger d-none" data-onboarding-character-message></span>
                            <span class="text-secondary ms-auto" data-onboarding-character-counter>0/70</span>
                        </div>
                    </div>

                    <div class="onboarding-design-panel">
                        <div class="d-flex align-items-center justify-content-between mb-2">
                            <label class="form-label mb-0">Design Presets</label>
                            <span class="text-secondary small">Optional</span>
                        </div>
                        <div class="onboarding-preset-grid mb-3">
                            <button type="button" class="onboarding-preset-tile" data-onboarding-preset="clean_white">Clean White</button>
                            <button type="button" class="onboarding-preset-tile" data-onboarding-preset="brand_gradient">Brand Gradient</button>
                            <button type="button" class="onboarding-preset-tile" data-onboarding-preset="full_image">Full Image</button>
                            <button type="button" class="onboarding-preset-tile" data-onboarding-preset="dark_overlay">Dark Overlay</button>
                            <button type="button" class="onboarding-preset-tile" data-onboarding-preset="minimal_text">Minimal Text</button>
                        </div>

                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label">Background Type</label>
                                <select class="form-select" name="background_type" data-onboarding-design-field="background_type">
                                    <option value="solid">Solid Color</option>
                                    <option value="gradient">Gradient</option>
                                    <option value="image">Image</option>
                                </select>
                            </div>
                            <div class="col-md-6" data-onboarding-background-solid>
                                <label class="form-label">Solid Color</label>
                                <input type="color" class="form-control form-control-color w-100" name="background_color" value="#ffffff" data-onboarding-design-field="background_color">
                            </div>
                            <div class="col-md-4" data-onboarding-background-gradient>
                                <label class="form-label">Gradient Start</label>
                                <input type="color" class="form-control form-control-color w-100" name="gradient_start_color" value="#0d6efd" data-onboarding-design-field="gradient_start_color">
                            </div>
                            <div class="col-md-4" data-onboarding-background-gradient>
                                <label class="form-label">Gradient End</label>
                                <input type="color" class="form-control form-control-color w-100" name="gradient_end_color" value="#20c997" data-onboarding-design-field="gradient_end_color">
                            </div>
                            <div class="col-md-4" data-onboarding-background-gradient>
                                <label class="form-label">Gradient Angle</label>
                                <input type="number" class="form-control" name="gradient_angle" min="0" max="360" value="180" data-onboarding-design-field="gradient_angle">
                            </div>
                            <div class="col-12" data-onboarding-background-image-wrap>
                                <label class="form-label">Background Image</label>
                                <div data-onboarding-background-filepond-slot>
                                    <input type="file" class="filepond" name="background_image" accept="image/*"
                                        data-max-files="1"
                                        data-max-file-size="10MB"
                                        data-accepted-file-types="image/jpeg,image/png,image/webp,image/gif,image/svg+xml"
                                        data-onboarding-design-field="background_image">
                                </div>
                                <small class="form-hint text-secondary">{{ __('labels.onboarding_background_supported_types') }}</small>
                            </div>
                            <div class="col-md-6" data-onboarding-background-image-options>
                                <label class="form-label">Background Fit</label>
                                <select class="form-select" name="background_image_fit" data-onboarding-design-field="background_image_fit">
                                    <option value="cover">Cover</option>
                                    <option value="contain">Contain</option>
                                    <option value="fill">Fill</option>
                                </select>
                            </div>
                            <div class="col-md-6" data-onboarding-background-image-options>
                                <label class="form-label">Background Position</label>
                                <select class="form-select" name="background_image_position" data-onboarding-design-field="background_image_position">
                                    <option value="center">Center</option>
                                    <option value="top">Top</option>
                                    <option value="bottom">Bottom</option>
                                    <option value="left">Left</option>
                                    <option value="right">Right</option>
                                </select>
                            </div>
                            <div class="col-md-4">
                                <label class="form-check form-switch mt-4">
                                    <input type="hidden" name="overlay_enabled" value="0">
                                    <input class="form-check-input" type="checkbox" name="overlay_enabled" value="1" data-onboarding-design-field="overlay_enabled">
                                    <span class="form-check-label">Overlay</span>
                                </label>
                            </div>
                            <div class="col-md-4" data-onboarding-overlay-fields>
                                <label class="form-label">Overlay Type</label>
                                <select class="form-select" name="overlay_type" data-onboarding-design-field="overlay_type">
                                    <option value="color">Color</option>
                                    <option value="image">Image</option>
                                </select>
                            </div>
                            <div class="col-md-4" data-onboarding-overlay-color-options>
                                <label class="form-label">Overlay Color</label>
                                <input type="color" class="form-control form-control-color w-100" name="overlay_color" value="#000000" data-onboarding-design-field="overlay_color">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Overlay Opacity</label>
                                <input type="range" class="form-range" name="overlay_opacity" min="0" max="1" step="0.05" value="0" data-onboarding-design-field="overlay_opacity">
                            </div>
                            <div class="col-12" data-onboarding-overlay-image-wrap>
                                <label class="form-label">Overlay Image</label>
                                <div data-onboarding-overlay-filepond-slot>
                                    <input type="file" class="filepond" name="overlay_image" accept="image/*"
                                        data-max-files="1"
                                        data-max-file-size="10MB"
                                        data-accepted-file-types="image/jpeg,image/png,image/webp,image/gif,image/svg+xml"
                                        data-onboarding-design-field="overlay_image">
                                </div>
                                <small class="form-hint text-secondary">{{ __('labels.onboarding_overlay_supported_types') }}</small>
                            </div>
                            <div class="col-md-6" data-onboarding-overlay-image-options>
                                <label class="form-label">Overlay Fit</label>
                                <select class="form-select" name="overlay_image_fit" data-onboarding-design-field="overlay_image_fit">
                                    <option value="cover">Cover</option>
                                    <option value="contain">Contain</option>
                                    <option value="fill">Fill</option>
                                </select>
                            </div>
                            <div class="col-md-6" data-onboarding-overlay-image-options>
                                <label class="form-label">Overlay Position</label>
                                <select class="form-select" name="overlay_image_position" data-onboarding-design-field="overlay_image_position">
                                    <option value="center">Center</option>
                                    <option value="top">Top</option>
                                    <option value="bottom">Bottom</option>
                                    <option value="left">Left</option>
                                    <option value="right">Right</option>
                                </select>
                            </div>
                            <div class="col-md-6" data-onboarding-overlay-image-options>
                                <label class="form-label d-flex justify-content-between">
                                    <span>Overlay Size</span>
                                    <span class="text-secondary" data-onboarding-range-value="overlay_image_size">100%</span>
                                </label>
                                <input type="range" class="form-range" name="overlay_image_size" min="20" max="100" value="100" data-onboarding-design-field="overlay_image_size" data-onboarding-range="overlay_image_size">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Title Color</label>
                                <input type="color" class="form-control form-control-color w-100" name="title_color" value="#111827" data-onboarding-design-field="title_color">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Subtitle Color</label>
                                <input type="color" class="form-control form-control-color w-100" name="subtitle_color" value="#6b7280" data-onboarding-design-field="subtitle_color">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Content Alignment</label>
                                <select class="form-select" name="content_alignment" data-onboarding-design-field="content_alignment">
                                    <option value="center">Center</option>
                                    <option value="left">Left</option>
                                    <option value="right">Right</option>
                                </select>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Button Background</label>
                                <input type="color" class="form-control form-control-color w-100" name="button_background_color" value="#0d6efd" data-onboarding-design-field="button_background_color">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Button Text Color</label>
                                <input type="color" class="form-control form-control-color w-100" name="button_foreground_color" value="#ffffff" data-onboarding-design-field="button_foreground_color">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Active Indicator</label>
                                <input type="color" class="form-control form-control-color w-100" name="indicator_active_color" value="#0d6efd" data-onboarding-design-field="indicator_active_color">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Inactive Indicator</label>
                                <input type="color" class="form-control form-control-color w-100" name="indicator_inactive_color" value="#dbe2ea" data-onboarding-design-field="indicator_inactive_color">
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-primary me-auto" data-onboarding-preview-current>
                        <i class="ti ti-eye"></i>
                        {{ __('labels.preview') }}
                    </button>
                    <button type="button" class="btn btn-link" data-bs-dismiss="modal" data-dismiss="modal" data-onboarding-editor-cancel>{{ __('labels.cancel') }}</button>
                    <button type="submit" class="btn btn-primary" data-onboarding-form-submit>{{ __('labels.create') }}</button>
                </div>
            </form>
        </div>
    </div>

    <div class="modal fade" id="onboarding-screen-preview-modal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-sm modal-dialog-centered">
            <div class="modal-content onboarding-preview-modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">{{ __('labels.preview') }}</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" data-dismiss="modal" aria-label="{{ __('labels.close') }}"></button>
                </div>
                <div class="modal-body">
                    @include('admin.onboarding-screens.partials.preview', [
                        'screen' => null,
                        'selectedApp' => $selectedApp,
                        'framed' => false,
                    ])
                </div>
            </div>
        </div>
    </div>
@endsection

@push('scripts')
    <script>
        window.onboardingScreenLabels = {
            enabled: @json(__('labels.onboarding_enabled')),
            disabled: @json(__('labels.onboarding_disabled')),
            deleteConfirmation: @json(__('labels.delete_onboarding_screen_confirmation')),
            createTitle: @json(__('labels.create_onboarding_screen')),
            editTitle: @json(__('labels.edit_onboarding_screen')),
            createButton: @json(__('labels.create')),
            updateButton: @json(__('labels.update')),
            characterLimitMessage: @json(__('labels.character_limit_message')),
            limitReached: @json(__('labels.onboarding_screen_limit_reached', ['count' => \App\Models\OnboardingScreen::MAX_PER_APP])),
        };
        window.onboardingPreviewFallback = {
            title: @json(__('labels.no_onboarding_preview_title')),
            subtitle: @json(__('labels.no_onboarding_preview_subtitle')),
        };
    </script>
    <script src="{{ hyperAsset('assets/js/onboarding-screens.js') }}" defer></script>
@endpush
