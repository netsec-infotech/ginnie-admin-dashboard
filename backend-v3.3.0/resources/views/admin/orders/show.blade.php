@php
    use App\Enums\Order\OrderItemStatusEnum;
    use App\Enums\Order\OrderStatusEnum;
    use App\Support\Currency\CurrencyDisplay;
    use Carbon\Carbon;
    use Illuminate\Support\Str;
@endphp
@php
    $isOrderTerminal = in_array(
        $order['status'] ?? null,
        [
            OrderStatusEnum::DELIVERED(),
            OrderStatusEnum::CANCELLED(),
            OrderStatusEnum::FAILED(),
            OrderStatusEnum::REJECTED_BY_SELLER(),
        ],
        true,
    );
@endphp
@php
    $isScheduledOrder =
        !empty($isScheduledOrder) ||
        str_contains(request()->route()?->getName() ?? '', 'scheduled_orders') ||
        request()->boolean('is_scheduled');
    $activeMenuPage = $isScheduledOrder
        ? $menuAdmin['scheduled_orders']['active'] ?? ''
        : $menuAdmin['orders']['active'] ?? '';
    $ordersListTitle = $isScheduledOrder ? __('labels.scheduled_orders') : __('labels.orders');
    $ordersListRoute = $isScheduledOrder ? route('admin.scheduled_orders.index') : route('admin.orders.index');
    $invoiceRoute = $isScheduledOrder
        ? route('admin.scheduled_orders.invoice', ['id' => $order['uuid']])
        : route('admin.orders.invoice', ['id' => $order['uuid']]);
    $orderCurrencyCode = strtoupper((string) ($order['currency_code'] ?? $order['base_currency_code'] ?? ($systemSettings['currency'] ?? '')));
    $money = fn ($amount) => CurrencyDisplay::format($amount, $orderCurrencyCode, $systemSettings['currencySymbol'] ?? null);
@endphp
@extends('layouts.admin.app', ['page' => $activeMenuPage])
@section('title', __('labels.order_details'))

@section('header_data')
    @php
        $page_title = __('labels.order_details');
        $page_pretitle = __('labels.admin') . ' ' . $ordersListTitle;
    @endphp
@endsection

@php
    $breadcrumbs = [
        ['title' => __('labels.home'), 'url' => route('admin.dashboard')],
        ['title' => $ordersListTitle, 'url' => $ordersListRoute],
        ['title' => __('labels.order_details'), 'url' => ''],
    ];
@endphp

@section('admin-content')
    <div class="page-wrapper">
        <div class="page-header d-print-none">
            <div class="container-xl">
                <div class="row g-2 align-items-center">
                    <div class="col-12 col-lg-auto">
                        <div class="page-pretitle">{{ __('labels.admin') }} · {{ $ordersListTitle }}</div>
                        <h2 class="page-title d-flex align-items-center flex-wrap gap-2">
                            <span>{{ __('labels.order') }} #{{ $order['id'] }}</span>
                            <span class="badge {{ $order['status'] }} text-uppercase">
                                {{ Str::ucfirst(Str::replace('_', ' ', $order['status'])) }}
                            </span>
                            @if (!empty($order['is_rush_order']))
                                <span class="badge bg-orange-lt text-orange">
                                    <i class="ti ti-bolt me-1"></i>{{ __('labels.is_rush_order') ?? 'Rush' }}
                                </span>
                            @endif
                            @if (!empty($order['is_flagged']) && ($order['delivery_type'] ?? '') !== 'pickup')
                                <span class="badge bg-danger-lt text-white"
                                    title="{{ collect(array_keys((array) ($order['escalation_reasons'] ?? [])))->map(fn ($r) => __('labels.' . $r) !== 'labels.' . $r ? __('labels.' . $r) : Str::title(str_replace('_', ' ', $r)))->join(', ') }}"
                                    data-bs-toggle="tooltip">
                                    <i class="ti ti-alert-triangle me-1"></i>{{ __('labels.flagged') }}
                                </span>
                            @endif
                        </h2>
                        <div class="text-secondary small mt-1 d-flex flex-wrap gap-3">
                            <span><i class="ti ti-user me-1"></i>{{ $order['billing_name'] ?? '—' }}</span>
                            <span><i
                                    class="ti ti-credit-card me-1"></i>{{ Str::upper($order['payment_method'] ?? '—') }}</span>
                            <span><i class="ti ti-clock me-1"></i>{{ $order['created_at'] ?? '—' }}</span>
                            @if (!empty($order['delivery_zone']['name']))
                                <span><i class="ti ti-map-pin me-1"></i>{{ $order['delivery_zone']['name'] }}</span>
                            @endif
                            @if (!empty($order['uuid']))
                                <span class="font-monospace text-truncate" title="{{ $order['uuid'] }}">
                                    <i class="ti ti-hash me-1"></i>{{ Str::limit($order['uuid'], 24, '…') }}
                                </span>
                            @endif
                        </div>
                    </div>

                    <div class="col-auto ms-auto d-flex align-items-center gap-3">
                        <div class="text-end">
                            <div class="text-secondary small">{{ __('labels.total') }}</div>
                            <div class="h2 mb-0">
                                {{ $money((float) ($order['final_total'] ?? 0)) }}
                            </div>
                        </div>
                    </div>

                    <div class="col-12 col-lg-auto d-print-none">
                        <div class="btn-list" id="adminOrderActions" data-order-id="{{ $order['id'] }}">
                            @if (
                                ($editPermission ?? false) &&
                                    ($order['status'] ?? '') === \App\Enums\Order\OrderStatusEnum::PENDING() &&
                                    ($order['delivery_type'] ?? '') !== 'pickup')
                                <button type="button" class="btn btn-warning" id="btnMarkPaymentReceived"
                                    data-order-id="{{ $order['id'] }}">
                                    <i class="ti ti-cash me-1"></i>{{ __('labels.mark_payment_received') }}
                                </button>
                            @endif
                            @if (($editPermission ?? false) && !$isOrderTerminal && ($order['delivery_type'] ?? '') !== 'pickup')
                                <button type="button" class="btn btn-danger" data-bs-toggle="modal"
                                    data-bs-target="#adminForceCancelModal">
                                    <i class="ti ti-ban me-1"></i>{{ __('labels.force_cancel_item') }}
                                </button>
                            @endif
                            <a href="{{ $invoiceRoute }}" target="_blank" class="btn btn-outline-primary">
                                <i class="ti ti-invoice me-1"></i>{{ __('labels.download_invoice') }}
                            </a>
                            <a href="{{ $ordersListRoute }}" class="btn btn-outline-secondary">
                                <i
                                    class="ti ti-arrow-left me-1"></i>{{ $isScheduledOrder ? __('labels.scheduled_orders') : __('labels.back_to_orders') }}
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        @if (!empty($order['is_flagged']) && ($order['delivery_type'] ?? '') !== 'pickup')
            @php
                // Build a store_id → store_name lookup from the order's items so
