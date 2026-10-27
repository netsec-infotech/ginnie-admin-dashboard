@extends('layouts.admin.app', ['page' => $menuAdmin['returns']['active'] ?? 'returns'])

@section('title', __('labels.return_detail'))

@section('header_data')
    @php
        $page_title    = __('labels.return_detail') . ' #' . $return->id;
        $page_pretitle = __('labels.admin') . ' ' . __('labels.return_requests');
    @endphp
@endsection

@php
    $breadcrumbs = [
        ['title' => __('labels.home'),            'url' => route('admin.dashboard')],
        ['title' => __('labels.return_requests'), 'url' => route('admin.returns.index')],
        ['title' => '#' . $return->id,            'url' => ''],
    ];
@endphp

@section('admin-content')
    <div class="page-wrapper">
        <div class="page-body">
            @include('returns._detail', [
                'return' => $return,
                'panel' => $panel,
                'refundBreakdown' => $refundBreakdown,
                'timelineSteps' => $timelineSteps,
                'canAssignRider' => $canAssignRider ?? false,
                'storeZoneId' => $storeZoneId ?? null,
            ])
        </div>
    </div>

    @include('admin.returns.partials.return-accept-modal')
    @include('admin.returns.partials.return-reject-modal')
    @if (($canAssignRider ?? false))
        @include('admin.returns.partials.assign-rider-modal')
    @endif
@endsection

@push('scripts')
    <script src="{{ hyperAsset('assets/js/admin-return-detail.js') }}" defer></script>
@endpush
