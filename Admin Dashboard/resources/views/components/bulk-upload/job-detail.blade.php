@props([
    'moduleConfig',
    'job',
])

@php
    $moduleKey = $moduleConfig->key;
@endphp

@push('scripts')
    <script>
        window.BULK_UPLOAD_STATUS_CFG = {
            statusUrl: @json(route("admin.{$moduleKey}.bulk-upload.jobs.status", ['id' => $job->id])),
            cancelUrl: @json(route("admin.{$moduleKey}.bulk-upload.jobs.cancel", ['id' => $job->id])),
            pollInterval: 2000,
            initialIsTerminal: {{ $job->isTerminal() ? 'true' : 'false' }},
            i18n: {
                status_pending:    @json(__('labels.bulk_upload_status_pending')),
                status_processing: @json(__('labels.bulk_upload_status_processing')),
                status_completed:  @json(__('labels.bulk_upload_status_completed')),
                status_failed:     @json(__('labels.bulk_upload_status_failed')),
                status_cancelled:  @json(__('labels.bulk_upload_status_cancelled')),
                cancel_title:      @json(__('labels.bulk_upload_cancel')),
                cancel_confirm:    @json(__('labels.bulk_upload_confirm_cancel')),
                cancel_yes:        @json(__('labels.bulk_upload_cancel_yes')),
                cancel_no:         @json(__('labels.cancel')),
                unexpected_error:  @json(__('labels.unexpected_error')),
            },
        };
    </script>
    <script src="{{ asset('assets/admin/js/bulk-upload-status.js') }}" defer></script>
@endpush

<div class="card-body">
    <div class="row g-3 mb-3">
        <div class="col-sm-6 col-md-3">
            <div class="text-muted small">{{ __('labels.file') }}</div>
            <div>{{ $job->original_filename }}</div>
        </div>
        <div class="col-sm-6 col-md-3">
            <div class="text-muted small">{{ __('labels.started_at') }}</div>
            <div id="bulk-started-at">{{ optional($job->started_at)->format('Y-m-d H:i') ?: '—' }}</div>
        </div>
        <div class="col-sm-6 col-md-3">
            <div class="text-muted small">{{ __('labels.finished_at') }}</div>
            <div id="bulk-finished-at">{{ optional($job->finished_at)->format('Y-m-d H:i') ?: '—' }}</div>
        </div>
        <div class="col-sm-6 col-md-3">
            <div class="text-muted small">{{ __('labels.bulk_upload_progress') }}</div>
            <div>
                <span id="bulk-processed-rows">{{ $job->processed_rows }}</span>
                /
                <span id="bulk-total-rows">{{ $job->total_rows }}</span>
                (<span id="bulk-progress-percent">0</span>%)
            </div>
        </div>
    </div>

    <div class="progress mb-4" style="height: 8px;">
        <div class="progress-bar" id="bulk-progress-bar" role="progressbar" style="width: 0%"
             aria-valuemin="0" aria-valuemax="100"></div>
    </div>

    @php
        $isUpdateRun = ($job->operation?->value ?? 'create') !== 'create';
        $cardCols = $isUpdateRun ? 'col-sm-6 col-md' : 'col-sm-4';
    @endphp

    <div class="row g-3 mb-4">
        <div class="{{ $cardCols }}">
            <div class="card bg-info-lt">
                <div class="card-body text-center">
                    <div class="text-muted small">{{ __('labels.bulk_upload_total') }}</div>
                    <div class="h2 mb-0" id="bulk-stat-total">{{ $job->total_rows }}</div>
                </div>
            </div>
        </div>
        <div class="{{ $cardCols }}">
            <div class="card bg-success-lt">
                <div class="card-body text-center">
                    <div class="text-muted small">{{ __('labels.bulk_upload_success_count') }}</div>
                    <div class="h2 mb-0" id="bulk-stat-success">{{ $job->success_count }}</div>
                </div>
            </div>
        </div>
        @if($isUpdateRun)
            <div class="{{ $cardCols }}">
                <div class="card bg-primary-lt">
                    <div class="card-body text-center">
                        <div class="text-muted small">{{ __('labels.bulk_upload_updated_count') }}</div>
                        <div class="h2 mb-0" id="bulk-stat-updated">{{ $job->updated_count }}</div>
                    </div>
                </div>
            </div>
            <div class="{{ $cardCols }}">
                <div class="card bg-warning-lt">
                    <div class="card-body text-center">
                        <div class="text-muted small">{{ __('labels.bulk_upload_skipped_count') }}</div>
                        <div class="h2 mb-0" id="bulk-stat-skipped">{{ $job->skipped_count }}</div>
                    </div>
                </div>
            </div>
        @endif
        <div class="{{ $cardCols }}">
            <div class="card bg-danger-lt">
                <div class="card-body text-center">
                    <div class="text-muted small">{{ __('labels.bulk_upload_failed_count') }}</div>
                    <div class="h2 mb-0" id="bulk-stat-failed">{{ $job->failed_count }}</div>
                </div>
            </div>
        </div>
    </div>

    <div class="card border">
        <div class="card-header d-flex justify-content-between align-items-center">
            <h4 class="card-title mb-0">{{ __('labels.bulk_upload_log') }}</h4>
            <button class="btn btn-sm btn-outline-dark" type="button" id="bulk-log-copy">
                {{ __('labels.copy') }}
            </button>
        </div>
        <div class="card-body p-0">
            <pre id="bulk-log" class="m-0 p-3" style="white-space: pre-wrap; word-break: break-word; max-height: 480px; overflow: auto;">{{ $job->error_log }}</pre>
        </div>
    </div>
</div>
