@extends('layouts.seller.app', [
    'page' => $menuSeller['watch_and_buy']['active'] ?? '',
    'sub_page' => $menuSeller['watch_and_buy']['route']['reels']['sub_active'] ?? '',
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
        ['title' => __('labels.home'), 'url' => route('seller.dashboard')],
        ['title' => __('labels.watch_buy_reels'), 'url' => null],
    ];
@endphp

@section('seller-content')
    @if (!$profile)
        <div class="alert alert-warning">{{ __('labels.watch_buy_profile_required') }} <a
                href="{{ route('seller.watch-and-buy.profile.edit') }}">{{ __('labels.watch_buy_profile') }}</a></div>
    @endif

    @if (!$enabled)
        <div class="alert alert-warning">{{ __('labels.watch_buy_feature_disabled') }}</div>
    @endif

    @if ($profile?->status?->value === 'suspended')
        <div class="alert alert-danger">{{ __('labels.watch_buy_profile_suspended') }}: {{ $profile->suspension_reason }}
        </div>
    @endif

    <div class="card">
        <div class="card-header">
            <div>
                <h3 class="card-title">{{ __('labels.watch_buy_reels') }}</h3>
                <x-breadcrumb :items="$breadcrumbs" />
            </div>
            <div class="card-actions d-flex gap-2">
                @if (
                    $enabled &&
                        $profile &&
                        $profile->status->value === 'active' &&
                        (auth()->user()?->hasRole(\App\Enums\DefaultSystemRolesEnum::SELLER()) ||
                            auth()->user()?->can('watch_buy.reel.create')))
                    <a class="btn btn-primary" href="{{ route('seller.watch-and-buy.reels.create') }}">
                        <i class="ti ti-plus me-1"></i>{{ __('labels.add_new_reel') }}
                    </a>
                @endif
                <button type="button" class="btn btn-outline-primary refresh-table">
                    <i class="ti ti-refresh me-1"></i>{{ __('labels.refresh') }}
                </button>
            </div>
        </div>
        <div class="card-table">
            <div class="row w-full p-3">
                <x-datatable id="seller-watch-buy-reels-table" :columns="$columns"
                    route="{{ route('seller.watch-and-buy.reels.datatable') }}" :options="['order' => [[4, 'desc']], 'pageLength' => 10]" />
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
