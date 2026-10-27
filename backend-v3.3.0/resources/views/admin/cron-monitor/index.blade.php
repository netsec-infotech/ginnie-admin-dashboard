@extends('layouts.admin.app', ['page' => $menuAdmin['cron_monitor']['active'] ?? '', 'sub_page' => ''])

@section('title', __('labels.cron_monitor'))

@section('header_data')
    @php
        $page_title = __('labels.cron_monitor');
        $page_pretitle = __('labels.system');
    @endphp
@endsection

@php
    $breadcrumbs = [
        ['title' => __('labels.home'), 'url' => route('admin.dashboard')],
        ['title' => __('labels.cron_monitor'), 'url' => null],
    ];
@endphp

@section('admin-content')
    <div class="row row-cards" id="cron-monitor-container">

        {{-- Health banner --}}
        <div class="col-12">
            <div class="card">
                <div class="card-header">
                    <div>
                        <h3 class="card-title">{{ __('labels.cron_monitor') }}</h3>
                        <x-breadcrumb :items="$breadcrumbs" />
                    </div>
                    <div class="card-actions">
                        <div class="row g-2 align-items-center">
                            <div class="col-auto">
                                <span class="text-muted small" id="auto-refresh-countdown"></span>
                            </div>
                            <div class="col-auto">
                                <button class="btn btn-outline-primary btn-sm" id="refresh-status">
                                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24"
                                        fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"
                                        stroke-linejoin="round" class="icon icon-tabler icon-tabler-refresh">
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

                <div class="card-body">
                    {{-- Scheduler & Queue Worker health indicators --}}
                    @php
                        $phpBinary = shell_exec('which php')
                            ? trim(shell_exec('which php'))
                            : '/usr/local/bin/php';
                        $schedulerCmd = '* * * * * ' . $phpBinary . ' ' . base_path('artisan') . ' schedule:run >> ' . storage_path('logs/schedule.txt') . ' 2>&1';
                        $queueCmd = '* * * * * ' . $phpBinary . ' ' . base_path('artisan') . ' queue:work --stop-when-empty >> ' . storage_path('logs/cron-log.txt') . ' 2>&1';
                    @endphp
                    <div class="row g-3 mb-4">
                        <div class="col-md-6">
                            <div class="card card-sm border" id="scheduler-health-card">
                                <div class="card-body">
                                    <div class="d-flex align-items-center mb-2">
                                        <span class="avatar me-3 rounded" id="scheduler-health-icon"
                                            style="background-color: {{ $isSchedulerHealthy ? '#2fb344' : '#d63939' }}">
                                            <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24"
                                                viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
                                                stroke-linecap="round" stroke-linejoin="round" class="icon text-white">
                                                <path stroke="none" d="M0 0h24v24H0z" fill="none" />
                                                <path d="M12 12m-9 0a9 9 0 1 0 18 0a9 9 0 1 0 -18 0" />
                                                <path d="M12 7l0 5l3 3" />
                                            </svg>
                                        </span>
                                        <div>
                                            <div class="fw-bold">{{ __('labels.task_scheduler') }} (schedule:run)</div>
                                            <div class="text-muted small" id="scheduler-health-text">
                                                @if($isSchedulerHealthy)
                                                    {{ __('labels.cron_running') }}
                                                @else
                                                    {{ __('labels.cron_not_detected') }}
                                                @endif
                                            </div>
                                        </div>
                                    </div>
                                    <div class="mt-2">
                                        <label
                                            class="form-label small text-muted mb-1">{{ __('labels.server_cron_command') }}</label>
                                        <div class="input-group input-group-sm">
                                            <input type="text" class="form-control form-control-sm" readonly
                                                value="{{ $schedulerCmd }}" id="scheduler-cron-cmd">
                                            <button class="btn btn-outline-secondary copy-command-btn" type="button"
                                                data-target="scheduler-cron-cmd" title="{{ __('labels.copy_command') }}">
                                                <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14"
                                                    viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
                                                    stroke-linecap="round" stroke-linejoin="round" class="icon m-1">
                                                    <path stroke="none" d="M0 0h24v24H0z" fill="none" />
                                                    <path
                                                        d="M7 7m0 2.667a2.667 2.667 0 0 1 2.667 -2.667h8.666a2.667 2.667 0 0 1 2.667 2.667v8.666a2.667 2.667 0 0 1 -2.667 2.667h-8.666a2.667 2.667 0 0 1 -2.667 -2.667z" />
                                                    <path
                                                        d="M4.012 16.737a2.005 2.005 0 0 1 -1.012 -1.737v-10c0 -1.1 .9 -2 2 -2h10c.75 0 1.158 .385 1.5 1" />
                                                </svg>
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="card card-sm border" id="queue-health-card">
                                <div class="card-body">
                                    <div class="d-flex align-items-center mb-2">
                                        <span class="avatar me-3 rounded" id="queue-health-icon"
                                            style="background-color: {{ $isQueueWorkerHealthy ? '#2fb344' : '#d63939' }}">
                                            <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24"
                                                viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
                                                stroke-linecap="round" stroke-linejoin="round" class="icon text-white">
                                                <path stroke="none" d="M0 0h24v24H0z" fill="none" />
                                                <path d="M12 6m-8 0a8 3 0 1 0 16 0a8 3 0 1 0 -16 0" />
                                                <path d="M4 6v6a8 3 0 0 0 16 0v-6" />
                                                <path d="M4 12v6a8 3 0 0 0 16 0v-6" />
                                            </svg>
                                        </span>
                                        <div>
                                            <div class="fw-bold">{{ __('labels.queue_worker') }} (queue:work)</div>
                                            <div class="text-muted small" id="queue-health-text">
                                                @if($isQueueWorkerHealthy)
                                                    {{ __('labels.cron_running') }}
                                                @else
                                                    {{ __('labels.cron_not_detected') }}
                                                @endif
                                            </div>
                                        </div>
                                    </div>
                                    <div class="mt-2">
                                        <label
                                            class="form-label small text-muted mb-1">{{ __('labels.server_cron_command') }}</label>
                                        <div class="input-group input-group-sm">
                                            <input type="text" class="form-control form-control-sm" readonly
                                                value="{{ $queueCmd }}" id="queue-cron-cmd">
                                            <button class="btn btn-outline-secondary copy-command-btn" type="button"
                                                data-target="queue-cron-cmd" title="{{ __('labels.copy_command') }}">
                                                <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14"
                                                    viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
                                                    stroke-linecap="round" stroke-linejoin="round" class="icon m-1">
                                                    <path stroke="none" d="M0 0h24v24H0z" fill="none" />
                                                    <path
                                                        d="M7 7m0 2.667a2.667 2.667 0 0 1 2.667 -2.667h8.666a2.667 2.667 0 0 1 2.667 2.667v8.666a2.667 2.667 0 0 1 -2.667 2.667h-8.666a2.667 2.667 0 0 1 -2.667 -2.667z" />
                                                    <path
                                                        d="M4.012 16.737a2.005 2.005 0 0 1 -1.012 -1.737v-10c0 -1.1 .9 -2 2 -2h10c.75 0 1.158 .385 1.5 1" />
                                                </svg>
                                            </button>
                                        </div>
                                    </div>
                                    @if($runPermission)
                                        <div class="mt-2">
                                            <button class="btn btn-sm btn-primary run-command-btn" data-command="queue:work">
                                                <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14"
                                                    viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
                                                    stroke-linecap="round" stroke-linejoin="round" class="icon me-1">
                                                    <path stroke="none" d="M0 0h24v24H0z" fill="none" />
                                                    <path d="M7 4v16l13 -8z" />
                                                </svg>
                                                {{ __('labels.run_now') }}
                                            </button>
                                        </div>
                                    @endif
                                </div>
                            </div>
                        </div>
                    </div>

                    {{-- Command cards --}}
                    <div class="row g-3" id="command-cards">
                        @foreach($commands as $cmd)
                            @if(in_array($cmd['type'], ['scheduled', 'manual']))
                                <div class="col-md-6 col-lg-4" data-command="{{ $cmd['command'] }}">
                                    <div class="card border h-100">
                                        <div class="card-body">
                                            <div class="d-flex align-items-start justify-content-between mb-2">
                                                <div>
                                                    <h4 class="card-title mb-1">{{ $cmd['name'] }}</h4>
                                                    <code class="small">{{ $cmd['command'] }}</code>
                                                </div>
                                                @php
                                                    $statusColor = match ($cmd['last_status']) {
                                                        'success' => 'bg-success-lt',
                                                        'running' => 'bg-azure-lt',
                                                        'failed' => 'bg-danger-lt',
                                                        default => 'bg-secondary-lt',
                                                    };
                                                    $statusLabel = match ($cmd['last_status']) {
                                                        'success' => __('labels.cron_status_success'),
                                                        'running' => __('labels.cron_status_running'),
                                                        'failed' => __('labels.cron_status_failed'),
                                                        default => __('labels.cron_status_never_run'),
                                                    };
                                                @endphp
                                                <span class="badge {{ $statusColor }} cmd-status-badge">{{ $statusLabel }}</span>
                                            </div>

                                            <p class="text-muted small mb-2">{{ __($cmd['description']) }}</p>

                                            <div class="mb-2">
                                                <span class="text-muted small">
                                                    <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14"
                                                        viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
                                                        stroke-linecap="round" stroke-linejoin="round" class="icon">
                                                        <path stroke="none" d="M0 0h24v24H0z" fill="none" />
                                                        <path d="M12 12m-9 0a9 9 0 1 0 18 0a9 9 0 1 0 -18 0" />
                                                        <path d="M12 7l0 5l3 3" />
                                                    </svg>
                                                    {{ __($cmd['frequency']) }}
                                                </span>
                                            </div>

                                            @if($cmd['last_run'])
                                                <div class="mb-2 small">
                                                    <span class="text-muted">{{ __('labels.last_run') }}:</span>
                                                    <span
                                                        class="cmd-last-run">{{ $cmd['last_run']->started_at->diffForHumans() }}</span>
                                                    @if($cmd['last_run']->duration_ms !== null)
                                                        <span class="text-muted ms-1">({{ $cmd['last_run']->duration_ms }}ms)</span>
                                                    @endif
                                                </div>
                                            @else
                                                <div class="mb-2 small text-muted">{{ __('labels.never_executed') }}</div>
                                            @endif

                                            {{-- Recent run history dots --}}
                                            @if($cmd['recent_runs']->count() > 0)
                                                <div class="d-flex gap-1 mb-3" title="{{ __('labels.recent_runs') }}">
                                                    @foreach($cmd['recent_runs'] as $run)
                                                        @php
                                                            $statusVal = $run->status instanceof \App\Enums\CommandRunStatusEnum
                                                                ? $run->status->value : (string) $run->status;
                                                            $dotColor = match ($statusVal) {
                                                                'success' => '#2fb344',
                                                                'failed' => '#d63939',
                                                                'running' => '#4299e1',
                                                                default => '#667382',
                                                            };
                                                        @endphp
                                                        <span class="rounded-circle d-inline-block"
                                                            style="width:10px;height:10px;background:{{ $dotColor }}"
                                                            data-bs-toggle="tooltip"
                                                            title="{{ $run->started_at?->format('M d, H:i') }} — {{ $statusVal }}">
                                                        </span>
                                                    @endforeach
                                                </div>
                                            @endif

                                            <div class="d-flex gap-2">
                                                @if($runPermission)
                                                    <button class="btn btn-sm btn-primary run-command-btn"
                                                        data-command="{{ $cmd['command'] }}">
                                                        <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14"
                                                            viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
                                                            stroke-linecap="round" stroke-linejoin="round" class="icon me-1">
                                                            <path stroke="none" d="M0 0h24v24H0z" fill="none" />
                                                            <path d="M7 4v16l13 -8z" />
                                                        </svg>
                                                        {{ __('labels.run_now') }}
                                                    </button>
                                                @endif
                                                <button class="btn btn-sm btn-outline-secondary view-history-btn"
                                                    data-command="{{ $cmd['command'] }}" data-name="{{ $cmd['name'] }}">
                                                    {{ __('labels.view_history') }}
                                                </button>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            @endif
                        @endforeach
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- History modal --}}
    <div class="modal modal-blur fade" id="history-modal" tabindex="-1" role="dialog" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-centered" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">
                        <span id="history-modal-title">{{ __('labels.run_history') }}</span>
                        <span id="history-total-count" class="text-muted small ms-2"></span>
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="table-responsive">
                        <table class="table table-vcenter table-striped">
                            <thead>
                                <tr>
                                    <th>#</th>
                                    <th>{{ __('labels.status') }}</th>
                                    <th>{{ __('labels.triggered_by') }}</th>
                                    <th>{{ __('labels.started_at') }}</th>
                                    <th>{{ __('labels.duration') }}</th>
                                    <th>{{ __('labels.output') }}</th>
                                </tr>
                            </thead>
                            <tbody id="history-table-body">
                                <tr>
                                    <td colspan="6" class="text-center text-muted">{{ __('labels.loading') }}...</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                    <div id="history-pagination" class="mt-3"></div>
                </div>
            </div>
        </div>
    </div>
