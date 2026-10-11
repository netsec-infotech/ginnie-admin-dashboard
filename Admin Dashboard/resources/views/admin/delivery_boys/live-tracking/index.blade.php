@extends('layouts.admin.app', ['page' => $menuAdmin['delivery_boy_management']['active'] ?? "", 'sub_page' => $menuAdmin['delivery_boy_management']['route']['delivery_boy_live_tracking']['sub_active'] ?? 'delivery_boy_live_tracking'])

@section('title', __('labels.delivery_boy_live_tracking'))

@section('header_data')
    @php
        $page_title = __('labels.delivery_boy_live_tracking');
        $page_pretitle = __('labels.admin') . " " . __('labels.delivery_boy_live_tracking');
    @endphp
@endsection

@php
    $breadcrumbs = [
        ['title' => __('labels.home'), 'url' => route('admin.dashboard')],
        ['title' => __('labels.delivery_boys'), 'url' => route('admin.delivery-boys.index')],
        ['title' => __('labels.live_tracking'), 'url' => '']
    ];
@endphp

@section('admin-content')
    <div class="page-wrapper">
        <div class="page-body">
            <div class="row g-3">
                {{-- Map panel --}}
                <div class="col-lg-8 col-xl-9">
                    <div class="card">
                        <div class="card-header">
                            <h3 class="card-title">
                                {{ __('labels.live_tracking') }}
                            </h3>
                            <div class="card-actions">
                                <span class="text-secondary" id="riderMapCount"></span>
                            </div>
                        </div>
                        <div class="card-body p-0">
                            <div id="riderTrackingMap" style="height: calc(100vh - 220px); min-height: 500px;"></div>
                        </div>
                        <div class="card-footer">
                            <div class="d-flex gap-3 flex-wrap align-items-center">
                                <span class="d-flex align-items-center gap-1">
                                    <span style="display:inline-block;width:12px;height:12px;border-radius:50%;background:#206bc4;"></span>
                                    {{ __('labels.on_delivery') }}
                                </span>
                                <span class="d-flex align-items-center gap-1">
                                    <span style="display:inline-block;width:12px;height:12px;border-radius:50%;background:#2fb344;"></span>
                                    {{ __('labels.available') }}
                                </span>
                                <span class="d-flex align-items-center gap-1">
                                    <span style="display:inline-block;width:12px;height:12px;border-radius:50%;background:#f76707;"></span>
                                    {{ __('labels.idle') }}
                                </span>
                            </div>
                        </div>
                    </div>
                </div>

                {{-- Sidebar rider list --}}
                <div class="col-lg-4 col-xl-3">
                    <div class="card">
                        <div class="card-header">
                            <h3 class="card-title">{{ __('labels.riders_in_view') }}</h3>
                        </div>
                        <div class="card-header align-items-center d-flex gap-1">
                            <div class="w-100">
                                <input type="text" class="form-control form-control-sm"
                                       id="riderSearchInput"
                                       placeholder="{{ __('labels.search') }}...">
                            </div>
                            <div class="w-100">
                                <select class="form-select form-select-sm" id="riderStatusFilter">
                                    <option value="">{{ __('labels.all_statuses') }}</option>
                                    <option value="on_delivery">{{ __('labels.on_delivery') }}</option>
                                    <option value="available">{{ __('labels.available') }}</option>
                                    <option value="idle">{{ __('labels.idle') }}</option>
                                </select>
                            </div>
                        </div>
                        <div class="list-group list-group-flush overflow-auto" id="riderSidebarList"
                             style="max-height: calc(100vh - 360px); min-height: 400px;">
                            <div class="list-group-item text-center text-secondary py-4" id="riderSidebarEmpty">
                                {{ __('labels.no_riders_in_view') }}
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection

@push('styles')
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css"
          integrity="sha256-p4NxAoJBhIIN+hmNHrzRCf9tD/miZyoHS5obTRR9BMY=" crossorigin="" />
    <style>
        .rider-tracking-icon img { display: block; }
        .rider-sidebar-item { cursor: pointer; transition: background .15s; }
        .rider-sidebar-item:hover, .rider-sidebar-item.active { background: var(--tblr-active-bg, #e9ecf0); }
        .rider-status-dot { display: inline-block; width: 10px; height: 10px; border-radius: 50%; }
        .rider-status-dot.on_delivery { background: #206bc4; }
        .rider-status-dot.available { background: #2fb344; }
        .rider-status-dot.idle { background: #f76707; }
    </style>
@endpush

@push('scripts')
    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"
            integrity="sha256-20nQCchB9co0qIjJZRGuk2/Z9VM+kNiyxNV1lvTlZBo=" crossorigin=""></script>
    <script>
        window.__RIDER_TRACKING_CONFIG__ = {
            dataUrl: '{{ route("admin.delivery-boys.live-tracking.data") }}',
            assetBase: '{{ asset("assets/images/map-markers") }}',
            pollInterval: 15000,
            riderDetailUrl: '{{ route("admin.delivery-boys.show", ":id") }}',
            defaultLatitude: {{ $defaultLatitude ?? 'null' }},
            defaultLongitude: {{ $defaultLongitude ?? 'null' }},
        };
    </script>
    <script src="{{ asset('assets/js/rider-live-tracking.js') }}" defer></script>
@endpush
