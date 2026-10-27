@php
    use App\Enums\DateRangeFilterEnum;
    use App\Enums\Order\OrderItemStatusEnum;
    use Illuminate\Support\Str;
    $orderTypeParam = request()->query('type', '');
    $orderIndexQuery = request()->except('status_group');
    $orderInsightUrls = [
        'total' => route('seller.orders.index', $orderIndexQuery),
        'preparing' => route('seller.orders.index', [...$orderIndexQuery, 'status_group' => 'preparing']),
        'delivered' => route('seller.orders.index', [...$orderIndexQuery, 'status_group' => 'delivered']),
        'rejected' => route('seller.orders.index', [...$orderIndexQuery, 'status_group' => 'rejected']),
    ];
@endphp
@extends('layouts.seller.app', ['page' => $menuSeller['orders']['active'] ?? ''])

@section('title', __('labels.orders'))

@section('header_data')
    @php
        $page_title = __('labels.orders');
        $page_pretitle = __('labels.seller') . ' ' . __('labels.orders');
    @endphp
@endsection

@php
    $breadcrumbs = [
        ['title' => __('labels.home'), 'url' => route('seller.dashboard')],
        ['title' => __('labels.orders'), 'url' => ''],
    ];
@endphp

@section('seller-content')
    <div class="page-wrapper">
        <div class="page-body">
            <div class="row row-cards py-3">
                <div class="col-sm-6 col-lg-3">
                    <div class="card card-sm position-relative order-insight-card {{ $activeStatusGroup === null ? 'active' : '' }}"
                        data-status-group="">
                        <div class="card-body">
                            <div class="row align-items-center">
                                <div class="col-auto">
                                    <span class="bg-primary text-white avatar">
                                        <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24"
                                            viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
                                            stroke-linecap="round" stroke-linejoin="round" class="icon icon-1">
                                            <path d="M6 19m-2 0a2 2 0 1 0 4 0a2 2 0 1 0 -4 0" />
                                            <path d="M17 19m-2 0a2 2 0 1 0 4 0a2 2 0 1 0 -4 0" />
                                            <path d="M17 17h-11v-14h-2" />
                                            <path d="M6 5l14 1l-1 7h-13" />
                                        </svg>
                                    </span>
                                </div>
                                <div class="col">
                                    <div class="fw-bold fs-2">
                                        <span id="insight-total-orders">{{ $orderInsights['total'] ?? 0 }}</span>
                                    </div>
                                    <div class="text-secondary">{{ __('labels.total_orders') ?? 'Total Orders' }}</div>
                                </div>
                            </div>
                            <a href="{{ $orderInsightUrls['total'] }}" class="stretched-link order-insight-card-link"
                                aria-label="{{ __('labels.total_orders') ?? 'Total Orders' }}"
                                @if ($activeStatusGroup === null) aria-current="page" @endif></a>
                        </div>
                    </div>
                </div>
                <div class="col-sm-6 col-lg-3">
                    <div class="card card-sm position-relative order-insight-card {{ $activeStatusGroup === 'preparing' ? 'active' : '' }}"
                        data-status-group="preparing">
                        <div class="card-body">
                            <div class="row align-items-center">
                                <div class="col-auto">
                                    <span class="bg-warning text-white avatar">
                                        <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24"
                                            viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
                                            stroke-linecap="round" stroke-linejoin="round"
                                            class="icon icon-tabler icons-tabler-outline icon-tabler-clock-hour-10">
                                            <path stroke="none" d="M0 0h24v24H0z" fill="none" />
                                            <path d="M3 12a9 9 0 1 0 18 0a9 9 0 1 0 -18 0" />
                                            <path d="M12 12l-3 -2" />
                                            <path d="M12 7v5" />
                                        </svg>
                                    </span>
                                </div>
                                <div class="col">
                                    <div class="fw-bold fs-2">
                                        <span id="insight-preparing-orders">{{ $orderInsights['preparing'] ?? 0 }}</span>
                                    </div>
                                    <div class="text-secondary">
                                        {{ __('labels.orders_under_preparation') ?? 'Orders Under Preparation' }}</div>
                                </div>
                            </div>
                            <a href="{{ $orderInsightUrls['preparing'] }}" class="stretched-link order-insight-card-link"
                                aria-label="{{ __('labels.orders_under_preparation') ?? 'Orders Under Preparation' }}"
                                @if ($activeStatusGroup === 'preparing') aria-current="page" @endif></a>
                        </div>
                    </div>
                </div>
                <div class="col-sm-6 col-lg-3">
                    <div class="card card-sm position-relative order-insight-card {{ $activeStatusGroup === 'delivered' ? 'active' : '' }}"
                        data-status-group="delivered">
                        <div class="card-body">
                            <div class="row align-items-center">
                                <div class="col-auto">
                                    <span class="bg-success text-white avatar">
                                        <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24"
                                            viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
                                            stroke-linecap="round" stroke-linejoin="round"
                                            class="icon icon-tabler icons-tabler-outline icon-tabler-truck-delivery">
                                            <path stroke="none" d="M0 0h24v24H0z" fill="none" />
                                            <path d="M5 17a2 2 0 1 0 4 0a2 2 0 1 0 -4 0" />
                                            <path d="M15 17a2 2 0 1 0 4 0a2 2 0 1 0 -4 0" />
                                            <path d="M5 17h-2v-4m-1 -8h11v12m-4 0h6m4 0h2v-6h-8m0 -5h5l3 5" />
                                            <path d="M3 9l4 0" />
                                        </svg>
                                    </span>
                                </div>
                                <div class="col">
                                    <div class="fw-bold fs-2">
                                        <span id="insight-delivered-orders">{{ $orderInsights['delivered'] ?? 0 }}</span>
                                    </div>
                                    <div class="text-secondary">{{ __('labels.orders_delivered') ?? 'Orders Delivered' }}
                                    </div>
                                </div>
                            </div>
                            <a href="{{ $orderInsightUrls['delivered'] }}" class="stretched-link order-insight-card-link"
                                aria-label="{{ __('labels.orders_delivered') ?? 'Orders Delivered' }}"
                                @if ($activeStatusGroup === 'delivered') aria-current="page" @endif></a>
                        </div>
                    </div>
                </div>
                <div class="col-sm-6 col-lg-3">
                    <div class="card card-sm position-relative order-insight-card {{ $activeStatusGroup === 'rejected' ? 'active' : '' }}"
                        data-status-group="rejected">
                        <div class="card-body">
                            <div class="row align-items-center">
                                <div class="col-auto">
                                    <span class="bg-danger text-white avatar">
                                        <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24"
                                            viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
                                            stroke-linecap="round" stroke-linejoin="round"
                                            class="icon icon-tabler icons-tabler-outline icon-tabler-ban">
                                            <path stroke="none" d="M0 0h24v24H0z" fill="none" />
                                            <path d="M3 12a9 9 0 1 0 18 0a9 9 0 1 0 -18 0" />
                                            <path d="M5.7 5.7l12.6 12.6" />
                                        </svg>
                                    </span>
                                </div>
                                <div class="col">
                                    <div class="fw-bold fs-2">
                                        <span id="insight-rejected-orders">{{ $orderInsights['rejected'] ?? 0 }}</span>
                                    </div>
                                    <div class="text-secondary">{{ __('labels.orders_rejected') ?? 'Orders Rejected' }}
                                    </div>
                                </div>
                            </div>
                            <a href="{{ $orderInsightUrls['rejected'] }}" class="stretched-link order-insight-card-link"
                                aria-label="{{ __('labels.orders_rejected') ?? 'Orders Rejected' }}"
                                @if ($activeStatusGroup === 'rejected') aria-current="page" @endif></a>
                        </div>
                    </div>
                </div>
            </div>
            <div class="row row-cards">
                <div class="col-12">
                    <div class="card">
                        <div class="card-header">
                            @php
                                $sellerActionCount =
                                    \App\Helpers\SidebarBadgeHelper::getBadgeCounts('seller')['orders'] ?? 0;
                            @endphp
                            <h3 class="card-title">
                                {{ __('labels.order_items') }} <span class="order-count"></span>
                                @if ($sellerActionCount > 0)
                                    <span class="badge bg-danger text-white ms-2">
                                        <span
                                            class="status-dot status-dot-animated bg-white me-1"></span>{{ $sellerActionCount }}
                                        {{ __('labels.action_required') ?? 'Action Required' }}
                                    </span>
                                @endif
                            </h3>
                            <div class="card-actions">
                                <div class="row g-2">
                                    <div class="col-auto">
                                        <select class="form-select" id="orderTypeFilter">
                                            <option value="">{{ __('labels.order_type') }}</option>
                                            <option value="regular" @selected($orderTypeParam === 'regular')>
                                                {{ __('labels.regular_order') }}</option>
                                            <option value="pickup" @selected($orderTypeParam === 'pickup')>
                                                {{ __('labels.self_pickup') }}</option>
                                            <option value="pos" @selected($orderTypeParam === 'pos')>
                                                {{ __('labels.pos_order') }}</option>
                                        </select>
                                    </div>
                                    <div class="col-auto" style="min-width: 200px;">
                                        <select id="storeFilter" class="form-select"
                                            placeholder="{{ __('labels.all_stores') }}"></select>
                                    </div>
                                    <div class="col-auto">
                                        <select class="form-select text-capitalize" id="statusFilter">
                                            <option value="">{{ __('labels.status') }}</option>
                                            @foreach (OrderItemStatusEnum::values() as $value)
                                                <option value="{{ $value }}">{{ Str::replace('_', ' ', $value) }}
                                                </option>
                                            @endforeach
                                        </select>
                                    </div>
                                    <div class="col-auto">
                                        <select class="form-select" id="rangeFilter">
                                            <option value="">{{ __('labels.date_range') }}</option>
                                            @foreach (DateRangeFilterEnum::values() as $value)
                                                <option value="{{ $value }}">{{ Str::replace('_', ' ', $value) }}
                                                </option>
                                            @endforeach
                                        </select>
                                    </div>
                                    <div class="col-auto">
                                        <button class="btn btn-outline-primary" id="refresh">
                                            <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24"
                                                viewBox="0 0 24 24" fill="none" stroke="currentColor"
                                                stroke-width="2" stroke-linecap="round" stroke-linejoin="round"
                                                class="icon icon-tabler icons-tabler-outline icon-tabler-refresh">
                                                <path stroke="none" d="M0 0h24v24H0z" fill="none" />
                                                <path d="M20 11a8.1 8.1 0 0 0 -15.5 -2m-.5 -4v4h4" />
                                                <path d="M4 13a8.1 8.1 0 0 0 15.5 2m.5 4v-4h-4" />
                                            </svg>
                                            {{ __('labels.refresh') }}
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="card-body p-0">
                            <ul class="nav nav-tabs px-3 pt-3" role="tablist" id="orders-tab">
                                <li class="nav-item" role="presentation">
                                    <a href="#orders-pane" class="nav-link active" data-bs-toggle="tab" role="tab">
                                        <i class="ti ti-shopping-bag me-1"></i>{{ __('labels.orders_list') }}
                                    </a>
                                </li>
                                <li class="nav-item" role="presentation">
                                    <a href="#order-items-pane" class="nav-link" data-bs-toggle="tab" role="tab">
                                        <i class="ti ti-list me-1"></i>{{ __('labels.order_items_list') }}
                                    </a>
                                </li>
                            </ul>
                            <div class="tab-content p-3">
                                <div class="tab-pane fade show active" id="orders-pane" role="tabpanel">
                                    <x-datatable id="orders-list-table" :columns="$orderColumns"
                                        route="{{ route('seller.orders.list_datatable', array_filter(['order_type' => $orderTypeParam, 'status_group' => $activeStatusGroup])) }}"
                                        :options="['order' => [[3, 'desc']], 'pageLength' => 10]" />
                                    @php
                                        $orderItemsLabels = json_encode(
                                            [
                                                'loading' => __('labels.loading'),
                                                'error' => __('labels.failed_to_load_items'),
                                            ],
                                            JSON_UNESCAPED_UNICODE,
                                        );
                                    @endphp
                                    <div id="order-items-meta" hidden
                                        data-items-url="{{ route('seller.orders.list_items', ['id' => '__ID__']) }}"
                                        data-items-labels="{{ $orderItemsLabels }}"></div>
                                </div>
                                <div class="tab-pane fade" id="order-items-pane" role="tabpanel">
                                    <x-datatable id="orders-table" :columns="$columns"
                                        route="{{ route('seller.orders.datatable', array_filter(['order_type' => $orderTypeParam, 'status_group' => $activeStatusGroup])) }}"
                                        :options="['order' => [[0, 'desc']], 'pageLength' => 10]" />
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    @include('seller.orders.partials.order-accept-modal')
    @include('seller.orders.partials.order-preparing-modal')
    @include('seller.orders.partials.order-reject-modal')
    @include('seller.orders.partials.order-cancel-item-modal')
    @include('seller.orders.partials.order-confirm-return-modal')

@endsection

@push('styles')
    <link rel="stylesheet" href="{{ hyperAsset('assets/css/order-acceptance-deadline.css') }}">
@endpush

@push('scripts')
    <script>
        window.__ORDER_STORE_CONFIG__ = {
            searchUrl: '{{ route('seller.stores.search') }}',
            initialStoreId: '{{ request()->query('store_id', '') }}',
        };
    </script>
    <script src="{{ hyperAsset('assets/js/order.js') }}" defer></script>
    <script src="{{ hyperAsset('assets/js/whatsapp-invoice.js') }}" defer></script>
    <script src="{{ hyperAsset('assets/js/order-acceptance-deadline.js') }}" defer></script>
    <script src="{{ hyperAsset('assets/js/seller-orders.js') }}" defer></script>
@endpush
