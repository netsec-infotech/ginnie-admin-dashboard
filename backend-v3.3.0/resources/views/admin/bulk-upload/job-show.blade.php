@extends('layouts.admin.app', ['page' => $menuAdmin[$moduleConfig->menuKey]['active'] ?? ""])

@php
    $moduleDisplay = __($moduleConfig->displayNameKey);
    $moduleKey = $moduleConfig->key;
@endphp

@section('title', __('labels.bulk_upload_job') . ' #' . $job->id)

@section('header_data')
    @php
        $page_title = __('labels.bulk_upload_job') . ' #' . $job->id;
        $page_pretitle = $moduleDisplay;
    @endphp
@endsection

@php
    $breadcrumbs = [
        ['title' => __('labels.home'), 'url' => route('admin.dashboard')],
        ['title' => $moduleDisplay, 'url' => route($moduleConfig->indexRouteName)],
        ['title' => __('labels.bulk_upload_history'), 'url' => route("admin.{$moduleKey}.bulk-upload.jobs.index")],
        ['title' => '#' . $job->id, 'url' => null],
    ];
@endphp

@section('admin-content')
    <div class="row row-cards">
        <div class="col-12">
            <div class="card">
                <div class="card-header">
                    <div>
                        <h3 class="card-title">
                            {{ __('labels.bulk_upload_job') }} #{{ $job->id }}
                            <span class="ms-2" id="bulk-status-badge">
                                <x-bulk-upload.status-badge :status="$job->status" id="bulk-status-text"/>
                            </span>
                        </h3>
                        <x-breadcrumb :items="$breadcrumbs"/>
                    </div>
                    <div class="card-actions">
                        {{-- Always rendered; hidden via d-none when there are no failures yet.
                             The polling JS reveals it as soon as failed_count > 0 + the errors
                             CSV path is populated on the job. data-bs-title is updated live by
                             the poll so the expiry stays accurate when the job lands in a
                             terminal state. --}}
                        <a id="bulk-download-errors-btn"
                           href="{{ route('admin.'.$moduleKey.'.bulk-upload.jobs.errors', ['id' => $job->id]) }}"
                           class="btn btn-outline-warning me-2 @if(! ($job->failed_count > 0 && $job->failed_rows_path)) d-none @endif"
                           data-bs-toggle="tooltip"
                           data-bs-placement="bottom"
                           data-bs-title="{{ $job->failedRowsExpiryLabel() ?? '' }}">
                            {{ __('labels.bulk_upload_download_errors') }}
                            <span class="badge bg-warning-lt ms-1" id="bulk-download-errors-count">{{ $job->failed_count }}</span>
                        </a>
                        <button type="button"
                                id="bulk-cancel-btn"
                                class="btn btn-outline-danger me-2 @if($job->isTerminal()) d-none @endif">
                            {{ __('labels.bulk_upload_cancel') }}
                        </button>
                        <a href="{{ route('admin.'.$moduleKey.'.bulk-upload.jobs.index') }}" class="btn btn-outline-secondary">
                            {{ __('labels.back') }}
                        </a>
                    </div>
                </div>
                <x-bulk-upload.job-detail :module-config="$moduleConfig" :job="$job"/>
            </div>
        </div>
    </div>
@endsection
