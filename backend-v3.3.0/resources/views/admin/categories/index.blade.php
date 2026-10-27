@php use App\Models\Setting; @endphp
@extends('layouts.admin.app', ['page' => $menuAdmin['categories']['active'] ?? "", 'sub_page' => $menuAdmin['categories']['route']['categories']['sub_active']])

@section('title', __('labels.categories'))

@section('header_data')
    @php
        $page_title = __('labels.categories');
        $page_pretitle = __('labels.list');
    @endphp
@endsection

@php
    $breadcrumbs = [
        ['title' => __('labels.home'), 'url' => route('admin.dashboard')],
        ['title' => __('labels.categories'), 'url' => null],
    ];
@endphp

@section('admin-content')
    <div class="row row-cards">
        <div class="col-12">
            <div class="card">
                <div class="card-header">
                    <div>
                        <h3 class="card-title">{{ __('labels.categories') }}</h3>
                        <x-breadcrumb :items="$breadcrumbs"/>
                    </div>
                    <div class="card-actions">
                        <div class="row g-2">
                            <div class="col-auto">
                                @if($createPermission ?? false)
                                    <div class="col text-end">
                                        <div class="d-flex gap-2">
                                            <a href="{{ route('admin.categories.create') }}"
                                               id="add-category-btn"
                                               class="btn btn-6 btn-outline-primary">
                                                <svg
                                                    xmlns="http://www.w3.org/2000/svg"
                                                    width="24"
                                                    height="24"
                                                    viewBox="0 0 24 24"
                                                    fill="none"
                                                    stroke="currentColor"
                                                    stroke-width="2"
                                                    stroke-linecap="round"
                                                    stroke-linejoin="round"
                                                    class="icon icon-2"
                                                >
                                                    <path d="M12 5l0 14"/>
                                                    <path d="M5 12l14 0"/>
                                                </svg>
                                                {{ __('labels.add_category') }}
                                            </a>
                                        </div>
                                    </div>
                                @endif
                            </div>
                            @if($createPermission ?? false)
                                <div class="col-auto">
                                    {{-- Shared bulk-upload controls keep categories aligned with the brand flow. --}}
                                    <x-bulk-upload.action-buttons
                                        module="categories"
                                        layout="buttons"
                                        :create-permission="$createPermission ?? false"/>
                                </div>
                            @endif
                            <div class="col-auto">
                                <a href="{{ route('admin.categories.sort') }}" class="btn btn-outline-info">
                                    <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24"
                                         fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"
                                         stroke-linejoin="round"
                                         class="icon icon-tabler icons-tabler-outline icon-tabler-arrows-sort">
                                        <path stroke="none" d="M0 0h24v24H0z" fill="none"/>
                                        <path d="M4 6l7 0"/>
                                        <path d="M4 12l7 0"/>
                                        <path d="M4 18l9 0"/>
                                        <path d="M15 9l3 -3l3 3"/>
                                        <path d="M18 6l0 12"/>
                                    </svg>
                                    {{ __('labels.sort') }}
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
                        <div class="alert alert-info alert-dismissible" role="alert">
                            <div class="alert-icon">
                                <!-- Download SVG icon from http://tabler.io/icons/icon/info-circle -->
                                <svg
                                    xmlns="http://www.w3.org/2000/svg"
                                    width="24"
                                    height="24"
                                    viewBox="0 0 24 24"
                                    fill="none"
                                    stroke="currentColor"
                                    stroke-width="2"
                                    stroke-linecap="round"
                                    stroke-linejoin="round"
                                    class="icon alert-icon icon-2"
                                >
                                    <path d="M3 12a9 9 0 1 0 18 0a9 9 0 0 0 -18 0"/>
                                    <path d="M12 9h.01"/>
                                    <path d="M11 12h1v4h1"/>
                                </svg>
                            </div>
                            {{ __('labels.global_scope_config_note') }}
                            <a href="{{route('admin.settings.show', ['home_general_settings'])}}" class="alert-action">
                                Link </a>
                            <a class="btn-close" data-bs-dismiss="alert" aria-label="close"></a>
                        </div>
                        @if($deletePermission ?? false)
                            <x-bulk-delete-toolbar
                                table-id="categories-table"
                                :delete-url="route('admin.categories.bulk-delete')"
                                :export-url="route('admin.categories.bulk-upload.export')"
                                :item-label="__('labels.categories')"/>
                        @endif
                        <x-datatable id="categories-table" :columns="$columns"
                                     route="{{ route('admin.categories.datatable') }}"
                                     :options="['order' => [[1, 'desc']],'pageLength' => 10,]"/>
                    </div>
                </div>
            </div>
        </div>
    </div>
    {{-- Bulk-upload modals must live at page root, outside dropdown/button markup. --}}
    <x-bulk-upload.modals module="categories"/>

    <div class="offcanvas offcanvas-end" tabindex="-1" id="view-category-offcanvas" aria-labelledby="offcanvasEndLabel">
        <div class="offcanvas-header">
            <h2 class="offcanvas-title" id="offcanvasEndLabel">Category Details</h2>
            <button type="button" class="btn-close text-reset" data-bs-dismiss="offcanvas" aria-label="Close"></button>
        </div>
        <div class="offcanvas-body">
            <div class="card card-sm border-0">
                <label class="fw-medium pb-1">Image</label>
                <div class="img-box-200px-h card-img">
                    <img id="card-image" src=""/>
                </div>
                <label class="fw-medium pb-1 pt-3">Icon</label>
                <div class="img-box-200px-h card-img">
                    <img id="icon-image" src=""/>
                </div>
                <label class="fw-medium pb-1 pt-3">Active Icon</label>
                <div class="img-box-200px-h card-img">
                    <img id="active-icon-image" src=""/>
                </div>
                <label class="fw-medium pb-1 pt-3">Background</label>
                <div id="background-display">
                    <p class="col-md-8 d-flex justify-content-between">Type: <span id="background-type"
                                                                                   class="fw-medium"></span></p>
                    <div id="background-color-display" style="display: none;">
                        <div class="row g-2">
                            <div class="col-6">
                                <span class="small text-muted d-block mb-1">Web Color: <span id="background-color-web-value" class="fw-medium"></span></span>
                                <div class="color-preview" id="background-color-web-preview"
                                     style="width: 100%; height: 40px; border: 1px solid #ccc; border-radius: 4px;"></div>
                            </div>
                            <div class="col-6">
                                <span class="small text-muted d-block mb-1">App Color: <span id="background-color-app-value" class="fw-medium"></span></span>
                                <div class="color-preview" id="background-color-app-preview"
                                     style="width: 100%; height: 40px; border: 1px solid #ccc; border-radius: 4px;"></div>
                            </div>
                        </div>
                    </div>
                    <div id="background-image-display" style="display: none;">
                        <div class="row g-2">
                            <div class="col-6">
                                <span class="small text-muted d-block mb-1">Web Image</span>
                                <div class="img-box-200px-h card-img border rounded p-1">
                                    <img id="background-image-web-preview" src="" style="width: 100%; height: 100%; object-fit: contain;"/>
                                </div>
                            </div>
                            <div class="col-6">
                                <span class="small text-muted d-block mb-1">App Image</span>
                                <div class="img-box-200px-h card-img border rounded p-1">
                                    <img id="background-image-app-preview" src="" style="width: 100%; height: 100%; object-fit: contain;"/>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div id="background-animation-display" style="display: none;">
                        <div class="row g-2">
                            <div class="col-6">
                                <span class="small text-muted d-block mb-1">Web Animation</span>
                                <div class="p-2 border rounded text-center bg-light">
                                    <lottie-player id="background-animation-web-preview" src="" background="transparent" speed="1" style="width: 100%; height: 140px; object-fit: contain;" loop autoplay></lottie-player>
                                </div>
                            </div>
                            <div class="col-6">
                                <span class="small text-muted d-block mb-1">App Animation</span>
                                <div class="p-2 border rounded text-center bg-light">
                                    <lottie-player id="background-animation-app-preview" src="" background="transparent" speed="1" style="width: 100%; height: 140px; object-fit: contain;" loop autoplay></lottie-player>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <label class="fw-medium pb-1 pt-3">Font Color</label>
                <div id="font-color-display">
                    <p class="col-md-8 d-flex justify-content-between">Color: <span id="font-color-value"
                                                                                    class="fw-medium"></span></p>
                    <div class="color-preview form-control form-control-color w-100" id="font-color-preview"></div>
                </div>
                <div class="card-body px-0">
                    <div>
                        <h4 id="category-name" class="fs-3"></h4>
                        <p id="category-description" class="fs-4"></p>
                        <p class="col-md-8 d-flex justify-content-between">Status: <span id="category-status"
                                                                                         class="badge bg-green-lt text-uppercase fw-medium"></span>
                        </p>
                        <p class="col-md-8 d-flex justify-content-between">Parent Category: <span id="parent-category"
                                                                                                  class="fw-medium"></span>
                        </p>
                        <p class="col-md-8 d-flex justify-content-between">Commission: <span class="fw-medium"></span>%
                        </p>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection

@push('scripts')
    <script src="{{ asset('assets/js/bulk-delete.js') }}" defer></script>
@endpush
