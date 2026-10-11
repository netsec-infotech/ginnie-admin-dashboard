@props([
    'moduleConfig',
    'columns',
    'breadcrumbs' => [],
])

@php
    $moduleKey = $moduleConfig->key;
@endphp

<div class="card">
    <div class="card-header">
        <div>
            <h3 class="card-title">{{ __('labels.bulk_upload_history') }}</h3>
            @if(!empty($breadcrumbs))
                <x-breadcrumb :items="$breadcrumbs"/>
            @endif
        </div>
        <div class="card-actions">
            <div class="row g-2">
                <div class="col-auto">
                    <a href="{{ route('admin.'.$moduleKey.'.bulk-upload.page') }}" class="btn btn-outline-primary">
                        {{ __('labels.bulk_upload') }}
                    </a>
                </div>
                <div class="col-auto">
                    <button class="btn btn-outline-primary" id="refresh">
                        <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24"
                             viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
                             stroke-linecap="round" stroke-linejoin="round"
                             class="icon icon-tabler icons-tabler-outline icon-tabler-refresh">
                            <path stroke="none" d="M0 0h24v24H0z" fill="none"/>
                            <path d="M20 11a8.1 8.1 0 0 0 -15.5 -2m-.5 -4v4h4"/>
                            <path d="M4 13a8.1 8.1 0 0 0 15.5 2m.5 4v-4h-4"/>
                        </svg>
                        {{ __('labels.refresh') }}
                    </button>
                </div>
            </div>
        </div>
    </div>
    <div class="card-table">
        <div class="row w-full p-3">
            <x-datatable id="bulk-upload-jobs-table" :columns="$columns"
                         :route="route('admin.'.$moduleKey.'.bulk-upload.jobs.datatable')"
                         :options="['order' => [[0, 'desc']], 'pageLength' => 10]"/>
        </div>
    </div>
</div>
