@php
    use App\Enums\Order\OrderItemStatusEnum;
    use App\Enums\Order\OrderStatusEnum;
    use App\Support\Currency\CurrencyDisplay;
@endphp
@php
    $isScheduledOrder = !empty($isScheduledOrder) || str_contains(request()->route()?->getName() ?? '', 'scheduled_orders') || request()->boolean('is_scheduled');
    $activeMenuPage = $isScheduledOrder ? ($menuSeller['scheduled_orders']['active'] ?? "") : ($menuSeller['orders']['active'] ?? "");
    $ordersListTitle = $isScheduledOrder ? __('labels.scheduled_orders') : __('labels.orders');
    $ordersListRoute = $isScheduledOrder ? route('seller.scheduled_orders.index') : route('seller.orders.index');
    $invoiceRoute = $isScheduledOrder ? route('seller.scheduled_orders.invoice', ['id' => $order['uuid']]) : route('seller.orders.invoice', ['id' => $order['uuid']]);
    $isReschedulePending = ($order['reschedule_status'] ?? null) === 'needs_reschedule';
    $orderCurrencyCode = strtoupper((string) ($order['currency_code'] ?? $order['base_currency_code'] ?? ($systemSettings['currency'] ?? '')));
    $money = fn ($amount) => CurrencyDisplay::format($amount, $orderCurrencyCode, $systemSettings['currencySymbol'] ?? null);
@endphp
@extends('layouts.seller.app', ['page' => $activeMenuPage])

@section('title', __('labels.order_details'))

@section('header_data')
    @php
        $page_title = __('labels.order_details');
        $page_pretitle = __('labels.seller') . " " . $ordersListTitle;
    @endphp
@endsection

@php
    $breadcrumbs = [
        ['title' => __('labels.home'), 'url' => route('seller.dashboard')],
        ['title' => $ordersListTitle, 'url' => $ordersListRoute],
        ['title' => __('labels.order_details'), 'url' => '']
    ];
@endphp

