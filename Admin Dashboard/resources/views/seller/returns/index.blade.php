@extends('layouts.seller.app', ['page' => $menuSeller['return_orders']['active'] ?? '', 'sub_page' => $menuSeller['return_orders']['route']['return_requests']['sub_active']])

@section('title', __('labels.return_requests'))

@section('header_data')
    @php
        $page_title = __('labels.return_requests');
        $page_pretitle = __('labels.seller') . ' ' . __('labels.return_requests');
    @endphp
@endsection

@php
    use App\Enums\Order\OrderItemReturnStatusEnum;

    $breadcrumbs = [
        ['title' => __('labels.home'), 'url' => route('seller.dashboard')],
        ['title' => __('labels.return_requests'), 'url' => ''],
    ];
@endphp

@section('seller-content')
    <div class="page-wrapper">
        <div class="page-body">
            <div class="row row-cards py-3">
                <div class="col-sm-6 col-lg-3">
                    <div class="card card-sm">
                        <div class="card-body">
                            <div class="row align-items-center">
                                <div class="col-auto">
                                    <span class="bg-primary text-white avatar">
                                        <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24"
                                            viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
                                            stroke-linecap="round" stroke-linejoin="round"
                                            class="icon icon-tabler icons-tabler-outline icon-tabler-rotate-clockwise-2">
                                            <path stroke="none" d="M0 0h24v24H0z" fill="none" />
                                            <path d="M9 4.55a8 8 0 0 1 6 14.9m0 -4.45v5h5" />
                                            <path d="M5.63 7.16a8 8 0 0 0 0 9.68" />
                                        </svg>
                                    </span>
                                </div>
                                <div class="col">
                                    <div class="fw-bold fs-2">
                                        <span id="insight-total-return-requests">{{ $returnInsights['total'] ?? 0 }}</span>
                                    </div>
                                    <div class="text-secondary">
                                        {{ __('labels.total_return_requests') ?? 'Total Return Requests' }}</div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-sm-6 col-lg-3">
                    <div class="card card-sm">
                        <div class="card-body">
                            <div class="row align-items-center">
                                <div class="col-auto">
                                    <span class="bg-success text-white avatar">
                                        <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24"
                                            viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
                                            stroke-linecap="round" stroke-linejoin="round"
                                            class="icon icon-tabler icons-tabler-outline icon-tabler-progress-check">
                                            <path stroke="none" d="M0 0h24v24H0z" fill="none" />
                                            <path d="M10 20.777a8.942 8.942 0 0 1 -2.48 -.969" />
                                            <path d="M14 3.223a9.003 9.003 0 0 1 0 17.554" />
                                            <path d="M4.579 17.093a8.961 8.961 0 0 1 -1.227 -2.592" />
                                            <path d="M3.124 10.5c.16 -.95 .468 -1.85 .9 -2.675l.169 -.305" />
                                            <path d="M6.907 4.579a8.954 8.954 0 0 1 3.093 -1.356" />
                                            <path d="M9 12l2 2l4 -4" />
                                        </svg>
                                    </span>
                                </div>
                                <div class="col">
                                    <div class="fw-bold fs-2">
                                        <span
                                            id="insight-approved-return-requests">{{ $returnInsights['approved'] ?? 0 }}</span>
                                    </div>
                                    <div class="text-secondary">{{ __('labels.requests_approved') ?? 'Requests Approved' }}
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-sm-6 col-lg-3">
                    <div class="card card-sm">
                        <div class="card-body">
                            <div class="row align-items-center">
                                <div class="col-auto">
                                    <span class="bg-info text-white avatar">
                                        <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24"
                                            viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
                                            stroke-linecap="round" stroke-linejoin="round"
                                            class="icon icon-tabler icons-tabler-outline icon-tabler-credit-card-refund">
                                            <path stroke="none" d="M0 0h24v24H0z" fill="none" />
                                            <path d="M12 19h-6a3 3 0 0 1 -3 -3v-8a3 3 0 0 1 3 -3h12a3 3 0 0 1 3 3v4.5" />
                                            <path d="M3 10h18" />
                                            <path d="M7 15h.01" />
                                            <path d="M11 15h2" />
                                            <path d="M16 19h6" />
                                            <path d="M19 16l-3 3l3 3" />
                                        </svg>
                                    </span>
                                </div>
                                <div class="col">
                                    <div class="fw-bold fs-2">
                                        <span
                                            id="insight-refund-processed">{{ $returnInsights['refund_processed'] ?? 0 }}</span>
                                    </div>
                                    <div class="text-secondary">{{ __('labels.refund_processed') ?? 'Refund Processed' }}
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-sm-6 col-lg-3">
                    <div class="card card-sm">
                        <div class="card-body">
                            <div class="row align-items-center">
                                <div class="col-auto">
                                    <span class="bg-warning text-white avatar">
                                        <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24"
                                            viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
                                            stroke-linecap="round" stroke-linejoin="round"
                                            class="icon icon-tabler icons-tabler-outline icon-tabler-analyze">
                                            <path stroke="none" d="M0 0h24v24H0z" fill="none" />
                                            <path d="M20 11a8.1 8.1 0 0 0 -6.986 -6.918a8.095 8.095 0 0 0 -8.019 3.918" />
                                            <path d="M4 13a8.1 8.1 0 0 0 15 3" />
                                            <path d="M18 16a1 1 0 1 0 2 0a1 1 0 1 0 -2 0" />
                                            <path d="M4 8a1 1 0 1 0 2 0a1 1 0 1 0 -2 0" />
                                            <path d="M9 12a3 3 0 1 0 6 0a3 3 0 1 0 -6 0" />
                                        </svg>
                                    </span>
                                </div>
                                <div class="col">
                                    <div class="fw-bold fs-2">
                                        <span id="insight-under-review">{{ $returnInsights['under_review'] ?? 0 }}</span>
                                    </div>
                                    <div class="text-secondary">{{ __('labels.under_review') ?? 'Under Review' }}</div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="row row-cards">
                <div class="col-12">
                    <div class="card">
                        <div class="card-header flex-wrap gap-2">
                            <h3 class="card-title">{{ __('labels.return_requests') }}</h3>
                            <div class="card-actions ms-auto">
                                <button class="btn btn-outline-primary" id="refresh">
                                    <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24"
                                        viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
                                        stroke-linecap="round" stroke-linejoin="round"
                                        class="icon icon-tabler icons-tabler-outline icon-tabler-refresh">
                                        <path stroke="none" d="M0 0h24v24H0z" fill="none" />
                                        <path d="M20 11a8.1 8.1 0 0 0 -15.5 -2m-.5 -4v4h4" />
                                        <path d="M4 13a8.1 8.1 0 0 0 15.5 2m.5 4v-4h-4" />
                                    </svg>
                                    {{ __('labels.refresh') }}
                                </button>
                            </div>
                        </div>

                        {{-- Filter bar --}}
                        <div class="card-body border-bottom">
                            <div class="row g-2 align-items-end" id="returns-filters">
                                <div class="col-12 col-md-3">
                                    {{--
                                        Return Dispute System
                                        UI Polish
                                        Purpose: Expose dynamic inspection and administrative review status filters.
                                    --}}
                                    <label class="form-label small mb-1">{{ __('labels.status') }}</label>
                                    <select class="form-select form-select-sm" data-filter="status">
                                        <option value="">{{ __('labels.all') }}</option>
                                        <option value="awaiting_inspection">Awaiting Inspection</option>
                                        <option value="inspection_due_today">Inspection Due Today</option>
                                        <option value="inspection_expired">Inspection Expired</option>
                                        <option value="under_admin_review">Under Admin Review</option>
                                        <option value="completed">Completed</option>
                                        <option value="{{ (string) OrderItemReturnStatusEnum::REQUESTED() }}">
                                            {{ __('labels.requested') }}</option>
                                        <option value="{{ (string) OrderItemReturnStatusEnum::SELLER_APPROVED() }}">
                                            {{ __('labels.pickup_approved') ?? 'Pickup Approved' }}</option>
                                        <option value="{{ (string) OrderItemReturnStatusEnum::PICKUP_ASSIGNED() }}">
                                            {{ __('labels.pickup_assigned') }}</option>
                                        <option value="{{ (string) OrderItemReturnStatusEnum::PICKED_UP() }}">
                                            {{ __('labels.picked_up') }}</option>
                                    </select>
                                </div>
                                <div class="col-6 col-md-2">
                                    <label class="form-label small mb-1">{{ __('labels.date_from') }}</label>
                                    <input type="date" class="form-control form-control-sm" data-filter="date_from">
                                </div>
                                <div class="col-6 col-md-2">
                                    <label class="form-label small mb-1">{{ __('labels.date_to') }}</label>
                                    <input type="date" class="form-control form-control-sm" data-filter="date_to">
                                </div>
                                <div class="col-6 col-md-2">
                                    <label class="form-label small mb-1">{{ __('labels.refund_min') }}</label>
                                    <input type="number" min="0" step="0.01"
                                        class="form-control form-control-sm" data-filter="refund_min">
                                </div>
                                <div class="col-6 col-md-2">
                                    <label class="form-label small mb-1">{{ __('labels.refund_max') }}</label>
                                    <input type="number" min="0" step="0.01"
                                        class="form-control form-control-sm" data-filter="refund_max">
                                </div>
                                <div class="col-12 col-md-1 text-end">
                                    <button type="button" class="btn btn-sm btn-link text-secondary"
                                        id="returns-filters-reset">
                                        {{ __('labels.reset') }}
                                    </button>
                                </div>
                            </div>
                        </div>

                        <div class="card-body">
                            <div class="table-responsive">
                                <x-datatable id="returns-table" :columns="$columns"
                                    route="{{ route('seller.returns.datatable') }}" :options="['order' => [[0, 'desc']], 'pageLength' => 10]" />
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    @include('seller.returns.partials.return-accept-modal')
    @include('seller.returns.partials.returns-reject-modal')
    {{-- CodeCanyon release flow: store confirm/reject modals are intentionally hidden from seller returns. --}}
@endsection

@push('scripts')
    <script src="{{ hyperAsset('assets/js/seller-returns.js') }}" defer></script>
@endpush
