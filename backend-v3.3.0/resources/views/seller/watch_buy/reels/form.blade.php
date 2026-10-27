@extends('layouts.seller.app', [
    'page' => $menuSeller['watch_and_buy']['active'] ?? '',
    'sub_page' => $menuSeller['watch_and_buy']['route']['reels']['sub_active'] ?? '',
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
        ['title' => __('labels.home'), 'url' => route('seller.dashboard')],
        ['title' => __('labels.watch_buy_reels'), 'url' => route('seller.watch-and-buy.reels.index')],
        ['title' => $reel->exists ? __('labels.edit') : __('labels.create'), 'url' => null],
    ];
@endphp

@section('seller-content')
    <div class="card">
        <form class="form-submit" method="post" enctype="multipart/form-data" data-seller-tour="reel-form"
            action="{{ $reel->exists ? route('seller.watch-and-buy.reels.update', $reel) : route('seller.watch-and-buy.reels.store') }}">
            @csrf
            <div class="card-header">
                <div>
                    <h3 class="card-title">{{ $reel->exists ? __('labels.edit') : __('labels.create') }}
                        {{ __('labels.watch_buy_reels') }}</h3>
                    <x-breadcrumb :items="$breadcrumbs" />
                </div>
            </div>
            <div class="card-body">
                @include('watch_buy._reel_fields')
                <div data-seller-tour="reel-products">
                @include('watch_buy._product_picker', [
                    'pickerId' => 'seller-reel-products',
                    'endpoint' => route('seller.watch-and-buy.reels.product-options'),
                    'required' => true,
                    'primary' => true,
                    'selectedOptions' => $selectedOptions ?? [],
                    'selected' => $selected ?? [],
                    'maxItems' => $config['maxProductsPerReel'],
                ])
                </div>
            </div>
            <div class="card-footer text-end">
                <a class="btn btn-link" href="{{ route('seller.watch-and-buy.reels.index') }}">{{ __('labels.cancel') }}</a>
                <button type="submit" class="btn btn-primary" @disabled(!$config['enabled'])>{{ __('labels.save') }}</button>
            </div>
        </form>
    </div>
@endsection
