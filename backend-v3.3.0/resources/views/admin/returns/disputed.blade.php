@extends('layouts.admin.app', ['page' => $menuAdmin['returns']['active'] ?? 'returns', 'sub_page' => $menuAdmin['returns']['route']['return-disputes']['sub_active'] ?? 'return-disputes'])

@section('title', 'Disputed Returns')

@section('header_data')
    @php
        $page_title = 'Disputed Return Requests';
        $page_pretitle = 'Admin return management';
    @endphp
@endsection

@php
    $breadcrumbs = [
        ['title' => __('labels.home'), 'url' => route('admin.dashboard')],
        ['title' => __('labels.return_requests'), 'url' => route('admin.returns.index')],
        ['title' => 'Disputed Returns', 'url' => ''],
    ];

    $activeDisputeStatusGroup = $activeDisputeStatusGroup ?? null;
    $disputeInsightUrls = [
        'total' => route('admin.returns.disputed'),
        'under_process' => route('admin.returns.disputed', ['status_group' => 'under_process']),
        'resolved' => route('admin.returns.disputed', ['status_group' => 'resolved']),
    ];
    $disputesDatatableUrl = route(
        'admin.returns.disputed-datatable',
        $activeDisputeStatusGroup ? ['status_group' => $activeDisputeStatusGroup] : [],
    );
@endphp

@section('admin-content')
    <div class="page-wrapper">
        <div class="page-body">
            <div class="row row-cards py-3">
                <div class="col-sm-6 col-lg-4">
                    <div class="card card-sm position-relative insight-filter-card dispute-insight-card {{ $activeDisputeStatusGroup === null ? 'active' : '' }}"
                        data-status-group="">
                        <div class="card-body">
                            <div class="row align-items-center">
                                <div class="col-auto">
                                    <span class="bg-primary text-white avatar">
                                        <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="icon icon-tabler icons-tabler-outline icon-tabler-alert-circle">
                                            <path stroke="none" d="M0 0h24v24H0z" fill="none"/>
                                            <path d="M3 12a9 9 0 1 0 18 0a9 9 0 0 0 -18 0" />
                                            <path d="M12 8v4" />
                                            <path d="M12 16h.01" />
                                        </svg>
                                    </span>
                                </div>
                                <div class="col">
                                    <div class="fw-bold fs-2">
                                        <span id="insight-total-disputed">{{ $disputeInsights['total'] ?? 0 }}</span>
                                    </div>
                                    <div class="text-secondary">{{ __('labels.total_disputed') ?? 'Total Disputed' }}</div>
                                </div>
                            </div>
                            <a href="{{ $disputeInsightUrls['total'] }}" class="stretched-link dispute-insight-card-link"
                                aria-label="{{ __('labels.total_disputed') ?? 'Total Disputed' }}"
                                @if ($activeDisputeStatusGroup === null) aria-current="page" @endif></a>
                        </div>
                    </div>
                </div>
                <div class="col-sm-6 col-lg-4">
                    <div class="card card-sm position-relative insight-filter-card dispute-insight-card {{ $activeDisputeStatusGroup === 'under_process' ? 'active' : '' }}"
                        data-status-group="under_process">
                        <div class="card-body">
                            <div class="row align-items-center">
                                <div class="col-auto">
                                    <span class="bg-warning text-white avatar">
                                        <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="icon icon-tabler icons-tabler-outline icon-tabler-clock">
                                            <path stroke="none" d="M0 0h24v24H0z" fill="none"/>
                                            <path d="M3 12a9 9 0 1 0 18 0a9 9 0 0 0 -18 0" />
                                            <path d="M12 7v5l3 3" />
                                        </svg>
                                    </span>
                                </div>
                                <div class="col">
                                    <div class="fw-bold fs-2">
                                        <span id="insight-disputes-under-process">{{ $disputeInsights['under_process'] ?? 0 }}</span>
                                    </div>
                                    <div class="text-secondary">{{ __('labels.disputes_under_process') ?? 'Disputes Under Process' }}</div>
                                </div>
                            </div>
                            <a href="{{ $disputeInsightUrls['under_process'] }}" class="stretched-link dispute-insight-card-link"
                                aria-label="{{ __('labels.disputes_under_process') ?? 'Disputes Under Process' }}"
                                @if ($activeDisputeStatusGroup === 'under_process') aria-current="page" @endif></a>
                        </div>
                    </div>
                </div>
                <div class="col-sm-6 col-lg-4">
                    <div class="card card-sm position-relative insight-filter-card dispute-insight-card {{ $activeDisputeStatusGroup === 'resolved' ? 'active' : '' }}"
                        data-status-group="resolved">
                        <div class="card-body">
                            <div class="row align-items-center">
                                <div class="col-auto">
                                    <span class="bg-success text-white avatar">
                                        <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="icon">
                                            <path d="M5 12l5 5l10 -10" />
                                        </svg>
                                    </span>
                                </div>
                                <div class="col">
                                    <div class="fw-bold fs-2">
                                        <span id="insight-disputes-resolved">{{ $disputeInsights['resolved'] ?? 0 }}</span>
                                    </div>
                                    <div class="text-secondary">{{ __('labels.disputes_resolved') ?? 'Disputes Resolved' }}</div>
                                </div>
                            </div>
                            <a href="{{ $disputeInsightUrls['resolved'] }}" class="stretched-link dispute-insight-card-link"
                                aria-label="{{ __('labels.disputes_resolved') ?? 'Disputes Resolved' }}"
                                @if ($activeDisputeStatusGroup === 'resolved') aria-current="page" @endif></a>
                        </div>
                    </div>
                </div>
            </div>
            <div class="row row-cards">
                <div class="col-12">
                    <div class="card">
                        <div class="card-header flex-wrap gap-2">
                            @php
                                $disputedActionCount =
                                    \App\Helpers\SidebarBadgeHelper::getBadgeCounts('admin')['return_disputes'] ?? 0;
                            @endphp
                            <h3 class="card-title">
                                Disputed Return Requests <span class="order-count"></span>
                                @if ($disputedActionCount > 0)
                                    <span class="badge bg-danger text-white ms-2">
                                        <span class="status-dot status-dot-animated bg-white me-1"></span>{{ $disputedActionCount }}
                                        {{ __('labels.action_required') ?? 'Action Required' }}
                                    </span>
                                @endif
                            </h3>
                            <div class="card-actions ms-auto">
                                <button class="btn btn-outline-primary" id="refresh-disputes">
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

                        <div class="card-body">
                            <div class="table-responsive">
                                <x-datatable id="disputes-table" :columns="$columns"
                                    route="{{ $disputesDatatableUrl }}" :options="['order' => [[0, 'desc']], 'pageLength' => 10]" />
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection

