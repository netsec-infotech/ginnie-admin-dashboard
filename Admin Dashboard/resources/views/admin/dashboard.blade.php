@extends('layouts.admin.app', ['page' => $menuAdmin['dashboard']['active'] ?? ""])

@section('title', __('labels.dashboard'))

@section('header_data')
    @php
        $page_title = __('labels.dashboard');
        $page_pretitle = __('labels.admin') . " " . __('labels.dashboard');
    @endphp
@endsection

@php
    $breadcrumbs = [
        ['title' => __('labels.home'), 'url' => '']
    ];
@endphp

@section('admin-content')
    @if($viewPermission ?? false)
        {{-- Zone Selector Bar --}}
        <div class="card mb-3 border-primary border-opacity-25">
            <div class="card-body py-2">
                <div class="d-flex align-items-center gap-3 flex-wrap">
                    <div class="d-flex align-items-center gap-2">
                        <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="text-primary">
                            <path d="M12 12m-3 0a3 3 0 1 0 6 0a3 3 0 1 0 -6 0"/>
                            <path d="M12 2a10 10 0 0 1 10 10c0 5.523-4.477 10-10 10S2 17.523 2 12 6.477 2 12 2"/>
                        </svg>
                        <span class="fw-bold text-primary">{{ __('labels.zone') }}</span>
                    </div>
                    <select class="form-select form-select-sm w-auto" id="zone-selector" style="min-width: 200px;">
                        <option value="">{{ __('labels.all_zones') }}</option>
                        @foreach($activeZones as $zone)
                            <option value="{{ $zone['id'] }}" {{ ($zoneId ?? null) == $zone['id'] ? 'selected' : '' }}>
                                {{ $zone['name'] }}
                            </option>
                        @endforeach
                    </select>
                    <div class="d-none d-md-flex align-items-center gap-1 ms-2" id="zone-pills">
                        @foreach(array_slice($activeZones, 0, 5) as $zone)
                            <button type="button"
                                class="btn btn-sm {{ ($zoneId ?? null) == $zone['id'] ? 'btn-primary' : 'btn-outline-primary' }} zone-pill rounded-pill px-3 py-1"
                                data-zone-id="{{ $zone['id'] }}">
                                {{ $zone['name'] }}
                            </button>
                        @endforeach
                        @if(count($activeZones) > 5)
                            <span class="text-muted small">+{{ count($activeZones) - 5 }} {{ __('labels.more') }}</span>
                        @endif
                    </div>
                    <div class="ms-auto text-muted small" id="zone-indicator">
                        @if($zoneId ?? null)
                            <span class="badge bg-primary-lt">{{ __('labels.filtered_by_zone') }}</span>
                        @else
                            <span class="badge bg-secondary-lt">{{ __('labels.showing_all_zones') }}</span>
                        @endif
                    </div>
                </div>
            </div>
        </div>

        <div class="row row-deck row-cards">
            <div class="col-sm-12 col-lg-6">
                <div class="card">
                    <div class="card-body">
                        <div class="row gy-3">
                            <div class="col-12 col-sm d-flex flex-column justify-content-between">
                                <div>
                                    <h3 class="h2 text-capitalize">Welcome back, {{$user->name ?? "Power"}}</h3>
                                </div>
                                <div>
                                    <div class="d-flex align-items-center">
                                        <div class="subheader">{{ __('labels.sales') }}</div>
                                        <div class="ms-auto lh-1">
                                            <div class="dropdown">
                                                <a class="dropdown-toggle text-secondary sales-period" href="#"
                                                    data-bs-toggle="dropdown" aria-haspopup="true" aria-expanded="false"
                                                    data-period="7">{{ __('labels.last_30_days') }}</a>
                                                <div class="dropdown-menu dropdown-menu-end">
                                                    <a class="dropdown-item" href="#"
                                                        data-period="7">{{ __('labels.last_7_days') }}</a>
                                                    <a class="dropdown-item active" href="#"
                                                        data-period="30">{{ __('labels.last_30_days') }}</a>
                                                    <a class="dropdown-item" href="#"
                                                        data-period="90">{{ __('labels.last_3_months') }}</a>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="h1 mb-3" id="sales-rate">{{ $conversionRateData['rate'] ?? 0 }}%</div>
                                    <div class="d-flex mb-2">
                                        <div>{{ __('labels.conversion_rate') }}</div>
                                        <div class="ms-auto">
                                            <span
                                                class="text-{{ !empty($conversionRateData['is_increase']) && $conversionRateData['is_increase'] ? 'green' : 'red' }} d-inline-flex align-items-center lh-1"
                                                id="sales-trend">
                                                {{ abs($conversionRateData['percentage_change'] ?? 0) }}%
                                            </span>
                                            <!-- Download SVG icon from http://tabler.io/icons/icon/trending-up or trending-down -->
                                            <span
                                                class="text-{{ !empty($conversionRateData['is_increase']) && $conversionRateData['is_increase'] ? 'green' : 'red' }}">
                                                <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24"
                                                    viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
                                                    stroke-linecap="round" stroke-linejoin="round" class="icon ms-1 icon-2">
                                                    <path id="sales-trend-path-1"
                                                        d="{{ $conversionRateData['is_increase'] ?? false ? 'M3 17l6 -6l4 4l8 -8' : 'M3 7l6 6l4 -4l8 8' }}" />
                                                    <path id="sales-trend-path-2"
                                                        d="{{ $conversionRateData['is_increase'] ?? false ? 'M14 7l7 0l0 7' : 'M21 7l0 7l-7 0' }}" />
                                                </svg></span>
                                        </div>
                                    </div>
                                    <div class="text-secondary mb-2" id="sales-details">
                                        {{ $conversionRateData['delivered_orders'] ?? 0 }}
                                        {{ __('labels.delivered_out_of_total_orders') }}
                                        {{ $conversionRateData['total_orders'] ?? 0 }}
                                    </div>
                                    <div class="progress progress-sm">
                                        <div class="progress-bar bg-primary" id="sales-progress"
                                            style="width: {{ $conversionRateData['rate'] ?? 0 }}%" role="progressbar"
                                            aria-valuenow="{{ $conversionRateData['rate'] ?? 0 }}" aria-valuemin="0"
                                            aria-valuemax="100"
                                            aria-label="{{ $conversionRateData['rate'] ?? 0 }}% {{ __('labels.complete') }}">
                                            <span class="visually-hidden">{{ $conversionRateData['rate'] ?? 0 }}%
                                                {{ __('labels.complete') }}</span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="col-12 col-sm-auto d-flex justify-content-center">
                                <img src="{{asset("assets/theme/img/dashboard.svg")}}" alt="Sales Illustration"
                                    class="img-fluid" style="max-height: 200px;" width="100%">
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class=" col-sm-6 col-lg-3">
                <div class="card">
                    <div class="card-body">
                        <div class="d-flex align-items-center">
                            <div class="subheader">{{ __('labels.revenue') }}</div>
                            <div class="ms-auto lh-1">
                                <div class="dropdown">
                                    <a class="dropdown-toggle text-secondary revenue-period" id="revenue-period" href="#"
                                        data-bs-toggle="dropdown" aria-haspopup="true" aria-expanded="false"
                                        data-period="7">{{ __('labels.last_30_days') }}</a>
                                    <div class="dropdown-menu dropdown-menu-end">
                                        <a class="dropdown-item" href="#" data-period="7">{{ __('labels.last_7_days') }}</a>
                                        <a class="dropdown-item active" href="#"
                                            data-period="30">{{ __('labels.last_30_days') }}</a>
                                        <a class="dropdown-item" href="#" data-period="90">{{ __('labels.last_3_months') }}</a>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="d-flex align-items-baseline">
                            <div class="h1 mb-0 me-2" id="revenue-total">{{ $revenueDataBg['formatted_total'] ?? 0 }}</div>
                            <div class="me-auto">

                                <span class="text-green d-inline-flex align-items-center lh-1" id="revenue-days">
                                    {{ count($revenueDataBg['daily'] ?? []) }} {{ __('labels.days') }}
                                    <!-- Download SVG icon from http://tabler.io/icons/icon/calendar -->
                                    <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24"
                                        fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"
                                        stroke-linejoin="round" class="icon ms-1 icon-2">
                                        <path
                                            d="M4 7a2 2 0 0 1 2 -2h12a2 2 0 0 1 2 2v12a2 2 0 0 1 -2 2h-12a2 2 0 0 1 -2 -2v-12z" />
                                        <path d="M16 3v4" />
                                        <path d="M8 3v4" />
                                        <path d="M4 11h16" />
                                        <path d="M11 15h1" />
                                        <path d="M12 15v3" />
                                    </svg>
                                </span>
                            </div>
                        </div>
                    </div>
                    <div id="chart-revenue-bg" class="rounded-bottom chart-sm"></div>
                </div>
            </div>
            <div class="col-sm-6 col-lg-3">
                <div class="card">
                    <div class="card-body">
                        <div class="d-flex align-items-center">
                            <div class="subheader">{{ __('labels.new_user_registrations') }}</div>
                            <div class="ms-auto lh-1">
                                <div class="dropdown">
                                    <a class="dropdown-toggle text-secondary new-users-period" href="#"
                                        data-bs-toggle="dropdown" aria-haspopup="true" aria-expanded="false"
                                        data-period="7">{{ __('labels.last_30_days') }}</a>
                                    <div class="dropdown-menu dropdown-menu-end">
                                        <a class="dropdown-item" href="#" data-period="7">{{ __('labels.last_7_days') }}</a>
                                        <a class="dropdown-item active" href="#"
                                            data-period="30">{{ __('labels.last_30_days') }}</a>
                                        <a class="dropdown-item" href="#" data-period="90">{{ __('labels.last_3_months') }}</a>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="d-flex align-items-baseline">
                            <div class="h1 mb-3 me-2" id="new-users-count">{{ $newUserRegistrationsData['count'] }}</div>
                            <div class="me-auto">
                                <span
                                    class="text-{{ $newUserRegistrationsData['is_increase'] ? 'green' : 'red' }} d-inline-flex align-items-center lh-1"
                                    id="new-users-trend">
                                    {{ abs($newUserRegistrationsData['percentage_change']) }}%
                                    <!-- Download SVG icon from http://tabler.io/icons/icon/trending-up -->
                                    <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24"
                                        fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"
                                        stroke-linejoin="round" class="icon ms-1 icon-2">
                                        @if($newUserRegistrationsData['is_increase'])
                                            <path d="M3 17l6 -6l4 4l8 -8" />
                                            <path d="M14 7l7 0l0 7" />
                                        @else
                                            <path d="M3 7l6 6l4 -4l8 8" />
                                            <path d="M21 7l0 7l-7 0" />
                                        @endif
                                    </svg>
                                </span>
                            </div>
                        </div>
                    </div>
                    <div id="chart-new-users" class="chart-sm"></div>
                </div>
            </div>
            <div class="col-12">
                <div class="row row-cards">
                    <div class="col-sm-6 col-lg-3">
                        <div class="card card-sm">
                            <a href="{{ route('admin.sellers.index') }}" class="card-body text-decoration-none">
                                <div class="row align-items-center">
                                    <div class="col-auto">
                                        <span
                                            class="bg-primary text-white avatar"><!-- Download SVG icon from http://tabler.io/icons/icon/building-store -->
                                            <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24"
                                                fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"
                                                stroke-linejoin="round"
                                                class="icon icon-tabler icons-tabler-outline icon-tabler-building-store">
                                                <path stroke="none" d="M0 0h24v24H0z" fill="none" />
                                                <path d="M3 21l18 0" />
                                                <path
                                                    d="M3 7v1a3 3 0 0 0 6 0v-1m0 1a3 3 0 0 0 6 0v-1m0 1a3 3 0 0 0 6 0v-1h-18l2 -4h14l2 4" />
                                                <path d="M5 21l0 -10.15" />
                                                <path d="M19 21l0 -10.15" />
                                                <path d="M9 21v-4a2 2 0 0 1 2 -2h2a2 2 0 0 1 2 2v4" />
                                            </svg>
                                        </span>
                                    </div>
                                    <div class="col">
                                        <div class="font-weight-medium"><span id="insight-total-sellers">{{$adminInsights['total_sellers']}}</span>
                                            {{ __('labels.sellers') }}</div>
                                        <div class="text-secondary"><span id="insight-total-stores">{{$adminInsights['total_stores']}}</span>
                                            {{ __('labels.active_stores') }}
                                        </div>
                                    </div>
                                </div>
                            </a>
                        </div>
                    </div>
                    <div class="col-sm-6 col-lg-3">
                        <div class="card card-sm">
                            <a href="{{ route('admin.orders.index') }}" class="card-body text-decoration-none">
                                <div class="row align-items-center">
                                    <div class="col-auto">
                                        <span
                                            class="bg-green text-white avatar"><!-- Download SVG icon from http://tabler.io/icons/icon/shopping-cart -->
                                            <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24"
                                                fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"
                                                stroke-linejoin="round" class="icon icon-1">
                                                <path d="M6 19m-2 0a2 2 0 1 0 4 0a2 2 0 1 0 -4 0" />
                                                <path d="M17 19m-2 0a2 2 0 1 0 4 0a2 2 0 1 0 -4 0" />
                                                <path d="M17 17h-11v-14h-2" />
                                                <path d="M6 5l14 1l-1 7h-13" />
                                            </svg></span>
                                    </div>
                                    <div class="col">
                                        <div class="font-weight-medium"><span id="insight-total-orders">{{$adminInsights['total_orders']}}</span>
                                            {{ __('labels.orders') }}</div>
                                        <div class="text-secondary"><span id="insight-delivered-orders">{{$adminInsights['total_delivered_orders']}}</span>
                                            {{ __('labels.delivered') }}</div>
                                    </div>
                                </div>
                            </a>
                        </div>
                    </div>
                    <div class="col-sm-6 col-lg-3">
                        <div class="card card-sm">
                            <a href="{{ route('admin.delivery-boys.index') }}" class="card-body text-decoration-none">
                                <div class="row align-items-center">
                                    <div class="col-auto">
                                        <span
                                            class="bg-yellow text-white avatar"><!-- Download SVG icon from http://tabler.io/icons/icon/bike -->
                                            <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24"
                                                fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"
                                                stroke-linejoin="round"
                                                class="icon icon-tabler icons-tabler-outline icon-tabler-bike">
                                                <path stroke="none" d="M0 0h24v24H0z" fill="none" />
                                                <path d="M5 18m-3 0a3 3 0 1 0 6 0a3 3 0 1 0 -6 0" />
                                                <path d="M19 18m-3 0a3 3 0 1 0 6 0a3 3 0 1 0 -6 0" />
                                                <path d="M12 19l0 -4l-3 -3l5 -4l2 3l3 0" />
                                                <path d="M17 5m-1 0a1 1 0 1 0 2 0a1 1 0 1 0 -2 0" />
                                            </svg></span>
                                    </div>
                                    <div class="col">
                                        <div class="font-weight-medium"><span id="insight-active-dbs">{{$adminInsights['total_active_delivery_boys']}}</span>
                                            {{ __('labels.active_delivery_boys') }}</div>
                                        <div class="text-secondary"><span id="insight-total-dbs">{{$adminInsights['total_delivery_boys']}}</span>
                                            {{ __('labels.total_delivery_boys') }}</div>
                                    </div>
                                </div>
                            </a>
                        </div>
                    </div>
                    <div class="col-sm-6 col-lg-3">
                        <div class="card card-sm">
                            <a href="{{ route('admin.products.index') }}" class="card-body text-decoration-none">
                                <div class="row align-items-center">
                                    <div class="col-auto">
                                        <span
                                            class="bg-azure text-white avatar"><!-- Download SVG icon from http://tabler.io/icons/icon/package -->
                                            <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24"
                                                fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"
                                                stroke-linejoin="round" class="icon icon-1">
                                                <path d="M12 3l8 4.5l0 9l-8 4.5l-8 -4.5l0 -9l8 -4.5" />
                                                <path d="M12 12l8 -4.5" />
                                                <path d="M12 12l0 9" />
                                                <path d="M12 12l-8 -4.5" />
                                            </svg></span>
                                    </div>
                                    <div class="col">
                                        <div class="font-weight-medium"><span id="insight-total-products">{{ $adminInsights['total_products'] }}</span>
                                            {{ __('labels.products') }}</div>
                                        <div class="text-secondary"><span id="insight-total-sales">{{ $adminInsights['total_product_sales'] }}</span>
                                            {{ __('labels.total_sales') }}
                                        </div>
                                    </div>
                                </div>
                            </a>
                        </div>
                    </div>
                </div>
            </div>
            {{-- Revenue vs Orders Dual-Axis Chart --}}
            <div class="col-lg-6">
                <div class="card">
                    <div class="card-header">
                        <div class="d-flex align-items-center">
                            <h3 class="card-title">{{ __('labels.revenue_vs_orders') }}</h3>
                            <div class="ms-auto">
                                <div class="dropdown ps-2">
                                    <a class="dropdown-toggle text-secondary revenue-vs-orders-period" href="#"
                                        data-bs-toggle="dropdown" aria-haspopup="true" aria-expanded="false"
                                        data-period="30">{{ __('labels.last_30_days') }}</a>
                                    <div class="dropdown-menu dropdown-menu-end">
                                        <a class="dropdown-item" href="#" data-period="7">{{ __('labels.last_7_days') }}</a>
                                        <a class="dropdown-item active" href="#" data-period="30">{{ __('labels.last_30_days') }}</a>
                                        <a class="dropdown-item" href="#" data-period="90">{{ __('labels.last_3_months') }}</a>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="card-body">
                        <div class="row mb-3">
                            <div class="col-sm-4 text-center">
                                <div class="h3 mb-0" id="rvo-total-orders">{{ $revenueVsOrders['total_orders'] }}</div>
                                <div class="text-secondary small">{{ __('labels.orders') }}</div>
                            </div>
                            <div class="col-sm-4 text-center">
                                <div class="h3 mb-0" id="rvo-total-revenue">{{ $revenueVsOrders['formatted_total_revenue'] }}</div>
                                <div class="text-secondary small">{{ __('labels.revenue') }}</div>
                            </div>
                            <div class="col-sm-4 text-center">
                                <div class="h3 mb-0" id="rvo-aov">{{ $revenueVsOrders['formatted_aov'] }}</div>
                                <div class="text-secondary small">{{ __('labels.avg_order_value') }}</div>
                            </div>
                        </div>
                        <div id="chart-revenue-vs-orders" class="chart-lg"></div>
                    </div>
                </div>
            </div>
            <div class="col-lg-6">
                <div class="card">
                    <div class="card-header">
                        <div class="d-flex align-items-center">
                            <h3 class="card-title">{{ __('labels.enhanced_commissions') }}</h3>
                            <div class="ms-auto">
                                <div class="dropdown ps-2">
                                    <a class="dropdown-toggle text-secondary commission-period" href="#"
                                        data-bs-toggle="dropdown" aria-haspopup="true" aria-expanded="false"
                                        data-period="30">{{ __('labels.last_30_days') }}</a>
                                    <div class="dropdown-menu dropdown-menu-end">
                                        <a class="dropdown-item" href="#" data-period="7">{{ __('labels.last_7_days') }}</a>
                                        <a class="dropdown-item active" href="#"
                                            data-period="30">{{ __('labels.last_30_days') }}</a>
                                        <a class="dropdown-item" href="#" data-period="90">{{ __('labels.last_3_months') }}</a>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="card-body">
                        <div class="row mb-3">
                            <div class="col-sm-4">
                                <div class="text-center">
                                    <div class="h3 mb-0" id="commission-total">
                                        {{ $enhancedCommissionsData['total_commission'] }}</div>
                                    <div class="text-secondary">{{ __('labels.total_commission') }}</div>
                                </div>
                            </div>
                            <div class="col-sm-4">
                                <div class="text-center">
                                    <div class="h3 mb-0" id="commission-orders">{{ $enhancedCommissionsData['total_orders'] }}
                                    </div>
                                    <div class="text-secondary">{{ __('labels.total_orders') }}</div>
                                </div>
                            </div>
                            <div class="col-sm-4">
                                <div class="text-center">
                                    <div class="h3 mb-0" id="commission-avg">{{ $enhancedCommissionsData['avg_commission'] }}
                                    </div>
                                    <div class="text-secondary">{{ __('labels.avg_commission') }}</div>
                                </div>
                            </div>
                        </div>
                        <div id="commission-chart" class="chart-lg"></div>
                    </div>
                </div>
            </div>

            <!-- Top Sellers Section -->
            <div class="col-lg-4">
                <div class="card">
                    <div class="card-header">
                        <div class="d-flex align-items-center">
                            <h3 class="card-title">{{ __('labels.top_sellers') }}</h3>
                            <div class="ms-auto">
                                <div class="dropdown ps-2">
                                    <a class="dropdown-toggle text-secondary top-sellers-period" href="#"
                                        data-bs-toggle="dropdown" aria-haspopup="true" aria-expanded="false"
                                        data-period="30">{{ __('labels.last_30_days') }}</a>
                                    <div class="dropdown-menu dropdown-menu-end">
                                        <a class="dropdown-item" href="#" data-period="7">{{ __('labels.last_7_days') }}</a>
                                        <a class="dropdown-item active" href="#"
                                            data-period="30">{{ __('labels.last_30_days') }}</a>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="card-body p-0">
                        <div class="list-group list-group-flush" id="top-sellers-list">
                            @forelse($topSellers as $index => $seller)
                                <div class="list-group-item d-flex align-items-center">
                                    <span class="badge bg-teal-lt me-3">{{ $index + 1 }}</span>
                                    <div class="avatar avatar-sm me-3">
                                        @if(!empty($seller['avatar']))
                                            <img src="{{ $seller['avatar'] }}" alt="{{ $seller['name'] }}" class="rounded">
                                        @else
                                            <span class="avatar avatar-sm bg-primary text-white">
                                                {{ strtoupper(substr($seller['name'], 0, 2)) }}
                                            </span>
                                        @endif
                                    </div>
                                    <div class="flex-fill">
                                        <div class="font-weight-medium">{{ $seller['name'] }}</div>
                                        <div class="text-secondary">
                                            {{ $seller['total_orders'] }} {{ __('labels.orders') }}
                                        </div>
                                    </div>
                                    <div class="text-end">
                                        <div class="font-weight-medium">{{ $seller['total_revenue'] }}</div>
                                    </div>
                                </div>
                            @empty
                                <div class="text-center w-100 py-5">
                                    <img src="{{ asset('assets/theme/img/not-found.svg') }}" alt="No data found" class="w-100"
                                        style="max-width: 400px; height: auto; margin: 0 auto; display: block;">
                                </div>
                            @endforelse
                        </div>

                    </div>
                </div>
            </div>

            <!-- Top Products Section -->
            <div class="col-lg-4">
                <div class="card">
                    <div class="card-header">
                        <div class="d-flex align-items-center">
                            <h3 class="card-title">{{ __('labels.top_selling_products') }}</h3>
                            <div class="ms-auto">
                                <div class="dropdown ps-2">
                                    <a class="dropdown-toggle text-secondary top-products-period" href="#"
                                        data-bs-toggle="dropdown" aria-haspopup="true" aria-expanded="false"
                                        data-period="30">{{ __('labels.last_30_days') }}</a>
                                    <div class="dropdown-menu dropdown-menu-end">
                                        <a class="dropdown-item" href="#" data-period="7">{{ __('labels.last_7_days') }}</a>
                                        <a class="dropdown-item active" href="#"
                                            data-period="30">{{ __('labels.last_30_days') }}</a>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="card-body p-0">
                        <div class="list-group list-group-flush" id="top-products-list">
                            @forelse($topSellingProducts as $index => $product)
                                <div class="list-group-item d-flex align-items-center">
                                    <span class="badge bg-primary-lt me-3">{{ $index + 1 }}</span>
                                    <div class="avatar avatar-sm me-3">
                                        @if(!empty($product['image']))
                                            <img src="{{ $product['image'] }}" alt="{{ $product['name'] }}" class="rounded">
                                        @else
                                            <span class="avatar avatar-sm bg-primary text-white">
                                                {{ strtoupper(substr($product['name'], 0, 1)) }}
                                            </span>
                                        @endif
                                    </div>
                                    <div class="flex-fill">
                                        <div class="font-weight-medium">
                                            <a href="{{ url('admin/products/' . $product['id']) }}"
                                                data-bs-toggle="tooltip" data-bs-title="{{ $product['name'] }}"
                                                class="text-decoration-none text-body">
                                                {{ Str::limit($product['name'], 25) }}
                                            </a>
                                        </div>
                                        <div class="text-secondary">
                                            {{ $product['category'] }}
                                        </div>
                                        <div class="text-secondary">
                                            {{ $product['total_quantity'] }} {{ __('labels.sold') }}
                                        </div>
                                    </div>
                                    <div class="text-end">
                                        <div class="font-weight-medium">{{ $product['total_revenue'] }}</div>
                                    </div>
                                </div>
                            @empty
                                <div class="text-center w-100 py-5">
                                    <img src="{{ asset('assets/theme/img/not-found.svg') }}" alt="No products found" class="w-100"
                                        style="max-width: 400px; height: auto; margin: 0 auto; display: block;">
                                </div>
                            @endforelse
                        </div>

                    </div>
                </div>
            </div>

            <!-- Top Delivery Boys Section -->
            <div class="col-lg-4">
                <div class="card">
                    <div class="card-header">
                        <div class="d-flex align-items-center">
                            <h3 class="card-title">{{ __('labels.top_delivery_boys') }}</h3>
                            <div class="ms-auto">
                                <div class="dropdown ps-2">
                                    <a class="dropdown-toggle text-secondary top-delivery-boys-period" href="#"
                                        data-bs-toggle="dropdown" aria-haspopup="true" aria-expanded="false"
                                        data-period="30">{{ __('labels.last_30_days') }}</a>
                                    <div class="dropdown-menu dropdown-menu-end">
                                        <a class="dropdown-item" href="#" data-period="7">{{ __('labels.last_7_days') }}</a>
                                        <a class="dropdown-item active" href="#"
                                            data-period="30">{{ __('labels.last_30_days') }}</a>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="card-body p-0">
                        <div class="list-group list-group-flush" id="top-delivery-boys-list">

                            @forelse($topDeliveryBoys as $index => $deliveryBoy)
                                <div class="list-group-item d-flex align-items-center">
                                    <span class="badge bg-warning-lt me-3">{{ $index + 1 }}</span>

                                    <div class="avatar avatar-sm me-3 bg-warning text-white">
                                        @if(!empty($deliveryBoy['avatar']))
                                            <img src="{{ $deliveryBoy['avatar'] }}" alt="{{ ($deliveryBoy['name'] ?? "") }}"
                                                class="rounded">
                                        @else
                                            {{ strtoupper(substr(($deliveryBoy['name'] ?? ""), 0, 2)) }}
                                        @endif
                                    </div>

                                    <div class="flex-fill">
                                        <div class="font-weight-medium text-capitalize">{{ ($deliveryBoy['name'] ?? "") }}</div>
                                        <div class="text-secondary">
                                            {{ $deliveryBoy['total_deliveries'] ?? 0 }} {{ __('labels.deliveries') }}
                                        </div>
                                    </div>

                                    <div class="text-end">
                                        <div class="font-weight-medium">{{ $deliveryBoy['total_revenue'] ?? 0 }}</div>
                                    </div>
                                </div>
                            @empty
                                <div class="text-center w-100 py-5">
                                    <img src="{{ asset('assets/theme/img/not-found.svg') }}" alt="No products found" class="w-100"
                                        style="max-width: 400px; height: auto; margin: 0 auto; display: block;">
                                </div>
                            @endforelse
                        </div>

                    </div>
                </div>
            </div>

            <!-- Categories with Filters Section -->
            <div class="col-12">
                <div class="card">
                    <div class="card-header">
                        <div class="d-flex align-items-center flex-wrap">
                            <h3 class="card-title">{{ __('labels.categories') }}</h3>
                            <div class="ms-auto">
                                <div class="d-flex gap-2 flex-wrap">
                                    <div class="dropdown ps-2">
                                        <a class="dropdown-toggle text-secondary" href="#" data-bs-toggle="dropdown"
                                            id="categories-filter">{{ __('labels.all_categories') }}</a>
                                        <div class="dropdown-menu dropdown-menu-end">
                                            <a class="dropdown-item active" href="#"
                                                data-filter="all">{{ __('labels.all_categories') }}</a>
                                            <a class="dropdown-item" href="#"
                                                data-filter="top_selling">{{ __('labels.top_selling') }}</a>
                                            <a class="dropdown-item" href="#"
                                                data-filter="no_products">{{ __('labels.no_products') }}</a>
                                        </div>
                                    </div>
                                    <div class="dropdown ps-2">
                                        <a class="dropdown-toggle text-secondary" href="#" data-bs-toggle="dropdown"
                                            id="categories-sort">{{ __('labels.sort_by_products_count') }}</a>
                                        <div class="dropdown-menu dropdown-menu-end">
                                            <a class="dropdown-item" href="#"
                                                data-sort="name">{{ __('labels.sort_by_name') }}</a>
                                            <a class="dropdown-item active" href="#"
                                                data-sort="products_count">{{ __('labels.sort_by_products_count') }}</a>
                                            <a class="dropdown-item" href="#"
                                                data-sort="total_sold">{{ __('labels.sort_by_total_product_sold') }}</a>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="card-body">
                        <div class="row" id="categories-grid">
                            @foreach($categoriesWithFilters as $category)
                                <div class="col-sm-6 col-md-4 col-lg-3 mb-3">
                                    <div class="card card-sm">
                                        <div class="card-body text-center">
                                            @if($category['image'])
                                                <img src="{{ $category['image'] }}" alt="{{ $category['title'] }}"
                                                    class="avatar avatar-lg mb-2 object-contain">
                                            @else
                                                <div class="avatar avatar-lg mb-2 object-contain avatar-placeholder">
                                                    {{ substr($category['title'], 0, 2) }}</div>
                                            @endif
                                            <h4 class="card-title">{{ $category['title'] }}</h4>
                                            <div class="text-secondary">{{ $category['products_count'] }}
                                                {{ __('labels.products') }}</div>
                                            @if(isset($category['total_sold']))
                                                <div class="text-success">{{ $category['total_sold'] }} {{ __('labels.sold') }}</div>
                                            @endif
                                        </div>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-12">
                <div class="card">
                    <div class="card-header border-0">
                        <div class="card-title">{{ __('labels.daily_orders_history') }}</div>
                    </div>
                    <div class="position-relative">
                        <div class="position-absolute top-0 left-0 px-3 mt-1 w-75">
                            <div class="row g-2">
                                <div class="col-auto">
                                    <div class="chart-sparkline chart-sparkline-square" id="sparkline-activity"></div>
                                </div>
                                <div class="col">
                                    <div>{{ __('labels.todays_earning') }}
                                        : {{ $todaysEarning['formatted_today'] }}</div>
                                    <div class="text-{{ $todaysEarning['is_increase'] ? 'green' : 'red' }}">
                                        <!-- Download SVG icon from http://tabler.io/icons/icon/trending-up or trending-down -->
                                        <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24"
                                            fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"
                                            stroke-linejoin="round"
                                            class="icon icon-inline {{ $todaysEarning['is_increase'] ? 'text-green' : 'text-red' }} icon-3">
                                            @if($todaysEarning['is_increase'])
                                                <path d="M3 17l6 -6l4 4l8 -8" />
                                                <path d="M14 7l7 0l0 7" />
                                            @else
                                                <path d="M3 7l6 6l4 -4l8 8" />
                                                <path d="M21 7l0 7l-7 0" />
                                            @endif
                                        </svg>
                                        {{ abs($todaysEarning['percentage_change']) }}
                                        % {{ $todaysEarning['is_increase'] ? __('labels.more') : __('labels.less') }}
                                        {{ __('labels.than_yesterday') }}
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div id="chart-development-activity"></div>
                    </div>
                </div>
            </div>

            {{-- Order Status Funnel --}}
            <div class="col-lg-6">
                <div class="card">
                    <div class="card-header">
                        <div class="d-flex align-items-center">
                            <h3 class="card-title">{{ __('labels.order_status_funnel') }}</h3>
                            <div class="ms-auto">
                                <div class="dropdown ps-2">
                                    <a class="dropdown-toggle text-secondary order-funnel-period" href="#"
                                        data-bs-toggle="dropdown" aria-haspopup="true" aria-expanded="false"
                                        data-period="30">{{ __('labels.last_30_days') }}</a>
                                    <div class="dropdown-menu dropdown-menu-end">
                                        <a class="dropdown-item" href="#" data-period="7">{{ __('labels.last_7_days') }}</a>
                                        <a class="dropdown-item active" href="#" data-period="30">{{ __('labels.last_30_days') }}</a>
                                        <a class="dropdown-item" href="#" data-period="90">{{ __('labels.last_3_months') }}</a>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="card-body" id="order-funnel-container">
                        @foreach($orderFunnel['funnel'] as $index => $stage)
                            @php
                                $maxCount = $orderFunnel['funnel'][0]['count'] ?? 1;
                                $widthPercent = $maxCount > 0 ? max(20, ($stage['count'] / $maxCount) * 100) : 20;
                                $bgColor = match($index) {
                                    0 => 'bg-primary',
                                    1 => 'bg-info',
                                    2 => 'bg-azure',
                                    3 => 'bg-success',
                                    4 => 'bg-danger',
                                    default => 'bg-secondary',
                                };
                            @endphp
                            <div class="d-flex align-items-center mb-2">
                                <div class="flex-fill">
                                    <div class="{{ $bgColor }} rounded px-3 py-2 text-white d-flex justify-content-between align-items-center"
                                        style="width: {{ $widthPercent }}%; transition: width 0.5s ease;">
                                        <span class="fw-medium small">{{ $stage['stage'] }}</span>
                                        <span class="fw-bold">{{ $stage['count'] }}</span>
                                    </div>
                                </div>
                            </div>
                        @endforeach
                        <div class="row mt-3 text-center">
                            <div class="col-6">
                                <div class="text-muted small">{{ __('labels.conversion_rate') }}</div>
                                <div class="h3 text-success">{{ $orderFunnel['conversion_rate'] }}%</div>
                            </div>
                            <div class="col-6">
                                <div class="text-muted small">{{ __('labels.cancellation_rate') }}</div>
                                <div class="h3 text-danger">{{ $orderFunnel['cancellation_rate'] }}%</div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            {{-- Alerts & Action Items --}}
            <div class="col-lg-6">
                <div class="card">
                    <div class="card-header">
                        <div class="d-flex align-items-center">
                            <h3 class="card-title">{{ __('labels.alerts_action_items') }}</h3>
                            <span class="badge bg-red-lt ms-2" id="alerts-count">{{ $alertsData['total'] }}</span>
                        </div>
                    </div>
                    <div class="card-body p-0" id="alerts-container">
                        @if(count($alertsData['alerts']) > 0)
                            <div class="list-group list-group-flush">
                                @foreach($alertsData['alerts'] as $alert)
                                    <div class="list-group-item d-flex align-items-center">
                                        <span class="badge bg-{{ $alert['severity'] === 'critical' ? 'danger' : ($alert['severity'] === 'warning' ? 'warning' : 'info') }} badge-empty me-3"></span>
                                        <div class="flex-fill">
                                            <div class="small">{{ $alert['message'] }}</div>
                                        </div>
                                        @if(isset($alert['action_route']))
                                            <a href="{{ route($alert['action_route']) }}" class="btn btn-sm btn-ghost-primary">
                                                {{ __('labels.view') }}
                                            </a>
                                        @endif
                                    </div>
                                @endforeach
                            </div>
                        @else
                            <div class="text-center py-4 text-muted">
                                <svg xmlns="http://www.w3.org/2000/svg" width="40" height="40" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1" class="mb-2 text-success">
                                    <path d="M5 12l5 5l10 -10"/>
                                </svg>
                                <p class="mb-0">{{ __('labels.no_alerts') }}</p>
                            </div>
                        @endif
                    </div>
                </div>
            </div>

            {{-- Financial Overview Section --}}
            <div class="col-12">
                <div class="d-flex align-items-center mb-2 mt-2">
                    <h2 class="page-title mb-0">{{ __('labels.financial_overview') }}</h2>
                    <span class="badge bg-blue-lt ms-2">{{ __('labels.eod_reconciliation') }}</span>
                </div>
            </div>

            {{-- Seller Settlements --}}
            <div class="col-sm-6 col-lg-4">
                <div class="card">
                    <div class="card-header">
                        <div class="d-flex align-items-center">
                            <h3 class="card-title">{{ __('labels.seller_settlements') }}</h3>
                            <div class="ms-auto">
                                <div class="dropdown ps-2">
                                    <a class="dropdown-toggle text-secondary seller-settlements-period" href="#"
                                        data-bs-toggle="dropdown" aria-haspopup="true" aria-expanded="false"
                                        data-period="1">{{ __('labels.today') }}</a>
                                    <div class="dropdown-menu dropdown-menu-end">
                                        <a class="dropdown-item active" href="#" data-period="1">{{ __('labels.today') }}</a>
                                        <a class="dropdown-item" href="#" data-period="2">{{ __('labels.yesterday') }}</a>
                                        <a class="dropdown-item" href="#" data-period="7">{{ __('labels.last_7_days') }}</a>
                                        <a class="dropdown-item" href="#" data-period="30">{{ __('labels.last_30_days') }}</a>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="card-body" id="seller-settlements-container">
                        <div class="row g-2">
                            <div class="col-6">
                                <div class="p-2 rounded bg-warning-lt text-center">
                                    <div class="h3 mb-0" id="ss-pending-amount">{{ $sellerSettlements['pending_amount'] }}</div>
                                    <div class="text-muted small">{{ __('labels.pending') }} (<span id="ss-pending-count">{{ $sellerSettlements['pending_count'] }}</span>)</div>
                                </div>
                            </div>
                            <div class="col-6">
                                <div class="p-2 rounded bg-success-lt text-center">
                                    <div class="h3 mb-0" id="ss-settled-amount">{{ $sellerSettlements['settled_amount'] }}</div>
                                    <div class="text-muted small">{{ __('labels.settled') }} (<span id="ss-settled-count">{{ $sellerSettlements['settled_count'] }}</span>)</div>
                                </div>
                            </div>
                        </div>
                        <div class="mt-3 text-center">
                            <div class="text-muted small">{{ __('labels.total_outstanding') }}</div>
                            <div class="h2 mb-0 text-warning" id="ss-outstanding">{{ $sellerSettlements['total_outstanding'] }}</div>
                        </div>
                    </div>
                </div>
            </div>

            {{-- Delivery Boy Settlements --}}
            <div class="col-sm-6 col-lg-4">
                <div class="card">
                    <div class="card-header">
                        <div class="d-flex align-items-center">
                            <h3 class="card-title">{{ __('labels.db_settlements') }}</h3>
                            <div class="ms-auto">
                                <div class="dropdown ps-2">
                                    <a class="dropdown-toggle text-secondary db-settlements-period" href="#"
                                        data-bs-toggle="dropdown" aria-haspopup="true" aria-expanded="false"
                                        data-period="1">{{ __('labels.today') }}</a>
                                    <div class="dropdown-menu dropdown-menu-end">
                                        <a class="dropdown-item active" href="#" data-period="1">{{ __('labels.today') }}</a>
                                        <a class="dropdown-item" href="#" data-period="2">{{ __('labels.yesterday') }}</a>
                                        <a class="dropdown-item" href="#" data-period="7">{{ __('labels.last_7_days') }}</a>
                                        <a class="dropdown-item" href="#" data-period="30">{{ __('labels.last_30_days') }}</a>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="card-body" id="db-settlements-container">
                        <div class="row g-2">
                            <div class="col-6">
                                <div class="p-2 rounded bg-warning-lt text-center">
                                    <div class="h3 mb-0" id="dbs-pending-amount">{{ $dbSettlements['pending_amount'] }}</div>
                                    <div class="text-muted small">{{ __('labels.unpaid') }} (<span id="dbs-pending-count">{{ $dbSettlements['pending_count'] }}</span>)</div>
                                </div>
                            </div>
                            <div class="col-6">
                                <div class="p-2 rounded bg-success-lt text-center">
                                    <div class="h3 mb-0" id="dbs-paid-amount">{{ $dbSettlements['paid_amount'] }}</div>
                                    <div class="text-muted small">{{ __('labels.paid') }} (<span id="dbs-paid-count">{{ $dbSettlements['paid_count'] }}</span>)</div>
                                </div>
                            </div>
                        </div>
                        <div class="mt-3 text-center">
                            <div class="text-muted small">{{ __('labels.total_unpaid_balance') }}</div>
                            <div class="h2 mb-0 text-warning" id="dbs-unpaid">{{ $dbSettlements['total_unpaid'] }}</div>
                        </div>
                    </div>
                </div>
            </div>

            {{-- Withdrawals --}}
            <div class="col-sm-6 col-lg-4">
                <div class="card">
                    <div class="card-header">
                        <div class="d-flex align-items-center">
                            <h3 class="card-title">{{ __('labels.withdrawals') }}</h3>
                            <div class="ms-auto">
                                <div class="dropdown ps-2">
                                    <a class="dropdown-toggle text-secondary withdrawals-period" href="#"
                                        data-bs-toggle="dropdown" aria-haspopup="true" aria-expanded="false"
                                        data-period="1">{{ __('labels.today') }}</a>
                                    <div class="dropdown-menu dropdown-menu-end">
                                        <a class="dropdown-item active" href="#" data-period="1">{{ __('labels.today') }}</a>
                                        <a class="dropdown-item" href="#" data-period="2">{{ __('labels.yesterday') }}</a>
                                        <a class="dropdown-item" href="#" data-period="7">{{ __('labels.last_7_days') }}</a>
                                        <a class="dropdown-item" href="#" data-period="30">{{ __('labels.last_30_days') }}</a>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="card-body" id="withdrawals-container">
                        <div class="row g-2">
                            <div class="col-4">
                                <div class="p-2 rounded bg-warning-lt text-center">
                                    <div class="h3 mb-0" id="wd-total-pending">{{ $withdrawals['total_pending'] }}</div>
                                    <div class="text-muted small">{{ __('labels.pending') }}</div>
                                </div>
                            </div>
                            <div class="col-4">
                                <div class="p-2 rounded bg-success-lt text-center">
                                    <div class="h3 mb-0" id="wd-approved-amount">{{ $withdrawals['approved_amount'] }}</div>
                                    <div class="text-muted small">{{ __('labels.approved') }}</div>
                                </div>
                            </div>
                            <div class="col-4">
                                <div class="p-2 rounded bg-danger-lt text-center">
                                    <div class="h3 mb-0" id="wd-total-rejected">{{ $withdrawals['total_rejected'] }}</div>
                                    <div class="text-muted small">{{ __('labels.rejected') }}</div>
                                </div>
                            </div>
                        </div>
                        <div class="mt-3 d-flex justify-content-between small text-muted">
                            <span>{{ __('labels.seller') }}: <strong id="wd-seller-pending">{{ $withdrawals['seller_pending'] }}</strong> {{ __('labels.pending') }}</span>
                            <span>{{ __('labels.delivery_boy') }}: <strong id="wd-db-pending">{{ $withdrawals['db_pending'] }}</strong> {{ __('labels.pending') }}</span>
                        </div>
                        @if($withdrawals['oldest_pending_days'] > 3)
                            <div class="mt-2 alert alert-warning py-1 px-2 mb-0 small">
                                {{ __('labels.oldest_pending_request') }}: <strong id="wd-oldest-days">{{ $withdrawals['oldest_pending_days'] }}</strong> {{ __('labels.days_ago') }}
                            </div>
                        @endif
                    </div>
                </div>
            </div>

            {{-- Cash Collection --}}
            <div class="col-sm-6 col-lg-4">
                <div class="card">
                    <div class="card-header">
                        <div class="d-flex align-items-center">
                            <h3 class="card-title">{{ __('labels.cash_collection') }}</h3>
                            <div class="ms-auto">
                                <div class="dropdown ps-2">
                                    <a class="dropdown-toggle text-secondary cash-collection-period" href="#"
                                        data-bs-toggle="dropdown" aria-haspopup="true" aria-expanded="false"
                                        data-period="1">{{ __('labels.today') }}</a>
                                    <div class="dropdown-menu dropdown-menu-end">
                                        <a class="dropdown-item active" href="#" data-period="1">{{ __('labels.today') }}</a>
                                        <a class="dropdown-item" href="#" data-period="2">{{ __('labels.yesterday') }}</a>
                                        <a class="dropdown-item" href="#" data-period="7">{{ __('labels.last_7_days') }}</a>
                                        <a class="dropdown-item" href="#" data-period="30">{{ __('labels.last_30_days') }}</a>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="card-body" id="cash-collection-container">
                        <div class="row g-2">
                            <div class="col-4">
                                <div class="p-2 rounded bg-primary-lt text-center">
                                    <div class="h3 mb-0" id="cc-collected">{{ $cashCollection['total_collected'] }}</div>
                                    <div class="text-muted small">{{ __('labels.collected') }}</div>
                                </div>
                            </div>
                            <div class="col-4">
                                <div class="p-2 rounded bg-success-lt text-center">
                                    <div class="h3 mb-0" id="cc-submitted">{{ $cashCollection['total_submitted'] }}</div>
                                    <div class="text-muted small">{{ __('labels.submitted') }}</div>
                                </div>
                            </div>
                            <div class="col-4">
                                <div class="p-2 rounded bg-danger-lt text-center">
                                    <div class="h3 mb-0" id="cc-unsubmitted">{{ $cashCollection['unsubmitted'] }}</div>
                                    <div class="text-muted small">{{ __('labels.unsubmitted') }}</div>
                                </div>
                            </div>
                        </div>
                        @if($cashCollection['top_collector_name'])
                            <div class="mt-3 d-flex align-items-center small text-muted">
                                <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="me-1 text-primary"><path d="M12 15l-2 5l9 -11h-5l2 -4h-4l-2 5" /></svg>
                                <span>{{ __('labels.top_collector') }}: <strong id="cc-top-name">{{ $cashCollection['top_collector_name'] }}</strong> (<span id="cc-top-amount">{{ $cashCollection['top_collector_amount'] }}</span>)</span>
                            </div>
                        @endif
                    </div>
                </div>
            </div>

            {{-- Ad Campaigns --}}
            <div class="col-sm-6 col-lg-4">
                <div class="card">
                    <div class="card-header">
                        <div class="d-flex align-items-center">
                            <h3 class="card-title">{{ __('labels.ad_campaigns') }}</h3>
                            <div class="ms-auto">
                                <div class="dropdown ps-2">
                                    <a class="dropdown-toggle text-secondary ad-campaigns-period" href="#"
                                        data-bs-toggle="dropdown" aria-haspopup="true" aria-expanded="false"
                                        data-period="1">{{ __('labels.today') }}</a>
                                    <div class="dropdown-menu dropdown-menu-end">
                                        <a class="dropdown-item active" href="#" data-period="1">{{ __('labels.today') }}</a>
                                        <a class="dropdown-item" href="#" data-period="2">{{ __('labels.yesterday') }}</a>
                                        <a class="dropdown-item" href="#" data-period="7">{{ __('labels.last_7_days') }}</a>
                                        <a class="dropdown-item" href="#" data-period="30">{{ __('labels.last_30_days') }}</a>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="card-body" id="ad-campaigns-container">
                        <div class="row g-2 mb-3">
                            <div class="col-4">
                                <div class="text-center">
                                    <div class="h3 mb-0 text-primary" id="ad-active">{{ $adCampaigns['active_campaigns'] }}</div>
                                    <div class="text-muted small">{{ __('labels.active') }}</div>
                                </div>
                            </div>
                            <div class="col-4">
                                <div class="text-center">
                                    <div class="h3 mb-0" id="ad-spend">{{ $adCampaigns['total_spend'] }}</div>
                                    <div class="text-muted small">{{ __('labels.spend') }}</div>
                                </div>
                            </div>
                            <div class="col-4">
                                <div class="text-center">
                                    <div class="h3 mb-0" id="ad-clicks">{{ $adCampaigns['total_clicks'] }}</div>
                                    <div class="text-muted small">{{ __('labels.clicks') }}</div>
                                </div>
                            </div>
                        </div>
                        @if(count($adCampaigns['top_campaigns']) > 0)
                            <div class="list-group list-group-flush" id="ad-top-campaigns">
                                @foreach($adCampaigns['top_campaigns'] as $campaign)
                                    <div class="list-group-item px-0 py-1 d-flex align-items-center">
                                        <div class="flex-fill small">{{ Str::limit($campaign['name'], 20) }}</div>
                                        <span class="badge bg-primary-lt me-1">{{ $campaign['clicks'] }} {{ __('labels.clicks') }}</span>
                                        <span class="text-muted small">{{ $campaign['spent'] }}</span>
                                    </div>
                                @endforeach
                            </div>
                        @endif
                        <div class="mt-3 text-center">
                            <a href="{{ route('admin.ads.campaigns.dashboard') }}" class="btn btn-sm btn-outline-primary">
                                {{ __('labels.go_to_ad_dashboard') }}
                                <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="ms-1"><path d="M5 12h14"/><path d="M13 18l6 -6"/><path d="M13 6l6 6"/></svg>
                            </a>
                        </div>
                    </div>
                </div>
            </div>

            {{-- Seller Subscriptions --}}
            <div class="col-sm-6 col-lg-4">
                <div class="card">
                    <div class="card-header">
                        <div class="d-flex align-items-center">
                            <h3 class="card-title">{{ __('labels.seller_subscriptions') }}</h3>
                            <div class="ms-auto">
                                <div class="dropdown ps-2">
                                    <a class="dropdown-toggle text-secondary subscriptions-period" href="#"
                                        data-bs-toggle="dropdown" aria-haspopup="true" aria-expanded="false"
                                        data-period="1">{{ __('labels.today') }}</a>
                                    <div class="dropdown-menu dropdown-menu-end">
                                        <a class="dropdown-item active" href="#" data-period="1">{{ __('labels.today') }}</a>
                                        <a class="dropdown-item" href="#" data-period="2">{{ __('labels.yesterday') }}</a>
                                        <a class="dropdown-item" href="#" data-period="7">{{ __('labels.last_7_days') }}</a>
                                        <a class="dropdown-item" href="#" data-period="30">{{ __('labels.last_30_days') }}</a>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="card-body" id="subscriptions-container">
                        <div class="row g-2">
                            <div class="col-6">
                                <div class="p-2 rounded bg-primary-lt text-center">
                                    <div class="h3 mb-0" id="sub-active">{{ $subscriptions['active_count'] }}</div>
                                    <div class="text-muted small">{{ __('labels.active_subscriptions') }}</div>
                                </div>
                            </div>
                            <div class="col-6">
                                <div class="p-2 rounded bg-success-lt text-center">
                                    <div class="h3 mb-0" id="sub-revenue">{{ $subscriptions['revenue'] }}</div>
                                    <div class="text-muted small">{{ __('labels.revenue') }}</div>
                                </div>
                            </div>
                        </div>
                        <div class="mt-3 d-flex justify-content-between align-items-center">
                            <div class="small text-muted">
                                {{ __('labels.popular_plan') }}: <strong id="sub-popular">{{ $subscriptions['popular_plan'] }}</strong>
                            </div>
                            @if($subscriptions['expiring_soon'] > 0)
                                <span class="badge bg-warning-lt" id="sub-expiring">
                                    {{ $subscriptions['expiring_soon'] }} {{ __('labels.expiring_soon') }}
                                </span>
                            @else
                                <span class="badge bg-success-lt" id="sub-expiring">{{ __('labels.none_expiring') }}</span>
                            @endif
                        </div>
                    </div>
                </div>
            </div>

            {{-- Customer Insights --}}
            <div class="col-lg-6">
                <div class="card">
                    <div class="card-header">
                        <div class="d-flex align-items-center">
                            <h3 class="card-title">{{ __('labels.customer_insights') }}</h3>
                            <div class="ms-auto">
                                <div class="dropdown ps-2">
                                    <a class="dropdown-toggle text-secondary customer-insights-period" href="#"
                                        data-bs-toggle="dropdown" aria-haspopup="true" aria-expanded="false"
                                        data-period="30">{{ __('labels.last_30_days') }}</a>
                                    <div class="dropdown-menu dropdown-menu-end">
                                        <a class="dropdown-item" href="#" data-period="7">{{ __('labels.last_7_days') }}</a>
                                        <a class="dropdown-item active" href="#" data-period="30">{{ __('labels.last_30_days') }}</a>
                                        <a class="dropdown-item" href="#" data-period="90">{{ __('labels.last_3_months') }}</a>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="card-body" id="customer-insights-container">
                        <div class="row g-3">
                            <div class="col-sm-4">
                                <div class="text-center p-2 rounded bg-primary-lt">
                                    <div class="h2 mb-0" id="ci-repeat-rate">{{ $customerInsights['repeat_rate'] }}%</div>
                                    <div class="text-muted small">{{ __('labels.repeat_rate') }}</div>
                                </div>
                            </div>
                            <div class="col-sm-4">
                                <div class="text-center p-2 rounded bg-success-lt">
                                    <div class="h2 mb-0" id="ci-avg-basket">{{ $customerInsights['avg_basket_size'] }}</div>
                                    <div class="text-muted small">{{ __('labels.avg_basket_size') }}</div>
                                </div>
                            </div>
                            <div class="col-sm-4">
                                <div class="text-center p-2 rounded bg-danger-lt">
                                    <div class="h2 mb-0" id="ci-churn-risk">{{ $customerInsights['churn_risk'] }}</div>
                                    <div class="text-muted small">{{ __('labels.churn_risk') }}</div>
                                </div>
                            </div>
                        </div>
                        <hr class="my-3">
                        <div class="row">
                            <div class="col-6">
                                <div class="d-flex align-items-center mb-2">
                                    <span class="badge bg-primary badge-empty me-2"></span>
                                    <span class="small">{{ __('labels.new_customers') }}</span>
                                    <span class="ms-auto fw-bold" id="ci-new">{{ $customerInsights['new_customers'] }}</span>
                                </div>
                            </div>
                            <div class="col-6">
                                <div class="d-flex align-items-center mb-2">
                                    <span class="badge bg-success badge-empty me-2"></span>
                                    <span class="small">{{ __('labels.returning_customers') }}</span>
                                    <span class="ms-auto fw-bold" id="ci-returning">{{ $customerInsights['returning_customers'] }}</span>
                                </div>
                            </div>
                        </div>
                        <div class="text-muted small mt-2">
                            {{ __('labels.total_active_customers') }}: <strong id="ci-total">{{ $customerInsights['total_customers'] }}</strong>
                        </div>
                    </div>
                </div>
            </div>

            {{-- Top Stores --}}
            <div class="col-lg-6">
                <div class="card">
                    <div class="card-header">
                        <div class="d-flex align-items-center">
                            <h3 class="card-title">{{ __('labels.top_stores') }}</h3>
                            <div class="ms-auto d-flex gap-2">
                                <div class="dropdown ps-2">
                                    <a class="dropdown-toggle text-secondary top-stores-rank" href="#"
                                        data-bs-toggle="dropdown" aria-haspopup="true" aria-expanded="false"
                                        data-rank="revenue">{{ __('labels.by_revenue') }}</a>
                                    <div class="dropdown-menu dropdown-menu-end">
                                        <a class="dropdown-item active" href="#" data-rank="revenue">{{ __('labels.by_revenue') }}</a>
                                        <a class="dropdown-item" href="#" data-rank="orders">{{ __('labels.by_orders') }}</a>
                                    </div>
                                </div>
                                <div class="dropdown ps-2">
                                    <a class="dropdown-toggle text-secondary top-stores-period" href="#"
                                        data-bs-toggle="dropdown" aria-haspopup="true" aria-expanded="false"
                                        data-period="30">{{ __('labels.last_30_days') }}</a>
                                    <div class="dropdown-menu dropdown-menu-end">
                                        <a class="dropdown-item" href="#" data-period="7">{{ __('labels.last_7_days') }}</a>
                                        <a class="dropdown-item active" href="#" data-period="30">{{ __('labels.last_30_days') }}</a>
                                        <a class="dropdown-item" href="#" data-period="90">{{ __('labels.last_3_months') }}</a>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="card-body p-0" id="top-stores-container">
                        @if(count($topStores) > 0)
                            <div class="list-group list-group-flush">
                                @foreach($topStores as $index => $store)
                                    <div class="list-group-item d-flex align-items-center">
                                        <span class="avatar avatar-sm me-3 rounded" style="background-image: url({{ $store['image'] ?? asset('assets/images/store-placeholder.jpg') }})"></span>
                                        <div class="flex-fill">
                                            <div class="fw-medium">{{ $store['name'] }}</div>
                                            <div class="text-muted small">{{ $store['total_orders'] }} {{ __('labels.orders') }}</div>
                                        </div>
                                        <div class="text-end">
                                            <div class="fw-bold">{{ $store['total_revenue'] }}</div>
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        @else
                            <div class="text-center py-4 text-muted">
                                <img src="{{ asset('assets/images/not-found.svg') }}" class="mb-2" width="60" alt="">
                                <p class="mb-0">{{ __('labels.no_data_found') }}</p>
                            </div>
                        @endif
                    </div>
                </div>
            </div>

            {{-- Top Zones (only in "All Zones" mode) --}}
            @if(!($zoneId ?? null))
            <div class="col-lg-6">
                <div class="card">
                    <div class="card-header">
                        <div class="d-flex align-items-center">
                            <h3 class="card-title">{{ __('labels.top_zones') }}</h3>
                            <div class="ms-auto">
                                <div class="dropdown ps-2">
                                    <a class="dropdown-toggle text-secondary top-zones-period" href="#"
                                        data-bs-toggle="dropdown" aria-haspopup="true" aria-expanded="false"
                                        data-period="30">{{ __('labels.last_30_days') }}</a>
                                    <div class="dropdown-menu dropdown-menu-end">
                                        <a class="dropdown-item" href="#" data-period="7">{{ __('labels.last_7_days') }}</a>
                                        <a class="dropdown-item active" href="#" data-period="30">{{ __('labels.last_30_days') }}</a>
                                        <a class="dropdown-item" href="#" data-period="90">{{ __('labels.last_3_months') }}</a>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="card-body p-0" id="top-zones-container">
                        @if(count($topZones) > 0)
                            <div class="list-group list-group-flush">
                                @foreach($topZones as $zone)
                                    <div class="list-group-item d-flex align-items-center">
                                        <span class="avatar avatar-sm me-3 bg-primary-lt rounded">
                                            <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="3"/><path d="M12 2a10 10 0 0 1 10 10c0 5.523-4.477 10-10 10S2 17.523 2 12 6.477 2 12 2"/></svg>
                                        </span>
                                        <div class="flex-fill">
                                            <div class="fw-medium">{{ $zone['name'] }}</div>
                                            <div class="text-muted small">{{ $zone['total_orders'] }} {{ __('labels.orders') }} · {{ $zone['active_delivery_boys'] }} {{ __('labels.delivery_boys') }}</div>
                                        </div>
                                        <div class="text-end">
                                            <div class="fw-bold">{{ $zone['total_revenue'] }}</div>
                                            <div class="small text-{{ $zone['delivery_rate'] >= 80 ? 'success' : 'warning' }}">{{ $zone['delivery_rate'] }}% {{ __('labels.delivered') }}</div>
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        @else
                            <div class="text-center py-4 text-muted">
                                <img src="{{ asset('assets/images/not-found.svg') }}" class="mb-2" width="60" alt="">
                                <p class="mb-0">{{ __('labels.no_data_found') }}</p>
                            </div>
                        @endif
                    </div>
                </div>
            </div>
            @endif

            {{-- Zone Health Panel --}}
            <div class="col-12">
                <div class="card">
                    <div class="card-header">
                        <div class="d-flex align-items-center">
                            <h3 class="card-title">{{ __('labels.zone_health') }}</h3>
                            <div class="ms-auto">
                                <div class="dropdown ps-2">
                                    <a class="dropdown-toggle text-secondary zone-health-period" href="#"
                                        data-bs-toggle="dropdown" aria-haspopup="true" aria-expanded="false"
                                        data-period="30">{{ __('labels.last_30_days') }}</a>
                                    <div class="dropdown-menu dropdown-menu-end">
                                        <a class="dropdown-item" href="#" data-period="7">{{ __('labels.last_7_days') }}</a>
                                        <a class="dropdown-item active" href="#" data-period="30">{{ __('labels.last_30_days') }}</a>
                                        <a class="dropdown-item" href="#" data-period="90">{{ __('labels.last_3_months') }}</a>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="card-body p-0" id="zone-health-container">
                        <div class="table-responsive">
                            <table class="table table-vcenter card-table table-hover">
                                <thead>
                                    <tr>
                                        <th>{{ __('labels.zone') }}</th>
                                        <th class="text-center">{{ __('labels.orders') }}</th>
                                        <th class="text-center">{{ __('labels.delivered') }}</th>
                                        <th class="text-center">{{ __('labels.delivery_rate') }}</th>
                                        <th class="text-end">{{ __('labels.revenue') }}</th>
                                        <th class="text-center">{{ __('labels.growth') }}</th>
                                        <th class="text-center">{{ __('labels.delivery_boys') }}</th>
                                        <th class="text-center">{{ __('labels.stores') }}</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($zoneHealth['zones'] as $zone)
                                        <tr class="{{ $zone['delivery_rate'] < 70 ? 'bg-danger-lt' : '' }}">
                                            <td>
                                                <div class="fw-medium">{{ $zone['name'] }}</div>
                                            </td>
                                            <td class="text-center">{{ $zone['total_orders'] }}</td>
                                            <td class="text-center">{{ $zone['delivered_orders'] }}</td>
                                            <td class="text-center">
                                                <span class="badge bg-{{ $zone['delivery_rate'] >= 80 ? 'success' : ($zone['delivery_rate'] >= 60 ? 'warning' : 'danger') }}-lt">
                                                    {{ $zone['delivery_rate'] }}%
                                                </span>
                                            </td>
                                            <td class="text-end fw-medium">{{ $zone['revenue'] }}</td>
                                            <td class="text-center">
                                                <span class="text-{{ $zone['revenue_growth'] >= 0 ? 'success' : 'danger' }}">
                                                    {{ $zone['revenue_growth'] >= 0 ? '+' : '' }}{{ $zone['revenue_growth'] }}%
                                                </span>
                                            </td>
                                            <td class="text-center">
                                                <span class="{{ $zone['active_delivery_boys'] == 0 ? 'text-danger fw-bold' : '' }}">
                                                    {{ $zone['active_delivery_boys'] }}/{{ $zone['total_delivery_boys'] }}
                                                </span>
                                            </td>
                                            <td class="text-center">{{ $zone['store_count'] }}</td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    @endif

@endsection
@push('styles')
    <link rel="stylesheet" href="{{asset('assets/vendor/star-rating.js/dist/star-rating.min.css')}}">
@endpush
@push('scripts')
    <script src="{{asset('assets/vendor/apexcharts/dist/apexcharts.min.js')}}" defer></script>
    <script src="{{asset('assets/vendor/star-rating.js/dist/star-rating.min.js')}}" defer></script>
    <script>
        var dashboardData = {
            monthlyRevenueData: @json($adminCommissionChart),
            storeOrderTotals: @json([]),
            dailyPurchaseHistory: @json($dailyPurchaseHistory),
            todaysEarning: @json([]),
            categoryProductWeightage: @json($categoryProductWeightage),
            newUserRegistrationsData: @json($newUserRegistrationsData),
            revenueDataBg: @json($revenueDataBg)
        };
        const commissionData = @json($enhancedCommissionsData['daily_data']);
        const revenueVsOrdersData = @json($revenueVsOrders);
        var currentZoneId = @json($zoneId ?? null);
    </script>
    <script src="{{hyperAsset('assets/js/admin-dashboard.js')}}" defer></script>
@endpush