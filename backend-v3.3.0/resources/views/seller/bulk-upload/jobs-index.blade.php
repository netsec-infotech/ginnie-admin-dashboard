@extends('layouts.seller.app', ['page' => $menuSeller[$moduleConfig->menuKey]['active'] ?? ""])

@php
    $moduleDisplay = __($moduleConfig->displayNameKey);
@endphp

@section('title', __('labels.bulk_upload_history') . ' - ' . $moduleDisplay)

@section('header_data')
    @php
        $page_title = __('labels.bulk_upload_history');
        $page_pretitle = $moduleDisplay;
    @endphp
@endsection

@php
    $breadcrumbs = [
        ['title' => __('labels.home'), 'url' => route('seller.dashboard')],
        ['title' => $moduleDisplay, 'url' => route('seller.products.index')],
        ['title' => __('labels.bulk_upload_history'), 'url' => null],
    ];
@endphp

@section('seller-content')
    <div class="row row-cards">
        <div class="col-12">
            <x-bulk-upload.jobs-table
                :module-config="$moduleConfig"
                :columns="$columns"
                :breadcrumbs="$breadcrumbs"
                route-prefix="seller"
            />
        </div>
    </div>
@endsection
