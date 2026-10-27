@php use App\Enums\DateRangeFilterEnum;use App\Enums\Order\OrderItemStatusEnum;use Illuminate\Support\Str;$orderTypeParam = request()->query('type', ''); @endphp
@extends('layouts.seller.app', ['page' => $menuSeller['scheduled_orders']['active'] ?? ""])

@section('title', __('labels.scheduled_orders'))

@section('header_data')
    @php
        $page_title = __('labels.scheduled_orders');
        $page_pretitle = __('labels.seller') . " " . __('labels.scheduled_orders');
    @endphp
@endsection

@php
    $breadcrumbs = [
        ['title' => __('labels.home'), 'url' => route('seller.dashboard')],
        ['title' => __('labels.scheduled_orders'), 'url' => '']
    ];
@endphp

@section('seller-content')
    <div class="page-wrapper">
        <div class="page-body">
            <div class="row row-cards">
                <div class="col-12">
                    <div class="card">
                        <div class="card-header">
                            <h3 class="card-title">{{ __('labels.scheduled_orders') }} <span class="order-count"></span></h3>
                            <div class="card-actions">
                                <div class="row g-2">
                                    <div class="col-auto">
                                        <select class="form-select" id="orderTypeFilter">
                                            <option value="">{{ __('labels.order_type') }}</option>
                                            <option value="regular" @selected($orderTypeParam === 'regular')>{{ __('labels.regular_order') }}</option>
                                            <option value="pickup" @selected($orderTypeParam === 'pickup')>{{ __('labels.self_pickup') }}</option>
                                            <option value="pos" @selected($orderTypeParam === 'pos')>{{ __('labels.pos_order') }}</option>
                                        </select>
                                    </div>
                                    <div class="col-auto" style="min-width: 200px;">
                                        <select id="storeFilter" class="form-select" placeholder="{{ __('labels.all_stores') }}"></select>
                                    </div>
                                    <div class="col-auto">
                                        <select class="form-select text-capitalize" id="statusFilter">
                                            <option value="">{{ __('labels.status') }}</option>
                                            @foreach(OrderItemStatusEnum::values() as $value)
                                                <option
                                                    value="{{$value}}">{{Str::replace("_", " ", $value)}}</option>
                                            @endforeach
                                        </select>
                                    </div>
                                    <div class="col-auto">
                                        <select class="form-select" id="rangeFilter">
                                            <option value="">{{ __('labels.date_range') }}</option>
                                            @foreach(DateRangeFilterEnum::values() as $value)
                                                <option
                                                    value="{{$value}}">{{Str::replace("_", " ", $value)}}</option>
                                            @endforeach
                                        </select>
                                    </div>
                                    <div class="col-auto">
                                        <button class="btn btn-outline-primary" id="refresh">
                                            <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24"
                                                 viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
                                                 stroke-linecap="round" stroke-linejoin="round"
                                                 class="icon icon-tabler icons-tabler-outline icon-tabler-refresh">
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
                                                 route="{{ route('seller.scheduled_orders.list_datatable', array_filter(['order_type' => $orderTypeParam])) }}"
                                                 :options="['order' => [[3, 'desc']],'pageLength' => 10,]"/>
                                    @php
                                        $orderItemsLabels = json_encode([
                                            'loading' => __('labels.loading'),
                                            'error' => __('labels.failed_to_load_items'),
                                        ], JSON_UNESCAPED_UNICODE);
                                    @endphp
                                    <div id="order-items-meta" hidden
                                         data-items-url="{{ route('seller.scheduled_orders.list_items', ['id' => '__ID__']) }}"
                                         data-items-labels="{{ $orderItemsLabels }}"></div>
                                </div>
                                <div class="tab-pane fade" id="order-items-pane" role="tabpanel">
                                    <x-datatable id="orders-table" :columns="$columns"
                                                 route="{{ route('seller.scheduled_orders.datatable', array_filter(['order_type' => $orderTypeParam])) }}"
                                                 :options="['order' => [[0, 'desc']],'pageLength' => 10,]"/>
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

@push('scripts')
    <script>
        window.__ORDER_STORE_CONFIG__ = {
            searchUrl: '{{ route("seller.stores.search") }}',
            initialStoreId: '{{ request()->query("store_id", "") }}',
        };
    </script>
    <script src="{{hyperAsset('assets/js/order.js')}}" defer></script>
    <script src="{{ hyperAsset('assets/js/whatsapp-invoice.js') }}" defer></script>
    <script src="{{hyperAsset('assets/js/seller-orders.js')}}" defer></script>
@endpush
