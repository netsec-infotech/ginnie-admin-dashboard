@php use App\Models\Setting; @endphp
@extends('layouts.admin.app', ['page' => $menuAdmin['stores']['active'] ?? ''])

@section('title', __('labels.stores'))

@section('header_data')
    @php
        $page_title = __('labels.stores');
        $page_pretitle = __('labels.list');
    @endphp
@endsection

@php
    $breadcrumbs = [
        ['title' => __('labels.home'), 'url' => route('admin.dashboard')],
        ['title' => __('labels.stores'), 'url' => null],
    ];
@endphp

@section('admin-content')
    <div class="row row-cards py-3">
        <div class="col-sm-6 col-lg-3">
            <div class="card card-sm">
                <div class="card-body">
                    <div class="row align-items-center">
                        <div class="col-auto">
                            <span class="bg-primary text-white avatar">
                                <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24"
                                    fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"
                                    stroke-linejoin="round"
                                    class="icon icon-tabler icons-tabler-outline icon-tabler-building-store">
                                    <path stroke="none" d="M0 0h24v24H0z" fill="none" />
                                    <path d="M3 21l18 0" />
                                    <path
                                        d="M3 7v1a3 3 0 0 0 6 0v-1m0 1a3 3 0 0 0 6 0v-1m0 1a3 3 0 0 0 6 0v-1h-18l2 -4h14l2 4" />
                                    <path d="M5 21l0 -10.15" />
                                    <path d="M19 21l0 -10.15" />
                                    <path d="M9 21v-4a2 2 0 0 1 2 -2h2a2 2 0 0 1 2 2v4" />
                                </svg>
                            </span>
                        </div>
                        <div class="col">
                            <div class="fw-bold fs-2">
                                <span id="insight-total-stores">{{ $storeInsights['total_stores'] ?? 0 }}</span>
                            </div>
                            <div class="text-secondary">{{ __('labels.total_stores') ?? 'Total Stores' }}</div>
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
                                <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24"
                                    fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"
                                    stroke-linejoin="round"
                                    class="icon icon-tabler icons-tabler-outline icon-tabler-eye-check">
                                    <path stroke="none" d="M0 0h24v24H0z" fill="none" />
                                    <path d="M10 12a2 2 0 1 0 4 0a2 2 0 0 0 -4 0" />
                                    <path
                                        d="M11.102 17.957c-3.204 -.307 -5.904 -2.294 -8.102 -5.957c2.4 -4 5.4 -6 9 -6c3.6 0 6.6 2 9 6a19.5 19.5 0 0 1 -.663 1.032" />
                                    <path d="M15 19l2 2l4 -4" />
                                </svg>
                            </span>
                        </div>
                        <div class="col">
                            <div class="fw-bold fs-2">
                                <span id="insight-approved-stores">{{ $storeInsights['approved_stores'] ?? 0 }}</span>
                            </div>
                            <div class="text-secondary">{{ __('labels.approved_stores') ?? 'Approved Stores' }}</div>
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
                                <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24"
                                    fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"
                                    stroke-linejoin="round" class="icon icon-tabler icons-tabler-outline icon-tabler-star">
                                    <path stroke="none" d="M0 0h24v24H0z" fill="none" />
                                    <path
                                        d="M12 17.75l-6.172 3.245l1.179 -6.873l-5 -4.867l6.9 -1l3.086 -6.253l3.086 6.253l6.9 1l-5 4.867l1.179 6.873z" />
                                </svg>
                            </span>
                        </div>
                        <div class="col">
                            <div class="fw-bold fs-2">
                                <span id="insight-recommended-stores">{{ $storeInsights['recommended_stores'] ?? 0 }}</span>
                            </div>
                            <div class="text-secondary">{{ __('labels.recommended_stores') ?? 'Recommended Stores' }}</div>
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
                            <span class="bg-danger text-white avatar">
                                <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24"
                                    fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"
                                    stroke-linejoin="round"
                                    class="icon icon-tabler icons-tabler-outline icon-tabler-eye-cancel">
                                    <path stroke="none" d="M0 0h24v24H0z" fill="none" />
                                    <path d="M10 12a2 2 0 1 0 4 0a2 2 0 0 0 -4 0" />
                                    <path d="M12 18c-3.6 0 -6.6 -2 -9 -6c2.4 -4 5.4 -6 9 -6c3.6 0 6.6 2 9 6" />
                                    <path d="M16 19a3 3 0 1 0 6 0a3 3 0 1 0 -6 0" />
                                    <path d="M17 21l4 -4" />
                                </svg>
                            </span>
                        </div>
                        <div class="col">
                            <div class="fw-bold fs-2">
                                <span
                                    id="insight-not-approved-stores">{{ $storeInsights['not_approved_stores'] ?? 0 }}</span>
                            </div>
                            <div class="text-secondary">{{ __('labels.not_approved_stores') ?? 'Not Approved Stores' }}
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="row row-cards">
        <div class="col-12">
            <div class="card">
                <div class="card-header">
                    <div>
                        @php
                            $approveStores = \App\Helpers\SidebarBadgeHelper::getBadgeCounts('admin')['stores'] ?? 0;
                        @endphp
                        <h3 class="card-title">
                            {{ __('labels.stores') }}
                            @if ($approveStores > 0)
                                <span class="badge bg-danger text-white ms-2">
                                    <span class="status-dot status-dot-animated bg-white me-1"></span>{{ $approveStores }}
                                    {{ __('labels.action_required') }}
                                </span>
                            @endif
                        </h3>
                        <x-breadcrumb :items="$breadcrumbs" />
                    </div>
                    <div class="card-actions">
                        <div class="row g-2">
                            <div class="col-auto">
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
                    </div>
                </div>
                @if (Setting::isSystemVendorTypeMultiple())
                    <div class="card-table">
                        <div class="align-items-center d-lg-flex d-block justify-content-between pt-3 px-3 gap-2">
                            <div class="row w-100">
                                <div class="col-md-3">
                                    <label class="form-label">{{ __('labels.search_by_seller') }}</label>
                                    <input type="text" class="form-control" name="name" id="select-seller"
                                        multiple placeholder="{{ __('labels.enter_seller_name') }}" />
                                </div>
                                <div class="col-md-3">
                                    <label class="form-label">{{ __('labels.filter_by_verification_status') }}</label>
                                    <select class="form-select text-capitalize" id="verification-status">
                                        <option value="">{{ __('labels.select_status') }}</option>
                                        @foreach ($verificationStatus as $status)
                                            <option value="{{ $status }}">{{ Str::replace('_', ' ', $status) }}
                                            </option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="col-md-3">
                                    <label class="form-label">{{ __('labels.filter_by_visibility_status') }}</label>
                                    <select class="form-select text-capitalize" id="visibility-status">
                                        <option value="">{{ __('labels.select_status') }}</option>
                                        @foreach ($visibilityStatus as $vStatus)
                                            <option value="{{ $vStatus }}">{{ Str::replace('_', ' ', $vStatus) }}
                                            </option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="col-md-3">
                                    <label class="form-label">{{ __('labels.recommended') }}</label>
                                    <select class="form-select text-capitalize" id="recommended-status">
                                        <option value="">{{ __('labels.select_status') }}</option>
                                        <option value="1">{{ __('labels.recommended_only') }}</option>
                                        <option value="0">{{ __('labels.not_recommended') }}</option>
                                    </select>
                                </div>
                            </div>
                            <button type="button" class="btn btn-primary mt-4 py-2" id="btn-filter">Filter</button>
                        </div>
                @endif
                <div class="alert alert-info alert-dismissible m-3" role="alert">
                    <div class="alert-icon">
                        <!-- Download SVG icon from http://tabler.io/icons/icon/info-circle -->
                        <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24"
                            fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"
                            stroke-linejoin="round" class="icon alert-icon icon-2">
                            <path d="M3 12a9 9 0 1 0 18 0a9 9 0 0 0 -18 0" />
                            <path d="M12 9h.01" />
                            <path d="M11 12h1v4h1" />
                        </svg>
                    </div>
                    {{ __('messages.verify_store_info') }}
                    <a class="btn-close" data-bs-dismiss="alert" aria-label="close"></a>
                </div>
                <div class="row w-full p-3">
                    <x-datatable id="stores-table" :columns="$columns"
                        route="{{ route('admin.sellers.store.datatable', isset($seller) ? ['seller_id' => $seller->id] : []) }}"
                        :options="['order' => [[0, 'desc']], 'pageLength' => 10]" />
                </div>
            </div>
        </div>
    </div>
    </div>
@endsection
