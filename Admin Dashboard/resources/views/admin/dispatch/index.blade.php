@php use App\Enums\DateRangeFilterEnum; @endphp
@extends('layouts.admin.app', ['page' => $menuAdmin['dispatch']['active'] ?? ''])

@section('title', __('labels.dispatch_management'))

@section('header_data')
    @php
        $page_title = __('labels.dispatch_management');
        $page_pretitle = __('labels.admin') . ' ' . __('labels.dispatch_management');
    @endphp
@endsection

@php
    $breadcrumbs = [
        ['title' => __('labels.home'), 'url' => route('admin.dashboard')],
        ['title' => __('labels.dispatch_management'), 'url' => null],
    ];
@endphp

@section('admin-content')
    <div id="dispatch-container"
         data-stats-url="{{ route('admin.dispatch.stats') }}"
         data-riders-url="{{ route('admin.dispatch.riders-on-delivery') }}"
         data-unassigned-url="{{ route('admin.dispatch.unassigned-orders') }}"
         data-pickup-url="{{ route('admin.dispatch.ready-for-pickup') }}"
         data-order-show-url="{{ route('admin.orders.show', ['id' => '__ID__']) }}"
         data-label-stale-alert="{{ __('labels.stale_unassigned_alert') }}"
         data-label-no-data="{{ __('labels.no_data_available') }}"
         data-label-no-activity="{{ __('labels.no_recent_activity') }}"
         data-rider-search-url="{{ route('admin.delivery-boys.search') }}"
         data-reassign-url="{{ route('admin.orders.reassign_rider', ['id' => '__ID__']) }}"
         data-edit-permission="{{ $editPermission ? '1' : '0' }}"
         data-label-assign-success="{{ __('labels.rider_assigned_successfully') }}"
         data-label-deliveries="{{ __('labels.deliveries') }}"
         data-label-hourly-deliveries="{{ __('labels.hourly_delivery_performance') }}">

        {{-- Header card with zone filter + auto-refresh --}}
        <div class="row row-cards mb-3">
            <div class="col-12">
                <div class="card">
                    <div class="card-header">
                        <div>
                            <h3 class="card-title">{{ __('labels.dispatch_management') }}</h3>
                            <x-breadcrumb :items="$breadcrumbs"/>
                        </div>
                        <div class="card-actions">
                            <div class="row g-2 align-items-center">
                                <div class="col-auto">
                                    <select class="form-select form-select-sm" id="dispatch-zone-filter"
                                            style="min-width: 180px;">
                                        <option value="">{{ __('labels.all_zones') }}</option>
                                        @foreach($zones as $zone)
                                            <option
                                                value="{{ $zone->id }}" {{ $zoneId == $zone->id ? 'selected' : '' }}>
                                                {{ $zone->name }}
                                            </option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="col-auto">
                                    <span class="text-muted small" id="dispatch-countdown"></span>
                                </div>
                                <div class="col-auto">
                                    <button class="btn btn-outline-primary btn-sm" id="dispatch-refresh">
                                        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16"
                                             viewBox="0 0 24 24"
                                             fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"
                                             stroke-linejoin="round" class="icon icon-tabler icon-tabler-refresh">
                                            <path stroke="none" d="M0 0h24v24H0z" fill="none"/>
                                            <path d="M20 11a8.1 8.1 0 0 0 -15.5 -2m-.5 -4v4h4"/>
                                            <path d="M4 13a8.1 8.1 0 0 0 15.5 2m.5 4v-4h-4"/>
                                        </svg>
                                        {{ __('labels.refresh') }}
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        {{-- Stale order alert --}}
        @if($stats['stale_unassigned_count'] > 0)
            <div class="row mb-3" id="stale-alert-row">
                <div class="col-12">
                    <div class="alert alert-danger alert-dismissible fade show d-flex align-items-center" role="alert">
                        <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none"
                             stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"
                             class="icon icon-tabler icon-tabler-alert-triangle me-2">
                            <path stroke="none" d="M0 0h24v24H0z" fill="none"/>
                            <path d="M12 9v4"/>
                            <path
                                d="M10.363 3.591l-8.106 13.534a1.914 1.914 0 0 0 1.636 2.871h16.214a1.914 1.914 0 0 0 1.636 -2.87l-8.106 -13.536a1.914 1.914 0 0 0 -3.274 0z"/>
                            <path d="M12 16h.01"/>
                        </svg>
                        <span id="stale-alert-text">
                            {{ __('labels.stale_unassigned_alert', ['count' => $stats['stale_unassigned_count']]) }}
                        </span>
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                </div>
            </div>
        @endif

        {{-- Stats cards --}}
        <div class="row row-deck row-cards mb-3">
            <div class="col-sm-6 col-lg-3">
                <div class="card">
                    <div class="card-body">
                        <div class="d-flex align-items-center">
                            <div class="subheader text-success">{{ __('labels.active_riders') }}</div>
                        </div>
                        <div class="h1 mb-0 mt-2" id="stat-active-riders">{{ $stats['active_riders'] }}</div>
                    </div>
                </div>
            </div>
            <div class="col-sm-6 col-lg-3">
                <div class="card">
                    <div class="card-body">
                        <div class="d-flex align-items-center">
                            <div class="subheader text-primary">{{ __('labels.on_delivery') }}</div>
                        </div>
                        <div class="h1 mb-0 mt-2" id="stat-riders-on-delivery">{{ $stats['riders_on_delivery'] }}</div>
                    </div>
                </div>
            </div>
            <div class="col-sm-6 col-lg-3">
                <div class="card">
                    <div class="card-body">
                        <div class="d-flex align-items-center">
                            <div class="subheader text-warning">{{ __('labels.idle_riders') }}</div>
                        </div>
                        <div class="h1 mb-0 mt-2" id="stat-idle-riders">{{ $stats['idle_riders'] }}</div>
                    </div>
                </div>
            </div>
            <div class="col-sm-6 col-lg-3">
                <div class="card">
                    <div class="card-body">
                        <div class="d-flex align-items-center">
                            <div class="subheader text-danger">{{ __('labels.unassigned_orders') }}</div>
                        </div>
                        <div class="h1 mb-0 mt-2" id="stat-unassigned-orders">{{ $stats['unassigned_orders'] }}</div>
                    </div>
                </div>
            </div>
            <div class="col-sm-6 col-lg-3">
                <div class="card">
                    <div class="card-body">
                        <div class="d-flex align-items-center">
                            <div class="subheader text-orange">{{ __('labels.ready_for_pickup') }}</div>
                        </div>
                        <div class="h1 mb-0 mt-2" id="stat-ready-for-pickup">{{ $stats['ready_for_pickup'] }}</div>
                    </div>
                </div>
            </div>
            <div class="col-sm-6 col-lg-3">
                <div class="card">
                    <div class="card-body">
                        <div class="d-flex align-items-center">
                            <div class="subheader text-info">{{ __('labels.ongoing_deliveries') }}</div>
                        </div>
                        <div class="h1 mb-0 mt-2" id="stat-ongoing-deliveries">{{ $stats['ongoing_deliveries'] }}</div>
                    </div>
                </div>
            </div>
            <div class="col-sm-6 col-lg-3">
                <div class="card">
                    <div class="card-body">
                        <div class="d-flex align-items-center">
                            <div class="subheader text-success">{{ __('labels.delivered_today') }}</div>
                        </div>
                        <div class="h1 mb-0 mt-2" id="stat-delivered-today">{{ $stats['delivered_today'] }}</div>
                    </div>
                </div>
            </div>
            <div class="col-sm-6 col-lg-3">
                <div class="card">
                    <div class="card-body">
                        <div class="d-flex align-items-center">
                            <div class="subheader text-secondary">{{ __('labels.avg_assignment_time') }}</div>
                        </div>
                        <div class="h1 mb-0 mt-2">
                            <span id="stat-avg-assignment-time">{{ $stats['avg_assignment_time_minutes'] }}</span>
                            <small class="text-muted">{{ __('labels.minutes') }}</small>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-sm-6 col-lg-3">
                <div class="card">
                    <div class="card-body">
                        <div class="d-flex align-items-center">
                            <div class="subheader text-danger">{{ __('labels.drops_today') }}</div>
                        </div>
                        <div class="h1 mb-0 mt-2" id="stat-drops-today">{{ $stats['drops_today'] }}</div>
                    </div>
                </div>
            </div>
        </div>

        {{-- Hourly delivery performance chart --}}
        <div class="row mb-3">
            <div class="col-12">
                <div class="card">
                    <div class="card-header">
                        <h3 class="card-title">{{ __('labels.hourly_delivery_performance') }}</h3>
                    </div>
                    <div class="card-body">
                        <div id="hourly-deliveries-chart" style="height: 200px;"></div>
                    </div>
                </div>
            </div>
        </div>

        {{-- Main content: tabs + zone availability + activity feed --}}
        <div class="row row-cards">
            {{-- Datatables tabs --}}
            <div class="col-lg-8">
                <div class="card">
                    <div class="card-header">
                        <ul class="nav nav-tabs card-header-tabs" role="tablist">
                            <li class="nav-item" role="presentation">
                                <a class="nav-link active" id="tab-riders" data-bs-toggle="tab" href="#pane-riders"
                                   role="tab" aria-selected="true">
                                    {{ __('labels.riders_on_delivery') }}
                                    <span class="badge bg-primary-lt ms-1"
                                          id="tab-riders-count">{{ $stats['riders_on_delivery'] }}</span>
                                </a>
                            </li>
                            <li class="nav-item" role="presentation">
                                <a class="nav-link" id="tab-unassigned" data-bs-toggle="tab" href="#pane-unassigned"
                                   role="tab" aria-selected="false">
                                    {{ __('labels.unassigned_orders') }}
                                    <span class="badge bg-danger-lt ms-1"
                                          id="tab-unassigned-count">{{ $stats['unassigned_orders'] }}</span>
                                </a>
                            </li>
                            <li class="nav-item" role="presentation">
                                <a class="nav-link" id="tab-pickup" data-bs-toggle="tab" href="#pane-pickup" role="tab"
                                   aria-selected="false">
                                    {{ __('labels.ready_for_pickup') }}
                                    <span class="badge bg-warning-lt ms-1"
                                          id="tab-pickup-count">{{ $stats['ready_for_pickup'] }}</span>
                                </a>
                            </li>
                        </ul>
                    </div>
                    <div class="card-body">
                        {{-- Datatable filters --}}
                        <div class="row g-2 mb-3" id="dispatch-datatable-filters">
                            <div class="col-auto">
                                <select class="form-select form-select-sm" id="dispatch-filter-payment-type"
                                        style="min-width: 160px;">
                                    <option value="">{{ __('labels.all_payment_types') }}</option>
                                    @foreach(\App\Enums\Payment\PaymentTypeEnum::cases() as $pt)
                                        <option
                                            value="{{ $pt->value }}">{{ ucfirst(str_replace('Payment', '', $pt->name)) }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-auto">
                                <select class="form-select form-select-sm text-capitalize" id="dispatch-filter-range"
                                        style="min-width: 160px;">
                                    <option value="">{{ __('labels.all_time') }}</option>
                                    @foreach(DateRangeFilterEnum::values() as $range)
                                        <option value="{{$range}}">{{ str_replace("_", " ",$range) }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-auto">
                                <label class="form-check form-check-inline mt-1">
                                    <input class="form-check-input" type="checkbox" id="dispatch-filter-stale-only">
                                    <span class="form-check-label">{{ __('labels.stale_only') }}</span>
                                </label>
                            </div>
                        </div>

                        <div class="tab-content">
                            <div class="tab-pane fade show active" id="pane-riders" role="tabpanel">
                                <x-datatable id="dispatch-riders-table"
                                             :columns="$ridersOnDeliveryColumns"
                                             route="{{ route('admin.dispatch.riders-on-delivery') }}"
                                             :options="['order' => [[5, 'desc']], 'pageLength' => 10]"/>
                            </div>
                            <div class="tab-pane fade" id="pane-unassigned" role="tabpanel">
                                <x-datatable id="dispatch-unassigned-table"
                                             :columns="$unassignedOrdersColumns"
                                             route="{{ route('admin.dispatch.unassigned-orders') }}"
                                             :options="['order' => [[8, 'asc']], 'pageLength' => 10]"/>
                            </div>
                            <div class="tab-pane fade" id="pane-pickup" role="tabpanel">
                                <x-datatable id="dispatch-pickup-table"
                                             :columns="$readyForPickupColumns"
                                             route="{{ route('admin.dispatch.ready-for-pickup') }}"
                                             :options="['order' => [[0, 'desc']], 'pageLength' => 10]"/>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            {{-- Right sidebar: zone availability + activity feed --}}
            <div class="col-lg-4">
                {{-- Zone rider availability --}}
                <div class="card mb-3">
                    <div class="card-header">
                        <h3 class="card-title">{{ __('labels.zone_rider_availability') }}</h3>
                    </div>
                    <div class="table-responsive" id="zone-availability-table">
                        <table class="table table-vcenter card-table table-sm">
                            <thead>
                            <tr>
                                <th>{{ __('labels.zone') }}</th>
                                <th class="text-center">{{ __('labels.total_riders') }}</th>
                                <th class="text-center">{{ __('labels.active_riders') }}</th>
                                <th class="text-center">{{ __('labels.on_delivery') }}</th>
                                <th class="text-center">{{ __('labels.idle_riders') }}</th>
                            </tr>
                            </thead>
                            <tbody>
                            @forelse($zoneAvailability as $za)
                                <tr class="{{ $za['idle'] == 0 && $za['active_riders'] > 0 ? 'bg-danger-lt' : '' }}">
                                    <td>{{ $za['zone_name'] }}</td>
                                    <td class="text-center">{{ $za['total_riders'] }}</td>
                                    <td class="text-center">{{ $za['active_riders'] }}</td>
                                    <td class="text-center">{{ $za['on_delivery'] }}</td>
                                    <td class="text-center">
                                            <span
                                                class="{{ $za['idle'] == 0 ? 'text-danger fw-bold' : 'text-success' }}">
                                                {{ $za['idle'] }}
                                            </span>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="5"
                                        class="text-center text-muted">{{ __('labels.no_data_available') }}</td>
                                </tr>
                            @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>

                {{-- Recent activity feed --}}
                <div class="card">
                    <div class="card-header">
                        <h3 class="card-title">{{ __('labels.recent_activity') }}</h3>
                    </div>
                    <div class="card-body p-0" id="activity-feed" style="max-height: 400px; overflow-y: auto;">
                        <div class="list-group list-group-flush">
                            @forelse($recentActivity as $activity)
                                <a href="{{ route('admin.orders.show', ['id' => $activity['order_id']]) }}"
                                   class="list-group-item list-group-item-action">
                                    <div class="d-flex justify-content-between align-items-start">
                                        <div class="small">{{ $activity['message'] }}</div>
                                        <span
                                            class="text-muted small text-nowrap ms-2">{{ $activity['time_ago'] }}</span>
                                    </div>
                                </a>
                            @empty
                                <div class="list-group-item text-center text-muted py-4">
                                    {{ __('labels.no_recent_activity') }}
                                </div>
                            @endforelse
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- Assign rider modal --}}
    @if($editPermission)
        <div class="modal modal-blur fade" id="dispatch-assign-rider-modal" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title">{{ __('labels.assign_rider') }}</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <input type="hidden" id="dispatch-assign-order-id" value="">
                        <input type="hidden" id="dispatch-assign-zone-id" value="">
                        <div class="mb-3">
                            <label class="form-label">{{ __('labels.select_rider') }}</label>
                            <select id="dispatch-assign-rider-select"
                                    placeholder="{{ __('labels.search_rider') }}"></select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">{{ __('labels.reason') }}</label>
                            <textarea id="dispatch-assign-reason" class="form-control" rows="3"
                                      placeholder="{{ __('labels.assignment_reason_placeholder') }}"></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn me-auto"
                                data-bs-dismiss="modal">{{ __('labels.cancel') }}</button>
                        <button type="button" class="btn btn-primary" id="dispatch-assign-submit">
                            <span class="spinner-border spinner-border-sm d-none me-1"
                                  id="dispatch-assign-spinner"></span>
                            {{ __('labels.assign_rider') }}
                        </button>
                    </div>
                </div>
            </div>
        </div>
    @endif
@endsection

@push('scripts')
    <script>
        window.__DISPATCH_HOURLY__ = @json($hourlyDeliveries);
    </script>
    <script src="{{asset('assets/vendor/apexcharts/dist/apexcharts.min.js')}}" defer></script>
    <script src="{{ hyperAsset('assets/js/dispatch-management.js') }}" defer></script>
@endpush
