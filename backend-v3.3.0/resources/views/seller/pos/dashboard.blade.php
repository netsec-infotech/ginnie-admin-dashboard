@extends('layouts.seller.app', [
    'page' => $menuSeller['pos']['active'] ?? '',
    'sub_page' => $menuSeller['pos']['route']['pos_dashboard']['sub_active'] ?? '',
])
@section('title', __('labels.pos_dashboard'))

@push('styles')
    <link href="{{ hyperAsset('assets/css/pos-dashboard.css') }}" rel="stylesheet">
@endpush

@section('header_data')
    @php
        $page_title = __('labels.pos_dashboard');
        $page_pretitle = __('labels.seller') . " " . __('labels.pos_dashboard');
    @endphp
@endsection

@php
    $breadcrumbs = [
        ['title' => __('labels.home'), 'url' => route('seller.dashboard')],
        ['title' => __('labels.pos_dashboard'), 'url' => '']
    ];
@endphp

@section('seller-content')
    @if($viewPermission ?? false)
        {{-- ── FILTERS ── --}}
        <div class="row mb-3">
            <div class="col-12">
                <div class="card">
                    <div class="card-body py-2">
                        <div class="row g-2 align-items-center">
                            <div class="col-auto">
                                <select class="form-select" id="posStoreFilter">
                                    <option value="">{{ __('labels.all_stores') }}</option>
                                    @foreach($stores as $store)
                                        <option value="{{ $store->id }}" @selected($selectedStoreId == $store->id)>{{ $store->name }}</option>
                                    @endforeach
                                </select>
                            </div>
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
                                <a href="{{ route('seller.pos.index') }}" class="btn btn-outline-primary">
                                    <i class="ti ti-devices-pc me-1"></i>{{ __('labels.open_pos') }}
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
                        <div class="h1 mb-0" id="kpiRevenue">{{ $salesSummary['formatted_revenue_display'] ?? $salesSummary['formatted_revenue'] }}</div>
                    </div>
                </div>
            </div>
            <div class="col-sm-6 col-xl">
                <div class="card">
                    <div class="card-body">
                        <div class="subheader text-secondary mb-2">{{ __('labels.total_earnings') }}</div>
                        <div class="h1 mb-0" id="kpiEarnings">{{ $salesSummary['formatted_earnings_display'] ?? $salesSummary['formatted_earnings'] }}</div>
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
                        <div class="subheader text-secondary mb-2">{{ __('labels.pos_refunds') }}</div>
                        <div class="h1 mb-0" id="kpiRefundTotal">{{ $refundSummary['formatted_refund_total_display'] ?? $refundSummary['formatted_refund_total'] }}</div>
                        <div class="text-secondary small" id="kpiRefundRate">
                            {{ $refundSummary['refund_count'] }} {{ __('labels.refunds') }}
                            ({{ $refundSummary['refund_rate'] }}%)
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-sm-6 col-xl">
                <div class="card">
                    <div class="card-body">
                        <div class="subheader text-secondary mb-2">{{ __('labels.pos_parked_sales') }}</div>
                        <div class="h1 mb-0" id="kpiParkedCount">{{ $parkedSales['count'] }}</div>
                        <div class="text-secondary small" id="kpiParkedTotal">
                            {{ __('labels.worth') }} {{ $parkedSales['formatted_total'] }}
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

        {{-- ── CHARTS ROW: Store Revenue + Customer Split ── --}}
        <div class="row row-deck row-cards mb-3">
            <div class="col-lg-6">
                <div class="card">
                    <div class="card-header">
                        <h3 class="card-title">{{ __('labels.pos_store_revenue') }}</h3>
                    </div>
                    <div class="card-body">
                        <div id="chart-pos-store-revenue"></div>
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
                                <div>
                                    <div class="d-flex align-items-center mb-1">
                                        <span class="legend-dot bg-green me-2"></span>
                                        <span>{{ __('labels.pos_unique_customers') }}</span>
                                        <span class="ms-auto fw-bold" id="kpiUnique">{{ $customerBreakdown['unique_customers'] }}</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        {{-- ── TOP PRODUCTS + DISCOUNTS ── --}}
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
                        <h3 class="card-title">{{ __('labels.pos_discounts_promos') }}</h3>
                    </div>
                    <div class="card-body">
                        <div class="mb-3">
                            <div class="d-flex justify-content-between mb-1">
                                <span class="text-secondary">{{ __('labels.pos_promo_codes_used') }}</span>
                                <span class="fw-bold" id="kpiPromoOrders">{{ $discountSummary['promo_orders'] }}</span>
                            </div>
                            <div class="d-flex justify-content-between">
                                <span class="text-secondary">{{ __('labels.pos_promo_discount_total') }}</span>
                                <span class="fw-bold text-danger" id="kpiPromoTotal">-{{ $discountSummary['formatted_promo_total'] }}</span>
                            </div>
                        </div>
                        <hr>
                        <div class="mb-3">
                            <div class="d-flex justify-content-between mb-1">
                                <span class="text-secondary">{{ __('labels.pos_wallet_payments') }}</span>
                                <span class="fw-bold" id="kpiWalletOrders">{{ $discountSummary['wallet_orders'] }}</span>
                            </div>
                            <div class="d-flex justify-content-between">
                                <span class="text-secondary">{{ __('labels.pos_wallet_amount') }}</span>
                                <span class="fw-bold" id="kpiWalletTotal">{{ $discountSummary['formatted_wallet_total'] }}</span>
                            </div>
                        </div>
                        <hr>
                        <div>
                            <div class="d-flex justify-content-between">
                                <span class="text-secondary">{{ __('labels.pos_cashier_discounts') }}</span>
                                <span class="fw-bold text-danger" id="kpiCashierDiscount">-{{ $discountSummary['formatted_cashier_discount'] }}</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        {{-- ── LOW STOCK ALERTS ── --}}
        @if(count($lowStock) > 0)
            <div class="row row-cards mb-3">
                <div class="col-12">
                    <div class="card">
                        <div class="card-header">
                            <h3 class="card-title">
                                <i class="ti ti-alert-triangle text-warning me-1"></i>
                                {{ __('labels.pos_low_stock') }}
                            </h3>
                        </div>
                        <div class="table-responsive">
                            <table class="table table-vcenter card-table" id="lowStockTable">
                                <thead>
                                <tr>
                                    <th>{{ __('labels.product') }}</th>
                                    <th>{{ __('labels.variant') }}</th>
                                    <th>{{ __('labels.store') }}</th>
                                    <th class="text-end">{{ __('labels.stock') }}</th>
                                </tr>
                                </thead>
                                <tbody>
                                @foreach($lowStock as $item)
                                    <tr>
                                        <td>{{ $item['product'] }}</td>
                                        <td>{{ $item['variant'] ?: '—' }}</td>
                                        <td>{{ $item['store'] }}</td>
                                        <td class="text-end">
                                            <span class="badge {{ $item['stock'] <= 3 ? 'bg-danger-lt' : 'bg-warning-lt' }}">{{ $item['stock'] }}</span>
                                        </td>
                                    </tr>
                                @endforeach
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        @endif

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
            'storeRevenueSplit' => $storeRevenueSplit ?? [],
            'topProducts'       => $topProducts ?? [],
            'salesSummary'      => $salesSummary ?? [],
            'refundSummary'     => $refundSummary ?? [],
            'discountSummary'   => $discountSummary ?? [],
            'parkedSales'       => $parkedSales ?? [],
        ];
    @endphp
    <script>
        var posDashboardData = @json($__dashboardPayload);
        var posDashboardUrl = "{{ route('seller.pos.dashboard.data') }}";
        var posOrdersUrl = "{{ route('seller.orders.index', ['type' => 'pos']) }}";
    </script>
    <script src="{{ hyperAsset('assets/js/pos-dashboard.js') }}" defer></script>
@endpush
