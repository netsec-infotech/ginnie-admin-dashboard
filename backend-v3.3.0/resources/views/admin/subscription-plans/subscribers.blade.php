@extends('layouts.admin.app', ['page' => $menuAdmin['subscriptions']['active'] ?? '', 'sub_page' => $menuAdmin['subscriptions']['route']['subscribers']['sub_active'] ?? null])

@section('title', __('labels.subscribers'))

@section('header_data')
    @php
        $page_title = __('labels.subscribers');
        $page_pretitle = __('labels.list');
    @endphp
@endsection

@php
    $breadcrumbs = [
        ['title' => __('labels.home'), 'url' => route('admin.dashboard')],
        ['title' => __('labels.subscriptions'), 'url' => route('admin.subscription-plans.index')],
        ['title' => __('labels.subscribers'), 'url' => null],
    ];

    $activeStatusGroup = $activeStatusGroup ?? null;
    $subscriberInsightUrls = [
        'total' => route('admin.subscription-plans.subscribers'),
        'active' => route('admin.subscription-plans.subscribers', ['status_group' => 'active']),
        'cancelled' => route('admin.subscription-plans.subscribers', ['status_group' => 'cancelled']),
        'pending' => route('admin.subscription-plans.subscribers', ['status_group' => 'pending']),
    ];
@endphp