// multi-vendor flags can call out the laggard store(s) by name
// instead of by id.
$storeNamesById = collect($order['items'] ?? [])
    ->map(fn($it) => $it['store'] ?? null)
    ->filter()
    ->keyBy('id')
    ->map(fn($s) => $s['name'] ?? null)
    ->all();

$resolveStoreNames = function (array $ids) use ($storeNamesById): string {
    $names = collect($ids)->map(fn($id) => $storeNamesById[$id] ?? null)->filter()->values();
    if ($names->isEmpty()) {
        return __('labels.one_or_more_stores');
    }
    if ($names->count() === 1) {
        return $names->first();
    }
    return $names->slice(0, -1)->join(', ') . ' ' . __('labels.and') . ' ' . $names->last();
};

$reasonsPayload = (array) ($order['escalation_reasons'] ?? []);
$reasonLines = [];
foreach ($reasonsPayload as $code => $payload) {
    $sinceIso = is_array($payload) ? $payload['since'] ?? ($payload['flagged_at'] ?? null) : null;
    $sinceCarbon = $sinceIso ? Carbon::parse($sinceIso) : null;
    $for = $sinceCarbon?->diffForHumans(null, true); // "17 minutes"
    $storeIds =
        is_array($payload) && !empty($payload['store_ids']) ? (array) $payload['store_ids'] : [];

    $reasonLines[] = match ($code) {
        'seller_unresponsive' => $for
            ? (!empty($storeIds)
                ? __('labels.flag_reason_seller_unresponsive_named_for', [
                    'stores' => $resolveStoreNames($storeIds),
                    'for' => $for,
                ])
                : __('labels.flag_reason_seller_unresponsive_for', ['for' => $for]))
            : __('labels.seller_unresponsive'),
        'no_rider_available' => $for
            ? __('labels.flag_reason_no_rider_available_for', ['for' => $for])
            : __('labels.no_rider_available'),
        'return_unconfirmed' => $for
            ? __('labels.flag_reason_return_unconfirmed_for', ['for' => $for])
            : __('labels.return_unconfirmed'),
        default => __('labels.' . $code) !== 'labels.' . $code
            ? __('labels.' . $code)
            : Str::title(str_replace('_', ' ', $code)),
                    };
                }
            @endphp
            <div class="container-xl mt-2">
                <div class="alert alert-danger d-flex align-items-start mb-0" role="alert">
                    <i class="ti ti-alert-triangle me-2 mt-1"></i>
                    <div class="flex-fill">
                        <strong>{{ __('labels.escalation_flagged') }}</strong>
                        @if (!empty($reasonLines))
                            <ul class="mb-0 mt-1 ps-3">
                                @foreach ($reasonLines as $line)
                                    <li>{{ $line }}</li>
                                @endforeach
                            </ul>
                        @endif
                    </div>
                </div>
            </div>
        @endif
        {{-- END PAGE HEADER --}}

        <div class="page-body">
            <div class="container-xl">
                <div class="row row-cards">
                    <div class="col-12 col-lg-6">
                        @php
                            $paymentStatusValue = strtolower((string) ($order['payment_status'] ?? ''));
                            $paymentBadgeClass = match (true) {
                                in_array($paymentStatusValue, ['paid', 'completed', 'success'], true) => 'bg-green-lt',
                                in_array($paymentStatusValue, ['failed', 'declined'], true) => 'bg-red-lt',
                                in_array($paymentStatusValue, ['refunded', 'partially_refunded'], true) => 'bg-blue-lt',
                                default => 'bg-yellow-lt',
                            };
                            $statusStripClass = match (true) {
                                in_array($order['status'] ?? '', ['cancelled', 'rejected_by_seller', 'failed'], true)
                                    => 'bg-red',
                                in_array($order['status'] ?? '', ['delivered'], true) => 'bg-success',
                                in_array($order['status'] ?? '', ['out_for_delivery', 'collected', 'assigned'], true)
                                    => 'bg-blue',
                                default => 'bg-primary',
                            };
                        @endphp
                        <div class="card">
                            <div class="card-status-start {{ $statusStripClass }}"></div>
                            <div class="card-header">
                                <h3 class="card-title">
                                    <i class="ti ti-receipt me-1"></i>{{ __('labels.order_summary') }}
                                </h3>
                            </div>
                            <div class="card-body">
                                <div class="datagrid">
                                    <div class="datagrid-item">
                                        <div class="datagrid-title">{{ __('labels.order_number') }}</div>
                                        <div class="datagrid-content">#{{ $order['id'] }}</div>
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
                                            <span class="badge {{ $paymentBadgeClass }}">
                                                {{ $order['payment_status'] }}
                                            </span>
                                        </div>
                                    </div>
                                    <div class="datagrid-item">
                                        <div class="datagrid-title">{{ __('labels.final_total') }}</div>
                                        <div class="datagrid-content fw-semibold">
                                            {{ $money($order['final_total']) }}
                                        </div>
                                    </div>
                                    <div class="datagrid-item">
                                        <div class="datagrid-title">{{ __('labels.uuid') }}</div>
                                        <div class="datagrid-content text-truncate font-monospace small"
                                            title="{{ $order['uuid'] }}">
                                            {{ $order['uuid'] }}
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        {{-- Customer card — avatar-led layout, mobile is a tel:
                            link, email is a mailto: link. Same visual rhythm
                            as the Delivery card so the right column reads
                            cleanly top-to-bottom. --}}
                        <div class="card mt-3">
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
                                            @if (!empty($order['email']))
                                                <a href="mailto:{{ $order['email'] }}" class="text-decoration-none">
                                                    <i class="ti ti-mail me-1"></i>{{ $order['email'] }}
                                                </a>
                                            @endif
                                            @if (!empty($order['billing_phone']))
                                                <a href="tel:{{ $order['billing_phone'] }}" class="text-decoration-none">
                                                    <i class="ti ti-phone me-1"></i>{{ $order['billing_phone'] }}
                                                </a>
                                            @endif
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        {{-- Shipping address — map-pin lead icon, recipient name
                             called out separately when it differs from the
                             billing name. --}}
                        @if (($order['delivery_type'] ?? '') !== 'pickup')
                            <div class="card mt-3">
                                <div class="card-header">
                                    <h3 class="card-title">
                                        <i class="ti ti-map-pin me-1"></i>{{ __('labels.shipping_address') }}
                                    </h3>
                                </div>
                                <div class="card-body">
                                    @if (!empty($order['shipping_name']) && $order['shipping_name'] !== ($order['billing_name'] ?? null))
                                        <div class="fw-semibold text-capitalize mb-1">
                                            <i class="ti ti-package me-1 text-secondary"></i>{{ $order['shipping_name'] }}
                                        </div>
                                    @endif
                                    <address class="mb-2 small text-secondary">
                                        {{ $order['shipping_address_1'] }}@if ($order['shipping_address_2'])
                                            , {{ $order['shipping_address_2'] }}
                                        @endif
                                        <br>
                                        @if ($order['shipping_landmark'])
                                            {{ $order['shipping_landmark'] }}<br>
                                        @endif
                                        {{ $order['shipping_city'] }}
                                        , {{ $order['shipping_state'] }} {{ $order['shipping_zip'] }}<br>
                                        {{ $order['shipping_country'] }}
                                    </address>
                                    @if (!empty($order['shipping_phone']))
                                        <a href="tel:{{ $order['shipping_phone'] }}" class="text-decoration-none small">
                                            <i class="ti ti-phone me-1"></i>{{ $order['shipping_phone'] }}
                                        </a>
                                    @endif
                                </div>
                            </div>
                        @endif
                        @if (!empty($order['order_note']) && ($order['delivery_type'] ?? '') !== 'pickup')
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

                    <!-- Customer Information Card -->
                    <div class="col-12 col-lg-6">
                        {{-- Delivery card — assigned rider, zone, ETA. Empty state
                        when unassigned. The card-header reserves space on the
                        right for an inline "Reassign rider" admin button to
                        land in the next phase without re-shuffling layout. --}}
                        @if (($order['delivery_type'] ?? '') === 'pickup')
                            <div class="card">
                                <div class="card-header">
                                    <h3 class="card-title">
                                        <i
                                            class="ti ti-package me-1"></i>{{ __('labels.pickup_information') ?? 'Pickup Information' }}
                                    </h3>
                                </div>
                                <div class="card-body">
                                    <div class="datagrid">
                                        <div class="datagrid-item">
                                            <div class="datagrid-title">
                                                {{ __('labels.delivery_type') ?? 'Delivery Type' }}</div>
                                            <div class="datagrid-content">
                                                <span
                                                    class="badge bg-green-lt">{{ __('labels.delivery_type_pickup') ?? 'Pickup' }}</span>
                                            </div>
                                        </div>
                                        <div class="datagrid-item">
                                            <div class="datagrid-title">
                                                {{ __('labels.ready_for_pickup') ?? 'Ready For Pickup' }}</div>
                                            <div class="datagrid-content">
                                                {{ $order['ready_for_pickup_at'] ?? '—' }}
                                            </div>
                                        </div>
                                        <div class="datagrid-item">
                                            <div class="datagrid-title">{{ __('labels.picked_up_at') ?? 'Picked Up At' }}
                                            </div>
                                            <div class="datagrid-content">
                                                {{ $order['picked_up_at'] ?? '—' }}
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        @else
                            <div class="card">
                                <div class="card-header">
                                    <h3 class="card-title">
                                        <i class="ti ti-truck-delivery me-1"></i>{{ __('labels.delivery_information') }}
                                    </h3>
                                    @php
                                        $canReassignRider =
                                            ($editPermission ?? false) &&
                                            in_array(
                                                $order['status'] ?? null,
                                                [OrderStatusEnum::READY_FOR_PICKUP(), OrderStatusEnum::ASSIGNED()],
                                                true,
                                            );
                                    @endphp
                                    <div class="card-actions" id="adminDeliveryActions">
                                        @if ($canReassignRider)
                                            <button type="button" class="btn btn-sm btn-outline-info"
                                                data-bs-toggle="modal" data-bs-target="#adminReassignRiderModal">
                                                <i
                                                    class="ti ti-users me-1"></i>{{ empty($order['delivery_boy']['id']) ? __('labels.assign_rider') : __('labels.reassign_rider') }}
                                            </button>
                                        @endif
                                    </div>
                                </div>
                                <div class="card-body">
                                    @if (!empty($order['delivery_boy']) && !empty($order['delivery_boy']['id']))
                                        <div class="d-flex align-items-start mb-3">
                                            <span class="avatar avatar-md me-3 bg-blue-lt">
                                                <i class="ti ti-motorbike"></i>
                                            </span>
                                            <div class="flex-fill">
                                                <div class="fw-semibold text-capitalize">
                                                    {{ $order['delivery_boy']['full_name'] ?? __('labels.delivery_boy') }}
                                                    @if (!empty($order['delivery_boy']['is_blocked']))
                                                        <span class="badge bg-danger-lt text-danger ms-1">
                                                            <i class="ti ti-lock me-1"></i>{{ __('labels.blocked') }}
                                                        </span>
                                                    @endif
                                                </div>
                                                <div class="text-secondary small">
                                                    @if (!empty($order['delivery_boy']['email']))
                                                        <span class="me-3"><i
                                                                class="ti ti-mail me-1"></i>{{ $order['delivery_boy']['email'] }}</span>
                                                    @endif
                                                    @if (!empty($order['delivery_boy']['mobile']))
                                                        <a href="tel:{{ $order['delivery_boy']['mobile'] }}"
                                                            class="text-decoration-none">
                                                            <i
                                                                class="ti ti-phone me-1"></i>{{ $order['delivery_boy']['mobile'] }}
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
                                        @if (!empty($order['scheduled_date']))
                                            <div class="datagrid-item">
                                                <div class="datagrid-title">
                                                    {{ __('labels.scheduled_date') ?? 'Scheduled Date' }}</div>
                                                <div class="datagrid-content">
                                                    {{ \Carbon\Carbon::parse($order['scheduled_date'])->format('M d, Y') }}
                                                </div>
                                            </div>
                                            @if (!empty($order['scheduled_time_slot_label']))
                                                <div class="datagrid-item">
                                                    <div class="datagrid-title">
                                                        {{ __('labels.scheduled_time') ?? 'Scheduled Time' }}</div>
                                                    <div class="datagrid-content">
                                                        <span class="badge bg-blue-lt">
                                                            <i
                                                                class="ti ti-clock me-1"></i>{{ $order['scheduled_time_slot_label'] }}
                                                        </span>
                                                    </div>
                                                </div>
                                            @endif
                                        @endif
                                        @if (!empty($order['delivery_route']))
                                            <div class="datagrid-item">
                                                <div class="datagrid-title">{{ __('labels.total_distance') }}</div>
                                                <div class="datagrid-content">
                                                    {{ number_format((float) ($order['delivery_route']['total_distance'] ?? 0), 2) }}
                                                    {{ __('labels.km') }}
                                                </div>
                                            </div>
                                            <div class="datagrid-item">
                                                <div class="datagrid-title">{{ __('labels.stops') }}</div>
                                                <div class="datagrid-content">
                                                    {{ (int) ($order['delivery_route']['stops_count'] ?? 0) }}
                                                </div>
                                            </div>
                                        @endif
                                    </div>

                                    {{-- Rider earnings breakdown — pulled from the active
                                     DeliveryBoyAssignment row when present, otherwise
                                     a live calculation off the zone + route + tips. Total is
                                     what the rider currently stands to earn for this
                                     trip, and it's what the Force Cancel pay-rider
                                     toggle controls. --}}
                                    @if (!empty($order['delivery_assignment']))
                                        @php $da = $order['delivery_assignment']; @endphp
                                        <hr class="my-3">
                                        <div class="d-flex align-items-center justify-content-between mb-2">
                                            <div class="fw-semibold">
                                                <i
                                                    class="ti ti-coin me-1 text-yellow"></i>{{ __('labels.rider_earnings') }}
                                            </div>
                                            @if (!empty($da['payment_status']))
                                                <span
                                                    class="badge bg-{{ $da['payment_status'] === 'paid' ? 'green-lt text-green' : 'yellow-lt text-yellow' }} text-uppercase">
                                                    {{ Str::ucfirst(Str::replace('_', ' ', $da['payment_status'])) }}
                                                </span>
                                            @elseif(empty($da['has_persisted_row']))
                                                <span class="badge bg-secondary-lt text-secondary text-uppercase">
                                                    {{ __('labels.rider_earnings_projected') }}
                                                </span>
                                            @endif
                                        </div>
                                        <div class="datagrid">
                                            <div class="datagrid-item">
                                                <div class="datagrid-title">{{ __('labels.base_fee') }}</div>
                                                <div class="datagrid-content">
                                                    {{ $money($da['base_fee']) }}
                                                </div>
                                            </div>
                                            <div class="datagrid-item">
                                                <div class="datagrid-title">{{ __('labels.per_store_pickup_fee') }}</div>
                                                <div class="datagrid-content">
                                                    {{ $money($da['per_store_pickup_fee']) }}
                                                </div>
                                            </div>
                                            <div class="datagrid-item">
                                                <div class="datagrid-title">{{ __('labels.distance_based_fee') }}</div>
                                                <div class="datagrid-content">
                                                    {{ $money($da['distance_based_fee']) }}
                                                </div>
                                            </div>
                                            <div class="datagrid-item">
                                                <div class="datagrid-title">{{ __('labels.per_order_incentive') }}</div>
                                                <div class="datagrid-content">
                                                    {{ $money($da['per_order_incentive']) }}
                                                </div>
                                            </div>
                                            @if ((float) ($da['tip_amount'] ?? 0) > 0)
                                                <div class="datagrid-item">
                                                    <div class="datagrid-title">{{ __('labels.rider_tips') }}</div>
                                                    <div class="datagrid-content text-success fw-semibold">
                                                        {{ $money($da['tip_amount']) }}
                                                    </div>
                                                </div>
                                            @endif
                                        </div>
                                        <div
                                            class="d-flex align-items-center justify-content-between mt-2 pt-2 border-top">
                                            <div class="fw-semibold">{{ __('labels.total_earnings') }}</div>
                                            <div class="h4 mb-0 text-green">
                                                {{ $money($da['total_earnings']) }}
                                            </div>
                                        </div>
                                        @if (!empty($da['assigned_at']))
                                            <div class="text-secondary small mt-1">
                                                <i class="ti ti-clock me-1"></i>{{ __('labels.assigned_at') }}:
                                                {{ $da['assigned_at'] }}
                                                @if (!empty($da['paid_at']))
                                                    · <i class="ti ti-cash me-1"></i>{{ __('labels.paid_at') }}:
                                                    {{ $da['paid_at'] }}
                                                @endif
                                            </div>
                                        @endif

                                        {{-- Settle Earnings panel — visible only when this assignment is
                                         CANCELLED_BY_ADMIN with payment_status=PENDING. Admin chooses
                                         Approve (pay total_earnings) or Reject (zero out). Both require
                                         a remark which lands in the order_audit_logs. --}}
                                        @if (!empty($da['awaiting_settle']) && ($editPermission ?? false))
                                            <hr class="my-3">
                                            <div class="alert flex-column alert-warning mb-3" role="alert">
                                                <div class="fw-semibold mb-1">
                                                    <i
                                                        class="ti ti-cash-banknote me-1"></i>{{ __('labels.settle_earnings') }}
                                                </div>
                                                <div class="small">
                                                    {{ __('labels.settle_help_text') }}
                                                </div>
                                            </div>
                                            <div class="d-flex gap-2 flex-wrap">
                                                <button type="button" class="btn btn-success admin-settle-rider-trigger"
                                                    data-bs-toggle="modal" data-bs-target="#adminSettleRiderEarningsModal"
                                                    data-decision="approve" data-assignment-id="{{ $da['id'] }}"
                                                    data-amount="{{ number_format((float) $da['total_earnings'], 2) }}"
                                                    data-currency="{{ $orderCurrencyCode }} ">
                                                    <i class="ti ti-check me-1"></i>{{ __('labels.settle_approve') }}
                                                    ({{ $money($da['total_earnings']) }})
                                                </button>
                                                <button type="button"
                                                    class="btn btn-outline-danger admin-settle-rider-trigger"
                                                    data-bs-toggle="modal" data-bs-target="#adminSettleRiderEarningsModal"
                                                    data-decision="reject" data-assignment-id="{{ $da['id'] }}"
                                                    data-amount="{{ number_format((float) $da['total_earnings'], 2) }}"
                                                    data-currency="{{ $orderCurrencyCode }} ">
                                                    <i class="ti ti-x me-1"></i>{{ __('labels.settle_reject') }}
                                                </button>
                                            </div>
                                        @endif
                                    @endif
                                </div>
                            </div>
                        @endif

                        {{-- Live tracking map — shown only when rider is assigned and delivery is active --}}
                        @php
                            $trackableStatuses = [
                                OrderStatusEnum::ASSIGNED(),
                                OrderStatusEnum::COLLECTED(),
                                OrderStatusEnum::OUT_FOR_DELIVERY(),
                            ];
                            $showTrackingMap =
                                !empty($order['delivery_boy_id']) &&
                                in_array($order['status'] ?? null, $trackableStatuses, true);
                        @endphp
                        @if ($showTrackingMap)
                            <div class="card mt-3" id="liveTrackingCard">
                                <div class="card-header">
                                    <h3 class="card-title">
                                        <i class="ti ti-map-pin me-1"></i>{{ __('labels.live_tracking') }}
                                    </h3>
                                    <div class="card-actions">
                                        <span class="badge bg-green-lt" id="trackingStatusBadge">
                                            <span class="badge-dot bg-green me-1"></span>{{ __('labels.live') }}
                                        </span>
                                    </div>
                                </div>
                                <div class="card-body p-0">
                                    <div id="liveTrackingMap"
                                        style="height: 400px; width: 100%; border-radius: 0 0 4px 4px;"></div>
                                </div>
                            </div>
                        @endif
                    </div>

                    @php
                        $itemsForStores = $order['items'] ?? [];
                        $storeBuckets = [];
                        foreach ($itemsForStores as $itm) {
                            $sid = $itm['store']['id'] ?? null;
                            if (!$sid) {
                                continue;
                            }
                            if (!isset($storeBuckets[$sid])) {
                                $storeBuckets[$sid] = [
                                    'store' => $itm['store'],
                                    'item_count' => 0,
                                    'subtotal' => 0.0,
                                ];
                            }
                            $storeBuckets[$sid]['item_count']++;
                            $storeBuckets[$sid]['subtotal'] += (float) ($itm['sub_total'] ?? 0);
                        }
                    @endphp
                    @if (!empty($storeBuckets))
                        <div class="col-12 mt-3">
                            <div class="card">
                                <div class="card-header">
                                    <h3 class="card-title">
                                        <i class="ti ti-building-store me-1"></i>{{ __('labels.stores_and_sellers') }}
                                        <span class="badge bg-secondary-lt ms-2">{{ count($storeBuckets) }}</span>
                                    </h3>
                                </div>
                                <div class="list-group list-group-flush">
                                    @foreach ($storeBuckets as $bucket)
                                        @php $st = $bucket['store']; @endphp
                                        <div class="list-group-item">
                                            <div class="row g-3 align-items-center">
                                                <div class="col-auto">
                                                    <span class="avatar avatar-md bg-orange-lt">
                                                        <i class="ti ti-building-store"></i>
                                                    </span>
                                                </div>
                                                <div class="col">
                                                    <div class="fw-semibold text-capitalize">{{ $st['name'] ?? '—' }}
                                                    </div>
                                                    <div class="text-secondary small d-flex flex-wrap gap-3 mt-1">
                                                        @if (!empty($st['contact_number']))
                                                            <a href="tel:{{ $st['contact_number'] }}"
                                                                class="text-decoration-none">
                                                                <i
                                                                    class="ti ti-phone me-1"></i>{{ $st['contact_number'] }}
                                                            </a>
                                                        @endif
                                                        @if (!empty($st['contact_email']))
                                                            <a href="mailto:{{ $st['contact_email'] }}"
                                                                class="text-decoration-none">
                                                                <i class="ti ti-mail me-1"></i>{{ $st['contact_email'] }}
                                                            </a>
                                                        @endif
                                                        @if (!empty($st['city']) || !empty($st['state']))
                                                            <span><i
                                                                    class="ti ti-map-pin me-1"></i>{{ trim(($st['city'] ?? '') . ', ' . ($st['state'] ?? ''), ', ') }}</span>
                                                        @endif
                                                    </div>
                                                    @if (!empty($st['seller']))
                                                        <div class="text-secondary small mt-1">
                                                            <span class="me-2"><i
                                                                    class="ti ti-user-cog me-1"></i>{{ __('labels.seller') }}:</span>
                                                            <span
                                                                class="text-capitalize">{{ $st['seller']['name'] ?? '—' }}</span>
                                                            @if (!empty($st['seller']['mobile']))
                                                                · <a href="tel:{{ $st['seller']['mobile'] }}"
                                                                    class="text-decoration-none">{{ $st['seller']['mobile'] }}</a>
                                                            @endif
                                                            @if (!empty($st['seller']['email']))
                                                                · <a href="mailto:{{ $st['seller']['email'] }}"
                                                                    class="text-decoration-none">{{ $st['seller']['email'] }}</a>
                                                            @endif
                                                        </div>
                                                    @endif
                                                </div>
                                                <div class="col-auto text-end">
                                                    <div class="text-secondary small">
                                                        {{ $bucket['item_count'] }}
                                                        {{ $bucket['item_count'] === 1 ? __('labels.item') : __('labels.items') }}
                                                    </div>
                                                    <div class="fw-semibold">
                                                        {{ $money($bucket['subtotal']) }}
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    @endforeach
                                </div>
                            </div>
                        </div>
                    @endif

                    @php
                        $hasActionCol =
                            ($editPermission ?? false) &&
                            !$isOrderTerminal &&
                            ($order['delivery_type'] ?? '') !== 'pickup';
                        $bulkEnabled = $hasActionCol;
                    @endphp

                    <!-- Order Items Card -->
                    <div class="col-12 mt-3">
                        <div class="card">
                            <div class="card-header">
                                <h3 class="card-title">{{ __('labels.order_items') }}</h3>
                                @if ($bulkEnabled)
                                    <div class="card-actions">
                                        <button type="button" class="btn btn-sm btn-primary" id="adminBulkUpdateBtn"
                                            data-bs-toggle="modal" data-bs-target="#adminBulkUpdateStatusModal" disabled>
                                            <i class="ti ti-refresh me-1"></i>{{ __('labels.bulk_update_status') }}
                                            <span class="badge bg-white text-primary ms-2 d-none"
                                                id="adminBulkSelectedCount">0</span>
                                        </button>
                                    </div>
                                @endif
                            </div>
                            <div class="card-body">
                                <div class="table-responsive">
                                    <table class="table table-vcenter card-table">
                                        <thead>
                                            <tr>
                                                @if ($bulkEnabled)
                                                    <th width="30">
                                                        <input type="checkbox" class="form-check-input"
                                                            id="adminSelectAllItems"
                                                            aria-label="{{ __('labels.select_all') }}">
                                                    </th>
                                                @endif
                                                <th>{{ __('labels.order_item_id') }}</th>
                                                <th>{{ __('labels.store_name') }}</th>
                                                <th>{{ __('labels.product') }}</th>
                                                <th>{{ __('labels.variant') }}</th>
                                                <th>{{ __('labels.price') }}</th>
                                                <th>{{ __('labels.status') }}</th>
                                                <th>{{ __('labels.quantity') }}</th>
                                                <th>{{ __('labels.subtotal') }}</th>
                                                @if ($hasActionCol)
                                                    <th>{{ __('labels.actions') }}</th>
                                                @endif
                                            </tr>
                                        </thead>
                                        <tbody>
                                            @foreach ($order['items'] as $item)
                                                @php
                                                    $itemId = $item['orderItem']['id'] ?? null;
                                                    $hasTransitions = !empty($itemTransitions[$itemId] ?? []);
                                                @endphp
                                                <tr>
                                                    @if ($bulkEnabled)
                                                        <td>
                                                            <input type="checkbox"
                                                                class="form-check-input admin-item-checkbox"
                                                                name="item_ids[]" value="{{ $itemId }}"
                                                                @disabled(!$hasTransitions)>
                                                        </td>
                                                    @endif
                                                    <td>#{{ $item['orderItem']['id'] ?? 'N/A' }}</td>
                                                    <td>{{ $item['store']['name'] ?? 'N/A' }}</td>
                                                    <td>{{ $item['product']['title'] ?? 'N/A' }}
                                                        @if (!empty($item['attachments']))
                                                            <br class="mt-7">
                                                            <span class="fw-medium">Attachments</span>
                                                            <ul class="list-unstyled mb-0">
                                                                @foreach ($item['attachments'] as $attachment)
                                                                    <li><a href="{{ $attachment }}" target="_blank"
                                                                            data-fslightbox="gallery">view</a>
                                                                    </li>
                                                                @endforeach
                                                            </ul>
                                                        @endif
                                                        @if (!empty($item['addons']))
                                                            <div class="mt-2">
                                                                <span class="fw-medium">{{ __('labels.addons') }}</span>
                                                                <ul class="list-unstyled small mb-0 ps-3">
                                                                    @foreach ($item['addons'] as $addon)
                                                                        <li>
                                                                            @if (!empty($addon['group']['title']))
                                                                                <span
                                                                                    class="text-muted">{{ $addon['group']['title'] }}
                                                                                    —</span>
                                                                            @endif
                                                                            {{ $addon['item']['title'] ?? '—' }}
                                                                            <span class="text-muted">·
                                                                                {{ $money($addon['price']) }}</span>
                                                                        </li>
                                                                    @endforeach
                                                                </ul>
                                                            </div>
                                                        @endif
                                                    </td>
                                                    <td>{{ $item['variant']['title'] ?? 'N/A' }}</td>
                                                    <td>{{ $money($item['price'] + $item['tax_amount']) }}
                                                    </td>
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
                                                    </td>
                                                    <td>{{ $item['quantity'] }}</td>
                                                    <td>
                                                        {{ $money($item['sub_total']) }}
                                                        @if (!empty($item['addons_total']) && (float) $item['addons_total'] > 0)
                                                            <div class="small text-muted">
                                                                {{ __('labels.addons_total') }}
                                                                :
                                                                {{ $money($item['addons_total']) }}
                                                            </div>
                                                        @endif
                                                    </td>
                                                    @if ($hasActionCol)
                                                        <td>
                                                            @php
                                                                $itemId = $item['orderItem']['id'] ?? null;
                                                                $transitions = $itemTransitions[$itemId] ?? [];
                                                            @endphp
                                                            @if (($order['delivery_type'] ?? '') === 'pickup' && !empty($transitions))
                                                                <div class="d-inline-flex gap-1 mb-1 me-1">
                                                                    @foreach ($transitions as $transition)
                                                                        <button type="button"
                                                                            class="btn btn-sm btn-{{ $transition['value'] === 'reject' ? 'outline-danger' : ($transition['value'] === 'accept' ? 'success' : 'primary') }} admin-update-item-status"
                                                                            data-item-id="{{ $itemId }}"
                                                                            data-status="{{ $transition['value'] }}"
                                                                            data-label="{{ $transition['label'] }}"
                                                                            data-acting-as="{{ $transition['acting_as'] }}">
                                                                            {{ $transition['label'] }}
                                                                        </button>
                                                                    @endforeach
                                                                </div>
                                                            @elseif(!empty($transitions))
                                                                <div class="dropdown">
                                                                    <button
                                                                        class="btn btn-sm btn-outline-primary dropdown-toggle"
                                                                        type="button" data-bs-toggle="dropdown"
                                                                        aria-expanded="false">
                                                                        <i
                                                                            class="ti ti-refresh me-1"></i>{{ __('labels.update_status') }}
                                                                    </button>
                                                                    <div class="dropdown-menu">
                                                                        @foreach ($transitions as $transition)
                                                                            <a href="javascript:void(0)"
                                                                                class="dropdown-item admin-update-item-status"
                                                                                data-item-id="{{ $itemId }}"
                                                                                data-status="{{ $transition['value'] }}"
                                                                                data-label="{{ $transition['label'] }}"
                                                                                data-acting-as="{{ $transition['acting_as'] }}">
                                                                                <i
                                                                                    class="ti ti-{{ $transition['acting_as'] === 'rider' ? 'bike' : 'building-store' }} me-1 text-muted"></i>
                                                                                {{ $transition['label'] }}
                                                                                <span
                                                                                    class="text-muted small ms-1">({{ $transition['acting_as'] === 'rider' ? __('labels.as_rider') : __('labels.as_seller') }})</span>
                                                                            </a>
                                                                        @endforeach
                                                                    </div>
                                                                </div>
                                                            @else
                                                                <span class="text-muted small">—</span>
                                                            @endif
                                                        </td>
                                                    @endif
                                                </tr>
                                            @endforeach
                                        </tbody>
                                        @php
                                            // Base small/large colspans; +1 each when the bulk-select column
                                            // is present (checkbox shares its visibility with the actions col).
                                            $colspanSmall = ($hasActionCol ? 6 : 5) + ($bulkEnabled ? 1 : 0);
                                            $colspanLarge = ($hasActionCol ? 7 : 6) + ($bulkEnabled ? 1 : 0);
                                        @endphp
                                        <tfoot>
                                            <tr>
                                                <td colspan="{{ $colspanSmall }}" class="text-end">
                                                    <strong>{{ __('labels.total') }}:</strong>
                                                </td>
                                                <td><strong>{{ collect($order['items'])->sum('quantity') }}</strong></td>
                                                <td>
                                                    <strong>{{ $money($order['subtotal']) }}</strong>
                                                </td>
                                            </tr>

                                            <tr>
                                                <td colspan="{{ $colspanLarge }}" class="text-end">
                                                    <b>{{ __('labels.shipping_handling') }}
                                                        :</b>
                                                </td>
                                                <td>{{ $money($order['delivery_charge']) }}
                                                </td>
                                            </tr>
                                            <tr>
                                                <td colspan="{{ $colspanLarge }}" class="text-end">
                                                    <b>{{ __('labels.handling_charges') }}:</b>
                                                </td>
                                                <td>{{ $money($order['handling_charges'] ?? 0) }}
                                                </td>
                                            </tr>
                                            <tr>
                                                <td colspan="{{ $colspanLarge }}" class="text-end">
                                                    <b>{{ __('labels.per_store_drop_off_fee') }}:</b>
                                                </td>
                                                <td>{{ $money($order['per_store_drop_off_fee'] ?? 0) }}
                                                </td>
                                            </tr>
                                            @php
                                                $riderTipAmount = (float) ($order['rider_tip_amount'] ?? 0);
                                                $grandTotal =
                                                    $order['subtotal'] +
                                                    $order['delivery_charge'] +
                                                    ($order['handling_charges'] ?? 0) +
                                                    ($order['per_store_drop_off_fee'] ?? 0);
                                                if ($riderTipAmount > 0) {
                                                    $grandTotal += $riderTipAmount;
                                                }
                                            @endphp
                                            @if ($riderTipAmount > 0)
                                                <tr>
                                                    <td colspan="{{ $colspanLarge }}" class="text-end">
                                                        <b>{{ __('labels.rider_tips') }}:</b>
                                                    </td>
                                                    <td>{{ $money($riderTipAmount) }}
                                                    </td>
                                                </tr>
                                            @endif
                                            <tr>
                                                <td colspan="{{ $colspanLarge }}" class="text-end">
                                                    <b>{{ __('labels.grand_total') }}:</b>
                                                </td>
                                                <td>
                                                    <b>{{ $money($grandTotal) }}</b>
                                                </td>
                                            </tr>
                                            @if ($order['wallet_balance'] > 0)
                                                <tr>
                                                    <td colspan="{{ $colspanLarge }}" class="text-end">
                                                        <b>{{ __('labels.wallet_used') }}:</b>
                                                    </td>
                                                    <td>
                                                        -
                                                        {{ $money($order['wallet_balance']) }}
                                                    </td>
                                                </tr>
                                            @endif
                                            @if ($order['promo_discount'] > 0)
                                                <tr>
                                                    <td colspan="{{ $colspanLarge }}" class="text-end">
                                                        <b>
                                                            {{ __('labels.promo_discount') }}
                                                            @if (!empty($order['promo_line']) && ($order['promo_line']['cashback_flag'] ?? false))
                                                                ({{ __('labels.cashback') }})

                                                                <span data-bs-toggle="tooltip" data-bs-placement="right"
                                                                    title="{{ __('messages.cashback_info_message') }}">
                                                                    <svg xmlns="http://www.w3.org/2000/svg" width="18"
                                                                        height="18" viewBox="0 0 24 24" fill="none"
                                                                        stroke="currentColor" stroke-width="2"
                                                                        stroke-linecap="round" stroke-linejoin="round"
                                                                        class="icon icon-tabler icons-tabler-outline icon-tabler-help-octagon">
                                                                        <path stroke="none" d="M0 0h24v24H0z"
                                                                            fill="none" />
                                                                        <path
                                                                            d="M12.802 2.165l5.575 2.389c.48 .206 .863 .589 1.07 1.07l2.388 5.574c.22 .512 .22 1.092 0 1.604l-2.389 5.575c-.206 .48 -.589 .863 -1.07 1.07l-5.574 2.388c-.512 .22 -1.092 .22 -1.604 0l-5.575 -2.389a2.036 2.036 0 0 1 -1.07 -1.07l-2.388 -5.574a2.036 2.036 0 0 1 0 -1.604l2.389 -5.575c.206 -.48 .589 -.863 1.07 -1.07l5.574 -2.388a2.036 2.036 0 0 1 1.604 0z" />
                                                                        <path d="M12 16v.01" />
                                                                        <path
                                                                            d="M12 13a2 2 0 0 0 .914 -3.782a1.98 1.98 0 0 0 -2.414 .483" />
                                                                    </svg>
                                                                </span>
                                                            @endif
                                                            <span
                                                                class="text-uppercase">({{ $order['promo_code'] }}):</span>
                                                        </b>
                                                    </td>
                                                    <td>
                                                        -
                                                        {{ $money($order['promo_discount']) }}
                                                    </td>
                                                </tr>
                                            @endif
                                            <tr>
                                                <td colspan="{{ $colspanLarge }}" class="text-end">
                                                    <b>{{ __('labels.total_payable') }}:</b>
                                                </td>
                                                <td>
                                                    <b>{{ $money($order['total_payable']) }}</b>
                                                </td>
                                            </tr>
                                        </tfoot>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>

                    @if (isset($auditLogs))
                        <div class="col-12 mt-3">
                            <div class="card">
                                <div class="card-header d-flex align-items-center">
                                    <h3 class="card-title mb-0">
                                        <i class="ti ti-history me-1"></i>{{ __('labels.activity_and_notes') }}
                                        <span class="badge bg-secondary-lt ms-2">{{ $auditLogs->count() }}</span>
                                    </h3>
                                    <div class="card-actions">
                                        @if ($editPermission ?? false)
                                            <button type="button" class="btn btn-primary btn-sm" data-bs-toggle="modal"
                                                data-bs-target="#adminAddNoteModal"
                                                adminAddNoteSubmit="{{ route($isScheduledOrder ? 'admin.scheduled_orders.add_note' : 'admin.orders.add_note', $order['id']) }}">
                                                <i class="ti ti-plus me-1"></i>{{ __('labels.add_note') }}
                                            </button>
                                        @endif
                                    </div>
                                </div>
                                @if ($auditLogs->isEmpty())
                                    <div class="card-body text-center py-4">
                                        <i class="ti ti-note-off fs-2 d-block mb-2 text-secondary"></i>
                                        <div class="text-secondary">{{ __('labels.audit_log_empty') }}</div>
                                    </div>
                                @else
                                    <div class="list-group list-group-flush">
                                        @foreach ($auditLogs as $log)
                                            @include('admin.orders.partials.audit-log-row', [
                                                'log' => $log,
                                            ])
                                        @endforeach
                                    </div>
                                @endif
                            </div>
                        </div>
                    @endif
                </div>

            </div>
        </div>
    </div>

    @if ($editPermission ?? false)
        @include('admin.orders.partials.add-note-modal')
        @include('admin.orders.partials.force-cancel-modal')
        @include('admin.orders.partials.reassign-rider-modal')
        @include('admin.orders.partials.admin-action-modals')
        @if (!$isOrderTerminal)
            @include('admin.orders.partials.bulk-update-status-modal')
        @endif
        @include('admin.orders.partials.settle-rider-earnings-modal')
    @endif
