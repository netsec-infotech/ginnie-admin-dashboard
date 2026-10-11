@extends('layouts.seller.app', [
    'page' => $menuSeller['return_orders']['active'] ?? '',
    'sub_page' => $menuSeller['return_orders']['route']['return_requests']['sub_active'] ?? '',
])

@section('title', __('labels.return_detail'))

@section('header_data')
    @php
        $page_title    = __('labels.return_detail') . ' #' . $return->id;
        $page_pretitle = __('labels.seller') . ' ' . __('labels.return_requests');
    @endphp
@endsection

@php
    $breadcrumbs = [
        ['title' => __('labels.home'),            'url' => route('seller.dashboard')],
        ['title' => __('labels.return_requests'), 'url' => route('seller.returns.index')],
        ['title' => '#' . $return->id,            'url' => ''],
    ];
@endphp

@section('seller-content')
    <div class="page-wrapper">
        <div class="page-body">
            @include('returns._detail', [
                'return' => $return,
                'panel' => $panel,
                'refundBreakdown' => $refundBreakdown,
                'timelineSteps' => $timelineSteps,
            ])
        </div>
    </div>

    @include('seller.returns.partials.return-accept-modal')
    @include('seller.returns.partials.returns-reject-modal')
    
    {{--
    // ==========================================================
    // Return Dispute System
    // Phase 3
    // Include Seller Store Confirm & Escalate Modals
    // ==========================================================
    --}}
    @include('seller.returns.partials.return-store-confirm-modal')
    @include('seller.returns.partials.return-store-escalate-modal')
@endsection

@push('scripts')
    <script src="{{ hyperAsset('assets/js/seller-return-detail.js') }}" defer></script>
@endpush