@section('admin-content')
    <div class="page-body">
        <div class="container-xl">
            <div class="row row-cards py-3">
                <div class="col-sm-6 col-lg-3">
                    <div class="card card-sm position-relative subscriber-insight-card insight-filter-card {{ $activeStatusGroup === null ? 'active' : '' }}"
                        data-status-group="">
                        <div class="card-body">
                            <div class="row align-items-center">
                                <div class="col-auto">
                                    <span class="bg-primary text-white avatar">
                                        <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="icon icon-tabler icons-tabler-outline icon-tabler-users">
                                            <path stroke="none" d="M0 0h24v24H0z" fill="none"/>
                                            <path d="M9 7m-4 0a4 4 0 1 0 8 0a4 4 0 1 0 -8 0" />
                                            <path d="M3 21v-2a4 4 0 0 1 4 -4h4a4 4 0 0 1 4 4v2" />
                                            <path d="M16 3.13a4 4 0 0 1 0 7.75" />
                                            <path d="M21 21v-2a4 4 0 0 0 -3 -3.85" />
                                        </svg>
                                    </span>
                                </div>
                                <div class="col">
                                    <div class="fw-bold fs-2">
                                        <span id="insight-total-subscribers">{{ $subscriberInsights['total'] ?? 0 }}</span>
                                    </div>
                                    <div class="text-secondary">{{ __('labels.total_subscribed_users') ?? 'Total Subscribed Users' }}</div>
                                </div>
                            </div>
                            <a href="{{ $subscriberInsightUrls['total'] }}" class="stretched-link subscriber-insight-card-link"
                                aria-label="{{ __('labels.total_subscribed_users') ?? 'Total Subscribed Users' }}"
                                {!! $activeStatusGroup === null ? 'aria-current="page"' : '' !!}></a>
                        </div>
                    </div>
                </div>
                <div class="col-sm-6 col-lg-3">
                    <div class="card card-sm position-relative subscriber-insight-card insight-filter-card {{ $activeStatusGroup === 'active' ? 'active' : '' }}"
                        data-status-group="active">
                        <div class="card-body">
                            <div class="row align-items-center">
                                <div class="col-auto">
                                    <span class="bg-success text-white avatar">
                                        <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="icon icon-tabler icons-tabler-outline icon-tabler-user-check">
                                            <path stroke="none" d="M0 0h24v24H0z" fill="none"/>
                                            <path d="M8 7a4 4 0 1 0 8 0a4 4 0 0 0 -8 0"/>
                                            <path d="M6 21v-2a4 4 0 0 1 4 -4h4"/>
                                            <path d="M15 19l2 2l4 -4"/>
                                        </svg>
                                    </span>
                                </div>
                                <div class="col">
                                    <div class="fw-bold fs-2">
                                        <span id="insight-active-subscribers">{{ $subscriberInsights['active'] ?? 0 }}</span>
                                    </div>
                                    <div class="text-secondary">{{ __('labels.active_subscriptions') ?? 'Active Subscriptions' }}</div>
                                </div>
                            </div>
                            <a href="{{ $subscriberInsightUrls['active'] }}" class="stretched-link subscriber-insight-card-link"
                                aria-label="{{ __('labels.active_subscriptions') ?? 'Active Subscriptions' }}"
                                {!! $activeStatusGroup === 'active' ? 'aria-current="page"' : '' !!}></a>
                        </div>
                    </div>
                </div>
                <div class="col-sm-6 col-lg-3">
                    <div class="card card-sm position-relative subscriber-insight-card insight-filter-card {{ $activeStatusGroup === 'cancelled' ? 'active' : '' }}"
                        data-status-group="cancelled">
                        <div class="card-body">
                            <div class="row align-items-center">
                                <div class="col-auto">
                                    <span class="bg-danger text-white avatar">
                                        <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="icon icon-tabler icons-tabler-outline icon-tabler-user-x">
                                            <path stroke="none" d="M0 0h24v24H0z" fill="none"/>
                                            <path d="M8 7a4 4 0 1 0 8 0a4 4 0 0 0 -8 0"/>
                                            <path d="M6 21v-2a4 4 0 0 1 4 -4h4"/>
                                            <path d="M17 17l4 4m0 -4l-4 4"/>
                                        </svg>
                                    </span>
                                </div>
                                <div class="col">
                                    <div class="fw-bold fs-2">
                                        <span id="insight-cancelled-subscribers">{{ $subscriberInsights['cancelled'] ?? 0 }}</span>
                                    </div>
                                    <div class="text-secondary">{{ __('labels.cancelled_subscriptions') ?? 'Cancelled Subscriptions' }}</div>
                                </div>
                            </div>
                            <a href="{{ $subscriberInsightUrls['cancelled'] }}" class="stretched-link subscriber-insight-card-link"
                                aria-label="{{ __('labels.cancelled_subscriptions') ?? 'Cancelled Subscriptions' }}"
                                {!! $activeStatusGroup === 'cancelled' ? 'aria-current="page"' : '' !!}></a>
                        </div>
                    </div>
                </div>
                <div class="col-sm-6 col-lg-3">
                    <div class="card card-sm position-relative subscriber-insight-card insight-filter-card {{ $activeStatusGroup === 'pending' ? 'active' : '' }}"
                        data-status-group="pending">
                        <div class="card-body">
                            <div class="row align-items-center">
                                <div class="col-auto">
                                    <span class="bg-warning text-white avatar">
                                        <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="icon">
                                            <path d="M12 9v4" />
                                            <path d="M10.363 3.591l-8.106 13.534a1.914 1.914 0 0 0 1.636 2.871h16.214a1.914 1.914 0 0 0 1.636 -2.87l-8.106 -13.536a1.914 1.914 0 0 0 -3.274 0z" />
                                            <path d="M12 16h.01" />
                                        </svg>
                                    </span>
                                </div>
                                <div class="col">
                                    <div class="fw-bold fs-2">
                                        <span id="insight-pending-subscribers">{{ $subscriberInsights['pending'] ?? 0 }}</span>
                                    </div>
                                    <div class="text-secondary">{{ __('labels.pending_subscriptions') ?? 'Pending Subscriptions' }}</div>
                                </div>
                            </div>
                            <a href="{{ $subscriberInsightUrls['pending'] }}" class="stretched-link subscriber-insight-card-link"
                                aria-label="{{ __('labels.pending_subscriptions') ?? 'Pending Subscriptions' }}"
                                {!! $activeStatusGroup === 'pending' ? 'aria-current="page"' : '' !!}></a>
                        </div>
                    </div>
                </div>
            </div>
            <div class="card">
                <div class="card-header">
                    <div>
                        <h3 class="card-title">{{ __('labels.subscribers') }}</h3>
                        <x-breadcrumb :items="$breadcrumbs" />
                    </div>
                    <div class="card-actions">
                        <div class="row g-2">
                            <div class="col-auto">
                                <select class="form-select" id="planFilter" style="max-width: 150px;">
                                    <option value="">{{ __('labels.plan_filter') }}</option>
                                    @foreach ($plans as $plan)
                                        <option value="{{ $plan->id }}" {{ $plan->is_selected ? 'selected' : '' }}>
                                            {{ ucfirst($plan->name) }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-auto">
                                <select class="form-select" id="statusFilter">
                                    <option value="">{{ __('labels.status_filter') }}</option>
                                    @foreach ($statuses ?? [] as $st)
                                        <option value="{{ $st }}"
                                            {{ isset($status) && $status === $st ? 'selected' : '' }}>
                                            {{ ucfirst($st) }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-auto">
                                <input type="text" class="form-control" name="name" id="select-seller" multiple
                                    placeholder="{{ __('labels.enter_seller_name') }}" />
                            </div>
                            <div class="col-auto">
                                <button class="btn btn-outline-primary" id="refresh">
                                    <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24"
                                        viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
                                        stroke-linecap="round" stroke-linejoin="round"
                                        class="icon icon-tabler icons-tabler-outline icon-tabler-refresh">
                                        <path stroke="none" d="M0 0h24v24H0z" fill="none" />
                                        <path d="M20 11a8.1 8.1 0 0 0 -15.5 -2m-.5 -4v4h4" />
                                        <path d="M4 13a8.1 8.1 0 0 0 15.5 2m.5 4v-4h-4" />
                                    </svg>
                                    {{ __('labels.refresh') }}
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="card-table">
                    <div class="row w-full p-3">
                        <x-datatable id="subscription-subscribers-table" :columns="$columns"
                            route="{{ isset($planId) && $planId ? route('admin.subscription-plans.subscribers.datatable', ['plan_id' => $planId]) : route('admin.subscription-plans.subscribers.datatable') }}"
                            :options="['order' => [[0, 'desc']], 'pageLength' => 10]" />
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection
@push('scripts')
    <script src="{{ hyperAsset('assets/js/subscribers.blade.php.js') }}" defer></script>
@endpush
