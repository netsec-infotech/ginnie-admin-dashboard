@extends('layouts.admin.app', [
    'page' => $menuAdmin['social_and_content']['active'] ?? '',
    'sub_page' => $menuAdmin['social_and_content']['route']['reels']['sub_active'] ?? '',
])

@section('title', __('labels.watch_buy_reels'))

@section('header_data')
    @php
        $page_title = __('labels.watch_buy_reels');
        $page_pretitle = __('labels.watch_buy');
    @endphp
@endsection

@php
    $breadcrumbs = [
        ['title' => __('labels.home'), 'url' => route('admin.dashboard')],
        ['title' => __('labels.watch_buy_reels'), 'url' => null],
    ];
@endphp

@section('admin-content')
    <div class="card">
        <div class="card-header">
            <div>
                <h3 class="card-title">{{ __('labels.watch_buy_reels') }}</h3>
                <x-breadcrumb :items="$breadcrumbs" />
            </div>
            <div class="card-actions d-flex gap-2">
                @can('watch_buy.reel.create')
                    <a class="btn btn-primary" href="{{ route('admin.watch-and-buy.reels.create') }}">
                        <i class="ti ti-plus me-1"></i>{{ __('labels.add_new_reel') }}
                    </a>
                @endcan
                <button type="button" class="btn btn-outline-primary refresh-table">
                    <i class="ti ti-refresh me-1"></i>{{ __('labels.refresh') }}
                </button>
            </div>
        </div>
        <div class="card-table">
            <div class="row w-full p-3">
                <x-datatable id="admin-watch-buy-reels-table" :columns="$columns"
                    route="{{ route('admin.watch-and-buy.reels.datatable') }}" :options="['order' => [[6, 'desc']], 'pageLength' => 10]" />
            </div>
        </div>
    </div>
@endsection


@push('styles')
    <link rel="stylesheet" href="{{ asset('assets/css/watch-buy-status.css') }}">
@endpush

@push('scripts')
    <script src="{{ hyperAsset('assets/js/watch-buy-management.js') }}" defer></script>
@endpush
