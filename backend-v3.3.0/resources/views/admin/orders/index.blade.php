@php
    use App\Enums\DateRangeFilterEnum;
    use App\Enums\Order\OrderItemStatusEnum;
    use App\Enums\Payment\PaymentTypeEnum;
    use Illuminate\Support\Str;
    $orderTypeParam = request()->query('type', '');
    $zoneIdParam = request()->query('zone_id', '');
    $orderIndexQuery = request()->except('status_group');
    $orderInsightUrls = [
        'total' => route('admin.orders.index', $orderIndexQuery),
        'preparing' => route('admin.orders.index', [...$orderIndexQuery, 'status_group' => 'preparing']),
        'delivered' => route('admin.orders.index', [...$orderIndexQuery, 'status_group' => 'delivered']),
        'rejected' => route('admin.orders.index', [...$orderIndexQuery, 'status_group' => 'rejected']),
    ];
@endphp
@extends('layouts.admin.app', ['page' => $menuAdmin['orders']['active'] ?? ''])

@section('title', __('labels.orders'))

@section('header_data')
    @php
        $page_title = __('labels.orders');
        $page_pretitle = __('labels.admin') . ' ' . __('labels.orders');
    @endphp
@endsection

@php
    $breadcrumbs = [
        ['title' => __('labels.home'), 'url' => route('admin.dashboard')],
        ['title' => __('labels.orders'), 'url' => ''],
    ];
@endphp