@section('seller-content')
    <div class="page-wrapper" data-seller-tour="order-details">
        <div class="page-header d-print-none">
            <div class="container-xl">
                <div class="row g-2 align-items-center">
                    <div class="col-12 col-lg-auto">
                        <div class="page-pretitle">{{ __('labels.seller') }} · {{ $ordersListTitle }}</div>
                        <h2 class="page-title d-flex align-items-center flex-wrap gap-2">
                            <span>{{ __('labels.order') }} #{{ $order['order_id'] }}</span>
                            <span class="badge {{ $order['status'] }} text-uppercase">
                                {{ Str::ucfirst(Str::replace('_', ' ', $order['status'])) }}
                            </span>
                            @if(!empty($order['is_rush_order']))
                                <span class="badge bg-orange-lt text-orange">
                                    <i class="ti ti-bolt me-1"></i>{{ __('labels.is_rush_order') ?? 'Rush' }}
                                </span>
                            @endif
                            @if($isReschedulePending)
                                <span class="badge bg-warning-lt text-warning">
                                    <i class="ti ti-calendar-exclamation me-1"></i>{{ __('labels.needs_reschedule') }}
                                </span>
                            @endif
                        </h2>
                        <div class="text-secondary small mt-1 d-flex flex-wrap gap-3">
                            <span><i class="ti ti-user me-1"></i>{{ $order['billing_name'] ?? '—' }}</span>
                            <span><i class="ti ti-credit-card me-1"></i>{{ Str::upper($order['payment_method'] ?? '—') }}</span>
                            <span><i class="ti ti-clock me-1"></i>{{ $order['created_at'] ?? '—' }}</span>
                            @if(!empty($order['delivery_zone']['name']))
                                <span><i class="ti ti-map-pin me-1"></i>{{ $order['delivery_zone']['name'] }}</span>
                            @endif
                            @if(!empty($order['uuid']))
                                <span class="font-monospace text-truncate" title="{{ $order['uuid'] }}">
                                    <i class="ti ti-hash me-1"></i>{{ Str::limit($order['uuid'], 24, '…') }}
                                </span>
                            @endif
                        </div>
                    </div>

                    <div class="col-auto ms-auto d-flex align-items-center gap-3">
                        <div class="text-end">
                            <div class="text-secondary small">{{ __('labels.total_price') }}</div>
                            <div class="h2 mb-0">{{ $money((float) ($order['total_payable'] ?? $order['total_price'] ?? 0)) }}</div>
                        </div>
                    </div>

                    <div class="col-12 col-lg-auto d-print-none">
                        <div class="btn-list" id="sellerOrderActions" data-order-id="{{ $order['id'] }}">
                            <a href="{{ $invoiceRoute }}"
                               target="_blank"
                               class="btn btn-outline-primary">
                                <i class="ti ti-invoice me-1"></i>{{ __('labels.download_invoice') }}
                            </a>
                            <a href="{{ $ordersListRoute }}"
                               class="btn btn-outline-secondary">
                                <i class="ti ti-arrow-left me-1"></i>{{ $isScheduledOrder ? __('labels.scheduled_orders') : __('labels.back_to_orders') }}
                            </a>
                            @php
                                $hasActivePickupItems = collect($order['items'] ?? [])->contains(function ($item) {
                                    return !in_array($item['orderItem']['status'] ?? '', [
                                        \App\Enums\Order\OrderItemStatusEnum::REJECTED(),
                                        \App\Enums\Order\OrderItemStatusEnum::CANCELLED(),
                                        \App\Enums\Order\OrderItemStatusEnum::FAILED(),
                                    ], true);
                                });
                            @endphp
                            @if(!$isReschedulePending && ($order['delivery_type'] ?? '') === 'pickup' && $hasActivePickupItems)
                                @if(in_array($order['status'] ?? '', ['preparing', 'accepted_by_seller'], true))
                                    <button type="button" class="btn btn-primary" id="btnMarkReadyForPickup" data-order-id="{{ $order['id'] }}">
                                        <i class="ti ti-check me-1"></i>Mark Ready for Pickup
                                    </button>
                                @elseif(($order['status'] ?? '') === 'ready_for_pickup')
                                    <button type="button" class="btn btn-success" id="btnVerifyPickupOtp" data-bs-toggle="modal" data-bs-target="#verifyOtpModal">
                                        <i class="ti ti-shield-check me-1"></i>Verify OTP
                                    </button>
                                @endif
                                @if($hasEligiblePickupReturnItems)
                                    <button type="button" class="btn btn-danger" id="btnPickupReturn" data-order-id="{{ $order['id'] }}" data-bs-toggle="modal" data-bs-target="#pickup-refund-modal">
                                        <i class="ti ti-rotate-2 me-1"></i>Return Items
                                    </button>
                                @endif
                            @endif
                        </div>
                    </div>
                </div>
            </div>
        </div>
        {{-- END PAGE HEADER --}}

        <div class="page-body">
            <div class="container-xl">
                @if($isReschedulePending)
                    <div class="alert alert-warning d-flex align-items-start gap-2" role="alert">
                        <i class="ti ti-calendar-exclamation fs-2"></i>
                        <div>
                            <div class="fw-semibold">{{ __('labels.customer_reschedule_pending') }}</div>
                            <div>{{ __('labels.customer_reschedule_pending_help') }}</div>
                            @if(!empty($order['reschedule_reason']) || !empty($order['reschedule_deadline']))
                                <div class="small mt-1">
                                    @if(!empty($order['reschedule_reason']))
                                        <span>{{ Str::headline($order['reschedule_reason']) }}</span>
                                    @endif
                                    @if(!empty($order['reschedule_deadline']))
                                        <span class="ms-2">{{ __('labels.deadline') ?? 'Deadline' }}: {{ \Carbon\Carbon::parse($order['reschedule_deadline'])->format('M d, Y h:i A') }}</span>
                                    @endif
                                </div>
                            @endif
                        </div>
                    </div>
                @endif
                <div class="row row-cards">
                    {{-- Order Summary — colored status strip + icon header.
                         Status + total are already in the page header so we
                         drop the duplicate rows here for a tighter datagrid. --}}
                    <div class="col-12 col-lg-6">
                        @php
                            $sellerPaymentStatus = strtolower((string) ($order['payment_status'] ?? ''));
                            $sellerPaymentBadgeClass = match (true) {
                                in_array($sellerPaymentStatus, ['paid', 'completed', 'success'], true)         => 'bg-green-lt',
                                in_array($sellerPaymentStatus, ['failed', 'declined'], true)                   => 'bg-red-lt',
                                in_array($sellerPaymentStatus, ['refunded', 'partially_refunded'], true)       => 'bg-blue-lt',
                                default                                                                        => 'bg-yellow-lt',
                            };
                            $sellerStatusStripClass = match (true) {
                                in_array($order['status'] ?? '', ['cancelled', 'rejected_by_seller', 'failed'], true) => 'bg-red',
                                in_array($order['status'] ?? '', ['delivered'], true)                                  => 'bg-success',
                                in_array($order['status'] ?? '', ['out_for_delivery', 'collected', 'assigned'], true)  => 'bg-blue',
                                default                                                                                => 'bg-primary',
                            };
                        @endphp
                        <div class="card">
                            <div class="card-status-start {{ $sellerStatusStripClass }}"></div>
                            <div class="card-header">
                                <h3 class="card-title">
                                    <i class="ti ti-receipt me-1"></i>{{ __('labels.order_summary') }}
                                </h3>
                            </div>
                            <div class="card-body">
                                <div class="datagrid">
                                    <div class="datagrid-item">
                                        <div class="datagrid-title">{{ __('labels.order_number') }}</div>
                                        <div class="datagrid-content">#{{ $order['order_id'] }}</div>
                                    </div>
                                    <div class="datagrid-item">
                                        <div class="datagrid-title">{{ __('labels.order_date') }}</div>
                                        <div class="datagrid-content">{{ $order['created_at'] }}</div>
                                    </div>
                                    <div class="datagrid-item">
                                        <div class="datagrid-title">{{ __('labels.payment_method') }}</div>
                                        <div class="datagrid-content text-uppercase">{{ $order['payment_method'] }}</div>
                                    </div>
                                    <div class="datagrid-item">
                                        <div class="datagrid-title">{{ __('labels.payment_status') }}</div>
                                        <div class="datagrid-content text-capitalize">
                                            <span class="badge {{ $sellerPaymentBadgeClass }}">
                                                {{ $order['payment_status'] }}
                                            </span>
                                        </div>
                                    </div>
                                    <div class="datagrid-item">
                                        <div class="datagrid-title">{{ __('labels.total_price') }}</div>
                                        <div class="datagrid-content fw-semibold">
                                            {{ $money($order['total_payable'] ?? $order['total_price']) }}
                                        </div>
                                    </div>
                                    <div class="datagrid-item">
                                        <div class="datagrid-title">{{ __('labels.uuid') }}</div>
                                        <div class="datagrid-content text-truncate font-monospace small" title="{{ $order['uuid'] }}">
                                            {{ $order['uuid'] }}
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        @if(!empty($order['order_note']) && ($order['delivery_type'] ?? '') !== 'pickup')
                            <div class="card mt-3">
                                <div class="card-header">
                                    <h3 class="card-title">
                                        <i class="ti ti-message-2 me-1"></i>{{ __('labels.order_note') }}
                                    </h3>
                                </div>
                                <div class="card-body py-2">
                                    <p class="text-secondary mb-0">{{ $order['order_note'] }}</p>
                                </div>
                            </div>
                        @endif
                    </div>

                    <div class="col-12 col-lg-6">
                        {{-- Customer card — avatar-led, mobile is a tel: link,
                             email is a mailto: link. Same rhythm as admin. --}}
                        <div class="card">
                            <div class="card-header">
                                <h3 class="card-title">
                                    <i class="ti ti-user-circle me-1"></i>{{ __('labels.customer_information') }}
                                </h3>
                            </div>
                            <div class="card-body">
                                <div class="d-flex align-items-start">
                                    <span class="avatar avatar-md me-3 bg-primary-lt">
                                        <i class="ti ti-user"></i>
                                    </span>
                                    <div class="flex-fill">
                                        <div class="fw-semibold text-capitalize">{{ $order['billing_name'] ?? '—' }}</div>
                                        <div class="text-secondary small d-flex flex-wrap gap-3 mt-1">
                                            @if(!empty($order['email']))
                                                <a href="mailto:{{ $order['email'] }}" class="text-decoration-none">
                                                    <i class="ti ti-mail me-1"></i>{{ $order['email'] }}
                                                </a>
                                            @endif
                                            @if(!empty($order['billing_phone']))
                                                <a href="tel:{{ $order['billing_phone'] }}" class="text-decoration-none">
                                                    <i class="ti ti-phone me-1"></i>{{ $order['billing_phone'] }}
                                                </a>
                                            @endif
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        {{-- Shipping address — recipient called out separately
                             when it differs from billing name. --}}
                             @if(($order['delivery_type'] ?? '') !== 'pickup')
                        <div class="card mt-3">
                            <div class="card-header">
                                <h3 class="card-title">
                                    <i class="ti ti-map-pin me-1"></i>{{ __('labels.shipping_address') }}
                                </h3>
                            </div>
                            <div class="card-body">
                                @if(!empty($order['shipping_name']) && $order['shipping_name'] !== ($order['billing_name'] ?? null))
                                    <div class="fw-semibold text-capitalize mb-1">
                                        <i class="ti ti-package me-1 text-secondary"></i>{{ $order['shipping_name'] }}
                                    </div>
                                @endif
                                <address class="mb-2 small text-secondary">
                                    {{ $order['shipping_address_1'] }}@if($order['shipping_address_2']), {{ $order['shipping_address_2'] }}@endif<br>
                                    @if($order['shipping_landmark'])
                                        {{ $order['shipping_landmark'] }}<br>
                                    @endif
                                    {{ $order['shipping_city'] }}, {{ $order['shipping_state'] }} {{ $order['shipping_zip'] }}<br>
                                    {{ $order['shipping_country'] }}
                                </address>
                                @if(!empty($order['shipping_phone']))
                                    <a href="tel:{{ $order['shipping_phone'] }}" class="text-decoration-none small">
                                        <i class="ti ti-phone me-1"></i>{{ $order['shipping_phone'] }}
                                    </a>
                                @endif
                            </div>
                        </div>
                        @endif

                        @if(($order['delivery_type'] ?? '') === 'pickup')
                            <div class="card mt-3">
                                <div class="card-header">
                                    <h3 class="card-title">
                                        <i class="ti ti-package me-1"></i>{{ __('labels.pickup_information') ?? 'Pickup Information' }}
                                    </h3>
                                </div>
                                <div class="card-body">
                                    <div class="datagrid">
                                        <div class="datagrid-item">
                                            <div class="datagrid-title">{{ __('labels.delivery_type') ?? 'Delivery Type' }}</div>
                                            <div class="datagrid-content">
                                                <span class="badge bg-green-lt">{{ __('labels.delivery_type_pickup') ?? 'Pickup' }}</span>
                                            </div>
                                        </div>
                                        <div class="datagrid-item">
                                            <div class="datagrid-title">{{ __('labels.picked_up_at') ?? 'Picked Up At' }}</div>
                                            <div class="datagrid-content">
                                                {{ $order['picked_up_at'] ?? '—' }}
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        @else
                            <div class="card mt-3">
                                <div class="card-header">
                                    <h3 class="card-title">
                                        <i class="ti ti-truck-delivery me-1"></i>{{ __('labels.delivery_information') }}
                                    </h3>
                                </div>
                                <div class="card-body">
                                    @if(!empty($order['delivery_boy']) && !empty($order['delivery_boy']['id']))
                                        <div class="d-flex align-items-start mb-3">
                                            <span class="avatar avatar-md me-3 bg-blue-lt">
                                                <i class="ti ti-motorbike"></i>
                                            </span>
                                            <div class="flex-fill">
                                                <div class="fw-semibold text-capitalize">
                                                    {{ $order['delivery_boy']['full_name'] ?? __('labels.delivery_boy') }}
                                                    @if(!empty($order['delivery_boy']['is_blocked']))
                                                        <span class="badge bg-danger-lt text-danger ms-1">
                                                            <i class="ti ti-lock me-1"></i>{{ __('labels.blocked') }}
                                                        </span>
                                                    @endif
                                                </div>
                                                <div class="text-secondary small">
                                                    @if(!empty($order['delivery_boy']['mobile']))
                                                        <a href="tel:{{ $order['delivery_boy']['mobile'] }}" class="text-decoration-none me-3">
                                                            <i class="ti ti-phone me-1"></i>{{ $order['delivery_boy']['mobile'] }}
                                                        </a>
                                                    @endif
                                                    @if(!empty($order['delivery_boy']['email']))
                                                        <a href="mailto:{{ $order['delivery_boy']['email'] }}" class="text-decoration-none">
                                                            <i class="ti ti-mail me-1"></i>{{ $order['delivery_boy']['email'] }}
                                                        </a>
                                                    @endif
                                                </div>
                                            </div>
                                        </div>
                                    @else
                                        <div class="d-flex align-items-center text-secondary mb-3">
                                            <span class="avatar avatar-md me-3 bg-secondary-lt">
                                                <i class="ti ti-user-question"></i>
                                            </span>
                                            <div>
                                                <div class="fw-semibold">{{ __('labels.no_rider_assigned') }}</div>
                                                <div class="small">{{ __('labels.no_rider_assigned_hint') }}</div>
                                            </div>
                                        </div>
                                    @endif

                                    <div class="datagrid">
                                        <div class="datagrid-item">
                                            <div class="datagrid-title">{{ __('labels.delivery_zone') }}</div>
                                            <div class="datagrid-content">
                                                {{ $order['delivery_zone']['name'] ?? '—' }}
                                            </div>
                                        </div>
                                        <div class="datagrid-item">
                                            <div class="datagrid-title">{{ __('labels.estimated_delivery_time') }}</div>
                                            <div class="datagrid-content">
                                                {{ $order['estimated_delivery_time'] ?? '—' }}
                                            </div>
                                        </div>
                                        @if(!empty($order['scheduled_date']))
                                            <div class="datagrid-item">
                                                <div class="datagrid-title">{{ __('labels.scheduled_date') ?? 'Scheduled Date' }}</div>
                                                <div class="datagrid-content">
                                                    {{ \Carbon\Carbon::parse($order['scheduled_date'])->format('M d, Y') }}
                                                </div>
                                            </div>
                                            @if(!empty($order['scheduled_time_slot_label']))
                                                <div class="datagrid-item">
                                                    <div class="datagrid-title">{{ __('labels.scheduled_time') ?? 'Scheduled Time' }}</div>
                                                    <div class="datagrid-content">
                                                        <span class="badge bg-blue-lt">
                                                            <i class="ti ti-clock me-1"></i>{{ $order['scheduled_time_slot_label'] }}
                                                        </span>
                                                    </div>
                                                </div>
                                            @endif
                                        @endif
                                    </div>
                                </div>
                            </div>
                        @endif
                    </div>
                    @php
                        // Bulk UI is hidden once the order has exited the seller-actionable
                        // window. Mirrors the admin "$isOrderTerminal" gating.
                        $sellerOrderTerminal = in_array($order['status'] ?? null, [
                            OrderStatusEnum::COLLECTED(),
                            OrderStatusEnum::OUT_FOR_DELIVERY(),
                            OrderStatusEnum::DELIVERED(),
                            OrderStatusEnum::FAILED(),
                            OrderStatusEnum::CANCELLED(),
                        ], true);
                        $bulkEnabled = !$sellerOrderTerminal && ($order['delivery_type'] ?? '') !== 'pickup';
                    @endphp

                    <!-- Order Items Card -->
                    <div class="col-12 mt-3">
                        <div class="card">
                            <div class="card-header">
                                <h3 class="card-title">{{ __('labels.order_items') }}</h3>
                                @if($bulkEnabled)
                                    <div class="card-actions">
                                        <button type="button"
                                                class="btn btn-sm btn-primary"
                                                id="sellerBulkUpdateBtn"
                                                data-bs-toggle="modal"
                                                data-bs-target="#sellerBulkUpdateStatusModal"
                                                disabled>
                                            <i class="ti ti-refresh me-1"></i>{{ __('labels.bulk_update_status') }}
                                            <span class="badge bg-white text-primary ms-2 d-none" id="sellerBulkSelectedCount">0</span>
                                        </button>
                                    </div>
                                @endif
                            </div>
                            <div class="card-body">
                                <div class="table-responsive">
                                    <table class="table table-vcenter card-table">
                                        <thead>
                                        <tr>
                                            @if($bulkEnabled)
                                                <th width="30">
                                                    <input type="checkbox"
                                                           class="form-check-input"
                                                           id="sellerSelectAllItems"
                                                           aria-label="{{ __('labels.select_all') }}">
                                                </th>
                                            @endif
                                            <th>{{ __('labels.product') }}</th>
                                            <th>{{ __('labels.variant') }}</th>
                                            <th>{{ __('labels.price') }}</th>
                                            <th>{{ __('labels.status') }}</th>
                                            <th>{{ __('labels.quantity') }}</th>
                                            <th>{{ __('labels.subtotal') }}</th>
                                            <th class="text-end">{{ __('labels.actions') }}</th>
                                        </tr>
                                        </thead>
                                        <tbody>
                                        @foreach($order['items'] as $item)
                                            @php
                                                $itemId = $item['orderItem']['id'] ?? null;
                                                $hasTransitions = !empty($itemTransitions[$itemId] ?? []);
                                            @endphp
                                            <tr>
                                                @if($bulkEnabled)
                                                    <td>
                                                        <input type="checkbox"
                                                               class="form-check-input seller-item-checkbox"
                                                               name="item_ids[]"
                                                               value="{{ $itemId }}"
                                                               @disabled(!$hasTransitions)>
                                                    </td>
                                                @endif
                                                <td>{{ $item['product']['title'] ?? 'N/A' }}
                                                    @if(!empty($item['attachments']))
                                                        <br class="mt-7">
                                                        <span class="fw-medium">Attachments</span>
                                                        <ul class="list-unstyled mb-0">
                                                            @foreach($item['attachments'] as $attachment)
                                                                <li><a href="{{ $attachment }}"
                                                                       target="_blank"
                                                                       data-fslightbox="gallery">view</a>
                                                                </li>
                                                            @endforeach
                                                        </ul>
                                                    @endif
                                                    @if(!empty($item['addons']))
                                                        <div class="mt-2">
                                                            <span class="fw-medium">{{ __('labels.addons') }}</span>
                                                            <ul class="list-unstyled small mb-0 ps-3">
                                                                @foreach($item['addons'] as $addon)
                                                                    <li>
                                                                        @if(!empty($addon['group']['title']))
                                                                            <span class="text-muted">{{ $addon['group']['title'] }} —</span>
                                                                        @endif
                                                                        {{ $addon['item']['title'] ?? '—' }}
                                                                        <span class="text-muted">· {{ $money($addon['price']) }}</span>
                                                                    </li>
                                                                @endforeach
                                                            </ul>
                                                        </div>
                                                    @endif
                                                </td>

                                                <td>{{ $item['variant']['title'] ?? 'N/A' }}</td>
                                                <td>{{ $money($item['price'] + $item['tax_amount']) }}</td>
                                                <td>
                                                    @php
                                                        $itemReason = $item['orderItem']['cancellation_reason']
                                                            ?? $item['orderItem']['delivery_fail_reason']
                                                            ?? ($itemStatusReasons[$itemId] ?? null);
                                                    @endphp
                                                    <span class="badge {{ $item['orderItem']['status'] }}">
                                                        {{ $item['orderItem']['status_formatted'] }}
                                                    </span>
                                                    @if(!empty($itemReason))
                                                        <div class="small text-muted mt-1">
                                                            <strong>{{ __('labels.reason') }}:</strong> {{ $itemReason }}
                                                        </div>
                                                    @endif
                                                    @if(!empty($acceptanceDeadlines[$itemId]))
                                                        <div>
                                                            @include('partials.order-acceptance-deadline', ['deadline' => $acceptanceDeadlines[$itemId]])
                                                        </div>
                                                    @endif
                                                </td>
                                                <td>{{ $item['quantity'] }}</td>
                                                <td>
                                                    {{ $money($item['sub_total']) }}
                                                    @if(!empty($item['addons_total']) && (float) $item['addons_total'] > 0)
                                                        <div class="small text-muted">
                                                            {{ __('labels.addons_total') }}: {{ $money($item['addons_total']) }}
                                                        </div>
                                                    @endif
                                                </td>
                                                @php $itemStatus = $item['orderItem']['status'] ?? null; @endphp
                                                <td class="text-end">
                                                    @if($isReschedulePending)
                                                        <span class="badge bg-warning-lt text-warning">
                                                            <i class="ti ti-calendar-exclamation me-1"></i>{{ __('labels.needs_reschedule') }}
                                                        </span>
                                                    @elseif(($order['delivery_type'] ?? '') === 'pickup' && !empty($itemTransitions[$itemId]))
                                                        <div class="d-inline-flex gap-1 mb-1 me-1">
                                                            @foreach($itemTransitions[$itemId] as $transition)
                                                                <button type="button"
                                                                        class="btn btn-sm btn-{{ $transition['value'] === 'reject' ? 'outline-danger' : ($transition['value'] === 'accept' ? 'success' : 'primary') }} btn-transition-status"
                                                                        data-item-id="{{ $itemId }}"
                                                                        data-status="{{ $transition['value'] }}">
                                                                    {{ $transition['label'] }}
                                                                </button>
                                                            @endforeach
                                                        </div>
                                                    @elseif(($order['delivery_type'] ?? '') !== 'pickup' && !empty($itemTransitions[$itemId]) && $itemStatus !== \App\Enums\Order\OrderItemStatusEnum::RETURNING_TO_STORE())
                                                        <div class="dropdown d-inline-block mb-1 me-1">
                                                            <button class="btn btn-sm btn-outline-primary dropdown-toggle"
                                                                    type="button"
                                                                    data-bs-toggle="dropdown"
                                                                    aria-expanded="false">
                                                                <i class="ti ti-refresh me-1"></i>{{ __('labels.update_status') }}
                                                            </button>
                                                            <div class="dropdown-menu">
                                                                @foreach($itemTransitions[$itemId] as $transition)
                                                                    <a href="javascript:void(0)"
                                                                       class="dropdown-item seller-update-item-status"
                                                                       data-item-id="{{ $itemId }}"
                                                                       data-status="{{ $transition['value'] }}"
                                                                       data-label="{{ $transition['label'] }}">
                                                                        <i class="ti ti-building-store me-1 text-muted"></i>{{ $transition['label'] }}
                                                                    </a>
                                                                @endforeach
                                                            </div>
                                                        </div>
                                                    @endif

                                                    @if(!$isReschedulePending && in_array($itemStatus, [\App\Enums\Order\OrderItemStatusEnum::ACCEPTED(), \App\Enums\Order\OrderItemStatusEnum::PREPARING()], true))
                                                        <button type="button"
                                                                class="btn btn-sm btn-outline-danger"
                                                                data-bs-toggle="modal"
                                                                data-bs-target="#cancelItemModal"
                                                                data-id="{{ $item['orderItem']['id'] }}">
                                                            <i class="ti ti-ban me-1"></i>{{ __('labels.cancel_item') }}
                                                        </button>
                                                    @elseif(!$isReschedulePending && $itemStatus === \App\Enums\Order\OrderItemStatusEnum::RETURNING_TO_STORE())
                                                        <button type="button"
                                                                class="btn btn-sm btn-success"
                                                                data-bs-toggle="modal"
                                                                data-bs-target="#confirmReturnModal"
                                                                data-id="{{ $item['orderItem']['id'] }}">
                                                            <i class="ti ti-package-import me-1"></i>{{ __('labels.confirm_received') }}
                                                        </button>
                                                    @else
                                                        @if(empty($itemTransitions[$itemId]))
                                                            <span class="text-muted">—</span>
                                                        @endif
                                                    @endif
                                                </td>
                                            </tr>
                                        @endforeach
                                        </tbody>
                                        <tfoot>
                                        @php
                                            // Without checkbox col: product, variant, price, status = 4
                                            // With checkbox col: +1 = 5
                                            $sellerColspan = $bulkEnabled ? 5 : 4;
                                        @endphp
                                        <tr>
                                            <td colspan="{{ $sellerColspan }}" class="text-end"><strong>{{ __('labels.subtotal') }}:</strong></td>
                                            <td><strong>{{ collect($order['items'])->sum('quantity')  }}</strong></td>
                                            <td>
                                                <strong>{{ $money($order['subtotal'] ?? $order['total_price']) }}</strong>
                                            </td>
                                            <td></td>
                                        </tr>
                                        @if(!empty($order['rider_tip_amount']) && (float)$order['rider_tip_amount'] > 0)
                                            <tr>
                                                <td colspan="{{ $sellerColspan + 1 }}" class="text-end">
                                                    <strong>{{ __('labels.rider_tips') }}:</strong>
                                                </td>
                                                <td>
                                                    <strong>{{ $money($order['rider_tip_amount']) }}</strong>
                                                </td>
                                                <td></td>
                                            </tr>
                                        @endif
                                        @if(!empty($order['promo_discount']) && (float)$order['promo_discount'] > 0)
                                            <tr>
                                                <td colspan="{{ $sellerColspan + 1 }}" class="text-end">
                                                    <strong>{{ __('labels.promo_discount') }}@if(!empty($order['promo_code'])) ({{ $order['promo_code'] }})@endif:</strong>
                                                </td>
                                                <td>
                                                    <strong>- {{ $money($order['promo_discount']) }}</strong>
                                                </td>
                                                <td></td>
                                            </tr>
                                        @endif
                                        @if(!empty($order['wallet_balance']) && (float)$order['wallet_balance'] > 0)
                                            <tr>
                                                <td colspan="{{ $sellerColspan + 1 }}" class="text-end"><strong>{{ __('labels.wallet_used') }}:</strong></td>
                                                <td>
                                                    <strong>- {{ $money($order['wallet_balance']) }}</strong>
                                                </td>
                                                <td></td>
                                            </tr>
                                        @endif
                                        <tr>
                                            <td colspan="{{ $sellerColspan + 1 }}" class="text-end"><strong>{{ __('labels.total_payable') }}:</strong></td>
                                            <td>
                                                <strong class="text-success">{{ $money($order['total_payable'] ?? $order['total_price']) }}</strong>
                                            </td>
                                            <td></td>
                                        </tr>
                                        </tfoot>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>

                    @if(isset($refunds) && $refunds->isNotEmpty())
                        <div class="col-12 mt-3">
                            <div class="card">
                                <div class="card-header">
                                    <h3 class="card-title">
                                        <i class="ti ti-history me-1"></i>Return & Refund History
                                    </h3>
                                </div>
                                <div class="card-body">
                                    <div class="accordion" id="refundsAccordion">
                                        @foreach($refunds as $refund)
                                            <div class="accordion-item mb-2 border rounded">
                                                <h2 class="accordion-header" id="headingRefund{{ $refund->id }}">
                                                    <button class="accordion-button collapsed py-2 px-3 fw-semibold text-secondary" type="button" data-bs-toggle="collapse" data-bs-target="#collapseRefund{{ $refund->id }}" aria-expanded="false" aria-controls="collapseRefund{{ $refund->id }}">
                                                        <div class="d-flex align-items-center justify-content-between w-100 pe-3">
                                                            <div>
                                                                Return #{{ $refund->id }}
                                                                <span class="text-muted small fw-normal ms-2">{{ $refund->created_at?->format('M d, Y h:i A') }}</span>
                                                            </div>
                                                            <div class="text-danger">
                                                                -{{ CurrencyDisplay::format($refund->total_amount, $refund->currency_code ?: $orderCurrencyCode, $systemSettings['currencySymbol'] ?? null) }}
                                                            </div>
                                                        </div>
                                                    </button>
                                                </h2>
                                                <div id="collapseRefund{{ $refund->id }}" class="accordion-collapse collapse" aria-labelledby="headingRefund{{ $refund->id }}" data-bs-parent="#refundsAccordion">
                                                    <div class="accordion-body bg-light py-2 px-3">
                                                        <div class="row small text-secondary mb-2">
                                                            <div class="col-sm-4">
                                                                <strong>Method:</strong> {{ ucfirst($refund->refund_method) }}
                                                                @if(!empty($refund->refund_method_meta['note']))
                                                                    ({{ $refund->refund_method_meta['note'] }})
                                                                @endif
                                                            </div>
                                                            <div class="col-sm-4">
                                                                <strong>Processed By:</strong> {{ $refund->refundedBy?->name ?? 'System' }}
                                                            </div>
                                                            <div class="col-sm-4">
                                                                <strong>Reason:</strong> {{ $refund->reason ?? 'No reason provided' }}
                                                            </div>
                                                        </div>
                                                        <div class="table-responsive">
                                                            <table class="table table-sm table-vcenter bg-white border mb-0 rounded">
                                                                <thead>
                                                                    <tr class="text-muted">
                                                                        <th>Item</th>
                                                                        <th class="text-center">Returned Qty</th>
                                                                        <th class="text-end">Amount</th>
                                                                    </tr>
                                                                </thead>
                                                                <tbody>
                                                                    @foreach($refund->lines as $line)
                                                                        <tr>
                                                                            <td>
                                                                                <span class="fw-medium">{{ $line->orderItem?->title ?? 'N/A' }}</span>
                                                                                @if($line->orderItem?->variant_title)
                                                                                    <span class="text-muted small">({{ $line->orderItem->variant_title }})</span>
                                                                                @endif
                                                                            </td>
                                                                            <td class="text-center">{{ $line->quantity }}</td>
                                                                            <td class="text-end fw-semibold text-danger">
                                                                                -{{ CurrencyDisplay::format($line->amount, $refund->currency_code ?: $orderCurrencyCode, $systemSettings['currencySymbol'] ?? null) }}
                                                                            </td>
                                                                        </tr>
                                                                    @endforeach
                                                                </tbody>
                                                            </table>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        @endforeach
                                    </div>
                                </div>
                            </div>
                        </div>
                    @endif

                    {{-- The legacy inline "Update Status" card was retired here — the
                         shared bulk-update modal now lives in the items card header. --}}
                </div>
            </div>
        </div>
    </div>

    @include('seller.orders.partials.order-cancel-item-modal')
    @include('seller.orders.partials.order-confirm-return-modal')
    @include('seller.orders.partials.pickup-refund-modal')
    @if(!$sellerOrderTerminal)
        @include('seller.orders.partials.bulk-update-status-modal')
    @endif

    <!-- Verify OTP Modal -->
    <div class="modal modal-blur fade" id="verifyOtpModal" tabindex="-1" role="dialog" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered" role="document">
            <div class="modal-content">
                <form id="verifyPickupOtpForm" data-order-id="{{ $order['id'] }}">
                    <div class="modal-header">
                        <h5 class="modal-title">Verify Pickup OTP</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <div class="mb-3">
                            <label class="form-label required">6-Digit OTP Code</label>
                            <input type="text" class="form-control" name="otp" id="pickup_otp_input" maxlength="6" placeholder="Enter 6-digit OTP code" required autocomplete="off">
                            <div class="invalid-feedback" id="otp-error-msg"></div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-link link-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-success" id="btnSubmitOtp">Verify & Complete</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
