@extends('layouts.seller.app', [
    'page' => $menuSeller['watch_and_buy']['active'] ?? '',
    'sub_page' => $menuSeller['watch_and_buy']['route']['profile']['sub_active'] ?? '',
])

@section('title', __('labels.watch_buy_profile'))

@section('header_data')
    @php
        $page_title = __('labels.watch_buy_profile');
        $page_pretitle = __('labels.watch_buy');
    @endphp
@endsection

@php
    $breadcrumbs = [
        ['title' => __('labels.home'), 'url' => route('seller.dashboard')],
        ['title' => __('labels.watch_buy_profile'), 'url' => null],
    ];
@endphp

@section('seller-content')
    @if (session('watch_buy_profile_required'))
        <div class="alert alert-warning">{{ session('watch_buy_profile_required') }}</div>
    @endif

    @if (!$enabled)
        <div class="alert alert-warning">{{ __('labels.watch_buy_feature_disabled') }}</div>
    @endif

    @if ($profile?->status?->value === 'suspended')
        <div class="alert alert-danger">
            <strong>{{ __('labels.watch_buy_profile_suspended') }}</strong><br>
            {{ $profile->suspension_reason }}
        </div>
    @endif

    <div class="card">
        <form class="form-submit" method="post" enctype="multipart/form-data" data-seller-tour="watch-profile"
            action="{{ route('seller.watch-and-buy.profile.update') }}">
            @csrf
            <div class="card-header">
                <div>
                    <h3 class="card-title">{{ __('labels.watch_buy_profile') }}</h3>
                    <x-breadcrumb :items="$breadcrumbs" />
                </div>
            </div>
            <div class="card-body">
                @include('watch_buy._profile_fields', ['profile' => $profile])
            </div>
            <div class="card-footer text-end">
                <button type="submit" class="btn btn-primary" @disabled(!$enabled || $profile?->status?->value === 'suspended')>
                    {{ __('labels.watch_buy_save_profile') }}
                </button>
            </div>
        </form>
    </div>

    @if ($profile && $statusViewPermission)
        @php
            $statusesEnabled = $config['enabled'] && $config['statusesEnabled'];
            $suspended = $profile->status->value === 'suspended';
            $canCreate = $statusCreatePermission;
            $canDelete = !$suspended && $statusDeletePermission;
        @endphp

        @include('watch_buy._seller_status_section', [
            'composerId' => 'seller-profile-watch-buy-status-composer',
            'pickerId' => 'seller-profile-story-products',
        ])
    @endif
@endsection

@push('styles')
    <link rel="stylesheet" href="{{ asset('assets/css/watch-buy-status.css') }}">
@endpush

@push('scripts')
    @if ($profile && $statusViewPermission)
        <script src="{{ asset('assets/js/watch-buy-status.js') }}" defer></script>

        @if ($errors->has('media') || $errors->has('text') || $errors->has('attachment_ids'))
            <script>
                document.addEventListener('DOMContentLoaded', () => {
                    bootstrap.Modal.getOrCreateInstance(document.getElementById('seller-profile-watch-buy-status-composer'))
                        .show();
                });
            </script>
        @endif
    @endif
@endpush
