@props([
    'moduleConfig',
    'availableFields',
    'availableMatchFields' => [],
    'activeJob' => null,
])

@php
    $moduleKey = $moduleConfig->key;
@endphp

@push('scripts')
    <script>
        window.BULK_UPLOAD_CFG = {
            previewUrl: @json(route("admin.{$moduleKey}.bulk-upload.preview")),
            confirmUrl: @json(route("admin.{$moduleKey}.bulk-upload.confirm")),
            indexUrl: @json(route($moduleConfig->indexRouteName)),
            csrf: @json(csrf_token()),
            availableFields: @json(collect($availableFields)->map(fn($f) => array_merge($f, ['label' => __($f['label'])]))->values()),
            availableMatchFields: @json(collect($availableMatchFields)->map(fn($f) => array_merge($f, ['label' => __($f['label'])]))->values()),
            i18n: {
                preview_ready: @json(__('labels.bulk_upload_preview_ready')),
                queued: @json(__('labels.bulk_upload_queued')),
                map_required: @json(__('labels.bulk_upload_title_mapping_required')),
                pick_column: @json(__('labels.bulk_upload_pick_column')),
                row: @json(__('labels.row')),
                upload_failed: @json(__('labels.upload_failed_generic')),
                unexpected_error: @json(__('labels.unexpected_error')),
                preview_total: @json(__('labels.bulk_upload_preview_total')),
                preview_showing: @json(__('labels.bulk_upload_preview_showing')),
                match_key_hint: @json(__('labels.bulk_upload_match_key_hint')),
            },
        };
    </script>
    <script src="{{ asset('assets/admin/js/bulk-upload.js') }}" defer></script>
@endpush

