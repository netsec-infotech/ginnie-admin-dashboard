@php use App\Models\Setting;use Illuminate\Support\Str; @endphp
@extends('layouts.seller.app', ['page' => $menuSeller['ad_wallet']['active'] ?? 'ad_wallet', 'sub_page' => $menuSeller['ad_wallet']['route']['balance']['sub_active'] ?? 'ad_wallet_balance'])

@section('title', __('labels.ad_wallet_topup'))

@section('header_data')
    @php
        $page_title = __('labels.ad_wallet_topup');
        $page_pretitle = __('labels.seller') . " " . __('labels.ad_wallet');
    @endphp
@endsection

@php
    $breadcrumbs = [
        ['title' => __('labels.home'), 'url' => route('seller.dashboard')],
        ['title' => __('labels.ad_wallet'), 'url' => route('seller.ads.wallet.index')],
        ['title' => __('labels.ad_wallet_topup'), 'url' => '']
    ];
@endphp

@section('seller-content')
    <div class="page-wrapper">
        <div class="page-body">
            <div class="container-xl">
                <div class="row row-cards">
                    <div class="col-md-6">
                        {{-- Gateway top-up --}}
                        <div class="card">
                            <div class="card-header">
                                <h3 class="card-title">{{ __('labels.ad_wallet_topup_via_gateway') }}</h3>
                            </div>
                            <form action="{{ route('seller.ads.wallet.topup.gateway') }}" method="post"
                                  class="form-submit">
                                @csrf
                                <div class="card-body">
                                    <div class="mb-3">
                                        <label class="form-label required">
                                            {{ __('labels.amount') }}
                                            <x-form-info-tooltip :title="__('labels.ad_topup_amount_gateway_tooltip')"/>
                                        </label>
                                        <input type="number" step="0.01"
                                               min="{{ $settings['walletMinTopup'] ?? '0.01' }}"
                                               class="form-control" name="amount"
                                               placeholder="{{ $settings['walletMinTopup'] ?? '' }}">
                                        @if(! empty($settings['walletMinTopup']))
                                            <small
                                                class="form-hint">{{ __('labels.ad_minimum_topup_required', ['min' => $settings['walletMinTopup']]) }}</small>
                                        @endif
                                    </div>
                                    <div class="mb-3">
                                        <label class="form-label required">
                                            {{ __('labels.payment_method') }}
                                            <x-form-info-tooltip :title="__('labels.ad_topup_payment_method_tooltip')"/>
                                        </label>
                                        <select name="payment_method" class="form-select text-capitalize" required>
                                            @foreach(Setting::getEnabledPaymentGateway(onlyGateway: true) as $value)
                                                <option
                                                    value="{{$value}}">{{Str::replace("Payment", "",$value)}}</option>
                                            @endforeach
                                        </select>
                                    </div>
                                    <div class="mb-3">
                                        <label class="form-label">
                                            {{ __('labels.description') }}
                                            <x-form-info-tooltip :title="__('labels.ad_topup_description_tooltip')"/>
                                        </label>
                                        <input type="text" maxlength="255" class="form-control" name="description">
                                    </div>
                                </div>
                                <div class="card-footer text-end">
                                    <button type="submit"
                                            class="btn btn-primary">{{ __('labels.proceed_to_pay') }}</button>
                                </div>
                            </form>
                        </div>
                    </div>

                    <div class="col-md-6">
                        {{-- Earnings transfer --}}
                        <div class="card">
                            <div class="card-header">
                                <h3 class="card-title">{{ __('labels.ad_wallet_topup_from_earnings') }}</h3>
                                @if($earningsWallet)
                                    <div class="card-subtitle">
                                        {{ __('labels.earnings_balance') }}:
                                        <strong>{{$systemSettings['currencySymbol'] ?? ''}}{{ number_format($earningsWallet->balance, 2) }}</strong>
                                    </div>
                                @endif
                            </div>
                            <form action="{{ route('seller.ads.wallet.topup.earnings') }}" method="post"
                                  class="form-submit"
                                  data-redirect="{{ route('seller.ads.wallet.index') }}">
                                @csrf
                                <div class="card-body">
                                    <div class="mb-3">
                                        <label class="form-label required">
                                            {{ __('labels.amount') }}
                                            <x-form-info-tooltip
                                                :title="__('labels.ad_topup_amount_earnings_tooltip')"/>
                                        </label>
                                        <input type="number" step="0.01"
                                               min="{{ $settings['walletMinTopup'] ?? '0.01' }}"
                                               max="{{ $earningsWallet?->balance ?? '' }}"
                                               class="form-control" name="amount">
                                        @if(! empty($settings['walletMinTopup']))
                                            <small
                                                class="form-hint">{{ __('labels.ad_minimum_topup_required', ['min' => $settings['walletMinTopup']]) }}</small>
                                        @endif
                                    </div>
                                    <div class="mb-3">
                                        <label class="form-label">
                                            {{ __('labels.description') }}
                                            <x-form-info-tooltip :title="__('labels.ad_topup_description_tooltip')"/>
                                        </label>
                                        <input type="text" maxlength="255" class="form-control" name="description">
                                    </div>
                                </div>
                                <div class="card-footer text-end">
                                    <button type="submit" class="btn btn-primary"
                                            @if(! $earningsWallet || $earningsWallet->balance <= 0) disabled @endif>
                                        {{ __('labels.transfer_to_ad_wallet') }}
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection
