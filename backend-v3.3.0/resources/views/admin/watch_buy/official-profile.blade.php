@extends('layouts.admin.app', [
    'page' => $menuAdmin['social_and_content']['active'] ?? '',
    'sub_page' => $menuAdmin['social_and_content']['route']['official_profile']['sub_active'] ?? '',
])

@section('title', __('labels.watch_buy_official_profile'))

@section('header_data')
    @php
        $page_title = __('labels.watch_buy_official_profile');
        $page_pretitle = __('labels.watch_buy');
    @endphp
@endsection

@php
    $breadcrumbs = [
        ['title' => __('labels.home'), 'url' => route('admin.dashboard')],
        ['title' => __('labels.watch_buy_official_profile'), 'url' => null],
    ];
@endphp

@section('admin-content')
    <div class="card">
        <form class="form-submit" method="post" enctype="multipart/form-data"
            action="{{ route('admin.watch-and-buy.official-profile.update') }}">
            @csrf
            <div class="card-header">
                <div>
                    <h3 class="card-title">{{ __('labels.watch_buy_official_profile') }}</h3>
                    <x-breadcrumb :items="$breadcrumbs" />
                </div>
            </div>
            <div class="card-body">
                @include('watch_buy._profile_fields', ['profile' => $profile])
            </div>
            <div class="card-footer text-end">
                <button type="submit" class="btn btn-primary">{{ __('labels.save') }}</button>
            </div>
        </form>
    </div>
@endsection
