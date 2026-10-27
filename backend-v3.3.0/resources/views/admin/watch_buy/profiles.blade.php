@extends('layouts.admin.app', [
    'page' => $menuAdmin['social_and_content']['active'] ?? '',
    'sub_page' => $menuAdmin['social_and_content']['route']['profiles']['sub_active'] ?? '',
])

@section('title', __('labels.watch_buy_profiles'))

@section('header_data')
    @php
        $page_title = __('labels.watch_buy_profiles');
        $page_pretitle = __('labels.list');
    @endphp
@endsection

@php
    $breadcrumbs = [
        ['title' => __('labels.home'), 'url' => route('admin.dashboard')],
        ['title' => __('labels.watch_buy_profiles'), 'url' => null],
    ];
@endphp

@section('admin-content')
    <div class="row row-cards">
        <div class="col-12">
            <div class="card">
                <div class="card-header">
                    <div>
                        <h3 class="card-title">{{ __('labels.watch_buy_profiles') }}</h3>
                        <x-breadcrumb :items="$breadcrumbs"/>
                    </div>
                    <div class="card-actions">
                        <div class="row g-2">
                            @if ($editPermission)
                                <div class="col-auto">
                                    <button class="btn btn-primary" type="button" data-bs-toggle="modal"
                                        data-bs-target="#watch-buy-profile-modal">
                                        <i class="ti ti-plus me-1"></i>{{ __('labels.watch_buy_add_seller_profile') }}
                                    </button>
                                </div>
                            @endif
                            <div class="col-auto">
                                <button class="btn btn-outline-primary" id="refresh" type="button">
                                    <i class="ti ti-refresh me-1"></i>{{ __('labels.refresh') }}
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="card-table">
                    <div class="row w-full p-3">
                        <x-datatable id="watch-buy-profiles-table" :columns="$columns"
                            route="{{ route('admin.watch-and-buy.profiles.datatable') }}"
                            :options="['order' => [[5, 'desc']], 'pageLength' => 10]"/>
                    </div>
                </div>
            </div>
        </div>
    </div>

    @if ($editPermission)
        <div class="modal modal-blur fade" id="watch-buy-profile-modal" tabindex="-1" data-bs-backdrop="static" aria-hidden="true">
            <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
                <div class="modal-content">
                    <form class="form-submit" method="post" enctype="multipart/form-data"
                        action="{{ route('admin.watch-and-buy.profiles.store') }}">
                        @csrf
                        <div class="modal-header">
                            <h5 class="modal-title">{{ __('labels.watch_buy_create_seller_profile') }}</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="{{ __('labels.close') }}"></button>
                        </div>
                        <div class="modal-body">
                            <div class="mb-3">
                                <label class="form-label required">{{ __('labels.seller') }}</label>
                                <select class="form-select watch-buy-profile-seller-select" name="seller_id" required
                                    data-endpoint="{{ route('admin.watch-and-buy.profiles.seller-options') }}"
                                    data-selected="{{ (int) old('seller_id') }}"
                                    data-placeholder="{{ __('labels.watch_buy_search_sellers') }}"></select>
                                @error('seller_id')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                            </div>
                            @include('watch_buy._profile_fields', ['profile' => null])
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn" data-bs-dismiss="modal">{{ __('labels.cancel') }}</button>
                            <button type="submit" class="btn btn-primary">{{ __('labels.watch_buy_create_profile') }}</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    @endif
@endsection

@push('scripts')
    <script src="{{ asset('assets/js/watch-buy-management.js') }}" defer></script>
    @if ($errors->any())
        <script>
            document.addEventListener('DOMContentLoaded', () => {
                bootstrap.Modal.getOrCreateInstance(document.getElementById('watch-buy-profile-modal')).show();
            });
        </script>
    @endif
@endpush
