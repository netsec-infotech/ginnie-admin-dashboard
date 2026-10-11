@extends('layouts.admin.app', ['page' => 'returns_disputed'])

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
@endphp

@section('admin-content')
    <div class="page-wrapper">
        <div class="page-body">
            <div class="row row-cards py-3">
                <div class="col-sm-6 col-lg-4">
                    <div class="card card-sm">
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
                        </div>
                    </div>
                </div>
                <div class="col-sm-6 col-lg-4">
                    <div class="card card-sm">
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
                        </div>
                    </div>
                </div>
                <div class="col-sm-6 col-lg-4">
                    <div class="card card-sm">
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
                                    route="{{ route('admin.returns.disputed-datatable') }}" :options="['order' => [[0, 'desc']], 'pageLength' => 10]" />
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
            const refreshBtn = document.getElementById('refresh-disputes');
            if (refreshBtn) {
                refreshBtn.addEventListener('click', function() {
                    if (window.LaravelDataTables && window.LaravelDataTables['disputes-table']) {
                        window.LaravelDataTables['disputes-table'].draw();
                    } else if ($.fn.DataTable.isDataTable('#disputes-table')) {
                        $('#disputes-table').DataTable().ajax.reload();
                    }
                });
            }
        });
    </script>
@endpush