@endsection

@push('scripts')
    <script src="{{ asset('assets/js/order.js') }}"></script>
    @if ($editPermission ?? false)
        @php
            // Items the admin can pick to cancel — drop already-terminal ones
            // so the dropdown stays clean.
            $cancellableItemsForJs = collect($order['items'] ?? [])
                ->filter(
                    fn($item) => !in_array(
                        $item['orderItem']['status'] ?? '',
                        ['cancelled', 'rejected', 'failed', 'refunded'],
                        true,
                    ),
                )
                ->map(
                    fn($item) => [
                        'id' => $item['orderItem']['id'] ?? null,
                        'title' => $item['product']['title'] ?? '',
                        'status' => $item['orderItem']['status'] ?? '',
                        'store' => $item['store']['name'] ?? null,
                    ],
                )
                ->filter(fn($i) => !empty($i['id']))
                ->values()
                ->all();
        @endphp
        <script>
            window.adminOrderEndpoints = {
                addNote: '{{ url('admin/orders') }}/{{ $order['id'] ?? '' }}/note',
                forceCancelTpl: '{{ url('admin/orders') }}/__ID__/force-cancel',
                reassignRider: '{{ url('admin/orders') }}/{{ $order['id'] ?? '' }}/reassign-rider',
                riderSearch: '{{ route('admin.delivery-boys.search') }}',
                itemUpdateStatusTpl: '{{ url('admin/orders/items') }}/__ID__/update-status',
                itemsBulkUpdateStatus: '{{ url('admin/orders') }}/{{ $order['id'] ?? '' }}/items/bulk-update-status',
                markPaymentReceived: '{{ url('admin/orders') }}/{{ $order['id'] ?? '' }}/mark-payment-received',
                // {{-- {assignmentId} is the DeliveryBoyAssignment row id, not the order id. --}}
                settleRiderEarningsTpl: '{{ url('admin/orders/assignments') }}/__ID__/settle-rider-earnings',
            };
            // Per-item allowed transitions — used by the bulk modal to compute
            // the intersection of valid actions across the current selection.
            window.adminOrderItemTransitions = @json($itemTransitions ?? []);
            window.adminOrderContext = {
                deliveryZoneId: @json($order['delivery_zone']['id'] ?? null),
                hasRider: @json(!empty($order['delivery_boy_id'])),
                currentRider: @json(
                    !empty($order['delivery_boy'])
                        ? [
                            'value' => $order['delivery_boy']['id'],
                            'text' => trim(
                                ($order['delivery_boy']['full_name'] ?? '') .
                                    (!empty($order['delivery_boy']['email']) ? ' · ' . $order['delivery_boy']['email'] : '') .
                                    (!empty($order['delivery_boy']['mobile']) ? ' · ' . $order['delivery_boy']['mobile'] : '')),
                        ]
                        : null),
                // Surface the rider's total payable on this order so the Force
                // Cancel modal can show what the pay_rider switch controls.
                riderEarnings: @json(
                    !empty($order['delivery_assignment'])
                        ? [
                            'total' => (float) ($order['delivery_assignment']['total_earnings'] ?? 0),
                            'currency' => $orderCurrencyCode.' ',
                        ]
                        : null),
            };
            window.adminOrderItems = @json($cancellableItemsForJs);
        </script>
        <script src="{{ asset('assets/js/admin-order-notes.js') }}" defer></script>
        <script src="{{ asset('assets/js/whatsapp-invoice.js') }}" defer></script>
        <script src="{{ asset('assets/js/admin-orders.js') }}" defer></script>
    @endif
    @if ($showTrackingMap ?? false)
        <link rel="stylesheet" href="{{ asset('assets/vendor/leaflet/leaflet.css') }}" />
        <script src="{{ asset('assets/vendor/leaflet/leaflet.js') }}"></script>
        <script>
            window.__TRACKING_CONFIG__ = {
                url: '{{ route($isScheduledOrder ? 'admin.scheduled_orders.live_tracking' : 'admin.orders.live_tracking', $order['id'] ?? 0) }}',
                pollInterval: 15000,
            };
        </script>
        <script src="{{ asset('assets/js/order-live-tracking.js') }}" defer></script>
    @endif
@endpush
