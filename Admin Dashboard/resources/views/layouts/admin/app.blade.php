@extends('layouts.main')

{{-- @section('title', 'Admin Dashboard') --}}

@section('header_data')
    @php
        $page_title = $page_title ?? 'Admin Dashboard';
        $page_pretitle = $page_pretitle ?? 'Overview';
    @endphp
@endsection
@if(($page ?? '') === ($menuAdmin['settings']['active'] ?? 'settings') && ($systemSettings['demoMode'] ?? false))
    @push('styles')
        <style>
            .form-submit button[type="submit"],
            .form-submit input[type="submit"] {
                display: none !important;
            }
        </style>
    @endpush
@endif
@section('content')
    @include('layouts.partials._header', [
        'page_title' => $page_title ?? 'Admin Dashboard',
        'page_pretitle' => $page_pretitle ?? 'Overview',
    ])
    <div class="page-body">
        <div class="container-xl">
            @yield('admin-content')
        </div>
    </div>
@endsection
