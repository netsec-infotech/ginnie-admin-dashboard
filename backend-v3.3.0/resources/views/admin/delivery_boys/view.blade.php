@extends('layouts.admin.app', ['page' => $menuAdmin['delivery_boys']['active'] ?? ""])

@section('title', $deliveryBoy->full_name)

@section('header_data')
    @php
        $page_title = $deliveryBoy->full_name;
        $page_pretitle = __('labels.delivery_boy_details');
    @endphp
@endsection

@php
    $breadcrumbs = [
        ['title' => __('labels.home'), 'url' => route('admin.dashboard')],
        ['title' => __('labels.delivery_boys'), 'url' => route('admin.delivery-boys.index')],
        ['title' => $deliveryBoy->full_name, 'url' => null],
    ];
@endphp

@section('admin-content')
    <div class="page-wrapper">
        <!-- PAGE HEADER -->
        <div class="page-header d-print-none">
            <div class="row g-2 align-items-center">
                <div class="col">
                    <h2 class="page-title text-capitalize">{{ $deliveryBoy->full_name }}
                        - {{ __('labels.delivery_boy') }}</h2>
                    <x-breadcrumb :items="$breadcrumbs"/>
                </div>
                <div class="col-12 col-md-auto ms-auto d-print-none">
                    <div class="btn-list">
                        @if($editPermission ?? false)
                            <button type="button" class="btn btn-primary"
                                    data-id="{{ $deliveryBoy->id }}" data-bs-toggle="modal"
                                    data-bs-target="#verificationStatusModal">
                                {{ __('labels.update_verification_status') }}
                            </button>
                        @endif
                        @if($blockPermission ?? false)
                            @if($deliveryBoy->is_blocked)
                                <button type="button" class="btn btn-success"
                                        id="unblockDeliveryBoyBtn"
                                        data-id="{{ $deliveryBoy->id }}"
                                        data-url="{{ route('admin.delivery-boys.unblock', $deliveryBoy->id) }}">
                                    <i class="ti ti-lock-open me-1"></i>{{ __('labels.unblock') }}
                                </button>
                            @else
                                <button type="button" class="btn btn-warning"
                                        data-id="{{ $deliveryBoy->id }}"
                                        data-url="{{ route('admin.delivery-boys.block', $deliveryBoy->id) }}"
                                        data-bs-toggle="modal" data-bs-target="#blockDeliveryBoyModal">
                                    <i class="ti ti-lock me-1"></i>{{ __('labels.block') }}
                                </button>
                            @endif
                        @endif
                        @if($deletePermission ?? false)
                            <button type="button" class="btn btn-danger"
                                    data-id="{{ $deliveryBoy->id }}" data-bs-toggle="modal"
                                    data-bs-target="#deleteModal">
                                {{ __('labels.delete') }}
                            </button>
                        @endif
                    </div>
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
                            <span class="avatar avatar-xl rounded"
                                  style="background-image: url('{{ $deliveryBoy->profile_image ?? asset('assets/images/user-placeholder.png') }}')">
                            </span>
                        </div>
                        <div class="col">
                            <h2 class="mb-1">{{ $deliveryBoy->full_name }}</h2>
                            <div class="text-secondary mb-1">
                                <i class="ti ti-mail me-1"></i>{{ $deliveryBoy->user->email ?? 'N/A' }}
                                <span class="mx-2">|</span>
                                <i class="ti ti-phone me-1"></i>{{ $deliveryBoy->user->mobile ?? 'N/A' }}
                            </div>
                            <div class="mt-2">
                                {{-- Verification badge --}}
                                @php
                                    $vStatus = $deliveryBoy->verification_status->value;
                                    $vClass = match($vStatus) {
                                        'verified' => 'bg-success-lt border-success-subtle',
                                        'rejected' => 'bg-danger-lt border-danger-subtle',
                                        default    => 'bg-warning-lt border-warning-subtle',
                                    };
                                @endphp
                                <span class="badge border p-2 {{ $vClass }}" data-role="verification-badge">
                                    {{ ucfirst(str_replace('_', ' ', $vStatus)) }}
                                </span>

                                {{-- Active/Inactive --}}
                                <span class="badge border p-2 {{ $deliveryBoy->status === 'active' ? 'bg-success-lt border-success-subtle' : 'bg-danger-lt border-danger-subtle' }}">
                                    {{ ucfirst($deliveryBoy->status) }}
                                </span>

                                {{-- Blocked --}}
                                @if($deliveryBoy->is_blocked)
                                    <span class="badge bg-danger-lt border border-danger-subtle p-2">
                                        <i class="ti ti-lock me-1"></i>{{ __('labels.blocked') }}
                                    </span>
                                @endif

                                {{-- Flagged --}}
                                @if($deliveryBoy->is_flagged)
                                    <span class="badge bg-orange-lt border border-orange-subtle p-2"
                                          data-bs-toggle="tooltip" data-bs-placement="top"
                                          title="{{ __('labels.flagged_tooltip') }}">
                                        <i class="ti ti-flag me-1"></i>{{ __('labels.flagged') }}
                                    </span>
                                @endif
                            </div>
                        </div>
                        <div class="col-auto">
                            <div class="text-end">
                                <div class="text-secondary small">{{ __('labels.registration_date') }}</div>
                                <div class="fw-bold">{{ $deliveryBoy->created_at->format('d M Y') }}</div>
                                @if($deliveryBoy->referral_code)
                                    <div class="text-secondary small mt-1">{{ __('labels.referral_code') }}: <code>{{ $deliveryBoy->referral_code }}</code></div>
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
                                    <span class="bg-primary text-white avatar"><i class="ti ti-package"></i></span>
                                </div>
                                <div class="col">
                                    <div class="fw-bold fs-3">{{ $assignmentStats['total'] }}</div>
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
                                    <div class="fw-bold fs-3">{{ $assignmentStats['completed'] }}
                                        <span class="text-secondary fs-5">({{ $assignmentStats['completion_rate'] }}%)</span>
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
                                    <span class="bg-warning text-white avatar"><i class="ti ti-star"></i></span>
                                </div>
                                <div class="col">
                                    <div class="fw-bold fs-3">{{ $reviewData ? number_format($reviewData->average_rating, 1) : '0.0' }}</div>
                                    <div class="text-secondary">{{ __('labels.average_rating') }}
                                        ({{ $reviewData->total_reviews ?? 0 }} {{ __('labels.reviews') }})
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
                                    <span class="bg-cyan text-white avatar"><i class="ti ti-wallet"></i></span>
                                </div>
                                <div class="col">
                                    <div class="fw-bold fs-3">{{ getCurrencySymbol() . $deliveryBoy->wallet?->balance ?? '0.00' }}</div>
                                    <div class="text-secondary">{{ __('labels.wallet_balance') }}</div>
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
                            <a href="#tab-assignments" class="nav-link" data-bs-toggle="tab" role="tab">
                                <i class="ti ti-package me-1"></i>{{ __('labels.assignments') }}
                            </a>
                        </li>
                        <li class="nav-item" role="presentation">
                            <a href="#tab-wallet" class="nav-link" data-bs-toggle="tab" role="tab">
                                <i class="ti ti-wallet me-1"></i>{{ __('labels.wallet') }} & {{ __('labels.earnings') }}
                            </a>
                        </li>
                        <li class="nav-item" role="presentation">
                            <a href="#tab-feedback" class="nav-link" data-bs-toggle="tab" role="tab">
                                <i class="ti ti-star me-1"></i>{{ __('labels.feedback') }}
                            </a>
                        </li>
                        <li class="nav-item" role="presentation">
                            <a href="#tab-documents" class="nav-link" data-bs-toggle="tab" role="tab">
                                <i class="ti ti-file-text me-1"></i>{{ __('labels.documents') }}
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
                                                        <td class="fw-bold border-0" style="width: 180px;">{{ __('labels.full_name') }}</td>
                                                        <td class="border-0">{{ $deliveryBoy->full_name }}</td>
                                                    </tr>
                                                    <tr>
                                                        <td class="fw-bold border-0">{{ __('labels.email') }}</td>
                                                        <td class="border-0">{{ $deliveryBoy->user->email ?? 'N/A' }}</td>
                                                    </tr>
                                                    <tr>
                                                        <td class="fw-bold border-0">{{ __('labels.mobile') }}</td>
                                                        <td class="border-0">{{ $deliveryBoy->user->mobile ?? 'N/A' }}</td>
                                                    </tr>
                                                    <tr>
                                                        <td class="fw-bold border-0">{{ __('labels.country') }}</td>
                                                        <td class="border-0">{{ $deliveryBoy->user->country ?? 'N/A' }}</td>
                                                    </tr>
                                                    <tr>
                                                        <td class="fw-bold border-0">{{ __('labels.address') }}</td>
                                                        <td class="border-0">{{ $deliveryBoy->address ?? 'N/A' }}</td>
                                                    </tr>
                                                    <tr>
                                                        <td class="fw-bold border-0">{{ __('labels.delivery_zone') }}</td>
                                                        <td class="border-0">{{ $deliveryBoy->deliveryZone?->name ?? 'N/A' }}</td>
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
                                                        <td class="fw-bold border-0" style="width: 180px;">{{ __('labels.verification_status') }}</td>
                                                        <td class="border-0">
                                                            <span class="badge border p-2 {{ $vClass }}" data-role="verification-badge">
                                                                {{ ucfirst(str_replace('_', ' ', $vStatus)) }}
                                                            </span>
                                                        </td>
                                                    </tr>
                                                    @if($deliveryBoy->is_blocked)
                                                        <tr>
                                                            <td class="fw-bold border-0">{{ __('labels.block_status') }}</td>
                                                            <td class="border-0">
                                                                <span class="badge bg-danger-lt border border-danger-subtle p-2">
                                                                    <i class="ti ti-lock me-1"></i>{{ __('labels.blocked') }}
                                                                </span>
                                                                @if($deliveryBoy->blocked_reason)
                                                                    <div class="text-muted small mt-1">
                                                                        {{ __('labels.reason') }}: {{ $deliveryBoy->blocked_reason }}
                                                                    </div>
                                                                @endif
                                                                @if($deliveryBoy->blocked_at)
                                                                    <div class="text-muted small">
                                                                        {{ $deliveryBoy->blocked_at->diffForHumans() }}
                                                                    </div>
                                                                @endif
                                                            </td>
                                                        </tr>
                                                    @endif
                                                    <tr id="verificationRemarkRow" class="{{ $deliveryBoy->verification_remark ? '' : 'd-none' }}">
                                                        <td class="fw-bold border-0">{{ __('labels.verification_remark') }}</td>
                                                        <td class="border-0" data-role="verification-remark">{{ $deliveryBoy->verification_remark }}</td>
                                                    </tr>
                                                    <tr>
                                                        <td class="fw-bold border-0">{{ __('labels.drop_count') }}</td>
                                                        <td class="border-0">
                                                            <span class="badge {{ $deliveryBoy->drop_count > 3 ? 'bg-danger-lt' : 'bg-secondary-lt' }}">
                                                                {{ $deliveryBoy->drop_count }}
                                                            </span>
                                                        </td>
                                                    </tr>
                                                    </tbody>
                                                </table>
                                            </div>
                                        </div>
                                    </div>

                                    {{-- Vehicle & License --}}
                                    <div class="card mb-4">
                                        <div class="card-header">
                                            <h4 class="card-title">{{ __('labels.vehicle_details') }}</h4>
                                        </div>
                                        <div class="card-body">
                                            <div class="table-responsive">
                                                <table class="table mb-0" style="border: none;">
                                                    <tbody>
                                                    <tr>
                                                        <td class="fw-bold border-0" style="width: 180px;">{{ __('labels.vehicle_type') }}</td>
                                                        <td class="border-0">{{ $deliveryBoy->vehicle_type ?? 'N/A' }}</td>
                                                    </tr>
                                                    <tr>
                                                        <td class="fw-bold border-0">{{ __('labels.driver_license_number') }}</td>
                                                        <td class="border-0">{{ $deliveryBoy->driver_license_number ?? 'N/A' }}</td>
                                                    </tr>
                                                    </tbody>
                                                </table>
                                            </div>
                                        </div>
                                    </div>

                                    {{-- Referrals --}}
                                    <div class="card mb-4">
                                        <div class="card-header">
                                            <h4 class="card-title">{{ __('labels.referrals') }}</h4>
                                        </div>
                                        <div class="card-body">
                                            <div class="table-responsive">
                                                <table class="table mb-0" style="border: none;">
                                                    <tbody>
                                                    <tr>
                                                        <td class="fw-bold border-0" style="width: 180px;">{{ __('labels.referred_by') }}</td>
                                                        <td class="border-0">
                                                            @if($deliveryBoy->referralAsReferred && $deliveryBoy->referralAsReferred->referrer)
                                                                {{ $deliveryBoy->referralAsReferred->referrer->full_name }}
                                                            @else
                                                                N/A
                                                            @endif
                                                        </td>
                                                    </tr>
                                                    <tr>
                                                        <td class="fw-bold border-0">{{ __('labels.people_referred') }}</td>
                                                        <td class="border-0"><span class="badge bg-primary-lt">{{ $referralsCount }}</span></td>
                                                    </tr>
                                                    <tr>
                                                        <td class="fw-bold border-0">{{ __('labels.referral_earnings') }}</td>
                                                        <td class="border-0">{{getCurrencySymbol() . number_format($referralEarningsTotal, 2) }}</td>
                                                    </tr>
                                                    </tbody>
                                                </table>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                {{-- Map & Location --}}
                                <div class="col-lg-6">
                                    <div class="card mb-4">
                                        <div class="card-header">
                                            <h4 class="card-title">
                                                <i class="ti ti-map-pin me-1"></i>{{ __('labels.live_location') }}
                                            </h4>
                                        </div>
                                        <div class="card-body p-0">
                                            <div id="delivery-boy-map" style="height: 350px; width: 100%;"></div>
                                        </div>
                                        @if($deliveryBoy->location)
                                            <div class="card-footer">
                                                <div class="row text-center">
                                                    <div class="col">
                                                        <div class="text-secondary small">{{ __('labels.latitude') }}</div>
                                                        <div class="fw-bold">{{ $deliveryBoy->location->latitude }}</div>
                                                    </div>
                                                    <div class="col">
                                                        <div class="text-secondary small">{{ __('labels.longitude') }}</div>
                                                        <div class="fw-bold">{{ $deliveryBoy->location->longitude }}</div>
                                                    </div>
                                                    <div class="col">
                                                        <div class="text-secondary small">{{ __('labels.last_updated') }}</div>
                                                        <div class="fw-bold">
                                                            {{ $deliveryBoy->location->updated_at?->diffForHumans() ?? 'N/A' }}
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        @endif
                                    </div>

                                    {{-- Assignment Breakdown --}}
                                    <div class="card mb-4">
                                        <div class="card-header">
                                            <h4 class="card-title">{{ __('labels.assignment_breakdown') }}</h4>
                                        </div>
                                        <div class="card-body">
                                            <div class="row text-center">
                                                <div class="col">
                                                    <div class="fw-bold fs-3 text-primary">{{ $assignmentStats['assigned'] }}</div>
                                                    <div class="text-secondary small">{{ __('labels.assigned') }}</div>
                                                </div>
                                                <div class="col">
                                                    <div class="fw-bold fs-3 text-info">{{ $assignmentStats['in_progress'] }}</div>
                                                    <div class="text-secondary small">{{ __('labels.in_progress') }}</div>
                                                </div>
                                                <div class="col">
                                                    <div class="fw-bold fs-3 text-success">{{ $assignmentStats['completed'] }}</div>
                                                    <div class="text-secondary small">{{ __('labels.completed') }}</div>
                                                </div>
                                                <div class="col">
                                                    <div class="fw-bold fs-3 text-warning">{{ $assignmentStats['canceled'] }}</div>
                                                    <div class="text-secondary small">{{ __('labels.canceled') }}</div>
                                                </div>
                                                <div class="col">
                                                    <div class="fw-bold fs-3 text-danger">{{ $assignmentStats['dropped'] }}</div>
                                                    <div class="text-secondary small">{{ __('labels.dropped') }}</div>
                                                </div>
                                            </div>
                                            <div class="mt-3">
                                                <div class="progress progress-sm">
                                                    @if($assignmentStats['total'] > 0)
                                                        <div class="progress-bar bg-success" style="width: {{ ($assignmentStats['completed'] / $assignmentStats['total']) * 100 }}%"></div>
                                                        <div class="progress-bar bg-info" style="width: {{ ($assignmentStats['in_progress'] / $assignmentStats['total']) * 100 }}%"></div>
                                                        <div class="progress-bar bg-warning" style="width: {{ ($assignmentStats['canceled'] / $assignmentStats['total']) * 100 }}%"></div>
                                                        <div class="progress-bar bg-danger" style="width: {{ ($assignmentStats['dropped'] / $assignmentStats['total']) * 100 }}%"></div>
                                                    @endif
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        {{-- TAB: ASSIGNMENTS --}}
                        <div class="tab-pane" id="tab-assignments" role="tabpanel">
                            <div class="card">
                                <div class="card-header">
                                    <h4 class="card-title">{{ __('labels.assignments') }}</h4>
                                </div>
                                <div class="card-body">
                                    <x-datatable id="delivery-boy-assignments-table" :columns="$assignmentColumns"
                                                 route="{{ route('admin.delivery-boys.assignments-datatable', $deliveryBoy->id) }}"
                                                 :options="['order' => [[6, 'desc']], 'pageLength' => 10]"/>
                                </div>
                            </div>
                        </div>

                        {{-- TAB: WALLET & EARNINGS --}}
                        <div class="tab-pane" id="tab-wallet" role="tabpanel">
                            <div class="row row-cards mb-4">
                                <div class="col-sm-6 col-lg-4">
                                    <div class="card card-sm">
                                        <div class="card-body">
                                            <div class="fw-bold fs-3">{{ getCurrencySymbol() . $earningsSummary['total_earnings'] }}</div>
                                            <div class="text-secondary">{{ __('labels.total_earnings') }}</div>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-sm-6 col-lg-4">
                                    <div class="card card-sm">
                                        <div class="card-body">
                                            <div class="fw-bold fs-3 text-success">{{ getCurrencySymbol() . $earningsSummary['total_paid'] }}</div>
                                            <div class="text-secondary">{{ __('labels.total_paid') }}</div>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-sm-6 col-lg-4">
                                    <div class="card card-sm">
                                        <div class="card-body">
                                            <div class="fw-bold fs-3 text-warning">{{ getCurrencySymbol() . $earningsSummary['total_pending'] }}</div>
                                            <div class="text-secondary">{{ __('labels.pending_earnings') }}</div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            {{-- COD Summary --}}
                            <div class="card mb-4">
                                <div class="card-header">
                                    <h4 class="card-title">{{ __('labels.cod_summary') }}</h4>
                                </div>
                                <div class="card-body">
                                    <div class="row text-center">
                                        <div class="col">
                                            <div class="fw-bold fs-3">{{ getCurrencySymbol() . $earningsSummary['cod_collected'] }}</div>
                                            <div class="text-secondary">{{ __('labels.cod_collected') }}</div>
                                        </div>
                                        <div class="col">
                                            <div class="fw-bold fs-3">{{ getCurrencySymbol() . $earningsSummary['cod_submitted'] }}</div>
                                            <div class="text-secondary">{{ __('labels.cod_submitted') }}</div>
                                        </div>
                                        <div class="col">
                                            @php
                                                $codOutstanding = (float) str_replace(',', '', $earningsSummary['cod_collected']) - (float) str_replace(',', '', $earningsSummary['cod_submitted']);
                                            @endphp
                                            <div class="fw-bold fs-3 {{ $codOutstanding > 0 ? 'text-danger' : 'text-success' }}">
                                                {{ getCurrencySymbol() . number_format($codOutstanding, 2) }}
                                            </div>
                                            <div class="text-secondary">{{ __('labels.cod_outstanding') }}</div>
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
                                    <x-datatable id="delivery-boy-wallet-table" :columns="$walletColumns"
                                                 route="{{ route('admin.delivery-boys.wallet-datatable', $deliveryBoy->id) }}"
                                                 :options="['order' => [[6, 'desc']], 'pageLength' => 10]"/>
                                </div>
                            </div>
                        </div>

                        {{-- TAB: FEEDBACK --}}
                        <div class="tab-pane" id="tab-feedback" role="tabpanel">
                            {{-- Rating Summary --}}
                            @if($reviewData && $reviewData->total_reviews > 0)
                                <div class="row row-cards mb-4">
                                    <div class="col-lg-4">
                                        <div class="card">
                                            <div class="card-body text-center">
                                                <div class="display-5 fw-bold text-warning">{{ number_format($reviewData->average_rating, 1) }}</div>
                                                <div class="mt-2">
                                                    <select id="rating-average" class="rating-stars" data-rating="{{ $reviewData->average_rating }}">
                                                        <option value="">{{ __('labels.select_a_rating') }}</option>
                                                        @for($i = 5; $i >= 1; $i--)
                                                            <option value="{{ $i }}" {{ round($reviewData->average_rating) == $i ? 'selected' : '' }}>{{ $i }}</option>
                                                        @endfor
                                                    </select>
                                                </div>
                                                <div class="text-secondary mt-1">{{ $reviewData->total_reviews }} {{ __('labels.reviews') }}</div>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-lg-8">
                                        <div class="card">
                                            <div class="card-header">
                                                <h4 class="card-title">{{ __('labels.rating_distribution') }}</h4>
                                            </div>
                                            <div class="card-body">
                                                @php
                                                    $starCounts = [
                                                        5 => $reviewData->five_star_count ?? 0,
                                                        4 => $reviewData->four_star_count ?? 0,
                                                        3 => $reviewData->three_star_count ?? 0,
                                                        2 => $reviewData->two_star_count ?? 0,
                                                        1 => $reviewData->one_star_count ?? 0,
                                                    ];
                                                    $maxCount = max(1, max($starCounts));
                                                @endphp
                                                @foreach($starCounts as $star => $count)
                                                    <div class="row align-items-center mb-2">
                                                        <div class="col-auto" style="width: 50px;">
                                                            <span class="fw-bold">{{ $star }} <i class="ti ti-star-filled text-warning"></i></span>
                                                        </div>
                                                        <div class="col">
                                                            <div class="progress progress-sm">
                                                                <div class="progress-bar bg-warning" style="width: {{ ($count / $maxCount) * 100 }}%"></div>
                                                            </div>
                                                        </div>
                                                        <div class="col-auto" style="width: 40px;">
                                                            <span class="text-secondary">{{ $count }}</span>
                                                        </div>
                                                    </div>
                                                @endforeach
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            @endif

                            {{-- Customer Reviews Datatable --}}
                            <div class="card">
                                <div class="card-header">
                                    <h4 class="card-title">{{ __('labels.customer_reviews') }}</h4>
                                </div>
                                <div class="card-body">
                                    <x-datatable id="feedback-table" :columns="$feedbackColumns"
                                                 route="{{ route('admin.delivery-boys.feedback-datatable', $deliveryBoy->id) }}"
                                                 :options="['order' => [[6, 'desc']], 'pageLength' => 10]"/>
                                </div>
                            </div>
                        </div>

                        {{-- TAB: DOCUMENTS --}}
                        <div class="tab-pane" id="tab-documents" role="tabpanel">
                            <div class="row row-cards">
                                <div class="col-lg-6">
                                    <div class="card mb-4">
                                        <div class="card-header">
                                            <h4 class="card-title">{{ __('labels.driver_license') }}</h4>
                                        </div>
                                        <div class="card-body">
                                            @if(!empty($deliveryBoy->driver_license) && is_array($deliveryBoy->driver_license))
                                                <div class="row g-2">
                                                    @foreach($deliveryBoy->driver_license as $licenseUrl)
                                                        <div class="col-12">
                                                            <div class="d-flex justify-content-center">
                                                                <a href="{{ $licenseUrl }}" target="_blank"
                                                                   data-fslightbox="gallery">
                                                                    <img src="{{ $licenseUrl }}"
                                                                         alt="{{ __('labels.driver_license') }}"
                                                                         class="rounded"
                                                                         style="max-height: 300px; max-width: 100%; object-fit: contain;">
                                                                </a>
                                                            </div>
                                                        </div>
                                                    @endforeach
                                                </div>
                                            @else
                                                <div class="alert alert-info mb-0">
                                                    {{ __('labels.no_document_uploaded') }}
                                                </div>
                                            @endif
                                        </div>
                                    </div>
                                </div>
                                <div class="col-lg-6">
                                    <div class="card mb-4">
                                        <div class="card-header">
                                            <h4 class="card-title">{{ __('labels.vehicle_registration') }}</h4>
                                        </div>
                                        <div class="card-body">
                                            @if(!empty($deliveryBoy->vehicle_registration) && is_array($deliveryBoy->vehicle_registration))
                                                <div class="row g-2">
                                                    @foreach($deliveryBoy->vehicle_registration as $registrationUrl)
                                                        <div class="col-12">
                                                            <div class="d-flex justify-content-center">
                                                                <a href="{{ $registrationUrl }}" target="_blank"
                                                                   data-fslightbox="gallery">
                                                                    <img src="{{ $registrationUrl }}"
                                                                         alt="{{ __('labels.vehicle_registration') }}"
                                                                         class="rounded"
                                                                         style="max-height: 300px; max-width: 100%; object-fit: contain;">
                                                                </a>
                                                            </div>
                                                        </div>
                                                    @endforeach
                                                </div>
                                            @else
                                                <div class="alert alert-info mb-0">
                                                    {{ __('labels.no_document_uploaded') }}
                                                </div>
                                            @endif
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- VERIFICATION STATUS MODAL --}}
    <div class="modal modal-blur fade" id="verificationStatusModal" tabindex="-1" role="dialog" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">{{ __('labels.update_verification_status') }}</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form id="updateVerificationStatusForm" method="POST"
                      action="{{ route('admin.delivery-boys.update-verification-status', $deliveryBoy->id) }}">
                    @csrf
                    <div class="modal-body">
                        <div class="mb-3">
                            <label class="form-label">{{ __('labels.verification_status') }}</label>
                            <select class="form-select" name="verification_status" required>
                                <option value="">{{ __('labels.select_status') }}</option>
                                @foreach($verificationStatuses as $status)
                                    <option value="{{ $status }}" {{ $deliveryBoy->verification_status->value === $status ? 'selected' : '' }}>
                                        {{ ucfirst(str_replace('_', ' ', $status)) }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">{{ __('labels.verification_remark') }}</label>
                            <textarea class="form-control" name="verification_remark" rows="3"
                                      placeholder="{{ __('labels.optional_remark') }}">{{ $deliveryBoy->verification_remark }}</textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">{{ __('labels.cancel') }}</button>
                        <button type="submit" class="btn btn-primary">{{ __('labels.update') }}</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    {{-- DELETE CONFIRMATION MODAL --}}
    <div class="modal modal-blur fade" id="deleteModal" tabindex="-1" role="dialog" aria-hidden="true">
        <div class="modal-dialog modal-sm modal-dialog-centered" role="document">
            <div class="modal-content">
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                <div class="modal-status bg-danger"></div>
                <div class="modal-body text-center py-4">
                    <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none"
                         stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"
                         class="icon mb-2 text-danger icon-lg">
                        <path d="M12 9v4"/>
                        <path d="M10.363 3.591l-8.106 13.534a1.914 1.914 0 0 0 1.636 2.871h16.214a1.914 1.914 0 0 0 1.636 -2.87l-8.106 -13.536a1.914 1.914 0 0 0 -3.274 0z"/>
                        <path d="M12 16h.01"/>
                    </svg>
                    <h3>{{ __('labels.delete_delivery_boy') }}</h3>
                    <div class="text-secondary">{{ __('labels.delete_delivery_boy_confirmation') }}</div>
                </div>
                <div class="modal-footer">
                    <div class="w-100">
                        <div class="row">
                            <div class="col">
                                <button class="btn btn-outline-secondary w-100" data-bs-dismiss="modal">{{ __('labels.cancel') }}</button>
                            </div>
                            <div class="col">
                                <button class="btn btn-danger w-100" id="confirmDelete" data-bs-dismiss="modal">{{ __('labels.delete') }}</button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    @if($blockPermission ?? false)
        <div class="modal modal-blur fade" id="blockDeliveryBoyModal" tabindex="-1" role="dialog" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered" role="document">
                <form id="blockDeliveryBoyForm">
                    @csrf
                    <div class="modal-content">
                        <div class="modal-status bg-warning"></div>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                        <div class="modal-body py-4">
                            <h3 class="mb-2 text-center">{{ __('labels.block_delivery_boy') }}</h3>
                            <p class="text-secondary text-center mb-3">
                                {{ __('labels.block_delivery_boy_help_text') }}
                            </p>
                            <div class="mb-3">
                                <label class="form-label required" for="blockReason">{{ __('labels.reason') }}</label>
                                <textarea id="blockReason" name="reason" class="form-control" rows="3" maxlength="500" required></textarea>
                                <div class="invalid-feedback"></div>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">
                                {{ __('labels.cancel') }}
                            </button>
                            <button type="submit" class="btn btn-warning" id="confirmBlockBtn">
                                <i class="ti ti-lock me-1"></i>{{ __('labels.block') }}
                            </button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    @endif
@endsection

@push('styles')
    <link rel="stylesheet" href="{{ asset('assets/vendor/star-rating.js/dist/star-rating.min.css') }}">
    <link rel="stylesheet" href="{{ asset('assets/vendor/leaflet/leaflet.css') }}"/>
    <style>
        #delivery-boy-map { z-index: 0; border-radius: 0 0 0.25rem 0.25rem; }
    </style>
@endpush

@push('scripts')
    <script src="{{ hyperAsset('assets/vendor/star-rating.js/dist/star-rating.min.js') }}" defer></script>
    <script src="{{ hyperAsset('assets/vendor/leaflet/leaflet.js') }}"></script>
    <script src="{{ hyperAsset('assets/js/delivery-boy.js') }}"></script>
    <script src="{{ hyperAsset('assets/js/delivery-boy-detail.js') }}" defer></script>
    @if($blockPermission ?? false)
        <script src="{{ hyperAsset('assets/js/delivery-boy-block.js') }}" defer></script>
    @endif
    <script>
        window.__DB_DETAIL_CONFIG__ = {
            latitude: {{ $deliveryBoy->location?->latitude ?? 'null' }},
            longitude: {{ $deliveryBoy->location?->longitude ?? 'null' }},
            name: @json($deliveryBoy->full_name),
            hasLocation: {{ $deliveryBoy->location ? 'true' : 'false' }},
        };
    </script>
@endpush
