@php use App\Enums\Wallet\WalletTransactionTypeEnum; @endphp
@extends('layouts.seller.app', ['page' => $menuSeller['ad_wallet']['active'] ?? 'ad_wallet', 'sub_page' => $menuSeller['ad_wallet']['route']['transactions']['sub_active'] ?? 'ad_wallet_transactions'])

@section('title', __('labels.transaction_history'))

@section('header_data')
    @php
        $page_title = __('labels.transaction_history');
        $page_pretitle = __('labels.seller') . " " . __('labels.ad_wallet');
    @endphp
@endsection

@php
    $breadcrumbs = [
        ['title' => __('labels.home'), 'url' => route('seller.dashboard')],
        ['title' => __('labels.ad_wallet'), 'url' => route('seller.ads.wallet.index')],
        ['title' => __('labels.transaction_history'), 'url' => '']
    ];
@endphp

@section('seller-content')
    <div class="page-wrapper">
        <div class="page-body">
            <div class="row row-cards">
                <div class="col-12">
                    <div class="card">
                        <div class="card-header">
                            <h3 class="card-title">{{ __('labels.transaction_history') }}</h3>
                            <div class="card-actions">
                                <div class="row g-2">
                                    <div class="col-auto">
                                        <select class="form-select text-capitalize" id="typeFilter">
                                            <option value="">{{ __('labels.transaction_type') }}</option>
                                            @foreach(WalletTransactionTypeEnum::values() as $value)
                                                <option value="{{ $value }}">{{ Str::replace('_', ' ', $value) }}</option>
                                            @endforeach
                                        </select>
                                    </div>
                                    <div class="col-auto">
                                        <a href="{{ route('seller.ads.wallet.index') }}" class="btn btn-outline-primary">
                                            {{ __('labels.back_to_wallet') }}
                                        </a>
                                    </div>
                                    <div class="col-auto">
                                        <button class="btn btn-outline-primary" id="refresh">
                                            {{ __('labels.refresh') }}
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="card-body">
                            <div class="row w-full p-3">
                                <x-datatable id="ad-wallet-transactions-table" :columns="$columns"
                                             route="{{ route('seller.ads.wallet.transactions.datatable') }}"
                                             :options="['order' => [[0, 'desc']], 'pageLength' => 10]"/>
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
        $(document).ready(function () {
            let table = $('#ad-wallet-transactions-table');

            $('#typeFilter').on('change', function () {
                if ($.fn.DataTable.isDataTable(table)) {
                    table.DataTable().ajax.reload(null, false);
                }
            });

            table.on('preXhr.dt', function (e, settings, data) {
                data.transaction_type = $('#typeFilter').val();
            });
        });
    </script>
@endpush
