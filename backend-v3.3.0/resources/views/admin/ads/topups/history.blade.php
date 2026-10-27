@extends('layouts.admin.app',['page' => $menuAdmin['ad_campaigns']['active'] ?? "", 'sub_page' => $menuAdmin['ad_campaigns']['route']['topup_transactions']['sub_active'] ?? "" ])

@section('title', __('labels.transaction_history'))

@section('header_data')
    @php
        $page_title = __('labels.transaction_history');
        $page_pretitle = __('labels.admin') . ' ' . __('labels.ad_wallet');
    @endphp
@endsection

@php
    $breadcrumbs = [
        ['title' => __('labels.home'), 'url' => route('admin.dashboard')],
        ['title' => __('labels.ad_campaigns'), 'url' => route('admin.ads.campaigns.index')],
        ['title' => __('labels.pending_wallet_deposits'), 'url' => route('admin.ads.campaigns.topup-transactions')],
        ['title' => __('labels.transaction_history'), 'url' => ''],
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
                                <h3 class="card-title">{{ __('labels.transaction_history') }}</h3>
                                <x-breadcrumb :items="$breadcrumbs"/>
                            </div>
                            <div class="card-actions">
                                <div class="row g-2">
                                    <div class="col-auto">
                                        <select class="form-select" id="transactionSellerFilter" placeholder="{{ __('labels.seller') }}">
                                        </select>
                                    </div>
                                    <div class="col-auto">
                                        <select class="form-select" id="transactionStatusFilter">
                                            @foreach($statusOptions as $value => $label)
                                                <option value="{{ $value }}">{{ $label }}</option>
                                            @endforeach
                                        </select>
                                    </div>
                                    <div class="col-auto">
                                        <select class="form-select text-capitalize" id="transactionPaymentMethodFilter">
                                            <option value="">{{ __('labels.all') }} {{ __('labels.payment_method') }}</option>
                                            @foreach($paymentMethods as $value => $label)
                                                <option value="{{ $value }}">{{ $label }}</option>
                                            @endforeach
                                        </select>
                                    </div>
                                    <div class="col-auto">
                                        <a href="{{ route('admin.ads.campaigns.topup-transactions', ['seller_id' => request('seller_id')]) }}" class="btn btn-outline-primary">
                                            {{ __('labels.pending_wallet_deposits') }}
                                        </a>
                                    </div>
                                    <div class="col-auto">
                                        <button class="btn btn-outline-primary" id="transactionsRefresh">
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
                                <x-datatable id="admin-ad-topup-history-table" :columns="$columns"
                                             route="{{ route('admin.ads.campaigns.topup-transactions.history.datatable') }}"
                                             :options="['order' => [[5, 'desc']], 'pageLength' => 10]"/>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection

@push('scripts')
    <script>
        window.adTopupHistoryConfig = {
            initialSeller: @json($selectedSeller ? [
                'value' => (string) $selectedSeller->id,
                'text' => $selectedSeller->user?->name ?? '',
            ] : null),
        };
    </script>
    <script src="{{ hyperAsset('assets/js/admin-ad-topup-shared.js') }}" defer></script>
    <script src="{{ hyperAsset('assets/js/admin-ad-topup-history.js') }}" defer></script>
@endpush
