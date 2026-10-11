@extends('layouts.admin.app', ['page' => $menuAdmin['seller_management']['active'] ?? "", 'sub_page' => $menuAdmin['seller_management']['route']['sellers']['sub_active'] ?? "" ])

@section('title', $seller->user->name ?? __('labels.seller_details'))

@section('header_data')
    @php
        $page_title = $seller->user->name ?? __('labels.seller');
        $page_pretitle = __('labels.seller_details');
    @endphp
@endsection

@php
    $breadcrumbs = [
        ['title' => __('labels.home'), 'url' => route('admin.dashboard')],
        ['title' => __('labels.sellers'), 'url' => route('admin.sellers.index')],
        ['title' => $seller->user->name ?? __('labels.seller'), 'url' => null],
    ];

    $vStatus = $seller->verification_status;
    $vClass = match($vStatus) {
        'approved' => 'bg-success-lt border-success-subtle',
        'rejected' => 'bg-danger-lt border-danger-subtle',
        default    => 'bg-warning-lt border-warning-subtle',
    };

    $visStatus = $seller->visibility_status;
    $visClass = match($visStatus) {
        'visible' => 'bg-success-lt border-success-subtle',
        default   => 'bg-secondary-lt border-secondary-subtle',
    };
@endphp

@section('admin-content')
    <div class="page-wrapper">
        <!-- PAGE HEADER -->
        <div class="page-header d-print-none">
            <div class="row g-2 align-items-center">
                <div class="col">
                    <h2 class="page-title text-capitalize">{{ $seller->user->name ?? '' }} - {{ __('labels.seller') }}</h2>
                    <x-breadcrumb :items="$breadcrumbs"/>
                </div>
                <div class="col-12 col-md-auto ms-auto d-print-none">
                    <div class="btn-list">
                        @if($editPermission ?? false)
                            <a href="{{ route('admin.sellers.edit', $seller->id) }}" class="btn btn-primary">
                                <i class="ti ti-edit me-1"></i>{{ __('labels.edit') }}
                            </a>
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
                                  style="background-image: url('{{ $profileImageUrl }}')">
                            </span>
                        </div>
                        <div class="col">
                            <h2 class="mb-1">{{ $seller->user->name ?? 'N/A' }}</h2>
                            <div class="text-secondary mb-1">
                                <i class="ti ti-mail me-1"></i>{{ $seller->user->email ?? 'N/A' }}
                                <span class="mx-2">|</span>
                                <i class="ti ti-phone me-1"></i>{{ $seller->user->mobile ?? 'N/A' }}
                            </div>
                            <div class="mt-2">
                                <span class="badge border p-2 {{ $vClass }}">
                                    {{ ucfirst(str_replace('_', ' ', $vStatus)) }}
                                </span>
                                <span class="badge border p-2 {{ $visClass }}">
                                    {{ ucfirst($visStatus) }}
                                </span>
                                @if($seller->activeSubscription?->plan)
                                    <span class="badge border p-2 bg-purple-lt border-purple-subtle">
                                        <i class="ti ti-crown me-1"></i>{{ $seller->activeSubscription->plan->name }}
                                    </span>
                                @endif
                            </div>
                        </div>
                        <div class="col-auto">
                            <div class="text-end">
                                <div class="text-secondary small">{{ __('labels.registration_date') }}</div>
                                <div class="fw-bold">{{ $seller->created_at->format('d M Y') }}</div>
                                <div class="text-secondary small mt-1">
                                    {{ __('labels.stores') }}: <strong>{{ $seller->stores->count() }}</strong>
                                </div>
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
                                    <div class="fw-bold fs-3">{{ getCurrencySymbol() . number_format($seller->wallet?->balance ?? 0, 2) }}</div>
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
                            <a href="#tab-overview" class="nav-link active" data-bs-toggle="tab" role="tab" aria-selected="true">
                                <i class="ti ti-info-circle me-1"></i>{{ __('labels.overview') }}
                            </a>
                        </li>
                        <li class="nav-item" role="presentation">
                            <a href="#tab-stores" class="nav-link" data-bs-toggle="tab" role="tab">
                                <i class="ti ti-building-store me-1"></i>{{ __('labels.stores') }}
                            </a>
                        </li>
                        <li class="nav-item" role="presentation">
                            <a href="#tab-wallet" class="nav-link" data-bs-toggle="tab" role="tab">
                                <i class="ti ti-wallet me-1"></i>{{ __('labels.wallet') }} & {{ __('labels.settlements') }}
                            </a>
                        </li>
                        <li class="nav-item" role="presentation">
                            <a href="#tab-feedback" class="nav-link" data-bs-toggle="tab" role="tab">
                                <i class="ti ti-star me-1"></i>{{ __('labels.feedback') }}
                            </a>
                        </li>
                        <li class="nav-item" role="presentation">
                            <a href="#tab-notifications" class="nav-link" data-bs-toggle="tab" role="tab">
                                <i class="ti ti-bell me-1"></i>{{ __('labels.notifications') }} & FCM
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
                                {{-- Personal / Business Information --}}
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
                                                        <td class="border-0">{{ $seller->user->name ?? 'N/A' }}</td>
                                                    </tr>
                                                    <tr>
                                                        <td class="fw-bold border-0">{{ __('labels.email') }}</td>
                                                        <td class="border-0">{{ $seller->user->email ?? 'N/A' }}</td>
                                                    </tr>
                                                    <tr>
                                                        <td class="fw-bold border-0">{{ __('labels.mobile') }}</td>
                                                        <td class="border-0">{{ $seller->user->mobile ?? 'N/A' }}</td>
                                                    </tr>
                                                    <tr>
                                                        <td class="fw-bold border-0">{{ __('labels.address') }}</td>
                                                        <td class="border-0">{{ $seller->address ?? 'N/A' }}</td>
                                                    </tr>
                                                    <tr>
                                                        <td class="fw-bold border-0">{{ __('labels.city') }}</td>
                                                        <td class="border-0">{{ $seller->city ?? 'N/A' }}</td>
                                                    </tr>
                                                    <tr>
                                                        <td class="fw-bold border-0">{{ __('labels.state') }}</td>
                                                        <td class="border-0">{{ $seller->state ?? 'N/A' }}</td>
                                                    </tr>
                                                    <tr>
                                                        <td class="fw-bold border-0">{{ __('labels.country') }}</td>
                                                        <td class="border-0">{{ $seller->country ?? 'N/A' }}</td>
                                                    </tr>
                                                    <tr>
                                                        <td class="fw-bold border-0">{{ __('labels.zipcode') }}</td>
                                                        <td class="border-0">{{ $seller->zipcode ?? 'N/A' }}</td>
                                                    </tr>
                                                    </tbody>
                                                </table>
                                            </div>
                                        </div>
                                    </div>

                                    {{-- Verification & Status --}}
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
                                                            <span class="badge border p-2 {{ $vClass }}">
                                                                {{ ucfirst(str_replace('_', ' ', $vStatus)) }}
                                                            </span>
                                                        </td>
                                                    </tr>
                                                    <tr>
                                                        <td class="fw-bold border-0">{{ __('labels.visibility_status') }}</td>
                                                        <td class="border-0">
                                                            <span class="badge border p-2 {{ $visClass }}">
                                                                {{ ucfirst($visStatus) }}
                                                            </span>
                                                        </td>
                                                    </tr>
                                                    @if($seller->activeSubscription)
                                                        <tr>
                                                            <td class="fw-bold border-0">{{ __('labels.subscription') }}</td>
                                                            <td class="border-0">
                                                                <span class="badge {{ $seller->activeSubscription->badgeClass() }} p-2">
                                                                    {{ $seller->activeSubscription->plan->name ?? 'N/A' }}
                                                                </span>
                                                                @if($seller->activeSubscription->end_date)
                                                                    <div class="text-muted small mt-1">
                                                                        {{ __('labels.expires') }}: {{ $seller->activeSubscription->end_date->format('d M Y') }}
                                                                    </div>
                                                                @endif
                                                            </td>
                                                        </tr>
                                                    @endif
                                                    <tr>
                                                        <td class="fw-bold border-0">{{ __('labels.post_accept_cancel_count') }}</td>
                                                        <td class="border-0">
                                                            <span class="badge {{ ($seller->post_accept_cancel_count ?? 0) > 3 ? 'bg-danger-lt' : 'bg-secondary-lt' }}">
                                                                {{ $seller->post_accept_cancel_count ?? 0 }}
                                                            </span>
                                                        </td>
                                                    </tr>
                                                    </tbody>
                                                </table>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                {{-- Map + Order Breakdown --}}
                                <div class="col-lg-6">
                                    <div class="card mb-4">
                                        <div class="card-header">
                                            <h4 class="card-title">{{ __('labels.location') }}</h4>
                                        </div>
                                        <div class="card-body p-0">
                                            <div id="seller-map" style="height: 300px; width: 100%;"></div>
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
                                                    ['label' => __('labels.delivered'), 'count' => $orderStats['delivered'], 'class' => 'bg-success'],
                                                    ['label' => __('labels.pending'), 'count' => $orderStats['pending'], 'class' => 'bg-warning'],
                                                    ['label' => __('labels.in_progress'), 'count' => $orderStats['in_progress'], 'class' => 'bg-info'],
                                                    ['label' => __('labels.cancelled'), 'count' => $orderStats['cancelled'], 'class' => 'bg-danger'],
                                                ];
                                            @endphp
                                            @foreach($breakdowns as $item)
                                                <div class="mb-3">
                                                    <div class="d-flex justify-content-between mb-1">
                                                        <span>{{ $item['label'] }}</span>
                                                        <span class="fw-bold">{{ $item['count'] }}</span>
                                                    </div>
                                                    <div class="progress progress-sm">
                                                        <div class="progress-bar {{ $item['class'] }}"
                                                             style="width: {{ round(($item['count'] / $total) * 100) }}%"></div>
                                                    </div>
                                                </div>
                                            @endforeach
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        {{-- TAB: STORES --}}
                        <div class="tab-pane" id="tab-stores" role="tabpanel">
                            @if($seller->stores->count() > 0)
                                <div class="row row-cards">
                                    @foreach($seller->stores as $store)
                                        <div class="col-md-6 col-lg-4">
                                            <div class="card">
                                                <div class="card-body">
                                                    <div class="d-flex align-items-center mb-3">
                                                        <span class="avatar avatar-md rounded me-3"
                                                              style="background-image: url('{{ $store->store_logo ?? asset('assets/images/store-placeholder.png') }}')"></span>
                                                        <div>
                                                            <h3 class="mb-0">{{ $store->name }}</h3>
                                                            <div class="text-secondary small">{{ $store->address ?? '' }}</div>
                                                        </div>
                                                    </div>
                                                    <div class="mb-2">
                                                        <span class="badge {{ $store->status === 'active' ? 'bg-success-lt' : 'bg-danger-lt' }}">
                                                            {{ ucfirst($store->status ?? 'inactive') }}
                                                        </span>
                                                    </div>
                                                    <div class="text-secondary small">
                                                        <i class="ti ti-phone me-1"></i>{{ $store->mobile ?? 'N/A' }}
                                                    </div>
                                                </div>
                                                <div class="card-footer">
                                                    <a href="{{ route('admin.sellers.store.show.index', ['id' => $seller->id]) }}"
                                                       class="btn btn-sm btn-outline-primary w-100">
                                                        {{ __('labels.view_store') }}
                                                    </a>
                                                </div>
                                            </div>
                                        </div>
                                    @endforeach
                                </div>
                            @else
                                <div class="empty">
                                    <div class="empty-icon"><i class="ti ti-building-store"></i></div>
                                    <p class="empty-title">{{ __('labels.no_stores_found') }}</p>
                                </div>
                            @endif
                        </div>

                        {{-- TAB: WALLET & SETTLEMENTS --}}
                        <div class="tab-pane" id="tab-wallet" role="tabpanel">
                            {{-- Earnings Summary Cards --}}
                            <div class="row row-cards mb-4">
                                <div class="col-sm-6 col-lg-3">
                                    <div class="card card-sm">
                                        <div class="card-body">
                                            <div class="fw-bold fs-3">{{  getCurrencySymbol() . number_format($earningsSummary['total_earnings'], 2) }}</div>
                                            <div class="text-secondary">{{ __('labels.total_earnings') }}</div>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-sm-6 col-lg-3">
                                    <div class="card card-sm">
                                        <div class="card-body">
                                            <div class="fw-bold fs-3 text-success">{{ getCurrencySymbol() . number_format($earningsSummary['total_settled'], 2) }}</div>
                                            <div class="text-secondary">{{ __('labels.total_settled') }}</div>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-sm-6 col-lg-3">
                                    <div class="card card-sm">
                                        <div class="card-body">
                                            <div class="fw-bold fs-3 text-warning">{{ getCurrencySymbol() . number_format($earningsSummary['total_pending'], 2) }}</div>
                                            <div class="text-secondary">{{ __('labels.pending_settlement') }}</div>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-sm-6 col-lg-3">
                                    <div class="card card-sm">
                                        <div class="card-body">
                                            <div class="fw-bold fs-3">{{ getCurrencySymbol() . $earningsSummary['total_entries'] }}</div>
                                            <div class="text-secondary">{{ __('labels.total_entries') }}</div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            {{-- Statements DataTable --}}
                            <div class="card">
                                <div class="card-header">
                                    <h4 class="card-title">{{ __('labels.recent_statements') }}</h4>
                                </div>
                                <div class="card-body">
                                    <x-datatable
                                        id="seller-statements-table"
                                        :route="route('admin.sellers.statements-datatable', $seller->id)"
                                        :columns="$statementColumns"
                                    />
                                </div>
                            </div>
                        </div>

                        {{-- TAB: FEEDBACK --}}
                        <div class="tab-pane" id="tab-feedback" role="tabpanel">
                            {{-- Rating Summary --}}
                            <div class="row mb-4">
                                <div class="col-md-4">
                                    <div class="card">
                                        <div class="card-body text-center">
                                            <div class="display-5 fw-bold text-warning">
                                                {{ $reviewData ? number_format($reviewData->average_rating, 1) : '0.0' }}
                                            </div>
                                            <div class="text-secondary">{{ __('labels.average_rating') }}</div>
                                            <div class="text-muted small">{{ $reviewData->total_reviews ?? 0 }} {{ __('labels.reviews') }}</div>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-8">
                                    <div class="card">
                                        <div class="card-body">
                                            @php
                                                $totalReviews = max($reviewData->total_reviews ?? 0, 1);
                                                $starCounts = [
                                                    5 => $reviewData->five_star_count ?? 0,
                                                    4 => $reviewData->four_star_count ?? 0,
                                                    3 => $reviewData->three_star_count ?? 0,
                                                    2 => $reviewData->two_star_count ?? 0,
                                                    1 => $reviewData->one_star_count ?? 0,
                                                ];
                                            @endphp
                                            @foreach($starCounts as $star => $count)
                                                <div class="d-flex align-items-center mb-2">
                                                    <span class="me-2" style="min-width: 20px;">{{ $star }}</span>
                                                    <i class="ti ti-star-filled text-warning me-2"></i>
                                                    <div class="progress flex-grow-1 progress-sm">
                                                        <div class="progress-bar bg-warning"
                                                             style="width: {{ round(($count / $totalReviews) * 100) }}%"></div>
                                                    </div>
                                                    <span class="ms-2 text-muted" style="min-width: 30px;">{{ $count }}</span>
                                                </div>
                                            @endforeach
                                        </div>
                                    </div>
                                </div>
                            </div>

                            {{-- Feedback DataTable --}}
                            <x-datatable
                                id="seller-feedback-table"
                                :route="route('admin.sellers.feedback-datatable', $seller->id)"
                                :columns="$feedbackColumns"
                            />
                        </div>

                        {{-- TAB: NOTIFICATIONS & FCM --}}
                        <div class="tab-pane" id="tab-notifications" role="tabpanel">
                            {{-- FCM Tokens --}}
                            <div class="card mb-4">
                                <div class="card-header">
                                    <h4 class="card-title"><i class="ti ti-device-mobile me-1"></i>{{ __('labels.fcm_device_tokens') }}</h4>
                                </div>
                                @if($fcmTokens->count() > 0)
                                    <div class="table-responsive">
                                        <table class="table table-vcenter card-table">
                                            <thead>
                                            <tr>
                                                <th>{{ __('labels.device_type') }}</th>
                                                <th>{{ __('labels.fcm_token') }}</th>
                                                <th>{{ __('labels.role_type') }}</th>
                                                <th>{{ __('labels.last_updated') }}</th>
                                            </tr>
                                            </thead>
                                            <tbody>
                                            @foreach($fcmTokens as $token)
                                                <tr>
                                                    <td>
                                                        @php
                                                            $deviceIcon = match($token->device_type?->value ?? $token->device_type) {
                                                                'android' => 'ti-brand-android',
                                                                'ios' => 'ti-brand-apple',
                                                                'web' => 'ti-world',
                                                                default => 'ti-device-mobile',
                                                            };
                                                        @endphp
                                                        <i class="ti {{ $deviceIcon }} me-1"></i>
                                                        {{ ucfirst($token->device_type?->value ?? $token->device_type ?? 'N/A') }}
                                                    </td>
                                                    <td>
                                                        <code class="text-truncate d-inline-block" style="max-width: 300px;">
                                                            {{ $token->fcm_token }}
                                                        </code>
                                                    </td>
                                                    <td><span class="badge bg-blue-lt">{{ ucfirst($token->role_type?->value ?? $token->role_type ?? 'N/A') }}</span></td>
                                                    <td>{{ $token->updated_at?->format('d M Y H:i') }}</td>
                                                </tr>
                                            @endforeach
                                            </tbody>
                                        </table>
                                    </div>
                                @else
                                    <div class="card-body">
                                        <div class="empty py-4">
                                            <div class="empty-icon"><i class="ti ti-device-mobile-off"></i></div>
                                            <p class="empty-title">{{ __('labels.no_fcm_tokens_found') }}</p>
                                        </div>
                                    </div>
                                @endif
                            </div>

                            {{-- Notifications DataTable --}}
                            <div class="card">
                                <div class="card-header">
                                    <h4 class="card-title"><i class="ti ti-bell me-1"></i>{{ __('labels.notification_history') }}</h4>
                                </div>
                                <div class="card-body">
                                    <x-datatable
                                        id="seller-notifications-table"
                                        :route="route('admin.sellers.notifications-datatable', $seller->id)"
                                        :columns="$notificationColumns"
                                    />
                                </div>
                            </div>
                        </div>

                        {{-- TAB: DOCUMENTS --}}
                        <div class="tab-pane" id="tab-documents" role="tabpanel">
                            @php
                                $documents = [
                                    ['label' => __('labels.business_license'), 'url' => $seller->getFirstMediaUrl(\App\Enums\SpatieMediaCollectionName::BUSINESS_LICENSE())],
                                    ['label' => __('labels.articles_of_incorporation'), 'url' => $seller->getFirstMediaUrl(\App\Enums\SpatieMediaCollectionName::ARTICLES_OF_INCORPORATION())],
                                    ['label' => __('labels.national_identity_card'), 'url' => $seller->getFirstMediaUrl(\App\Enums\SpatieMediaCollectionName::NATIONAL_IDENTITY_CARD())],
                                    ['label' => __('labels.authorized_signature'), 'url' => $seller->getFirstMediaUrl(\App\Enums\SpatieMediaCollectionName::AUTHORIZED_SIGNATURE())],
                                ];
                            @endphp
                            <div class="row row-cards">
                                @foreach($documents as $doc)
                                    <div class="col-md-6 col-lg-3">
                                        <div class="card">
                                            <div class="card-header">
                                                <h4 class="card-title">{{ $doc['label'] }}</h4>
                                            </div>
                                            <div class="card-body text-center">
                                                @if(!empty($doc['url']))
                                                    <a href="{{ $doc['url'] }}" target="_blank" data-fslightbox="gallery">
                                                        <img src="{{ $doc['url'] }}" alt="{{ $doc['label'] }}"
                                                             class="img-fluid rounded" style="max-height: 200px;">
                                                    </a>
                                                @else
                                                    <div class="empty py-4">
                                                        <div class="empty-icon"><i class="ti ti-file-off"></i></div>
                                                        <p class="empty-title text-muted">{{ __('labels.not_uploaded') }}</p>
                                                    </div>
                                                @endif
                                            </div>
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        </div>

                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection

@push('styles')
    <link rel="stylesheet" href="{{ asset('assets/vendor/leaflet/leaflet.css') }}"/>
@endpush

@push('scripts')
    <script src="{{ asset('assets/vendor/leaflet/leaflet.js') }}"></script>
    <script>
        window.__SELLER_DETAIL_CONFIG__ = {
            hasLocation: {{ ($seller->latitude && $seller->longitude) ? 'true' : 'false' }},
            latitude: {{ $seller->latitude ?? 0 }},
            longitude: {{ $seller->longitude ?? 0 }},
            name: @json($seller->user->name ?? 'Seller'),
        };
    </script>
    <script src="{{ asset('assets/js/seller-detail.js') }}" defer></script>
@endpush
