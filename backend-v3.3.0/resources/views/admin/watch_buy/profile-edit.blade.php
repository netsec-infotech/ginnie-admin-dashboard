@extends('layouts.admin.app', [
    'page' => $menuAdmin['social_and_content']['active'] ?? '',
    'sub_page' => $menuAdmin['social_and_content']['route']['profiles']['sub_active'] ?? ''
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
        ['title' => __('labels.home'), 'url' => route('admin.dashboard')],
        ['title' => __('labels.watch_buy'), 'url' => null],
        ['title' => __('labels.watch_buy_profiles'), 'url' => route('admin.watch-and-buy.profiles.index')],
        ['title' => '@' . $profile->username, 'url' => null],
    ];
@endphp

@section('admin-content')
    <div class="card">
        <form class="form-submit" method="post" enctype="multipart/form-data" action="{{ route('admin.watch-and-buy.profiles.update', $profile) }}">
            @csrf
            <div class="card-header">
                <div>
                    <h3 class="card-title">{{ __('labels.watch_buy_profile') }} — {{ '@'.$profile->username }}</h3>
                    <x-breadcrumb :items="$breadcrumbs"/>
                </div>
            </div>
            <div class="card-body">
                @include('watch_buy._profile_fields', ['profile' => $profile])
            </div>
            <div class="card-footer text-end">
                <a class="btn btn-link" href="{{ route('admin.watch-and-buy.profiles.index') }}">{{ __('labels.cancel') }}</a>
                <button type="submit" class="btn btn-primary">{{ __('labels.save') }}</button>
            </div>
        </form>
    </div>
@endsection

