@extends('layouts.admin.app', [
    'page' => $menuAdmin['social_and_content']['active'] ?? '',
    'sub_page' => $menuAdmin['social_and_content']['route']['reels']['sub_active'] ?? '',
])

@section('title', __('labels.watch_buy_reels'))

@section('header_data')
    @php
        $page_title = $reel->exists ? __('labels.edit') : __('labels.create');
        $page_pretitle = __('labels.watch_buy_reels');
    @endphp
@endsection

@push('styles')
    <link rel="stylesheet" href="{{ asset('assets/css/watch-buy-status.css') }}">
@endpush

@php
    $breadcrumbs = [
        ['title' => __('labels.home'), 'url' => route('admin.dashboard')],
        ['title' => __('labels.watch_buy_reels'), 'url' => route('admin.watch-and-buy.reels.index')],
        ['title' => $reel->exists ? __('labels.edit') : __('labels.create'), 'url' => null],
    ];
@endphp

@section('admin-content')
    <div class="card">
        <form class="form-submit" method="post" enctype="multipart/form-data"
            action="{{ $reel->exists ? route('admin.watch-and-buy.reels.update', $reel) : route('admin.watch-and-buy.reels.store') }}">
            @csrf
            <div class="card-header">
                <div>
                    <h3 class="card-title">{{ $reel->exists ? __('labels.edit') : __('labels.create') }}
                        {{ __('labels.watch_buy_reels') }}</h3>
                    <x-breadcrumb :items="$breadcrumbs" />
                </div>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label class="form-label required">{{ __('labels.watch_buy_creation_mode') }}</label>
                        <select class="form-select" name="source" required>
                            @if ($reel->source?->value === 'seller')
                                <option value="seller" @selected(old('source', 'seller') === 'seller')>{{ __('labels.seller') }}
                                    ({{ __('labels.source') }})</option>
                            @endif
                            <option value="seller_behalf" @selected(old('source', $reel->source?->value ?: 'seller_behalf') === 'seller_behalf')>
                                {{ __('labels.watch_buy_seller_behalf') }}</option>
                            <option value="admin_direct" @selected(old('source', $reel->source?->value) === 'admin_direct')>
                                {{ __('labels.watch_buy_admin_direct') }}</option>
                        </select>
                    </div>
                    <div class="col-md-6 mb-3" id="watch-buy-seller-profile-container">
                        <label class="form-label required">{{ __('labels.watch_buy_seller_profile') }}</label>
                        @php
                            $selectedProfileId = (int) old('profile_id', $reel->profile_id);
                            $selectedProfile = $profiles->firstWhere('id', $selectedProfileId);
                        @endphp
                        <select class="form-select watch-buy-reel-profile-select" name="profile_id"
                            id="watch-buy-seller-profile-select"
                            placeholder="{{ __('labels.watch_buy_seller_profile') }}"
                            data-placeholder="{{ __('labels.watch_buy_seller_profile') }}"
                            data-endpoint="{{ route('admin.watch-and-buy.profiles.options') }}">
                            @if ($selectedProfile)
                                <option value="{{ $selectedProfile->id }}" selected>
                                    {{ '@' . $selectedProfile->username }} — {{ $selectedProfile->seller?->user?->name }}
                                </option>
                            @endif
                        </select>
                    </div>
                </div>

                @include('watch_buy._reel_fields')
                @include('watch_buy._product_picker', [
                    'pickerId' => 'admin-reel-products',
                    'endpoint' => route('admin.watch-and-buy.reels.product-options'),
                    'required' => true,
                    'primary' => true,
                    'selectedOptions' => $selectedOptions ?? [],
                    'selected' => $selected ?? [],
                    'maxItems' => $config['maxProductsPerReel'],
                    'sourceSelector' => '[name="source"]',
                    'profileSelector' => '[name="profile_id"]',
                ])
            </div>
            <div class="card-footer text-end">
                <a class="btn btn-link"
                    href="{{ route('admin.watch-and-buy.reels.index') }}">{{ __('labels.cancel') }}</a>
                <button type="submit" class="btn btn-primary">{{ __('labels.save') }}</button>
            </div>
        </form>
    </div>
@endsection
