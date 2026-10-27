@extends('layouts.seller.app', [
    'page' => $menuSeller['watch_and_buy']['active'] ?? '',
    'sub_page' => $menuSeller['watch_and_buy']['route']['stories']['sub_active'] ?? '',
])

@section('title', __('labels.watch_buy_statuses'))

@section('header_data')
    @php
        $page_title = __('labels.watch_buy_statuses');
        $page_pretitle = __('labels.watch_buy');
    @endphp
@endsection

@php
    $statusesEnabled = $config['enabled'] && $config['statusesEnabled'];
    $suspended = $profile->status->value === 'suspended';
    $isSeller = auth()->user()?->hasRole(\App\Enums\DefaultSystemRolesEnum::SELLER());
    $canCreate = $isSeller || auth()->user()?->can('watch_buy.status.create');
    $canDelete = ! $suspended && ($isSeller || auth()->user()?->can('watch_buy.status.delete'));
@endphp

@section('seller-content')
    @if ($suspended)
        <div class="alert alert-danger">
            {{ __('labels.watch_buy_profile_suspended') }}: {{ $profile->suspension_reason }}
        </div>
    @endif

    @include('watch_buy._seller_status_section', [
        'composerId' => 'seller-watch-buy-status-composer',
        'pickerId' => 'seller-story-products',
    ])
@endsection

@push('styles')
    <link rel="stylesheet" href="{{ asset('assets/css/watch-buy-status.css') }}">
@endpush

@push('scripts')
    <script src="{{ asset('assets/js/watch-buy-status.js') }}" defer></script>

    @if ($errors->any())
        <script>
            document.addEventListener('DOMContentLoaded', () => {
                bootstrap.Modal.getOrCreateInstance(document.getElementById('seller-watch-buy-status-composer')).show();
            });
        </script>
    @endif
@endpush
