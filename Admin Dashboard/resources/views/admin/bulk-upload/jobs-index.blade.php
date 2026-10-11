@extends('layouts.admin.app', ['page' => $menuAdmin[$moduleConfig->menuKey]['active'] ?? ""])

@php
    $moduleDisplay = __($moduleConfig->displayNameKey);
@endphp

@section('title', __('labels.bulk_upload_history') . ' — ' . $moduleDisplay)

@section('header_data')
    @php
        $page_title = __('labels.bulk_upload_history');
        $page_pretitle = $moduleDisplay;
    @endphp
@endsection

@php
    $breadcrumbs = [
        ['title' => __('labels.home'), 'url' => route('admin.dashboard')],
        ['title' => $moduleDisplay, 'url' => route($moduleConfig->indexRouteName)],
        ['title' => __('labels.bulk_upload_history'), 'url' => null],
    ];
@endphp

@section('admin-content')
    <div class="row row-cards">
        <div class="col-12">
            <x-bulk-upload.jobs-table
                :module-config="$moduleConfig"
                :columns="$columns"
                :breadcrumbs="$breadcrumbs"
            />
        </div>
    </div>
@endsection
