@extends('layouts.admin.app', ['page' => $menuAdmin['products']['active'] ?? '', 'sub_page' => $menuAdmin['products']['route']['bulk_upload']['sub_active'] ?? null])

@section('title', __('labels.bulk_upload') . ' ' . __('labels.products'))

@section('header_data')
    @php
        $page_title = __('labels.bulk_upload') . ' ' . __('labels.products');
        $page_pretitle = __('labels.products');
    @endphp
@endsection

@php
    $breadcrumbs = [
        ['title' => __('labels.home'), 'url' => route('admin.dashboard')],
        ['title' => __('labels.products'), 'url' => route('admin.products.index')],
        ['title' => __('labels.bulk_upload'), 'url' => null],
    ];
@endphp

@section('admin-content')
    <div class="row row-cards">
        <div class="col-12">
            <div class="card">
                <div class="card-header">
                    <div>
                        <h3 class="card-title">{{ __('labels.bulk_upload') }} {{ __('labels.products') }}</h3>
                        <x-breadcrumb :items="$breadcrumbs" />
                    </div>
                    <div class="card-actions">
                        <a href="{{ route('admin.products.bulk-upload.jobs.index') }}" class="btn btn-outline-primary">
                            {{ __('labels.bulk_upload_history') }}
                        </a>
                        <a href="{{ route('admin.products.index') }}" class="btn btn-outline-secondary">
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
                        :default-values="$defaultValues"
                        :catalog-languages="$catalogLanguages"
                        :supports-translation="$supportsTranslation"
                        :can-translate="$canTranslate"
                    />
                </div>
            </div>
        </div>
    </div>
@endsection