@push('scripts')
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const validStatusGroups = new Set(['under_process', 'resolved']);
            const datatableBaseUrl = @json(route('admin.returns.disputed-datatable'));

            const getDisputesTable = function() {
                if (window.LaravelDataTables && window.LaravelDataTables['disputes-table']) {
                    return window.LaravelDataTables['disputes-table'];
                }

                if ($.fn.DataTable.isDataTable('#disputes-table')) {
                    return $('#disputes-table').DataTable();
                }

                return null;
            };

            const getStatusGroupFromUrl = function() {
                const statusGroup = new URL(window.location.href).searchParams.get('status_group');

                return validStatusGroups.has(statusGroup) ? statusGroup : null;
            };

            let activeStatusGroup = getStatusGroupFromUrl();

            const syncActiveInsightCard = function() {
                document.querySelectorAll('.dispute-insight-card').forEach(function(card) {
                    const cardStatusGroup = card.dataset.statusGroup || null;
                    const isActive = cardStatusGroup === activeStatusGroup;
                    const link = card.querySelector('.dispute-insight-card-link');

                    card.classList.toggle('active', isActive);

                    if (link) {
                        if (isActive) {
                            link.setAttribute('aria-current', 'page');
                        } else {
                            link.removeAttribute('aria-current');
                        }
                    }
                });
            };

            const updatePageUrl = function(statusGroup, historyMethod) {
                const url = new URL(window.location.href);

                if (statusGroup) {
                    url.searchParams.set('status_group', statusGroup);
                } else {
                    url.searchParams.delete('status_group');
                }

                window.history[historyMethod]({ statusGroup: statusGroup }, '', url);
            };

            const reloadDisputesTable = function(statusGroup) {
                const table = getDisputesTable();
                if (!table) {
                    return false;
                }

                const datatableUrl = new URL(datatableBaseUrl);
                if (statusGroup) {
                    datatableUrl.searchParams.set('status_group', statusGroup);
                }

                table.ajax.url(datatableUrl.toString()).load(null, false);

                return true;
            };

            document.querySelectorAll('.dispute-insight-card-link').forEach(function(link) {
                link.addEventListener('click', function(event) {
                    if (event.ctrlKey || event.metaKey || event.shiftKey || event.altKey) {
                        return;
                    }

                    event.preventDefault();

                    const card = link.closest('.dispute-insight-card');
                    const selectedStatusGroup = card?.dataset.statusGroup || null;

                    if (!reloadDisputesTable(selectedStatusGroup)) {
                        window.location.assign(link.href);
                        return;
                    }

                    const historyMethod = selectedStatusGroup === activeStatusGroup ? 'replaceState' : 'pushState';
                    activeStatusGroup = selectedStatusGroup;
                    updatePageUrl(activeStatusGroup, historyMethod);
                    syncActiveInsightCard();
                });
            });

            window.addEventListener('popstate', function() {
                activeStatusGroup = getStatusGroupFromUrl();
                syncActiveInsightCard();
                reloadDisputesTable(activeStatusGroup);
            });

            syncActiveInsightCard();

            const refreshBtn = document.getElementById('refresh-disputes');
            if (refreshBtn) {
                refreshBtn.addEventListener('click', function() {
                    const table = getDisputesTable();
                    if (table) {
                        table.ajax.reload(null, false);
                    }
                });
            }
        });
    </script>
@endpush
