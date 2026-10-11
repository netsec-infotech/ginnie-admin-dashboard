@extends('layouts.admin.app', ['page' => $menuAdmin[$moduleConfig->menuKey]['active'] ?? ""])

@php
    $moduleDisplay = __($moduleConfig->displayNameKey);
@endphp

@section('title', __('labels.bulk_upload') . ' ' . $moduleDisplay)

@section('header_data')
    @php
        $page_title = __('labels.bulk_upload') . ' ' . $moduleDisplay;
        $page_pretitle = $moduleDisplay;
    @endphp
@endsection

@php
    $breadcrumbs = [
        ['title' => __('labels.home'), 'url' => route('admin.dashboard')],
        ['title' => $moduleDisplay, 'url' => route($moduleConfig->indexRouteName)],
        ['title' => __('labels.bulk_upload'), 'url' => null],
    ];
@endphp

@section('admin-content')
    <div class="row row-cards">
        <div class="col-12">
            <div class="card">
                <div class="card-header">
                    <div>
                        <h3 class="card-title">{{ __('labels.bulk_upload') }} {{ $moduleDisplay }}</h3>
                        <x-breadcrumb :items="$breadcrumbs"/>
                    </div>
                    <div class="card-actions">
                        <a href="{{ route('admin.'.$moduleConfig->key.'.bulk-upload.jobs.index') }}" class="btn btn-outline-primary">
                            {{ __('labels.bulk_upload_history') }}
                        </a>
                        <a href="{{ route($moduleConfig->indexRouteName) }}" class="btn btn-outline-secondary">
                            {{ __('labels.back') }}
                        </a>
                    </div>
                </div>
                <div class="card-body">
                    <x-bulk-upload.upload-form
                        :module-config="$moduleConfig"
                        :available-fields="$availableFields"
                        :available-match-fields="$availableMatchFields"
                        :active-job="$activeJob"
                    />
                </div>
            </div>
        </div>
    </div>
@endsection
