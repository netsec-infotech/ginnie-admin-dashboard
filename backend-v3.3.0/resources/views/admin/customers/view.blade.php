@extends('layouts.admin.app', ['page' => $menuAdmin['customers']['active'] ?? '', 'sub_page' => $menuAdmin['customers']['route']['customers']['sub_active'] ?? null])

@section('title', $customer->name ?? __('labels.customer_details'))

@section('header_data')
    @php
        $page_title = $customer->name ?? __('labels.customer');
        $page_pretitle = __('labels.customer_details');
    @endphp
@endsection

@php
    $breadcrumbs = [
        ['title' => __('labels.home'), 'url' => route('admin.dashboard')],
        ['title' => __('labels.customers'), 'url' => route('admin.customers.index')],
        ['title' => $customer->name ?? __('labels.customer'), 'url' => null],
    ];
@endphp

@section('admin-content')
    <div class="page-wrapper">
        <!-- PAGE HEADER -->
        <div class="page-header d-print-none">
            <div class="row g-2 align-items-center">
                <div class="col">
                    <h2 class="page-title text-capitalize">{{ $customer->name ?? '' }} - {{ __('labels.customer') }}</h2>
                    <x-breadcrumb :items="$breadcrumbs" />
                </div>
            </div>
        </div>

        <!-- PAGE BODY -->
        <div class="page-body">
            {{-- PROFILE CARD --}}
            <div class="card mb-4">
                <div class="card-body">
                    <div class="row align-items-center">
                        <div class="col-auto">
                            <span class="avatar avatar-xl rounded" style="background-image: url('{{ $profileImageUrl }}')">
                            </span>
                        </div>
                        <div class="col">
                            <h2 class="mb-1">{{ $customer->name ?? 'N/A' }}</h2>
                            <div class="text-secondary mb-1">
                                <i class="ti ti-mail me-1"></i>{{ $customer->email ?? 'N/A' }}
                                <span class="mx-2">|</span>
                                <i class="ti ti-phone me-1"></i>{{ $customer->mobile ?? 'N/A' }}
                            </div>
                            <div class="mt-2">
                                <span
                                    class="badge border p-2 {{ $customer->status ? 'bg-success-lt border-success-subtle' : 'bg-danger-lt border-danger-subtle' }}">
                                    {{ $customer->status ? __('labels.active') : __('labels.inactive') }}
                                </span>
                                @if ($customer->email_verified_at)
                                    <span class="badge border p-2 bg-success-lt border-success-subtle">
                                        <i class="ti ti-mail-check me-1"></i>{{ __('labels.email_verified') }}
                                    </span>
                                @else
                                    <span class="badge border p-2 bg-warning-lt border-warning-subtle">
                                        <i class="ti ti-mail-x me-1"></i>{{ __('labels.email_not_verified') }}
                                    </span>
                                @endif
                                @if ($customer->mobile_verified_at)
                                    <span class="badge border p-2 bg-success-lt border-success-subtle">
                                        <i class="ti ti-phone-check me-1"></i>{{ __('labels.mobile_verified') }}
                                    </span>
                                @else
                                    <span class="badge border p-2 bg-warning-lt border-warning-subtle">
                                        <i class="ti ti-phone-x me-1"></i>{{ __('labels.mobile_not_verified') }}
                                    </span>
                                @endif
                            </div>
                        </div>
                        <div class="col-auto">
                            <div class="text-end">
                                <div class="text-secondary small">{{ __('labels.registration_date') }}</div>
                                <div class="fw-bold">{{ $customer->created_at->format('d M Y') }}</div>
                                @if ($customer->referral_code)
                                    <div class="text-secondary small mt-1">{{ __('labels.referral_code') }}:
                                        <code>{{ $customer->referral_code }}</code>
                                    </div>
                                @endif
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            {{-- STAT CARDS ROW --}}
            <div class="row row-cards mb-4">
                <div class="col-sm-6 col-lg-3">
                    <div class="card card-sm">
                        <div class="card-body">
                            <div class="row align-items-center">
                                <div class="col-auto">
                                    <span class="bg-primary text-white avatar"><i class="ti ti-shopping-cart"></i></span>
                                </div>
                                <div class="col">
                                    <div class="fw-bold fs-3">{{ $orderStats['total'] }}</div>
                                    <div class="text-secondary">{{ __('labels.total_orders') }}</div>
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
                                    <span class="bg-success text-white avatar"><i class="ti ti-circle-check"></i></span>
                                </div>
                                <div class="col">
                                    <div class="fw-bold fs-3">{{ $orderStats['delivered'] }}
                                        <span class="text-secondary fs-5">({{ $orderStats['completion_rate'] }}%)</span>
                                    </div>
                                    <div class="text-secondary">{{ __('labels.completed_orders') }}</div>
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
                                    <span class="bg-info text-white avatar"><i class="ti ti-receipt-2"></i></span>
                                </div>
                                <div class="col">
                                    <div class="fw-bold fs-3">
                                        {{ getCurrencySymbol() . number_format($orderStats['total_spent'], 2) }}</div>
                                    <div class="text-secondary">{{ __('labels.total_spent') }}</div>
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
                                    <span class="bg-cyan text-white avatar"><i class="ti ti-wallet"></i></span>
                                </div>
                                <div class="col">
                                    <div class="fw-bold fs-3">
                                        {{ getCurrencySymbol() . number_format($customer->wallet?->balance ?? 0, 2) }}
                                    </div>
                                    <div class="text-secondary">{{ __('labels.wallet_balance') }}</div>
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
                                    <span class="bg-warning text-white avatar"><i class="ti ti-star"></i></span>
                                </div>
                                <div class="col">
                                    <div class="fw-bold fs-3" id="top-loyalty-balance-display">
                                        {{ $loyaltyPointsBalance ?? 0 }}</div>
                                    <div class="text-secondary">{{ __('labels.loyalty_points') }}</div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            {{-- TABS --}}
            <div class="card">
                <div class="card-header">
                    <ul class="nav nav-tabs card-header-tabs" data-bs-toggle="tabs" role="tablist">
                        <li class="nav-item" role="presentation">
                            <a href="#tab-overview" class="nav-link active" data-bs-toggle="tab" role="tab"
                                aria-selected="true">
                                <i class="ti ti-info-circle me-1"></i>{{ __('labels.overview') }}
                            </a>
                        </li>
                        <li class="nav-item" role="presentation">
                            <a href="#tab-orders" class="nav-link" data-bs-toggle="tab" role="tab">
                                <i class="ti ti-shopping-cart me-1"></i>{{ __('labels.orders') }}
                            </a>
                        </li>
                        <li class="nav-item" role="presentation">
                            <a href="#tab-wallet" class="nav-link" data-bs-toggle="tab" role="tab">
                                <i class="ti ti-wallet me-1"></i>{{ __('labels.wallet') }}
                            </a>
                        </li>
                        <li class="nav-item" role="presentation">
                            <a href="#tab-addresses" class="nav-link" data-bs-toggle="tab" role="tab">
                                <i class="ti ti-map-pin me-1"></i>{{ __('labels.addresses') }}
                            </a>
                        </li>
                        <li class="nav-item" role="presentation">
                            <a href="#tab-notifications" class="nav-link" data-bs-toggle="tab" role="tab">
                                <i class="ti ti-bell me-1"></i>{{ __('labels.notifications') }}
                            </a>
                        </li>
                        <li class="nav-item" role="presentation">
                            <a href="#tab-loyalty" class="nav-link" data-bs-toggle="tab" role="tab">
                                <i class="ti ti-star me-1"></i>{{ __('labels.loyalty_points') ?? 'Loyalty Points' }}
                            </a>
                        </li>
                    </ul>
                </div>
                <div class="card-body">
                    <div class="tab-content">

                        {{-- TAB: OVERVIEW --}}
                        <div class="tab-pane active show" id="tab-overview" role="tabpanel">
                            <div class="row row-cards">
                                {{-- Personal Information --}}
                                <div class="col-lg-6">
                                    <div class="card mb-4">
                                        <div class="card-header">
                                            <h4 class="card-title">{{ __('labels.personal_information') }}</h4>
                                        </div>
                                        <div class="card-body">
                                            <div class="table-responsive">
                                                <table class="table mb-0" style="border: none;">
                                                    <tbody>
                                                        <tr>
                                                            <td class="fw-bold border-0" style="width: 180px;">
                                                                {{ __('labels.full_name') }}</td>
                                                            <td class="border-0">{{ $customer->name ?? 'N/A' }}</td>
                                                        </tr>
                                                        <tr>
                                                            <td class="fw-bold border-0">{{ __('labels.email') }}</td>
                                                            <td class="border-0">{{ $customer->email ?? 'N/A' }}</td>
                                                        </tr>
                                                        <tr>
                                                            <td class="fw-bold border-0">{{ __('labels.mobile') }}</td>
                                                            <td class="border-0">{{ $customer->mobile ?? 'N/A' }}</td>
                                                        </tr>
                                                        <tr>
                                                            <td class="fw-bold border-0">{{ __('labels.country') }}</td>
                                                            <td class="border-0">{{ $customer->country ?? 'N/A' }}</td>
                                                        </tr>
                                                        <tr>
                                                            <td class="fw-bold border-0">{{ __('labels.country_code') }}
                                                            </td>
                                                            <td class="border-0">{{ $customer->country_code ?? 'N/A' }}
                                                            </td>
                                                        </tr>
                                                        <tr>
                                                            <td class="fw-bold border-0">{{ __('labels.referral_code') }}
                                                            </td>
                                                            <td class="border-0">{{ $customer->referral_code ?? 'N/A' }}
                                                            </td>
                                                        </tr>
                                                        <tr>
                                                            <td class="fw-bold border-0">{{ __('labels.friends_code') }}
                                                            </td>
                                                            <td class="border-0">{{ $customer->friends_code ?? 'N/A' }}
                                                            </td>
                                                        </tr>
                                                        <tr>
                                                            <td class="fw-bold border-0">{{ __('labels.reward_points') }}
                                                            </td>
                                                            <td class="border-0">
                                                                <span
                                                                    class="badge bg-purple-lt">{{ getCurrencySymbol() . $customer->reward_points ?? 0 }}</span>
                                                            </td>
                                                        </tr>
                                                    </tbody>
                                                </table>
                                            </div>
                                        </div>
                                    </div>

                                    {{-- Verification Details --}}
                                    <div class="card mb-4">
                                        <div class="card-header">
                                            <h4 class="card-title">{{ __('labels.verification_details') }}</h4>
                                        </div>
                                        <div class="card-body">
                                            <div class="table-responsive">
                                                <table class="table mb-0" style="border: none;">
                                                    <tbody>
                                                        <tr>
                                                            <td class="fw-bold border-0" style="width: 180px;">
                                                                {{ __('labels.email_verified') }}</td>
                                                            <td class="border-0">
                                                                @if ($customer->email_verified_at)
                                                                    <span
                                                                        class="badge bg-success-lt">{{ $customer->email_verified_at->format('d M Y H:i') }}</span>
                                                                @else
                                                                    <span
                                                                        class="badge bg-warning-lt">{{ __('labels.not_verified') }}</span>
                                                                @endif
                                                            </td>
                                                        </tr>
                                                        <tr>
                                                            <td class="fw-bold border-0">
                                                                {{ __('labels.mobile_verified') }}</td>
                                                            <td class="border-0">
                                                                @if ($customer->mobile_verified_at)
                                                                    <span
                                                                        class="badge bg-success-lt">{{ $customer->mobile_verified_at->format('d M Y H:i') }}</span>
                                                                @else
                                                                    <span
                                                                        class="badge bg-warning-lt">{{ __('labels.not_verified') }}</span>
                                                                @endif
                                                            </td>
                                                        </tr>
                                                        <tr>
                                                            <td class="fw-bold border-0">{{ __('labels.login_type') }}
                                                            </td>
                                                            <td class="border-0">
                                                                {{ ucfirst($customer->logged_in_type?->value ?? 'N/A') }}
                                                            </td>
                                                        </tr>
                                                    </tbody>
                                                </table>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                {{-- Order Breakdown + Map --}}
                                <div class="col-lg-6">
                                    {{-- Primary Address Map --}}
                                    <div class="card mb-4">
                                        <div class="card-header">
                                            <h4 class="card-title">{{ __('labels.primary_address') }}</h4>
                                        </div>
                                        <div class="card-body p-0">
                                            <div id="customer-map" style="height: 300px; width: 100%;"></div>
                                        </div>
                                    </div>

                                    {{-- Order Breakdown --}}
                                    <div class="card mb-4">
                                        <div class="card-header">
                                            <h4 class="card-title">{{ __('labels.order_breakdown') }}</h4>
                                        </div>
                                        <div class="card-body">
                                            @php
                                                $total = max($orderStats['total'], 1);
                                                $breakdowns = [
                                                    [
                                                        'label' => __('labels.delivered'),
                                                        'count' => $orderStats['delivered'],
                                                        'class' => 'bg-success',
                                                    ],
                                                    [
                                                        'label' => __('labels.pending'),
                                                        'count' => $orderStats['pending'],
                                                        'class' => 'bg-warning',
                                                    ],
                                                    [
                                                        'label' => __('labels.in_progress'),
                                                        'count' => $orderStats['in_progress'],
                                                        'class' => 'bg-info',
                                                    ],
                                                    [
                                                        'label' => __('labels.cancelled'),
                                                        'count' => $orderStats['cancelled'],
                                                        'class' => 'bg-danger',
                                                    ],
                                                ];
                                            @endphp
                                            @foreach ($breakdowns as $item)
                                                <div class="mb-3">
                                                    <div class="d-flex justify-content-between mb-1">
                                                        <span>{{ $item['label'] }}</span>
                                                        <span class="fw-bold">{{ $item['count'] }}</span>
                                                    </div>
                                                    <div class="progress progress-sm">
                                                        <div class="progress-bar {{ $item['class'] }}"
                                                            style="width: {{ round(($item['count'] / $total) * 100) }}%">
                                                        </div>
                                                    </div>
                                                </div>
                                            @endforeach
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        {{-- TAB: ORDERS --}}
                        <div class="tab-pane" id="tab-orders" role="tabpanel">
                            <x-datatable id="customer-orders-table" :route="route('admin.customers.orders-datatable', $customer->id)" :columns="$orderColumns" />
                        </div>

                        {{-- TAB: WALLET --}}
                        <div class="tab-pane" id="tab-wallet" role="tabpanel">
                            <div class="row row-cards mb-4">
                                <div class="col-sm-6 col-lg-4">
                                    <div class="card card-sm">
                                        <div class="card-body">
                                            <div class="fw-bold fs-3">
                                                {{ getCurrencySymbol() . number_format($customer->wallet?->balance ?? 0, 2) }}
                                            </div>
                                            <div class="text-secondary">{{ __('labels.current_balance') }}</div>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-sm-6 col-lg-4">
                                    <div class="card card-sm">
                                        <div class="card-body">
                                            <div class="fw-bold fs-3 text-warning">{{ $loyaltyPointsBalance ?? 0 }}</div>
                                            <div class="text-secondary">{{ __('labels.loyalty_points') }}</div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            {{-- Wallet Transactions DataTable --}}
                            <div class="card">
                                <div class="card-header">
                                    <h4 class="card-title">{{ __('labels.recent_transactions') }}</h4>
                                </div>
                                <div class="card-body">
                                    <x-datatable id="customer-wallet-table" :route="route('admin.customers.wallet-datatable', $customer->id)" :columns="$walletColumns" />
                                </div>
                            </div>
                        </div>

                        {{-- TAB: ADDRESSES --}}
                        <div class="tab-pane" id="tab-addresses" role="tabpanel">
                            @if ($addresses->count() > 0)
                                <div class="row row-cards">
                                    @foreach ($addresses as $address)
                                        <div class="col-md-6 col-lg-4">
                                            <div class="card">
                                                <div class="card-body">
                                                    <div class="d-flex align-items-center mb-2">
                                                        @php
                                                            $typeIcon = match (
                                                                strtolower($address->address_type ?? '')
                                                            ) {
                                                                'home' => 'ti-home',
                                                                'office', 'work' => 'ti-building',
                                                                default => 'ti-map-pin',
                                                            };
                                                        @endphp
                                                        <i class="ti {{ $typeIcon }} me-2 text-primary"></i>
                                                        <span
                                                            class="badge bg-blue-lt">{{ ucfirst($address->address_type ?? 'Other') }}</span>
                                                    </div>
                                                    <div class="mb-1">{{ $address->address_line1 ?? '' }}</div>
                                                    @if ($address->address_line2)
                                                        <div class="mb-1 text-secondary">{{ $address->address_line2 }}
                                                        </div>
                                                    @endif
                                                    <div class="text-secondary small">
                                                        {{ collect([$address->city, $address->state, $address->zipcode])->filter()->implode(', ') }}
                                                    </div>
                                                    @if ($address->landmark)
                                                        <div class="text-muted small mt-1">
                                                            <i class="ti ti-flag me-1"></i>{{ $address->landmark }}
                                                        </div>
                                                    @endif
                                                    @if ($address->mobile)
                                                        <div class="text-muted small mt-1">
                                                            <i class="ti ti-phone me-1"></i>{{ $address->mobile }}
                                                        </div>
                                                    @endif
                                                </div>
                                            </div>
                                        </div>
                                    @endforeach
                                </div>
                            @else
                                <div class="empty">
                                    <div class="empty-icon"><i class="ti ti-map-pin-off"></i></div>
                                    <p class="empty-title">{{ __('labels.no_addresses_found') }}</p>
                                </div>
                            @endif
                        </div>

                        {{-- TAB: NOTIFICATIONS --}}
                        <div class="tab-pane" id="tab-notifications" role="tabpanel">
                            <x-datatable id="customer-notifications-table" :route="route('admin.customers.notifications-datatable', $customer->id)" :columns="$notificationColumns" />
                        </div>

                        {{-- TAB: LOYALTY POINTS --}}
                        <div class="tab-pane" id="tab-loyalty" role="tabpanel">
                            <div class="d-flex justify-content-between align-items-center mt-1 px-3">
                                <div>
                                    <h4 class="mb-0">{{ __('labels.loyalty_points') ?? 'Loyalty Points' }}</h4>
                                    <span class="text-muted small">{{ __('labels.points_balance') ?? 'Points Balance' }}:
                                        <strong class="text-success"
                                            id="loyalty-balance-display">{{ $loyaltyPointsBalance ?? 0 }}</strong></span>
                                </div>
                                <button type="button" class="btn btn-primary btn-sm" data-bs-toggle="modal"
                                    data-bs-target="#adjust-loyalty-modal">
                                    <i
                                        class="ti ti-adjustments me-1"></i>{{ __('labels.adjust_loyalty_points') ?? 'Adjust Loyalty Points' }}
                                </button>
                            </div>
                            <x-datatable id="customer-loyalty-table" :route="route('admin.customers.loyalty-datatable', $customer->id)" :columns="$loyaltyColumns" />
                        </div>

                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- ADJUST LOYALTY POINTS MODAL --}}
    <div class="modal modal-blur fade" id="adjust-loyalty-modal" tabindex="-1" role="dialog" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered" role="document">
            <div class="modal-content">
                <form id="adjust-loyalty-form"
                    action="{{ route('admin.customers.adjust-loyalty-points', $customer->id) }}" method="POST">
                    @csrf
                    <div class="modal-header">
                        <h5 class="modal-title">{{ __('labels.adjust_loyalty_points') ?? 'Adjust Loyalty Points' }}</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <div class="mb-3">
                            <label class="form-label required">{{ __('labels.action') ?? 'Action' }}</label>
                            <select name="point_action" id="loyalty-point-action" class="form-select" required>
                                <option value="credit">{{ __('labels.credit_points') ?? 'Credit Points (+)' }}</option>
                                <option value="debit" id="loyalty-debit-option">{{ __('labels.debit_points') ?? 'Debit Points (-)' }}</option>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label required">{{ __('labels.points_amount') ?? 'Points Amount' }}</label>
                            <input type="number" name="points" id="loyalty-points-input" min="1" step="1" class="form-control"
                                required placeholder="e.g. 50">
                            <small id="loyalty-points-help" class="form-text text-muted mt-1 d-block"></small>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">{{ __('labels.note') ?? 'Reason / Note' }}</label>
                            <textarea name="note" class="form-control" rows="2" placeholder="e.g. Customer support bonus"></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-link link-secondary me-auto"
                            data-bs-dismiss="modal">{{ __('labels.cancel') ?? 'Cancel' }}</button>
                        <button type="submit" class="btn btn-primary">{{ __('labels.submit') ?? 'Submit' }}</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