@endsection

@push('styles')
    <link rel="stylesheet" href="{{ hyperAsset('assets/css/order-acceptance-deadline.css') }}">
@endpush

@push('scripts')
    <script src="{{ asset('assets/js/order.js') }}"></script>
    @if(!$sellerOrderTerminal)
        <script>
            window.sellerOrderEndpoints = {
                itemsBulkUpdateStatus: '{{ route($isScheduledOrder ? 'seller.scheduled_orders.items_bulk_update_status' : 'seller.orders.items_bulk_update_status', ['id' => $order['id'] ?? 0]) }}',
            };
            // Same shape the admin page uses: { itemId: [{value,label,acting_as}, ...] }
            window.sellerOrderItemTransitions = @json($itemTransitions ?? []);
        </script>
    @endif
    <script>
        window.sellerRefundEndpoints = {
            preview: '{{ route($isScheduledOrder ? 'seller.scheduled_orders.refunds.preview' : 'seller.orders.refunds.preview', ['id' => $order['id'] ?? 0]) }}',
            create: '{{ route($isScheduledOrder ? 'seller.scheduled_orders.refunds.create' : 'seller.orders.refunds.create', ['id' => $order['id'] ?? 0]) }}',
            history: '{{ route($isScheduledOrder ? 'seller.scheduled_orders.refunds.history' : 'seller.orders.refunds.history', ['id' => $order['id'] ?? 0]) }}',
        };
        window.currencySymbol = '{{ $orderCurrencyCode }} ';
    </script>
    <script src="{{ hyperAsset('assets/js/order-acceptance-deadline.js') }}" defer></script>
    <script src="{{ asset('assets/js/whatsapp-invoice.js') }}" defer></script>
    <script src="{{ asset('assets/js/seller-orders.js') }}" defer></script>
    <script src="{{ hyperAsset('assets/js/seller-order-pickup.js') }}" defer></script>
    <script src="{{ asset('assets/js/seller-order-return.js') }}" defer></script>
@endpush
