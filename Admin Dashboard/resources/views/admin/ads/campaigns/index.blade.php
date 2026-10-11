@extends('layouts.admin.app', ['page' => $menuAdmin['ad_campaigns']['active'] ?? '', 'sub_page' => $menuAdmin['ad_campaigns']['route']['index']['sub_active'] ?? ''])

@section('title', __('labels.ad_campaigns'))

@section('header_data')
    @php
        $page_title = __('labels.ad_campaigns');
        $page_pretitle = __('labels.list');
    @endphp
@endsection

@php
    $breadcrumbs = [
        ['title' => __('labels.home'), 'url' => route('admin.dashboard')],
        ['title' => __('labels.ad_campaigns'), 'url' => null],
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
                                    class="icon icon-tabler icons-tabler-outline icon-tabler-speakerphone">
                                    <path stroke="none" d="M0 0h24v24H0z" fill="none" />
                                    <path d="M18 8a3 3 0 0 1 0 6" />
                                    <path
                                        d="M10 8v6a2 2 0 0 1 -2 2h-1a1 1 0 0 1 -1 -1v-8a1 1 0 0 1 1 -1h1a2 2 0 0 1 2 2z" />
                                    <path d="M10 8l7 -4v16l-7 -4" />
                                </svg>
                            </span>
                        </div>
                        <div class="col">
                            <div class="fw-bold fs-2">
                                <span id="insight-total-campaigns">{{ $campaignInsights['total'] ?? 0 }}</span>
                            </div>
                            <div class="text-secondary">{{ __('labels.campaigns_listed') ?? 'Campaigns Listed' }}</div>
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
                                    class="icon icon-tabler icons-tabler-outline icon-tabler-player-play">
                                    <path stroke="none" d="M0 0h24v24H0z" fill="none" />
                                    <path d="M7 4v16l13 -8z" />
                                </svg>
                            </span>
                        </div>
                        <div class="col">
                            <div class="fw-bold fs-2">
                                <span id="insight-running-campaigns">{{ $campaignInsights['running'] ?? 0 }}</span>
                            </div>
                            <div class="text-secondary">{{ __('labels.campaigns_running') ?? 'Campaigns Running' }}</div>
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
                                <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24"
                                    fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"
                                    stroke-linejoin="round" class="icon">
                                    <path d="M12 9v4" />
                                    <path
                                        d="M10.363 3.591l-8.106 13.534a1.914 1.914 0 0 0 1.636 2.871h16.214a1.914 1.914 0 0 0 1.636 -2.87l-8.106 -13.536a1.914 1.914 0 0 0 -3.274 0z" />
                                    <path d="M12 16h.01" />
                                </svg>
                            </span>
                        </div>
                        <div class="col">
                            <div class="fw-bold fs-2">
                                <span id="insight-pending-campaigns">{{ $campaignInsights['pending'] ?? 0 }}</span>
                            </div>
                            <div class="text-secondary">{{ __('labels.campaigns_pending_approval') ?? 'Pending Approval' }}
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
                            <span class="bg-danger text-white avatar">
                                <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24"
                                    fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"
                                    stroke-linejoin="round"
                                    class="icon icon-tabler icons-tabler-outline icon-tabler-cancel">
                                    <path stroke="none" d="M0 0h24v24H0z" fill="none" />
                                    <path d="M3 12a9 9 0 1 0 18 0a9 9 0 1 0 -18 0" />
                                    <path d="M18.364 5.636l-12.728 12.728" />
                                </svg>
                            </span>
                        </div>
                        <div class="col">
                            <div class="fw-bold fs-2">
                                <span id="insight-rejected-campaigns">{{ $campaignInsights['rejected'] ?? 0 }}</span>
                            </div>
                            <div class="text-secondary">{{ __('labels.campaigns_rejected') ?? 'Campaigns Rejected' }}</div>
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
                            $adCampaignsPendingApproval =
                                \App\Helpers\SidebarBadgeHelper::getBadgeCounts('admin')['ad_campaigns'] ?? 0;
                        @endphp
                        <h3 class="card-title">{{ __('labels.ad_campaigns') }}
                            @if ($adCampaignsPendingApproval > 0)
                                <span class="badge bg-danger text-white ms-2">
                                    <span
                                        class="status-dot status-dot-animated bg-white me-1"></span>{{ $adCampaignsPendingApproval }}
                                    {{ __('labels.action_required') }}
                                </span>
                            @endif
                        </h3>
                        <x-breadcrumb :items="$breadcrumbs" />
                    </div>
                    <div class="card-actions">
                        <div class="row g-2">
                            <div class="col-auto">
                                <select class="form-select" id="statusFilter">
                                    @foreach ($statusOptions as $value => $label)
                                        <option value="{{ $value }}">{{ $label }}</option>
                                    @endforeach
                                </select>
                            </div>
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
                <div class="card-table">
                    <div class="row w-full p-3">
                        <x-datatable id="ad-campaigns-table" :columns="$columns"
                            route="{{ route('admin.ads.campaigns.datatable') }}" :options="['order' => [[5, 'desc']], 'pageLength' => 10]" />
                    </div>
                </div>
            </div>

            {{-- Reject modal --}}
            <div class="modal fade" id="rejectModal" tabindex="-1" aria-hidden="true">
                <div class="modal-dialog modal-sm modal-dialog-centered">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h5 class="modal-title">{{ __('labels.reject_campaign') }}</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                        </div>
                        <div class="modal-body">
                            <label class="form-label fw-semibold"
                                for="rejectReason">{{ __('labels.ad_campaign_rejection_reason') }} <span
                                    class="text-danger">*</span></label>
                            <textarea id="rejectReason" class="form-control" rows="4"
                                placeholder="{{ __('labels.ad_campaign_reject_reason_placeholder') }}"></textarea>
                            <div class="invalid-feedback" id="rejectReasonError">
                                {{ __('labels.ad_campaign_reason_required') }}</div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-link text-secondary"
                                data-bs-dismiss="modal">{{ __('labels.cancel') }}</button>
                            <button type="button" class="btn btn-danger"
                                id="confirmRejectBtn">{{ __('labels.ad_campaign_reject') }}</button>
                        </div>
                    </div>
                </div>
            </div>

            {{-- Force-stop modal --}}
            <div class="modal fade" id="forceStopModal" tabindex="-1" aria-hidden="true">
                <div class="modal-dialog modal-sm modal-dialog-centered">
                    <div class="modal-content border-danger">
                        <div class="modal-header bg-danger text-white">
                            <h5 class="modal-title">{{ __('labels.force_stop_campaign') }}</h5>
                            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                        </div>
                        <div class="modal-body">
                            <p class="text-muted small mb-3">
                                {{ __('labels.ad_campaign_force_stop_warning') }}
                            </p>
                            <label class="form-label fw-semibold"
                                for="forceStopReason">{{ __('labels.ad_campaign_force_stop_reason') }} <span
                                    class="text-danger">*</span></label>
                            <textarea id="forceStopReason" class="form-control" rows="4"
                                placeholder="{{ __('labels.ad_campaign_force_stop_reason_placeholder') }}"></textarea>
                            <div class="invalid-feedback" id="forceStopReasonError">
                                {{ __('labels.ad_campaign_reason_required') }}</div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-link text-secondary"
                                data-bs-dismiss="modal">{{ __('labels.cancel') }}</button>
                            <button type="button" class="btn btn-danger"
                                id="confirmForceStopBtn">{{ __('labels.ad_campaign_force_stop') }}</button>
                        </div>
                    </div>
                </div>
            </div>
        @endsection

        @push('scripts')
            <script src="{{ hyperAsset('assets/js/admin-ad-campaigns.js') }}" defer></script>
        @endpush
