@extends('layouts.admin.app', ['page' => $config['page'], 'sub_page' => $config['sub_page']])

@section('title', $config['title'])

@section('header_data')
    @php
        $page_title = $config['title'];
        $page_pretitle = __('labels.admin') . ' ' . __('labels.reviews');
    @endphp
@endsection

@php
    $breadcrumbs = [
        ['title' => __('labels.home'), 'url' => route('admin.dashboard')],
        ['title' => $config['title'], 'url' => null],
    ];
@endphp

@section('admin-content')
    <div class="page-wrapper">
        <div class="page-body">
            <div class="row row-cards">
                <div class="col-12">
                    <div class="card">
                        <div class="card-header">
                            <div>
                                <h3 class="card-title">{{ $config['title'] }}</h3>
                                <x-breadcrumb :items="$breadcrumbs" />
                            </div>
                            <div class="card-actions">
                                <div class="row g-2">
                                    <div class="col-auto">
                                        <select class="form-select" id="ratingFilter">
                                            <option value="">{{ __('labels.all_ratings') }}</option>
                                            @foreach($ratingOptions as $value => $label)
                                                <option value="{{ $value }}">{{ $label }}</option>
                                            @endforeach
                                        </select>
                                    </div>
                                    <div class="col-auto">
                                        <select class="form-select" id="rangeFilter">
                                            @foreach($dateRangeOptions as $value => $label)
                                                <option value="{{ $value }}">{{ $label }}</option>
                                            @endforeach
                                        </select>
                                    </div>
                                    @if($config['context'] === 'products')
                                        <div class="col-auto">
                                            <select class="form-select" id="imageFilter">
                                                @foreach($imageOptions as $value => $label)
                                                    <option value="{{ $value }}">{{ $label }}</option>
                                                @endforeach
                                            </select>
                                        </div>
                                    @endif
                                    <div class="col-auto">
                                        <button class="btn btn-outline-primary" id="refresh">
                                            <i class="ti ti-refresh me-1"></i>{{ __('labels.refresh') }}
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="card-body">
                            <x-datatable
                                id="admin-reviews-table"
                                :columns="$columns"
                                :route="route($config['route_prefix'] . '.datatable')"
                                class="table table-striped table-bordered table-vcenter review-moderation-table w-100"
                                :options="['order' => [[$config['context'] === 'products' ? 7 : 6, 'desc']], 'pageLength' => 10]"
                            />
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection

@push('scripts')
    <script src="{{ hyperAsset('assets/js/admin-reviews.js') }}" defer></script>
@endpush
