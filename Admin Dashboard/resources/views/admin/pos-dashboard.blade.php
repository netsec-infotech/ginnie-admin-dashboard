@extends('layouts.admin.app', ['page' => $menuAdmin['pos-dashboard']['active'] ?? ''])

@section('title', __('labels.pos_dashboard'))

@push('styles')
    <link href="{{ hyperAsset('assets/css/pos-dashboard.css') }}" rel="stylesheet">
@endpush

@section('header_data')
    @php
        $page_title = __('labels.pos_dashboard');
        $page_pretitle = __('labels.admin') . ' ' . __('labels.pos_dashboard');
    @endphp
@endsection

@php
    $breadcrumbs = [
        ['title' => __('labels.home'), 'url' => route('admin.dashboard')],
        ['title' => __('labels.pos_dashboard'), 'url' => '']
    ];
@endphp

@section('admin-content')
    @if($viewPermission ?? false)
        {{-- ── FILTERS ── --}}
        <div class="row mb-3">
            <div class="col-12">
                <div class="card">
                    <div class="card-body py-2">
                        <div class="row g-2 align-items-center">
                            <div class="col-auto">
                                <select class="form-select" id="posRangeFilter">
                                    <option value="today" @selected($dateRange === 'today')>{{ __('labels.today') }}</option>
                                    <option value="7days" @selected($dateRange === '7days')>{{ __('labels.last_7_days') }}</option>
                                    <option value="30days" @selected($dateRange === '30days')>{{ __('labels.last_30_days') }}</option>
                                    <option value="custom" @selected($dateRange === 'custom')>{{ __('labels.custom_range') }}</option>
                                </select>
                            </div>
                            <div class="col-auto pos-dashboard-custom-date" id="customDateGroup" style="display: {{ $dateRange === 'custom' ? 'flex' : 'none' }};">
                                <input type="date" class="form-control" id="posDateFrom" value="{{ $dateFrom }}">
                                <input type="date" class="form-control" id="posDateTo" value="{{ $dateTo }}">
                                <button class="btn btn-primary btn-icon" id="posApplyCustom" title="{{ __('labels.apply') }}">
                                    <i class="ti ti-check"></i>
                                </button>
                            </div>
                            <div class="col-auto ms-auto">
                                <a href="{{ route('admin.orders.index', ['type' => 'pos']) }}" class="btn btn-outline-primary">
                                    <i class="ti ti-list me-1"></i>{{ __('labels.pos_orders') }}
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        {{-- ── KPI STAT CARDS ── --}}
        <div class="row row-deck row-cards mb-3">
            <div class="col-sm-6 col-xl">
                <div class="card">
                    <div class="card-body">
                        <div class="d-flex align-items-center mb-2">
                            <div class="subheader text-secondary">{{ __('labels.total_revenue') }}</div>
                            <span class="ms-auto badge bg-green-lt" id="kpiOrderCount">
                                {{ $salesSummary['total_orders'] }} {{ __('labels.orders') }}
                            </span>
                        </div>
                        <div class="h1 mb-0" id="kpiRevenue">{{ $salesSummary['formatted_revenue'] }}</div>
                    </div>
                </div>
            </div>
            <div class="col-sm-6 col-xl">
                <div class="card">
                    <div class="card-body">
                        <div class="subheader text-secondary mb-2">{{ __('labels.total_earnings') }}</div>
                        <div class="h1 mb-0" id="kpiEarnings">{{ $salesSummary['formatted_earnings'] }}</div>
                    </div>
                </div>
            </div>
            <div class="col-sm-6 col-xl">
                <div class="card">
                    <div class="card-body">
                        <div class="subheader text-secondary mb-2">{{ __('labels.avg_order_value') }}</div>
                        <div class="h1 mb-0" id="kpiAvgValue">{{ $salesSummary['formatted_avg_value'] }}</div>
                    </div>
                </div>
            </div>
            <div class="col-sm-6 col-xl">
                <div class="card">
                    <div class="card-body">
                        <div class="subheader text-secondary mb-2">{{ __('labels.admin_pos_active_sellers') }}</div>
                        <div class="h1 mb-0" id="kpiActiveSellers">{{ $salesSummary['active_sellers'] }}</div>
                    </div>
                </div>
            </div>
            <div class="col-sm-6 col-xl">
                <div class="card">
                    <div class="card-body">
                        <div class="subheader text-secondary mb-2">{{ __('labels.pos_refunds') }}</div>
                        <div class="h1 mb-0" id="kpiRefundTotal">{{ $refundSummary['formatted_refund_total'] }}</div>
                        <div class="text-secondary small" id="kpiRefundRate">
                            {{ $refundSummary['refund_count'] }} {{ __('labels.refunds') }}
                            ({{ $refundSummary['refund_rate'] }}%)
                        </div>
                    </div>
                </div>
            </div>
        </div>

        {{-- ── CHARTS ROW: Sales Trend + Payment Breakdown ── --}}
        <div class="row row-deck row-cards mb-3">
            <div class="col-lg-8">
                <div class="card">
                    <div class="card-header">
                        <h3 class="card-title">{{ __('labels.pos_sales_trend') }}</h3>
                    </div>
                    <div class="card-body">
                        <div id="chart-pos-sales-trend"></div>
                    </div>
                </div>
            </div>
            <div class="col-lg-4">
                <div class="card">
                    <div class="card-header">
                        <h3 class="card-title">{{ __('labels.pos_payment_methods') }}</h3>
                    </div>
                    <div class="card-body">
                        <div id="chart-pos-payment-donut"></div>
                    </div>
                </div>
            </div>
        </div>

        {{-- ── CHARTS ROW: Top Sellers + Customer Breakdown ── --}}
        <div class="row row-deck row-cards mb-3">
            <div class="col-lg-6">
                <div class="card">
                    <div class="card-header">
                        <h3 class="card-title">{{ __('labels.admin_pos_top_sellers') }}</h3>
                    </div>
                    <div class="card-body">
                        <div id="chart-pos-top-sellers"></div>
                    </div>
                </div>
            </div>
            <div class="col-lg-6">
                <div class="card">
                    <div class="card-header">
                        <h3 class="card-title">{{ __('labels.pos_customer_breakdown') }}</h3>
                    </div>
                    <div class="card-body">
                        <div class="row align-items-center">
                            <div class="col-auto">
                                <div id="chart-pos-customer-donut"></div>
                            </div>
                            <div class="col">
                                <div class="mb-3">
                                    <div class="d-flex align-items-center mb-1">
                                        <span class="legend-dot bg-primary me-2"></span>
                                        <span>{{ __('labels.pos_registered_customers') }}</span>
                                        <span class="ms-auto fw-bold" id="kpiRegistered">{{ $customerBreakdown['registered_count'] }}</span>
                                    </div>
                                    <div class="text-secondary small" id="kpiRegisteredPct">{{ $customerBreakdown['registered_pct'] }}%</div>
                                </div>
                                <div class="mb-3">
                                    <div class="d-flex align-items-center mb-1">
                                        <span class="legend-dot bg-cyan me-2"></span>
                                        <span>{{ __('labels.pos_walkin_customers') }}</span>
                                        <span class="ms-auto fw-bold" id="kpiWalkin">{{ $customerBreakdown['walkin_count'] }}</span>
                                    </div>
                                    <div class="text-secondary small" id="kpiWalkinPct">{{ $customerBreakdown['walkin_pct'] }}%</div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        {{-- ── TOP PRODUCTS + SELLER ADOPTION ── --}}
        <div class="row row-deck row-cards mb-3">
            <div class="col-lg-7">
                <div class="card">
                    <div class="card-header">
                        <h3 class="card-title">{{ __('labels.pos_top_products') }}</h3>
                    </div>
                    <div class="table-responsive">
                        <table class="table table-vcenter card-table" id="topProductsTable">
                            <thead>
                            <tr>
                                <th>{{ __('labels.product') }}</th>
                                <th class="text-end">{{ __('labels.quantity') }}</th>
                                <th class="text-end">{{ __('labels.revenue') }}</th>
                            </tr>
                            </thead>
                            <tbody>
                            @forelse($topProducts as $product)
                                <tr>
                                    <td>{{ $product['title'] }}</td>
                                    <td class="text-end">{{ $product['qty_sold'] }}</td>
                                    <td class="text-end">{{ $product['formatted_revenue'] }}</td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="3" class="text-center text-secondary">{{ __('labels.no_data') }}</td>
                                </tr>
                            @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
            <div class="col-lg-5">
                <div class="card">
                    <div class="card-header">
                        <h3 class="card-title">
                            {{ __('labels.admin_pos_seller_adoption') }}
                            <x-form-info-tooltip :title="__('labels.admin_pos_seller_adoption_tooltip')" />
                        </h3>
                    </div>
                    <div class="card-body">
                        <div class="mb-3">
                            <div class="d-flex justify-content-between mb-1">
                                <span class="text-secondary">
                                    {{ __('labels.admin_pos_total_sellers') }}
                                    <x-form-info-tooltip :title="__('labels.admin_pos_total_sellers_tooltip')" />
                                </span>
                                <span class="fw-bold" id="kpiTotalSellers">{{ $sellerAdoption['total_sellers'] }}</span>
                            </div>
                        </div>
                        <div class="mb-3">
                            <div class="d-flex justify-content-between mb-1">
                                <span class="text-secondary">
                                    {{ __('labels.admin_pos_sellers_with_orders') }}
                                    <x-form-info-tooltip :title="__('labels.admin_pos_sellers_with_orders_tooltip')" />
                                </span>
                                <span class="fw-bold" id="kpiSellersWithOrders">{{ $sellerAdoption['sellers_with_pos_orders'] }}</span>
                            </div>
                        </div>
                        <hr>
                        <div>
                            <div class="d-flex justify-content-between">
                                <span class="text-secondary">
                                    {{ __('labels.admin_pos_adoption_rate') }}
                                    <x-form-info-tooltip :title="__('labels.admin_pos_adoption_rate_tooltip')" />
                                </span>
                                <span class="fw-bold text-primary" id="kpiAdoptionPct">{{ $sellerAdoption['adoption_pct'] }}%</span>
                            </div>
                             <div class="progress mt-2 pos-progress-height">
                                <div class="progress-bar bg-primary" id="kpiAdoptionBar" style="width: {{ $sellerAdoption['adoption_pct'] }}%"></div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

    @else
        <div class="row">
            <div class="col-12">
                <div class="card">
                    <div class="card-body text-center py-5">
                        <i class="ti ti-lock fs-1 text-secondary"></i>
                        <p class="text-secondary mt-2">{{ __('labels.no_permission') }}</p>
                    </div>
                </div>
            </div>
        </div>
    @endif
@endsection

@push('scripts')
    <script src="{{ asset('assets/vendor/apexcharts/dist/apexcharts.min.js') }}" defer></script>
    @php
        $__dashboardPayload = [
            'salesTrend'        => $salesTrend ?? [],
            'paymentBreakdown'  => $paymentBreakdown ?? [],
            'customerBreakdown' => $customerBreakdown ?? [],
            'topSellers'        => $topSellers ?? [],
            'topProducts'       => $topProducts ?? [],
            'salesSummary'      => $salesSummary ?? [],
            'refundSummary'     => $refundSummary ?? [],
            'sellerAdoption'    => $sellerAdoption ?? [],
        ];
    @endphp
    <script>
        var adminPosDashboardData = @json($__dashboardPayload);
        var adminPosDashboardUrl = "{{ route('admin.pos-dashboard.data') }}";
        var adminPosOrdersUrl = "{{ route('admin.orders.index', ['type' => 'pos']) }}";
    </script>
    <script src="{{ hyperAsset('assets/js/admin-pos-dashboard.js') }}" defer></script>
@endpush