@endsection

@push('styles')
    <link rel="stylesheet" href="{{ asset('assets/vendor/leaflet/leaflet.css') }}" />
@endpush

@push('scripts')
    <script src="{{ asset('assets/vendor/leaflet/leaflet.js') }}"></script>
    @php
        $primaryAddress = $addresses->first();
        $hasLocation = $primaryAddress && $primaryAddress->latitude && $primaryAddress->longitude;
    @endphp
    <script>
        window.__CUSTOMER_DETAIL_CONFIG__ = {
            hasLocation: {{ $hasLocation ? 'true' : 'false' }},
            latitude: {{ $primaryAddress->latitude ?? 0 }},
            longitude: {{ $primaryAddress->longitude ?? 0 }},
            name: @json($customer->name ?? 'Customer'),
        };

        document.addEventListener('DOMContentLoaded', function() {
            var form = document.getElementById('adjust-loyalty-form');
            var actionSelect = document.getElementById('loyalty-point-action');
            var debitOption = document.getElementById('loyalty-debit-option');
            var pointsInput = document.getElementById('loyalty-points-input');
            var helpText = document.getElementById('loyalty-points-help');
            var modalEl = document.getElementById('adjust-loyalty-modal');

            function getCurrentLoyaltyBalance() {
                var balanceDisplay = document.getElementById('loyalty-balance-display');
                if (balanceDisplay) {
                    var parsed = parseInt(balanceDisplay.textContent.trim(), 10);
                    return isNaN(parsed) ? 0 : parsed;
                }
                return {{ (int) ($loyaltyPointsBalance ?? 0) }};
            }

            function syncLoyaltyModalState() {
                var currentBalance = getCurrentLoyaltyBalance();
                if (currentBalance <= 0) {
                    if (debitOption) {
                        debitOption.disabled = true;
                        debitOption.textContent = "{{ __('labels.debit_points') ?? 'Debit Points (-)' }} (0 available)";
                    }
                    if (actionSelect && actionSelect.value === 'debit') {
                        actionSelect.value = 'credit';
                    }
                    if (pointsInput) {
                        pointsInput.removeAttribute('max');
                    }
                    if (helpText) {
                        helpText.textContent = '';
                    }
                } else {
                    if (debitOption) {
                        debitOption.disabled = false;
                        debitOption.textContent = "{{ __('labels.debit_points') ?? 'Debit Points (-)' }} (" + currentBalance + " available)";
                    }
                    if (actionSelect && actionSelect.value === 'debit') {
                        if (pointsInput) {
                            pointsInput.max = currentBalance;
                        }
                        if (helpText) {
                            helpText.textContent = "{{ __('labels.max_debit_available', ['balance' => '']) }}" + currentBalance;
                        }
                    } else {
                        if (pointsInput) {
                            pointsInput.removeAttribute('max');
                        }
                        if (helpText) {
                            helpText.textContent = '';
                        }
                    }
                }
            }

            if (actionSelect) {
                actionSelect.addEventListener('change', syncLoyaltyModalState);
            }

            if (modalEl) {
                modalEl.addEventListener('show.bs.modal', syncLoyaltyModalState);
            }

            if (form) {
                form.addEventListener('submit', function(e) {
                    e.preventDefault();

                    var currentBalance = getCurrentLoyaltyBalance();
                    var selectedAction = actionSelect ? actionSelect.value : 'credit';
                    var requestedPoints = parseInt(pointsInput ? pointsInput.value : '0', 10) || 0;

                    if (selectedAction === 'debit') {
                        if (currentBalance <= 0) {
                            alert(@json(__('labels.no_points_available_to_debit')));
                            return;
                        }
                        if (requestedPoints > currentBalance) {
                            alert(@json(__('labels.cannot_debit_more_than_balance')) + ' (' + currentBalance + ' available)');
                            return;
                        }
                    }

                    var formData = new FormData(form);
                    var targetUrl = form.getAttribute('action');
                    fetch(targetUrl, {
                            method: 'POST',
                            body: formData,
                            headers: {
                                'X-Requested-With': 'XMLHttpRequest'
                            }
                        })
                        .then(response => response.json())
                        .then(data => {
                            if (data.success) {
                                if (window.bootstrap && window.bootstrap.Modal) {
                                    var modalInstance = bootstrap.Modal.getInstance(modalEl) ||
                                        new bootstrap.Modal(modalEl);
                                    if (modalInstance) modalInstance.hide();
                                } else if (typeof $ !== 'undefined') {
                                    $(modalEl).modal('hide');
                                }

                                form.reset();

                                var balanceDisplay = document.getElementById('loyalty-balance-display');
                                if (balanceDisplay && data.data && data.data.points_balance !==
                                    undefined) {
                                    balanceDisplay.textContent = data.data.points_balance;
                                }

                                var topBalanceDisplay = document.getElementById(
                                    'top-loyalty-balance-display');
                                if (topBalanceDisplay && data.data && data.data.points_balance !==
                                    undefined) {
                                    topBalanceDisplay.textContent = data.data.points_balance;
                                }

                                syncLoyaltyModalState();

                                if (typeof $ !== 'undefined' && $.fn.DataTable && $.fn.DataTable
                                    .isDataTable('#customer-loyalty-table')) {
                                    $('#customer-loyalty-table').DataTable().ajax.reload(null, false);
                                } else {
                                    location.reload();
                                }
                            } else {
                                alert(data.message || 'Error adjusting loyalty points');
                            }
                        })
                        .catch(err => {
                            console.error(err);
                            alert('Error processing request: ' + (err.message || err));
                        });
                });
            }
        });
    </script>
    <script src="{{ asset('assets/js/customer-detail.js') }}" defer></script>
@endpush
