@extends('layouts.seller.app', ['page' => $menuSeller['products']['active'] ?? '', 'sub_page' => $menuSeller['products']['route']['bulk_upload']['sub_active']])

@section('title', __('labels.bulk_upload') . ' ' . __('labels.products'))

@section('header_data')
    @php
        $page_title = __('labels.bulk_upload') . ' ' . __('labels.products');
        $page_pretitle = __('labels.products');
    @endphp
@endsection

@php
    $breadcrumbs = [
        ['title' => __('labels.home'), 'url' => route('seller.dashboard')],
        ['title' => __('labels.products'), 'url' => route('seller.products.index')],
        ['title' => __('labels.bulk_upload'), 'url' => null],
    ];
@endphp

@section('seller-content')
    <div data-seller-tour="bulk-upload">
    <div class="row row-cards">
        <div class="col-12">
            <div class="card">
                <div class="card-header">
                    <div>
                        <h3 class="card-title">{{ __('labels.bulk_upload') }} {{ __('labels.products') }}</h3>
                        <x-breadcrumb :items="$breadcrumbs" />
                    </div>
                    <div class="card-actions">
                        <a href="{{ route('seller.products.images-upload.page') }}" class="btn btn-outline-info">
                            {{ __('labels.upload_images_zip') ?? 'Upload Images ZIP' }}
                        </a>
                        <a href="{{ route('seller.products.index') }}" class="btn btn-outline-secondary">
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
                        index-route-name="seller.products.index"
                        route-prefix="seller"
                    />
                </div>
            </div>
        </div>
    </div>
    </div>
@endsection
