@extends('layouts.seller.app', ['page' => $menuSeller['ad_wallet']['active'] ?? 'ad_wallet', 'sub_page' => $menuSeller['ad_wallet']['route']['balance']['sub_active'] ?? 'ad_wallet_balance'])

@section('title', __('labels.ad_wallet_balance'))

@section('header_data')
    @php
        $page_title = __('labels.ad_wallet_balance');
        $page_pretitle = __('labels.seller') . " " . __('labels.ad_wallet');
    @endphp
@endsection

@php
    $breadcrumbs = [
        ['title' => __('labels.home'), 'url' => route('seller.dashboard')],
        ['title' => __('labels.ad_wallet'), 'url' => '']
    ];
@endphp

@section('seller-content')
    <div class="page-wrapper">
        <div class="page-body">
            <div class="row row-cards">
                <div class="col-12">
                    @if(! ($featureEnabled ?? false))
                        <div class="alert alert-warning">
                            <h4 class="alert-title">{{ __('labels.ad_feature_disabled') }}</h4>
                            <div class="text-muted">{{ __('labels.ad_feature_disabled_hint') }}</div>
                        </div>
                    @endif

                    <div class="card">
                        <div class="card-header">
                            <h3 class="card-title">{{ __('labels.ad_wallet_balance') }}</h3>
                            <div class="card-actions">
                                <div class="row g-2">
                                    <div class="col-auto">
                                        <a href="{{ route('seller.ads.wallet.transactions') }}"
                                           class="btn btn-outline-primary">
                                            {{ __('labels.transaction_history') }}
                                        </a>
                                    </div>
                                    @if(($topupPermission ?? false) && ($featureEnabled ?? false))
                                        <div class="col-auto">
                                            <a href="{{ route('seller.ads.wallet.topup') }}"
                                               class="btn btn-primary">
                                                {{ __('labels.ad_wallet_topup') }}
                                            </a>
                                        </div>
                                    @endif
                                </div>
                            </div>
                        </div>
                        <div class="card-body">
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="card">
                                        <div class="card-body p-4 text-center">
                                            <h3 class="m-0 mb-1">{{ __('labels.current_balance') }}</h3>
                                            <div class="text-muted">{{ __('labels.ad_wallet_available_for_spend') }}</div>
                                            <div class="display-5 fw-bold my-3">
                                                {{$systemSettings['currencySymbol'] ?? ''}}{{ $wallet ? number_format($wallet->balance, 2) : '0.00' }}
                                            </div>
                                            @if(! empty($settings['cpcRate']))
                                                <div class="text-muted">
                                                    {{ __('labels.ad_wallet_estimated_clicks', [
                                                        'count' => $wallet ? (int) floor($wallet->balance / $settings['cpcRate']) : 0,
                                                    ]) }}
                                                </div>
                                            @endif
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="card">
                                        <div class="card-header">
                                            <h3 class="card-title">{{ __('labels.wallet_information') }}</h3>
                                        </div>
                                        <div class="card-body">
                                            <div class="table-responsive">
                                                <table class="table mb-0" style="border: none;">
                                                    <tbody>
                                                    <tr>
                                                        <td class="fw-bold border-0" style="width: 180px;">{{ __('labels.wallet_id') }}</td>
                                                        <td class="border-0">{{ $wallet ? $wallet->id : 'N/A' }}</td>
                                                    </tr>
                                                    <tr>
                                                        <td class="fw-bold border-0">{{ __('labels.currency') }}</td>
                                                        <td class="border-0">{{ $systemSettings['currency'] ?? ($wallet->currency_code ?? '') }}</td>
                                                    </tr>
                                                    @if(! empty($settings['cpcRate']))
                                                        <tr>
                                                            <td class="fw-bold border-0">{{ __('labels.ad_cpc_rate') }}</td>
                                                            <td class="border-0">{{$systemSettings['currencySymbol'] ?? ''}}{{ number_format((float) $settings['cpcRate'], 4) }}</td>
                                                        </tr>
                                                    @endif
                                                    @if(! empty($settings['walletMinTopup']))
                                                        <tr>
                                                            <td class="fw-bold border-0">{{ __('labels.ad_wallet_min_topup') }}</td>
                                                            <td class="border-0">{{$systemSettings['currencySymbol'] ?? ''}}{{ number_format((float) $settings['walletMinTopup'], 2) }}</td>
                                                        </tr>
                                                    @endif
                                                    <tr>
                                                        <td class="fw-bold border-0">{{ __('labels.last_updated') }}</td>
                                                        <td class="border-0">{{ $wallet ? $wallet->updated_at->format('d M Y, H:i') : 'N/A' }}</td>
                                                    </tr>
                                                    </tbody>
                                                </table>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection
