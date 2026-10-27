@extends('layouts.admin.app', ['page' => $menuAdmin['languages']['active'] ?? '', 'sub_page' => $menuAdmin['languages']['route']['index']['sub_active'] ?? ''])

@section('title', __('labels.languages_management'))

@section('header_data')
    @php
        $page_title = __('labels.languages_management');
        $page_pretitle = __('labels.admin').' '.__('labels.settings');
    @endphp
@endsection

@php
    $breadcrumbs = [
        ['title' => __('labels.home'), 'url' => route('admin.dashboard')],
        ['title' => __('labels.languages_management'), 'url' => null],
    ];
@endphp

@section('admin-content')
    <div class="row row-cards">
        <div class="col-12">
            <div class="card">
                <div class="card-header">
                    <div>
                        <h3 class="card-title">{{ __('labels.languages_management') }}</h3>
                        <x-breadcrumb :items="$breadcrumbs"/>
                        <div class="text-muted">{{ __('labels.language_areas_hint') }}</div>
                    </div>
                </div>
            </div>
        </div>

        @can('language.edit')
            <div class="col-12">
                @include('admin.languages.partials.pack-sync')
            </div>
        @endcan

        @foreach($areas as $area)
            <div class="col-md-6 col-lg-4">
                <div class="card h-100">
                    <div class="card-body">
                        <div class="d-flex align-items-start justify-content-between gap-3 mb-3">
                            <span class="avatar avatar-md bg-primary-lt">
                                <i class="ti {{ $area['icon'] }} fs-2"></i>
                            </span>
                            <span class="badge {{ $area['available'] ? 'bg-success-lt' : 'bg-warning-lt' }}">
                                {{ $area['available'] ? __('labels.available') : __('labels.coming_soon') }}
                            </span>
                        </div>
                        <h3 class="card-title mb-2">{{ $area['name'] }}</h3>
                        <p class="text-muted mb-0">{{ $area['description'] }}</p>
                    </div>
                    <div class="card-footer">
                        @if($area['available'])
                            <a href="{{ $area['url'] }}" class="btn btn-primary w-100">
                                <i class="ti ti-settings me-1"></i>
                                {{ __('labels.manage') }}
                            </a>
                        @else
                            <button type="button" class="btn w-100" disabled>
                                <i class="ti ti-clock-pause me-1"></i>
                                {{ __('labels.coming_soon') }}
                            </button>
                        @endif
                    </div>
                </div>
            </div>
        @endforeach
    </div>
@endsection