@section('admin-content')
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
                                $adminActionCount =
                                    \App\Helpers\SidebarBadgeHelper::getBadgeCounts('admin')['orders'] ?? 0;
                            @endphp
                            <h3 class="card-title">
                                {{ __('labels.order_items') }} <span class="order-count"></span>
                                @if ($adminActionCount > 0)
                                    <span class="badge bg-danger text-white ms-2">
                                        <span
                                            class="status-dot status-dot-animated bg-white me-1"></span>{{ $adminActionCount }}
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
                                                {{ __('labels.pos_order') }}
                                            </option>
                                        </select>
                                    </div>
                                    <div class="col-auto" style="min-width: 200px;">
                                        <select id="zoneFilter" class="form-select"
                                            placeholder="{{ __('labels.all_zones') }}"></select>
                                    </div>
                                    <div class="col-auto">
                                        <select class="form-select text-capitalize" id="paymentFilter">
                                            <option value="">{{ __('labels.payment_type') }}</option>
                                            @foreach (PaymentTypeEnum::values() as $value)
                                                <option value="{{ $value }}">{{ Str::replace('_', ' ', $value) }}
                                                </option>
                                            @endforeach
                                        </select>
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
                            <div class="tab-content">
                                <div class="tab-pane fade show active" id="orders-pane" role="tabpanel">
                                    <x-datatable id="orders-list-table" :columns="$orderColumns"
                                        route="{{ route('admin.orders.list_datatable', array_filter(['order_type' => $orderTypeParam, 'status_group' => $activeStatusGroup])) }}"
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
                                        data-items-url="{{ route('admin.orders.list_items', ['id' => '__ID__']) }}"
                                        data-items-labels="{{ $orderItemsLabels }}"></div>
                                </div>
                                <div class="tab-pane fade" id="order-items-pane" role="tabpanel">
                                    <x-datatable id="orders-table" :columns="$columns"
                                        route="{{ route('admin.orders.datatable', array_filter(['order_type' => $orderTypeParam, 'status_group' => $activeStatusGroup])) }}"
                                        :options="['order' => [[0, 'desc']], 'pageLength' => 10]" />
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- REJECT MODAL -->
    <div class="modal modal-blur fade" id="rejectModel" tabindex="-1" role="dialog" aria-hidden="true">
        <div class="modal-dialog modal-sm modal-dialog-centered" role="document">
            <div class="modal-content">
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                <div class="modal-status bg-danger"></div>
                <div class="modal-body text-center py-4">
                    <!-- Download SVG icon from http://tabler.io/icons/icon/alert-triangle -->
                    <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24"
                        fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"
                        stroke-linejoin="round" class="icon mb-2 text-danger icon-lg">
                        <path d="M12 9v4" />
                        <path
                            d="M10.363 3.591l-8.106 13.534a1.914 1.914 0 0 0 1.636 2.871h16.214a1.914 1.914 0 0 0 1.636 -2.87l-8.106 -13.536a1.914 1.914 0 0 0 -3.274 0z" />
                        <path d="M12 16h.01" />
                    </svg>
                    <h3>Reject Order</h3>
                    <div class="text-secondary">
                        Are you sure you want to reject this order? This action cannot be undone.
                    </div>
                </div>
                <div class="modal-footer">
                    <div class="w-100">
                        <div class="row">
                            <div class="col">
                                <a href="#" class="btn w-100" data-bs-dismiss="modal">Cancel</a>
                            </div>
                            <div class="col">
                                <a href="#" class="btn btn-danger w-100" id="confirmReject"
                                    data-bs-dismiss="modal">Reject
                                    Order</a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <!-- END MODAL -->
    <!-- ACCEPT MODAL -->
    <div class="modal modal-blur fade" id="acceptModel" tabindex="-1" role="dialog" aria-hidden="true">
        <div class="modal-dialog modal-sm modal-dialog-centered" role="document">
            <div class="modal-content">
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                <div class="modal-status bg-success"></div>
                <div class="modal-body text-center py-4">
                    <!-- Download SVG icon from http://tabler.io/icons/icon/circle-check -->
                    <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24"
                        fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"
                        stroke-linejoin="round" class="icon mb-2 text-green icon-lg">
                        <path d="M12 12m-9 0a9 9 0 1 0 18 0a9 9 0 1 0 -18 0" />
                        <path d="M9 12l2 2l4 -4" />
                    </svg>
                    <h3>Accept Order</h3>
                    <div class="text-secondary">
                        Are you sure you want to accept this order? You will be responsible for fulfilling it.
                    </div>
                </div>
                <div class="modal-footer">
                    <div class="w-100">
                        <div class="row">
                            <div class="col">
                                <a href="#" class="btn w-100" data-bs-dismiss="modal">Cancel</a>
                            </div>
                            <div class="col">
                                <a href="#" class="btn btn-success w-100" id="confirmAccept"
                                    data-bs-dismiss="modal">Accept
                                    Order</a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <!-- END MODAL -->
    <!-- PREPARING MODAL -->
    <div class="modal modal-blur fade" id="preparingModel" tabindex="-1" role="dialog" aria-hidden="true">
        <div class="modal-dialog modal-sm modal-dialog-centered" role="document">
            <div class="modal-content">
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                <div class="modal-status bg-primary"></div>
                <div class="modal-body text-center py-4">
                    <!-- Download SVG icon from http://tabler.io/icons/icon/package -->
                    <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24"
                        fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"
                        stroke-linejoin="round" class="icon mb-2 text-primary icon-lg">
                        <path d="M12 3l8 4.5l0 9l-8 4.5l-8 -4.5l0 -9l8 -4.5" />
                        <path d="M12 12l8 -4.5" />
                        <path d="M12 12l0 9" />
                        <path d="M12 12l-8 -4.5" />
                    </svg>
                    <h3>Mark as Preparing</h3>
                    <div class="text-secondary">
                        Are you sure you want to mark this order as preparing? This indicates you've started working on
                        the order.
                    </div>
                </div>
                <div class="modal-footer">
                    <div class="w-100">
                        <div class="row">
                            <div class="col">
                                <a href="#" class="btn btn-outline-secondary w-100"
                                    data-bs-dismiss="modal">Cancel</a>
                            </div>
                            <div class="col">
                                <a href="#" class="btn btn-primary w-100" id="confirmPreparing"
                                    data-bs-dismiss="modal">Mark
                                    as Preparing</a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <!-- END MODAL -->
@endsection

@push('styles')
    <link rel="stylesheet" href="{{ hyperAsset('assets/css/order-acceptance-deadline.css') }}">
@endpush

@push('scripts')
    <script>
        window.__ORDER_ZONE_CONFIG__ = {
            searchUrl: '{{ route('admin.delivery-zones.search') }}',
            initialZoneId: '{{ $zoneIdParam }}',
        };
    </script>
    <script src="{{ hyperAsset('assets/js/order.js') }}" defer></script>
    <script src="{{ hyperAsset('assets/js/whatsapp-invoice.js') }}" defer></script>
    <script src="{{ hyperAsset('assets/js/order-acceptance-deadline.js') }}" defer></script>
@endpush