@endsection

@push('scripts')
    <script>
        window.cronMonitorConfig = {
            statusUrl: @json(route('admin.cron-monitor.status')),
            runUrl: @json(route('admin.cron-monitor.run')),
            historyUrl: @json(route('admin.cron-monitor.history')),
            labels: {
                running: @json(__('labels.cron_running')),
                notConfigured: @json(__('labels.cron_not_detected')),
                success: @json(__('labels.cron_status_success')),
                failed: @json(__('labels.cron_status_failed')),
                statusRunning: @json(__('labels.cron_status_running')),
                neverRun: @json(__('labels.cron_status_never_run')),
                neverExecuted: @json(__('labels.never_executed')),
                runConfirmTitle: @json(__('labels.run_command_confirm_title')),
                runConfirmText: @json(__('labels.run_command_confirm_text')),
                confirmYes: @json(__('labels.yes')),
                confirmCancel: @json(__('labels.cancel')),
                commandExecuted: @json(__('labels.command_executed_successfully')),
                noHistory: @json(__('labels.no_history_found')),
                autoRefreshIn: @json(__('labels.auto_refresh_in')),
                runNow: @json(__('labels.run_now')),
                runHistory: @json(__('labels.run_history')),
                loading: @json(__('labels.loading')),
                totalRecords: @json(__('labels.total_records')),
                showMore: @json(__('labels.show_more')),
                showLess: @json(__('labels.show_less')),
                copied: @json(__('labels.copied')),
            },
        };
    </script>
    <script src="{{ hyperAsset('assets/js/cron-monitor.js') }}" defer></script>
@endpush