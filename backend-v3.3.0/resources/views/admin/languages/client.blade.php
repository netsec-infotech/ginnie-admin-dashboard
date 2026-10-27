@php
    $menuKey = match($surface) {
        \App\Enums\LanguageSurfaceEnum::WEB => 'website',
        \App\Enums\LanguageSurfaceEnum::CUSTOMER_APP => 'customer_app',
        \App\Enums\LanguageSurfaceEnum::SELLER_APP => 'seller_app',
        \App\Enums\LanguageSurfaceEnum::RIDER_APP => 'rider_app',
    };
@endphp
@extends('layouts.admin.app', ['page' => $menuAdmin['languages']['active'] ?? '', 'sub_page' => $menuAdmin['languages']['route'][$menuKey]['sub_active'] ?? ''])

@section('title', $surface->label())

@section('header_data')
    @php
        $page_title = $surface->label();
        $page_pretitle = __('labels.languages_management');
    @endphp
@endsection

@php
    $breadcrumbs = [
        ['title' => __('labels.home'), 'url' => route('admin.dashboard')],
        ['title' => __('labels.languages_management'), 'url' => route('admin.languages.index')],
        ['title' => $surface->label(), 'url' => null],
    ];
@endphp

@section('admin-content')
    <div class="row row-cards">
        <div class="col-12">
            <div class="card">
                <div class="card-header">
                    <div>
                        <h3 class="card-title">{{ $surface->label() }}</h3>
                        <x-breadcrumb :items="$breadcrumbs" />
                        <div class="text-muted">{{ $surface->description() }}</div>
                    </div>
                    <div class="card-actions">
                        <div class="row g-2">
                            @if ($createPermission)
                                <div class="col-auto">
                                    <a href="{{ route('admin.languages.clients.create', $surface->value) }}"
                                        class="btn btn-6 btn-outline-primary">
                                        <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24"
                                            viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
                                            stroke-linecap="round" stroke-linejoin="round"
                                            class="icon icon-tabler icons-tabler-outline icon-tabler-plus">
                                            <path stroke="none" d="M0 0h24v24H0z" fill="none" />
                                            <path d="M12 5l0 14" />
                                            <path d="M5 12l14 0" />
                                        </svg>
                                        {{ __('labels.add_language') }}
                                    </a>
                                </div>
                            @endif
                            <div class="col-auto">
                                <button class="btn btn-outline-primary" id="refresh" type="button">
                                    <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24"
                                        viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
                                        stroke-linecap="round" stroke-linejoin="round"
                                        class="icon icon-tabler icons-tabler-outline icon-tabler-refresh">
                                        <path stroke="none" d="M0 0h24v24H0z" fill="none" />
                                        <path d="M20 11a8.1 8.1 0 0 0 -15.5 -2m-.5 -4v4h4" />
                                        <path d="M4 13a8.1 8.1 0 0 0 15.5 2m.5 4v-4h-4" />
                                    </svg>
                                    {{ __('labels.refresh') }}
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="card-table">
                    <div class="card-actions mt-3 ms-3">
                        <div class="row g-2">
                            <div class="col-auto">
                                <label class="visually-hidden"
                                    for="language-status-filter">{{ __('labels.status_filter') }}</label>
                                <select id="language-status-filter" class="form-select">
                                    <option value="">{{ __('labels.all_statuses') }}</option>
                                    <option value="active">{{ __('labels.active') }}</option>
                                    <option value="inactive">{{ __('labels.inactive') }}</option>
                                </select>
                            </div>
                        </div>
                    </div>
                    <div class="row w-full p-3">
                        <x-datatable id="languages-table" :columns="$columns"
                            route="{{ route('admin.languages.clients.datatable', $surface->value) }}" :options="['order' => [[6, 'asc']], 'pageLength' => 10]" />
                    </div>
                </div>
            </div>
        </div>

        <div class="col-12">
            @include('admin.languages.partials.source-keys-summary')
        </div>

        <div class="col-12">
            @include('admin.languages.partials.quick-add-languages')
        </div>
    </div>
@endsection

@push('scripts')
    <script>
        window.languageLabels = {
            areYouSure: @json(__('labels.are_you_sure')),
            cancel: @json(__('labels.cancel')),
            confirmDeactivate: @json(__('labels.language_deactivate_confirm')),
            deactivate: @json(__('labels.deactivate')),
            delete: @json(__('labels.delete')),
            requestFailed: @json(__('labels.language_action_failed')),
            deleteConfirm: @json(__('labels.language_delete_confirm')),
            success: @json(__('labels.success')),
        };
    </script>
    <script src="{{ hyperAsset('assets/js/languages.js') }}" defer></script>
@endpush