<div class="row g-4">
    @if($activeJob)
        {{-- One-at-a-time lockout — a job is still pending/processing. --}}
        <div class="col-12">
            <div class="alert alert-warning" role="alert">
                <div class="alert-title">{{ __('labels.bulk_upload_already_in_progress') }}</div>
                <p class="mb-2">
                    {{ __('labels.bulk_upload_active_job_notice', [
                        'file' => $activeJob->original_filename,
                        'processed' => $activeJob->processed_rows,
                        'total' => $activeJob->total_rows,
                    ]) }}
                </p>
                <a href="{{ route('admin.'.$moduleKey.'.bulk-upload.jobs.show', ['id' => $activeJob->id]) }}"
                   class="btn btn-outline-warning">
                    {{ __('labels.bulk_upload_view_active_job') }}
                </a>
            </div>
        </div>
    @else
        <div class="col-12">
            <div class="alert alert-info flex-column align-items-start" role="alert">
                <div class="alert-title">{{ __('labels.instructions') }}</div>
                <ul class="mb-2">
                    <li>{{ __('labels.bulk_upload_step1_hint') }}</li>
                    <li>{{ __('labels.bulk_upload_step2_hint') }}</li>
                    <li>{{ __('labels.bulk_upload_step3_hint') }}</li>
                </ul>
                <a href="{{ route('admin.'.$moduleKey.'.bulk-upload.template') }}" class="btn btn-outline-primary btn-sm">
                    {{ __('labels.download_template') }}
                </a>

                <details>
                    <summary class="text-primary" style="cursor: pointer;">
                        {{ __('labels.bulk_upload_logo_hosting_title') }}
                    </summary>
                    <div class="mt-2">
                        <p class="mb-2">{{ __('labels.bulk_upload_logo_hosting_intro') }}</p>
                        <div class="fw-bold mb-1">{{ __('labels.bulk_upload_logo_hosting_cloud_storage') }}</div>
                        <ul class="mb-2">
                            <li>
                                <a href="https://www.cloudflare.com/developer-platform/products/r2/" target="_blank" rel="noopener">Cloudflare R2</a>
                                — {{ __('labels.bulk_upload_logo_cloudflare_r2_desc') }}
                            </li>
                            <li>
                                <a href="https://aws.amazon.com/s3/" target="_blank" rel="noopener">AWS S3</a>
                                — {{ __('labels.bulk_upload_logo_aws_s3_desc') }}
                            </li>
                            <li>
                                <a href="https://www.backblaze.com/cloud-storage" target="_blank" rel="noopener">Backblaze B2</a>
                                — {{ __('labels.bulk_upload_logo_backblaze_desc') }}
                            </li>
                            <li>
                                <a href="https://docs.github.com/en/repositories/working-with-files/using-files-in-your-repository/about-files-on-github" target="_blank" rel="noopener">GitHub raw</a>
                                — {{ __('labels.bulk_upload_logo_github_desc') }}
                            </li>
                        </ul>
                        <p class="small text-muted mb-0">
                            {{ __('labels.bulk_upload_logo_hosting_note', [
                                'mb' => round(((int) config('bulk_upload.logo_max_bytes', 5242880)) / (1024 * 1024), 1),
                            ]) }}
                        </p>
                    </div>
                </details>
            </div>
        </div>

        {{-- Step 1: file picker --}}
        <div class="col-12" id="bulk-upload-step1">
            <form id="bulk-upload-preview-form" enctype="multipart/form-data">
                @csrf
                <label class="form-label required">{{ __('labels.bulk_upload_choose_csv') }}</label>
                <input id="csv-file" type="file" name="csv_file" accept=".csv,text/csv"
                       class="form-control" required>
                <div class="small text-muted mt-1">.csv, max {{ (int) config('bulk_upload.max_file_kb', 10240) / 1024 }}MB</div>
                <div class="mt-3 d-flex gap-2">
                    <button type="submit" class="btn btn-primary" id="bulk-upload-preview-submit">
                        <span class="preview-text">{{ __('labels.bulk_upload_load_preview') }}</span>
                        <span class="spinner-border spinner-border-sm d-none" role="status" aria-hidden="true"></span>
                    </button>
                </div>
            </form>
        </div>

        {{-- Step 2: preview + mapping (hidden until preview succeeds) --}}
        <div class="col-12 d-none" id="bulk-upload-step2">
            <hr>
            <h4 class="mb-3">{{ __('labels.bulk_upload_mapping_title') }}</h4>
            <div class="small text-muted mb-3" id="bulk-upload-preview-summary"></div>
            <div class="row g-3">
                <div class="col-12">
                    <div class="card border">
                        <div class="card-header">
                            <h4 class="card-title mb-0">{{ __('labels.bulk_upload_csv_preview') }}</h4>
                        </div>
                        <div class="table-responsive" style="max-height: 480px;">
                            <table class="table table-sm mb-0" id="bulk-upload-preview-table">
                                <thead></thead>
                                <tbody></tbody>
                            </table>
                        </div>
                    </div>
                </div>
                <div class="col-12">
                    <div class="card border">
                        <div class="card-header">
                            <h4 class="card-title mb-0">{{ __('labels.bulk_upload_field_mapping') }}</h4>
                        </div>
                        <div class="card-body">
                            <form id="bulk-upload-confirm-form">
                                @csrf
                                <input type="hidden" name="preview_token" id="preview-token">
                                <input type="hidden" name="original_filename" id="original-filename">

                                {{-- Operation mode --}}
                                <div class="mb-3 row align-items-center">
                                    <label class="col-sm-5 col-form-label">{{ __('labels.bulk_upload_mode') }}</label>
                                    <div class="col-sm-7">
                                        <select class="form-select form-select-sm" name="operation" id="bulk-operation-select">
                                            <option value="create" selected>{{ __('labels.bulk_upload_mode_create') }}</option>
                                            @if(!empty($availableMatchFields))
                                                <option value="update">{{ __('labels.bulk_upload_mode_update') }}</option>
                                            @endif
                                        </select>
                                    </div>
                                </div>

                                {{-- Match-by — only meaningful when operation = update --}}
                                @if(!empty($availableMatchFields))
                                    <div class="mb-3 row align-items-center d-none" id="bulk-match-by-row">
                                        <label class="col-sm-5 col-form-label">{{ __('labels.bulk_upload_match_by') }}</label>
                                        <div class="col-sm-7">
                                            <select class="form-select form-select-sm" name="match_field" id="bulk-match-by-select">
                                                @foreach($availableMatchFields as $mf)
                                                    <option value="{{ $mf['key'] }}">{{ __($mf['label']) }}</option>
                                                @endforeach
                                            </select>
                                        </div>
                                    </div>
                                @endif

                                {{-- Notify-on-finish: optional FCM + database + mail notification when the job lands in a terminal state. --}}
                                <div class="mb-3 row align-items-center">
                                    <div class="col-sm-5 col-form-label">
                                        {{ __('labels.notify_me_when_finished') }}
                                        <x-form-info-tooltip :title="__('labels.notify_me_when_finished_hint')" />
                                    </div>
                                    <div class="col-sm-7">
                                        <label class="form-check form-switch mb-0">
                                            <input class="form-check-input" type="checkbox"
                                                   name="notify_on_finish" id="bulk-notify-on-finish" value="1">
                                            <span class="form-check-label small text-muted">
                                                {{ __('labels.notify_me_when_finished_label') }}
                                            </span>
                                        </label>
                                    </div>
                                </div>

                                <hr class="my-3">

                                <div id="mapping-rows"></div>
                                <div class="d-flex gap-2 mt-3">
                                    <button type="submit" class="btn btn-success" id="bulk-upload-confirm-submit">
                                        <span class="confirm-text">{{ __('labels.bulk_upload_start_processing') }}</span>
                                        <span class="spinner-border spinner-border-sm d-none" role="status" aria-hidden="true"></span>
                                    </button>
                                    <button type="button" class="btn btn-outline-secondary" id="bulk-upload-reset">
                                        {{ __('labels.cancel') }}
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    @endif
</div>
