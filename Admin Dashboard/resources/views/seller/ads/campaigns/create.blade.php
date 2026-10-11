@extends('layouts.seller.app', ['page' => $menuSeller['ad_campaigns']['active'] ?? 'ad_campaigns', 'sub_page' => $menuSeller['ad_campaigns']['route']['create']['sub_active'] ?? 'ad_campaigns_create'])

@section('title', __('labels.create_ad_campaign'))

@section('header_data')
    @php
        $page_title    = __('labels.create_ad_campaign');
        $page_pretitle = __('labels.ad_campaigns_subtitle');
    @endphp
@endsection

@php
    $breadcrumbs = [
        ['title' => __('labels.home'),         'url' => route('seller.dashboard')],
        ['title' => __('labels.ad_campaigns'), 'url' => route('seller.ads.campaigns.index')],
        ['title' => __('labels.create_ad_campaign'), 'url' => ''],
    ];

    $cpcRate      = (float) ($settings['cpcRate'] ?? 0);
    $minBudget    = max(1, (float) ($settings['walletMinTopup'] ?? 1));
    $currencySymbol = $systemSettings['currencySymbol'] ?? '';
@endphp

@section('seller-content')
<div class="page-wrapper">
    <div class="page-body">
        <div class="container-xl">
            <div class="row justify-content-center">
                <div class="col-lg-8 col-xl-7">

                    @unless($featureEnabled)
                        <div class="alert alert-warning mb-4">
                            <h4 class="alert-title">{{ __('labels.ad_feature_disabled') }}</h4>
                            <div class="text-muted">{{ __('labels.ad_feature_disabled_hint') }}</div>
                        </div>
                    @endunless

                    {{-- ── Insufficient balance banner ─────────────── --}}
                    <div class="alert alert-warning balance-warning mb-4" id="balanceWarning">
                        <div class="d-flex align-items-start gap-3">
                            <svg xmlns="http://www.w3.org/2000/svg" class="icon icon-tabler icon-tabler-alert-triangle flex-shrink-0 mt-1"
                                 width="22" height="22" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" fill="none">
                                <path stroke="none" d="M0 0h24v24H0z" fill="none"/>
                                <path d="M12 9v4m0 4h.01M10.363 3.591 2.257 17.125a1.914 1.914 0 0 0 1.636 2.871h16.214a1.914 1.914 0 0 0 1.636-2.871L13.637 3.591a1.914 1.914 0 0 0-3.274 0z"/>
                            </svg>
                            <div>
                                <h4 class="alert-title mb-1">{{ __('labels.ad_wallet_balance') }}: <span id="walletBalanceDisplay">{{ $currencySymbol }}{{ number_format($walletBalance, 2) }}</span></h4>
                                <p class="mb-2 text-muted" id="balanceWarningText">{{ __('labels.ad_insufficient_wallet_balance') }}</p>
                                <a href="{{ route('seller.ads.wallet.topup') }}" class="btn btn-sm btn-warning">
                                    {{ __('labels.ad_topup_wallet') }} →
                                </a>
                            </div>
                        </div>
                    </div>

                    {{-- ── Main form card ───────────────────────────── --}}
                    <div class="card">
                        <div class="card-header">
                            <h3 class="card-title">{{ __('labels.create_ad_campaign') }}</h3>
                        </div>

                        <form class="form-submit"
                              action="{{ route('seller.ads.campaigns.store') }}"
                              method="POST"
                              data-redirect="{{ route('seller.ads.campaigns.index') }}">
                            @csrf
                            <div class="card-body">

                                @if($products->isEmpty())
                                    <div class="alert alert-info">{{ __('labels.ad_campaign_no_products') }}</div>
                                @else

                                {{-- Product selector --}}
                                <div class="mb-4">
                                    <label class="form-label fw-semibold" for="product_id">
                                        {{ __('labels.ad_campaign_product') }}
                                        <x-form-info-tooltip :title="__('labels.ad_campaign_select_product')" />
                                    </label>
                                    <div class="row g-2" id="productCards">
                                        {{-- Rendered as select for simplicity; JS enhances with preview chip --}}
                                        <div class="col-12">
                                            <select name="product_id" id="select-product" class="form-select" required>
                                            </select>
                                        </div>
                                        {{-- Live ad preview chip --}}
                                        <div class="col-12" id="adPreviewWrap" style="display:none;">
                                            <div class="ad-preview-chip">
                                                <div class="avatar rounded" id="previewThumb"
                                                     style="background-color:#f1f3f5; width:40px; height:40px;"></div>
                                                <div>
                                                    <div class="fw-semibold small" id="previewName">—</div>
                                                    <span class="sponsored-badge">Sponsored</span>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                {{-- Budget slider --}}
                                <div class="mb-4">
                                    <label class="form-label fw-semibold" for="budgetRange">
                                        {{ __('labels.ad_budget') }}
                                        <x-form-info-tooltip :title="__('labels.ad_campaign_budget_hint')" />
                                    </label>

                                    {{-- Live value display --}}
                                    <div class="d-flex align-items-baseline gap-1 mb-2">
                                        <span class="fs-2 fw-bold text-primary" id="budgetDisplay">{{ $currencySymbol }}{{ number_format($minBudget, 0) }}</span>
                                        <span class="text-muted small">{{ $systemSettings['currency'] ?? '' }}</span>
                                    </div>

                                    <div class="budget-slider-wrap">
                                        <input type="range" class="budget-range" id="budgetRange"
                                               min="{{ $minBudget }}" max="5000" step="50"
                                               value="{{ $minBudget }}"
                                               data-cpc="{{ $cpcRate }}"
                                               data-wallet="{{ $walletBalance }}"
                                               data-symbol="{{ $currencySymbol }}"
                                               data-multiplier-min="{{ $impressionMultiplierMin }}"
                                               data-multiplier-max="{{ $impressionMultiplierMax }}"
                                               style="--pct: 0%">
                                        <input type="hidden" name="budget" id="budgetHidden" value="{{ $minBudget }}">
                                    </div>

                                    {{-- Preset chips --}}
                                    <div class="budget-presets" id="budgetPresets"
                                         data-symbol="{{ $currencySymbol }}"
                                         data-min="{{ $minBudget }}">
                                        @foreach([100, 500, 1000, 2000, 5000] as $preset)
                                            @if($preset >= $minBudget)
                                                <div class="budget-chip" data-value="{{ $preset }}">
                                                    {{ $currencySymbol }}{{ number_format($preset) }}
                                                </div>
                                            @endif
                                        @endforeach
                                    </div>
                                </div>

                                {{-- Estimated stats pills --}}
                                <div class="d-flex gap-3 mb-4">
                                    <div class="stat-pill">
                                        <div class="stat-value" id="estClicks">—</div>
                                        <div class="stat-label">{{ __('labels.ad_estimated_clicks') }}</div>
                                    </div>
                                    <div class="stat-pill">
                                        <div class="stat-value" id="estReach">—</div>
                                        <div class="stat-label">{{ __('labels.ad_estimated_reach') }}</div>
                                    </div>
                                </div>

                                @if($cpcRate <= 0)
                                    <div class="alert alert-warning">{{ __('labels.ad_cpc_rate_not_configured') }}</div>
                                @endif

                                @endif {{-- end products not empty --}}
                            </div>

                            @unless($products->isEmpty())
                            <div class="card-footer">
                                <div class="row">
                                    <div class="col">
                                        <a href="{{ route('seller.ads.campaigns.index') }}" class="btn btn-link">
                                            {{ __('labels.cancel') }}
                                        </a>
                                    </div>
                                    <div class="col-auto">
                                        <button type="submit" class="btn btn-primary" @unless($featureEnabled) disabled @endunless>
                                            {{ __('labels.ad_campaign_submitted_for_approval') === '' ? __('labels.submit') : __('labels.submit') }}
                                            <svg xmlns="http://www.w3.org/2000/svg" class="icon ms-1" width="16" height="16" viewBox="0 0 24 24"
                                                 stroke-width="2" stroke="currentColor" fill="none">
                                                <path stroke="none" d="M0 0h24v24H0z" fill="none"/>
                                                <path d="m9 6 6 6-6 6"/>
                                            </svg>
                                        </button>
                                    </div>
                                </div>
                            </div>
                            @endunless
                        </form>
                    </div>

                    {{-- ── What You Get strip ───────────────────────── --}}
                    <div class="benefit-strip">
                        <h4 class="mb-3 fw-bold" style="letter-spacing:-.01em;">✦ {{ __('labels.what_you_get') ?? 'What you get' }}</h4>
                        <div class="d-flex flex-column gap-3">

                            <div class="benefit-card benefit-search">
                                <div class="benefit-icon">🔍</div>
                                <div>
                                    <div class="benefit-title">{{ __('labels.ad_benefit_search_title') }}</div>
                                    <div class="benefit-body">{{ __('labels.ad_benefit_search_body') }}</div>
                                </div>
                            </div>

                            <div class="benefit-card benefit-related">
                                <div class="benefit-icon">🛍️</div>
                                <div>
                                    <div class="benefit-title">{{ __('labels.ad_benefit_related_title') }}</div>
                                    <div class="benefit-body">{{ __('labels.ad_benefit_related_body') }}</div>
                                </div>
                            </div>

                            <div class="benefit-card benefit-dash">
                                <div class="benefit-icon">📊</div>
                                <div>
                                    <div class="benefit-title">{{ __('labels.ad_benefit_dashboard_title') }}</div>
                                    <div class="benefit-body">{{ __('labels.ad_benefit_dashboard_body') }}</div>
                                </div>
                            </div>

                            <div class="benefit-card benefit-pause">
                                <div class="benefit-icon">⏸️</div>
                                <div>
                                    <div class="benefit-title">{{ __('labels.ad_benefit_pause_title') }}</div>
                                    <div class="benefit-body">{{ __('labels.ad_benefit_pause_body') }}</div>
                                </div>
                            </div>

                            <div class="benefit-card benefit-refund">
                                <div class="benefit-icon">💰</div>
                                <div>
                                    <div class="benefit-title">{{ __('labels.ad_benefit_refund_title') }}</div>
                                    <div class="benefit-body">{{ __('labels.ad_benefit_refund_body') }}</div>
                                </div>
                            </div>

                        </div>
                    </div>
                    {{-- end benefit strip --}}

                </div>
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
    <script src="{{ hyperAsset('assets/js/ad-campaigns.js') }}" defer></script>
@endpush
