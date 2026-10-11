@extends('layouts.admin.app',['page' => $menuAdmin['ad_campaigns']['active'] ?? "", 'sub_page' => $menuAdmin['ad_campaigns']['route']['topups']['sub_active'] ?? "" ])

@section('title', __('labels.ad_wallet_sellers'))

@section('header_data')
    @php
        $page_title = __('labels.ad_wallet_sellers');
        $page_pretitle = __('labels.admin') . ' ' . __('labels.ad_wallet');
    @endphp
@endsection

@php
    $breadcrumbs = [
        ['title' => __('labels.home'), 'url' => route('admin.dashboard')],
        ['title' => __('labels.ad_campaigns'), 'url' => route('admin.ads.campaigns.index')],
        ['title' => __('labels.ad_wallet_sellers'), 'url' => ''],
    ];
@endphp

@section('admin-content')
    <div class="page-wrapper">
        <div class="page-body">
            <div class="row row-cards">
                <div class="col-12">
                    <div class="card">
                        <div class="card-header">
                            <div>
                                <h3 class="card-title">{{ __('labels.ad_wallet_sellers') }}</h3>
                                <x-breadcrumb :items="$breadcrumbs"/>
                            </div>
                            <div class="card-actions">
                                <div class="row g-2">
                                    <div class="col-auto">
                                        <select class="form-select" id="sellerFilter" placeholder="{{ __('labels.seller') }}">
                                        </select>
                                    </div>
                                    <div class="col-auto">
                                        <select class="form-select" id="activityFilter">
                                            @foreach($activityOptions as $value => $label)
                                                <option value="{{ $value }}">{{ $label }}</option>
                                            @endforeach
                                        </select>
                                    </div>
                                    <div class="col-auto">
                                        <a href="{{ route('admin.ads.campaigns.topup-transactions') }}" class="btn btn-outline-primary">
                                            {{ __('labels.pending_wallet_deposits') }}
                                        </a>
                                    </div>
                                    <div class="col-auto">
                                        <button class="btn btn-outline-primary" id="refresh">
                                            <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="icon icon-tabler icons-tabler-outline icon-tabler-refresh">
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
                        <div class="card-body">
                            <div class="row w-full p-3">
                                <x-datatable id="admin-ad-topups-table" :columns="$columns"
                                             route="{{ route('admin.ads.campaigns.topups.datatable') }}"
                                             :options="['order' => [[2, 'desc']], 'pageLength' => 10]"/>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection

@push('scripts')
    <script src="{{ hyperAsset('assets/js/admin-ad-topup-shared.js') }}" defer></script>
    <script src="{{ hyperAsset('assets/js/admin-ad-topups.js') }}" defer></script>
@endpush
