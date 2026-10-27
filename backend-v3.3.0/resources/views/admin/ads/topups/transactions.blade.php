@extends('layouts.admin.app',['page' => $menuAdmin['ad_campaigns']['active'] ?? "", 'sub_page' => $menuAdmin['ad_campaigns']['route']['topup_transactions']['sub_active'] ?? "" ])

@section('title', __('labels.pending_wallet_deposits'))

@section('header_data')
    @php
        $page_title = __('labels.pending_wallet_deposits');
        $page_pretitle = __('labels.admin') . ' ' . __('labels.ad_wallet');
    @endphp
@endsection

@php
    $breadcrumbs = [
        ['title' => __('labels.home'), 'url' => route('admin.dashboard')],
        ['title' => __('labels.ad_campaigns'), 'url' => route('admin.ads.campaigns.index')],
        ['title' => __('labels.pending_wallet_deposits'), 'url' => ''],
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
                                <h3 class="card-title">{{ __('labels.pending_wallet_deposits') }}</h3>
                                <x-breadcrumb :items="$breadcrumbs"/>
                            </div>
                            <div class="card-actions">
                                <div class="row g-2">
                                    <div class="col-auto">
                                        <select class="form-select" id="transactionSellerFilter" placeholder="{{ __('labels.seller') }}">
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
                                        <a href="{{ route('admin.ads.campaigns.topup-transactions.history', ['seller_id' => request('seller_id')]) }}" class="btn btn-outline-primary">
                                            {{ __('labels.transaction_history') }}
                                        </a>
                                    </div>
                                    <div class="col-auto">
                                        <a href="{{ route('admin.ads.campaigns.topups') }}" class="btn btn-outline-primary">
                                            {{ __('labels.ad_wallet_sellers') }}
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
                                <x-datatable id="admin-ad-topup-transactions-table" :columns="$columns"
                                             route="{{ route('admin.ads.campaigns.topup-transactions.datatable') }}"
                                             :options="['order' => [[4, 'desc']], 'pageLength' => 10]"/>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="modal modal-blur fade" id="approveDepositModal" tabindex="-1" role="dialog" aria-hidden="true">
        <div class="modal-dialog modal-sm modal-dialog-centered" role="document">
            <div class="modal-content">
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                <div class="modal-status bg-success"></div>
                <div class="modal-body text-center py-4">
                    <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="icon mb-2 text-green icon-lg">
                        <path d="M12 12m-9 0a9 9 0 1 0 18 0a9 9 0 1 0 -18 0"/>
                        <path d="M9 12l2 2l4 -4"/>
                    </svg>
                    <h3>{{ __('labels.complete') }} {{ __('labels.ad_wallet_topup') }}</h3>
                    <div class="text-secondary">{{ __('labels.confirm_manual_deposit_message') }}</div>
                    <div class="mt-3">
                        <div class="text-muted">{{ __('labels.seller') }}: <span id="approve-deposit-user"></span></div>
                        <div class="text-muted">{{ __('labels.amount') }}: <span id="approve-deposit-amount"></span></div>
                    </div>
                </div>
                <div class="modal-footer">
                    <div class="w-100">
                        <div class="row">
                            <div class="col">
                                <button class="btn btn-outline-secondary w-100" data-bs-dismiss="modal">{{ __('labels.cancel') }}</button>
                            </div>
                            <div class="col">
                                <button class="btn btn-success w-100" type="button" onclick="window.submitAdTopupAction('approve')">{{ __('labels.complete') }}</button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="modal modal-blur fade" id="rejectDepositModal" tabindex="-1" role="dialog" aria-hidden="true">
        <div class="modal-dialog modal-sm modal-dialog-centered" role="document">
            <div class="modal-content">
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                <div class="modal-status bg-danger"></div>
                <div class="modal-body text-center py-4">
                    <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="icon mb-2 text-danger icon-lg">
                        <path d="M12 9v4"/>
                        <path d="M10.363 3.591l-8.106 13.534a1.914 1.914 0 0 0 1.636 2.871h16.214a1.914 1.914 0 0 0 1.636 -2.87l-8.106 -13.536a1.914 1.914 0 0 0 -3.274 0z"/>
                        <path d="M12 16h.01"/>
                    </svg>
                    <h3>{{ __('labels.fail') }} {{ __('labels.ad_wallet_topup') }}</h3>
                    <div class="text-secondary">{{ __('labels.confirm_manual_deposit_message') }}</div>
                    <div class="mt-3">
                        <div class="text-muted">{{ __('labels.seller') }}: <span id="reject-deposit-user"></span></div>
                        <div class="text-muted">{{ __('labels.amount') }}: <span id="reject-deposit-amount"></span></div>
                    </div>
                </div>
                <div class="modal-footer">
                    <div class="w-100">
                        <div class="row">
                            <div class="col">
                                <button class="btn btn-outline-secondary w-100" data-bs-dismiss="modal">{{ __('labels.cancel') }}</button>
                            </div>
                            <div class="col">
                                <button class="btn btn-danger w-100" type="button" onclick="window.submitAdTopupAction('reject')">{{ __('labels.fail') }}</button>
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
        window.adTopupConfig = {
            processBaseUrl: @json(url('admin/ads/campaigns/topups')),
            csrfToken: @json(csrf_token()),
            initialSeller: @json($selectedSeller ? [
                'value' => (string) $selectedSeller->id,
                'text' => $selectedSeller->user?->name ?? '',
            ] : null),
        };
    </script>
    <script src="{{ hyperAsset('assets/js/admin-ad-topup-shared.js') }}" defer></script>
    <script src="{{ hyperAsset('assets/js/admin-ad-topup-actions.js') }}" defer></script>
    <script src="{{ hyperAsset('assets/js/admin-ad-topup-transactions.js') }}" defer></script>
@endpush
