@extends('layouts.admin.app',['page' => $menuAdmin['ad_campaigns']['active'] ?? "", 'sub_page' => $menuAdmin['ad_campaigns']['route']['dashboard']['sub_active'] ?? "" ])

@section('title', __('labels.ad_campaign_dashboard'))

@section('header_data')
    @php
        $page_title    = __('labels.ad_campaign_dashboard');
        $page_pretitle = __('labels.ad_campaigns');
    @endphp
@endsection

@php
    $breadcrumbs = [
        ['title' => __('labels.home'),         'url' => route('admin.dashboard')],
        ['title' => __('labels.ad_campaigns'), 'url' => route('admin.ads.campaigns.index')],
        ['title' => __('labels.dashboard'),    'url' => null],
    ];
@endphp

@push('styles')
    <style>
        .stat-card .stat-value { font-size: 1.5rem; font-weight: 700; }
        .stat-card .stat-label { font-size: .8rem; color: var(--tblr-secondary); }
        .stat-card .stat-icon { width: 40px; height: 40px; display: flex; align-items: center; justify-content: center; border-radius: .5rem; }
        .period-btn.active { background: var(--tblr-primary); color: #fff; }
    </style>
@endpush

@section('admin-content')
    <div class="row row-deck row-cards" id="ad-dashboard">
        {{-- Period selector --}}
        <div class="col-12">
            <div class="card">
                <div class="card-header">
                    <div>
                        <h3 class="card-title">{{ __('labels.ad_campaign_dashboard') }}</h3>
                        <x-breadcrumb :items="$breadcrumbs"/>
                    </div>
                    <div class="card-actions">
                        <div class="btn-group" role="group" id="periodSelector">
                            <button type="button" class="btn btn-sm period-btn" data-days="1">{{ __('labels.today') }}</button>
                            <button type="button" class="btn btn-sm period-btn active" data-days="7">{{ __('labels.last_7_days') }}</button>
                            <button type="button" class="btn btn-sm period-btn" data-days="30">{{ __('labels.last_30_days') }}</button>
                            <button type="button" class="btn btn-sm period-btn" data-days="90">{{ __('labels.last_3_months') }}</button>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        {{-- Stat cards --}}
        <div class="col-sm-6 col-lg-3">
            <div class="card stat-card">
                <div class="card-body">
                    <div class="d-flex align-items-center">
                        <div class="stat-icon bg-primary-lt text-primary me-3">
                            <i class="ti ti-currency-dollar fs-2"></i>
                        </div>
                        <div>
                            <div class="stat-value" id="statSpent">{{ $data['stat_cards']['formatted_spent'] }}</div>
                            <div class="stat-label">{{ __('labels.ad_dashboard_total_spent') }}</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-sm-6 col-lg-3">
            <div class="card stat-card">
                <div class="card-body">
                    <div class="d-flex align-items-center">
                        <div class="stat-icon bg-success-lt text-success me-3">
                            <i class="ti ti-click fs-2"></i>
                        </div>
                        <div>
                            <div class="stat-value" id="statClicks">{{ number_format($data['stat_cards']['total_clicks']) }}</div>
                            <div class="stat-label">{{ __('labels.ad_campaign_clicks') }}</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-sm-6 col-lg-3">
            <div class="card stat-card">
                <div class="card-body">
                    <div class="d-flex align-items-center">
                        <div class="stat-icon bg-info-lt text-info me-3">
                            <i class="ti ti-eye fs-2"></i>
                        </div>
                        <div>
                            <div class="stat-value" id="statImpressions">{{ number_format($data['stat_cards']['total_impressions']) }}</div>
                            <div class="stat-label">{{ __('labels.ad_campaign_impressions') }}</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-sm-6 col-lg-3">
            <div class="card stat-card">
                <div class="card-body">
                    <div class="d-flex align-items-center">
                        <div class="stat-icon bg-warning-lt text-warning me-3">
                            <i class="ti ti-percentage fs-2"></i>
                        </div>
                        <div>
                            <div class="stat-value" id="statCtr">{{ $data['stat_cards']['ctr'] }}%</div>
                            <div class="stat-label">{{ __('labels.ad_dashboard_ctr') }}</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        {{-- Revenue trend area chart --}}
        <div class="col-lg-8">
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">{{ __('labels.ad_dashboard_spend_trend') }}</h3>
                </div>
                <div class="card-body">
                    <div id="spendTrendChart" style="height: 280px;"></div>
                </div>
            </div>
        </div>

        {{-- Campaign status donut --}}
        <div class="col-lg-4">
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">{{ __('labels.ad_dashboard_status_breakdown') }}</h3>
                </div>
                <div class="card-body">
                    <div id="statusDonutChart" style="height: 280px;"></div>
                </div>
            </div>
        </div>

        {{-- Clicks vs Impressions dual-line chart --}}
        <div class="col-lg-12">
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">{{ __('labels.ad_dashboard_clicks_vs_impressions') }}</h3>
                </div>
                <div class="card-body">
                    <div id="clicksImpressionsChart" style="height: 280px;"></div>
                </div>
            </div>
        </div>

        {{-- Top campaigns table --}}
        <div class="col-lg-12">
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">{{ __('labels.ad_dashboard_top_campaigns') }}</h3>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-vcenter card-table" id="topCampaignsTable">
                            <thead>
                                <tr>
                                    <th>{{ __('labels.ad_campaign_product') }}</th>
                                    <th>{{ __('labels.seller') }}</th>
                                    <th>{{ __('labels.ad_campaign_status') }}</th>
                                    <th>{{ __('labels.ad_campaign_budget_label') }}</th>
                                    <th>{{ __('labels.ad_dashboard_total_spent') }}</th>
                                    <th>{{ __('labels.ad_campaign_clicks') }}</th>
                                    <th>{{ __('labels.ad_campaign_impressions') }}</th>
                                    <th>{{ __('labels.ad_dashboard_ctr') }}</th>
                                    <th>{{ __('labels.ad_dashboard_progress') }}</th>
                                </tr>
                            </thead>
                            <tbody id="topCampaignsBody">
                                @foreach($data['top_campaigns'] as $campaign)
                                    <tr>
                                        <td>{{ $campaign['product_title'] }}</td>
                                        <td>{{ $campaign['seller_name'] }}</td>
                                        <td><span class="badge bg-{{ $campaign['status_class'] }}-lt">{{ $campaign['status'] }}</span></td>
                                        <td>{{ $campaign['formatted_budget'] }}</td>
                                        <td>{{ $campaign['formatted_spent'] }}</td>
                                        <td>{{ number_format($campaign['clicks']) }}</td>
                                        <td>{{ number_format($campaign['impressions']) }}</td>
                                        <td>{{ $campaign['ctr'] }}%</td>
                                        <td>
                                            <div class="progress progress-sm">
                                                <div class="progress-bar" style="width: {{ $campaign['progress'] }}%"></div>
                                            </div>
                                            <small class="text-muted">{{ $campaign['progress'] }}%</small>
                                        </td>
                                    </tr>
                                @endforeach
                                @if(empty($data['top_campaigns']))
                                    <tr>
                                        <td colspan="9" class="text-center text-muted py-4">{{ __('labels.no_data_available') }}</td>
                                    </tr>
                                @endif
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection

@push('scripts')
    <script src="https://cdn.jsdelivr.net/npm/apexcharts"></script>
    <script>
        window.adDashboardConfig = {
            dataUrl: '{{ route("admin.ads.campaigns.dashboard.data") }}',
            initialData: @json($data),
            currencySymbol: '{{ $systemSettings["currencySymbol"] ?? "$" }}',
        };
    </script>
    <script src="{{ hyperAsset('assets/js/ad-dashboard-admin.js') }}" defer></script>
@endpush
