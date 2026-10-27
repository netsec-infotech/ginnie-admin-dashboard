@extends('layouts.admin.app', ['page' => $menuAdmin['support']['active'] ?? 'support', 'sub_page' => 'dashboard'])

@section('title', 'Support dashboard')

@section('header_data')
    @php
        $page_title = 'Support dashboard';
        $page_pretitle = 'Customer support';
    @endphp
@endsection

@section('admin-content')
    <div id="support-dashboard"
         data-refresh-url="{{ route('admin.support.dashboard.data') }}"
         data-queue-url="{{ route('admin.support.dashboard.queue') }}"
         data-days="{{ $days }}"
         data-late-minutes="{{ $lateReplyMinutes }}"
         data-poll-interval="{{ $pollInterval }}">
        <div id="support-dashboard-realtime-alert" class="alert alert-warning d-none align-items-center gap-3" role="alert" aria-live="polite">
            <span class="avatar avatar-sm bg-warning-lt text-warning flex-shrink-0"><i class="ti ti-plug-connected-x fs-2"></i></span>
            <div class="flex-fill">
                <div class="fw-semibold">Support dashboard live connection failed</div>
                <div class="small">Dashboard data is using the 15-second API fallback until the private Reverb or Pusher channel reconnects.</div>
            </div>
            @can('updateSetting', [\App\Models\Setting::class, 'broadcast_driver'])
                <a class="btn btn-warning btn-sm flex-shrink-0" href="{{ route('admin.settings.show', ['setting' => 'broadcast_driver']) }}">
                    {{ __('labels.configure_now') }}
                </a>
            @endcan
        </div>

        <div class="card mb-3">
            <div class="card-header flex-wrap gap-3">
                <div class="flex-fill">
                    <div class="d-flex align-items-center gap-2">
                        <h3 class="card-title mb-0">Support performance</h3>
                        <span id="support-dashboard-connection" class="small fw-medium text-secondary d-inline-flex align-items-center gap-1" aria-live="polite">
                            <span class="status-dot status-dot-animated bg-secondary"></span> Connecting
                        </span>
                    </div>
                    <div class="text-secondary mt-1">
                        Activity from {{ $from->format('d M Y') }} to {{ $to->format('d M Y') }}
                        <span class="d-none d-sm-inline">· Updated <span id="support-dashboard-updated">just now</span></span>
                    </div>
                </div>
                <div class="card-actions d-flex align-items-center gap-2">
                    <div class="dropdown">
                        <button class="btn btn-outline-secondary dropdown-toggle" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                            <i class="ti ti-calendar-stats me-1"></i> Last {{ $days }} days
                        </button>
                        <div class="dropdown-menu dropdown-menu-end">
                            <div class="dropdown-header">Reporting period</div>
                            @foreach([7, 30, 90] as $period)
                                <a class="dropdown-item {{ $days === $period ? 'active' : '' }}"
                                   href="{{ route('admin.support.dashboard', ['days' => $period, 'late_minutes' => $lateReplyMinutes]) }}"
                                   @if($days === $period) aria-current="page" @endif>
                                    <span class="me-auto">Last {{ $period }} days</span>
                                    @if($days === $period)<i class="ti ti-check ms-2"></i>@endif
                                </a>
                            @endforeach
                        </div>
                    </div>
                    <a class="btn btn-primary" href="{{ route('admin.support.index') }}">
                        <i class="ti ti-messages me-1"></i><span class="d-none d-sm-inline">Open inbox</span>
                    </a>
                </div>
            </div>
        </div>

        <div id="support-dashboard-content" aria-live="polite">
            @include('admin.support.partials.dashboard-content')
        </div>

        <div class="row row-cards mt-1">
            <div class="col-12">
                <div class="card">
                    <div class="card-header">
                        <div>
                            <h3 class="card-title">Support team</h3>
                            <div class="text-secondary">Search, sort, paginate, and export executive performance. Average response is assignment to first reply.</div>
                        </div>
                        <div class="card-actions">
                            <button class="btn btn-icon btn-ghost-secondary refresh-table" type="button" aria-label="Refresh support team" title="Refresh">
                                <i class="ti ti-refresh"></i>
                            </button>
                        </div>
                    </div>
                    <div class="card-body">
                        <x-datatable id="support-team-table"
                                     :columns="$teamColumns"
                                     route="{{ route('admin.support.dashboard.team', ['days' => $days]) }}"
                                     :options="['order' => [[2, 'desc']], 'pageLength' => 10]"
                                     class="table table-vcenter w-100"/>
                    </div>
                </div>
            </div>

            <div class="col-12">
                <div class="card">
                    <div class="card-header">
                        <div>
                            <h3 class="card-title">Ratings feedback</h3>
                            <div class="text-secondary">Customer comments attributed to the executive assigned when the conversation was resolved.</div>
                        </div>
                        <div class="card-actions">
                            <button class="btn btn-icon btn-ghost-secondary refresh-table" type="button" aria-label="Refresh ratings feedback" title="Refresh">
                                <i class="ti ti-refresh"></i>
                            </button>
                        </div>
                    </div>
                    <div class="card-body">
                        <x-datatable id="support-ratings-table"
                                     :columns="$ratingColumns"
                                     route="{{ route('admin.support.dashboard.ratings', ['days' => $days]) }}"
                                     :options="['order' => [[5, 'desc']], 'pageLength' => 10]"
                                     class="table table-vcenter w-100"/>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection

@push('script')
    <script src="{{ hyperAsset('assets/js/support-dashboard.js') }}"></script>
@endpush

@push('styles')
    <style>
        #support-dashboard-content { transition: opacity .18s ease, transform .18s ease; }
        #support-dashboard-content.is-refreshing { opacity: .55; transform: translateY(.15rem); }
        .support-dashboard-list { max-height: 26rem; overflow-y: auto; scrollbar-width: thin; }
        .support-dashboard-queue-item { animation: support-dashboard-item-in .18s ease both; }
        .support-dashboard-wait { font-variant-numeric: tabular-nums; }
        #support-ratings-table td { vertical-align: top; }
        @keyframes support-dashboard-item-in {
            from { opacity: 0; transform: translateY(.25rem); }
            to { opacity: 1; transform: translateY(0); }
        }
        @media (prefers-reduced-motion: reduce) {
            #support-dashboard-content, .support-dashboard-queue-item { transition: none; animation: none; }
        }
    </style>
@endpush
