@extends('layouts.seller.app', ['page' => $menuSeller['ad_campaigns']['active'] ?? '', 'sub_page' => $menuSeller['ad_campaigns']['route']['index']['sub_active'] ?? ''])
@section('title', __('labels.ad_campaigns'))

@section('header_data')
    @php
        $page_title = __('labels.ad_campaigns');
        $page_pretitle = __('labels.ad_campaigns_subtitle');
    @endphp
@endsection

@php
    $breadcrumbs = [
        ['title' => __('labels.home'), 'url' => route('seller.dashboard')],
        ['title' => __('labels.ad_campaigns'), 'url' => ''],
    ];
@endphp

@section('seller-content')
    <div class="row row-cards py-3">
        <div class="col-sm-6 col-lg-3">
            <div class="card card-sm">
                <div class="card-body">
                    <div class="row align-items-center">
                        <div class="col-auto">
                            <span class="bg-success text-white avatar">
                                <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24"
                                    fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"
                                    stroke-linejoin="round"
                                    class="icon icon-tabler icons-tabler-outline icon-tabler-player-play">
                                    <path stroke="none" d="M0 0h24v24H0z" fill="none" />
                                    <path d="M7 4v16l13 -8z" />
                                </svg>
                            </span>
                        </div>
                        <div class="col">
                            <div class="fw-bold fs-2">
                                <span id="insight-running-campaigns">{{ $campaignInsights['running'] ?? 0 }}</span>
                            </div>
                            <div class="text-secondary">
                                {{ __('labels.campaigns_approved_and_running') ?? 'Campaigns Approved & Running' }}</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-sm-6 col-lg-3">
            <div class="card card-sm">
                <div class="card-body">
                    <div class="row align-items-center">
                        <div class="col-auto">
                            <span class="bg-warning text-white avatar">
                                <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24"
                                    fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"
                                    stroke-linejoin="round" class="icon">
                                    <path d="M12 9v4" />
                                    <path
                                        d="M10.363 3.591l-8.106 13.534a1.914 1.914 0 0 0 1.636 2.871h16.214a1.914 1.914 0 0 0 1.636 -2.87l-8.106 -13.536a1.914 1.914 0 0 0 -3.274 0z" />
                                    <path d="M12 16h.01" />
                                </svg>
                            </span>
                        </div>
                        <div class="col">
                            <div class="fw-bold fs-2">
                                <span id="insight-pending-campaigns">{{ $campaignInsights['pending'] ?? 0 }}</span>
                            </div>
                            <div class="text-secondary">
                                {{ __('labels.campaigns_pending_approval') ?? 'Campaigns Pending Approval' }}</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-sm-6 col-lg-3">
            <div class="card card-sm">
                <div class="card-body">
                    <div class="row align-items-center">
                        <div class="col-auto">
                            <span class="bg-danger text-white avatar">
                                <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24"
                                    fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"
                                    stroke-linejoin="round"
                                    class="icon icon-tabler icons-tabler-outline icon-tabler-cancel">
                                    <path stroke="none" d="M0 0h24v24H0z" fill="none" />
                                    <path d="M3 12a9 9 0 1 0 18 0a9 9 0 1 0 -18 0" />
                                    <path d="M18.364 5.636l-12.728 12.728" />
                                </svg>
                            </span>
                        </div>
                        <div class="col">
                            <div class="fw-bold fs-2">
                                <span id="insight-rejected-campaigns">{{ $campaignInsights['rejected'] ?? 0 }}</span>
                            </div>
                            <div class="text-secondary">{{ __('labels.campaigns_rejected') ?? 'Campaigns Rejected' }}</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-sm-6 col-lg-3">
            <div class="card card-sm">
                <div class="card-body">
                    <div class="row align-items-center">
                        <div class="col-auto">
                            <span class="bg-secondary text-white avatar">
                                <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24"
                                    fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"
                                    stroke-linejoin="round"
                                    class="icon icon-tabler icons-tabler-outline icon-tabler-player-stop">
                                    <path stroke="none" d="M0 0h24v24H0z" fill="none" />
                                    <rect x="5" y="5" width="14" height="14" rx="2" />
                                </svg>
                            </span>
                        </div>
                        <div class="col">
                            <div class="fw-bold fs-2">
                                <span
                                    id="insight-force-stopped-campaigns">{{ $campaignInsights['force_stopped'] ?? 0 }}</span>
                            </div>
                            <div class="text-secondary">
                                {{ __('labels.campaigns_force_stopped') ?? 'Campaigns Force Stopped' }}</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="row row-cards">
        <div class="col-12">
            <div class="card">
                <div class="card-header">
                    <div>
                        <h3 class="card-title">{{ __('labels.ad_campaigns') }}</h3>
                        <x-breadcrumb :items="$breadcrumbs" />
                    </div>
                    <div class="card-actions">
                        <div class="row g-2">
                            <div class="col-auto">
                                <select class="form-select" id="statusFilter">
                                    @foreach ($statusOptions as $value => $label)
                                        <option value="{{ $value }}">{{ $label }}</option>
                                    @endforeach
                                </select>
                            </div>
                            @if ($createPermission ?? false)
                                <div class="col-auto">
                                    <a href="{{ route('seller.ads.campaigns.create') }}" id="create-campaign-btn"
                                        class="btn btn-primary">
                                        <svg xmlns="http://www.w3.org/2000/svg" class="icon me-1" width="16"
                                            height="16" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"
                                            fill="none">
                                            <path stroke="none" d="M0 0h24v24H0z" fill="none" />
                                            <line x1="12" y1="5" x2="12" y2="19" />
                                            <line x1="5" y1="12" x2="19" y2="12" />
                                        </svg>
                                        {{ __('labels.create_ad_campaign') }}
                                    </a>
                                </div>
                            @endif
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
                        <x-datatable id="ad-campaigns-table" :columns="$columns"
                            route="{{ route('seller.ads.campaigns.datatable') }}" :options="['order' => [[5, 'desc']], 'pageLength' => 10]" />
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection

@push('scripts')
    <script src="{{ asset('assets/js/ad-campaigns.js') }}" defer></script>
@endpush
