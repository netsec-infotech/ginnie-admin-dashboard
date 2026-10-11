@extends('layouts.admin.app', ['page' => $menuAdmin['delivery_zones']['active'] ?? "", 'sub_page' => $menuAdmin['delivery_zones']['route']['zone_preview']['sub_active'] ?? "" ])

@section('title', __('labels.zone_preview'))

@section('header_data')
    @php
        $page_title = __('labels.zone_preview');
        $page_pretitle = __('labels.admin') . " " . __('labels.tools');
    @endphp
@endsection

@php
    $breadcrumbs = [
        ['title' => __('labels.home'), 'url' => route('admin.dashboard')],
        ['title' => __('labels.zone_preview'), 'url' => null],
    ];
@endphp

@section('admin-content')
    <div class="row row-cards">
        <div class="col-12">
            <div class="card">
                <div class="card-header">
                    <div>
                        <h3 class="card-title">{{ __('labels.zone_preview') }}</h3>
                        <p class="text-muted m-0 small">{{ __('labels.zone_preview_help') }}</p>
                        <x-breadcrumb :items="$breadcrumbs"/>
                    </div>
                </div>
                <div class="card-body">
                    <div class="row g-3 align-items-end mb-3">
                        <div class="col-md-4">
                            <label class="form-label required">{{ __('labels.zone') }}</label>
                            <select id="zonePreviewZone" class="form-select">
                                <option value="">{{ __('labels.select_zone_to_preview') }}</option>
                                @foreach($deliveryZones as $zone)
                                    <option value="{{ $zone->id }}">{{ $zone->name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">{{ __('labels.viewing') }}</label>
                            <select id="zonePreviewContext" class="form-select" disabled>
                                <option value="">{{ __('labels.all') }}</option>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">{{ __('labels.search') }}</label>
                            <input type="search" id="zonePreviewSearch" class="form-control"
                                   placeholder="{{ __('labels.search_in_active_tab') }}" autocomplete="off"
                                   disabled>
                        </div>
                    </div>
                    <div class="row g-3 align-items-end mb-3">
                        <div class="col-12">
                            <div id="zonePreviewSummary" class="text-muted small"></div>
                        </div>
                    </div>

                    <ul class="nav nav-tabs" id="zonePreviewTabs" role="tablist">
                        <li class="nav-item" role="presentation">
                            <button class="nav-link active" data-bs-toggle="tab"
                                    data-bs-target="#tab-zp-categories"
                                    data-tab="categories" type="button" role="tab">
                                {{ __('labels.categories') }}
                                <span class="badge bg-azure-lt ms-1" id="zonePreviewCategoriesCount"></span>
                            </button>
                        </li>
                        <li class="nav-item" role="presentation">
                            <button class="nav-link" data-bs-toggle="tab" data-bs-target="#tab-zp-brands"
                                    data-tab="brands" type="button" role="tab">
                                {{ __('labels.brands') }}
                                <span class="badge bg-azure-lt ms-1" id="zonePreviewBrandsCount"></span>
                            </button>
                        </li>
                        <li class="nav-item" role="presentation">
                            <button class="nav-link" data-bs-toggle="tab" data-bs-target="#tab-zp-banners"
                                    data-tab="banners" type="button" role="tab">
                                {{ __('labels.banners') }}
                                <span class="badge bg-azure-lt ms-1" id="zonePreviewBannersCount"></span>
                            </button>
                        </li>
                        <li class="nav-item" role="presentation">
                            <button class="nav-link" data-bs-toggle="tab"
                                    data-bs-target="#tab-zp-featured-sections"
                                    data-tab="featured-sections" type="button" role="tab">
                                {{ __('labels.featured_sections') }}
                                <span class="badge bg-azure-lt ms-1"
                                      id="zonePreviewFeaturedSectionsCount"></span>
                            </button>
                        </li>
                        <li class="nav-item" role="presentation">
                            <button class="nav-link" data-bs-toggle="tab" data-bs-target="#tab-zp-products"
                                    data-tab="products" type="button" role="tab">
                                {{ __('labels.products') }}
                                <span class="badge bg-azure-lt ms-1" id="zonePreviewProductsCount"></span>
                            </button>
                        </li>
                    </ul>
                    <div class="tab-content border border-top-0 p-3" id="zonePreviewTabsContent">
                        <div class="tab-pane fade show active" id="tab-zp-categories" role="tabpanel">
                            <div id="zonePreviewCategoriesBody">
                                <p class="text-muted m-0">{{ __('labels.select_zone_to_preview') }}</p>
                            </div>
                        </div>
                        <div class="tab-pane fade" id="tab-zp-brands" role="tabpanel">
                            <div id="zonePreviewBrandsBody">
                                <p class="text-muted m-0">{{ __('labels.select_zone_to_preview') }}</p>
                            </div>
                        </div>
                        <div class="tab-pane fade" id="tab-zp-banners" role="tabpanel">
                            <div id="zonePreviewBannersBody">
                                <p class="text-muted m-0">{{ __('labels.select_zone_to_preview') }}</p>
                            </div>
                        </div>
                        <div class="tab-pane fade" id="tab-zp-featured-sections" role="tabpanel">
                            <div id="zonePreviewFeaturedSectionsBody">
                                <p class="text-muted m-0">{{ __('labels.select_zone_to_preview') }}</p>
                            </div>
                        </div>
                        <div class="tab-pane fade" id="tab-zp-products" role="tabpanel">
                            <div id="zonePreviewProductsBody">
                                <p class="text-muted m-0">{{ __('labels.select_zone_to_preview') }}</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    {{-- Endpoint URLs the JS reads from data attributes so we don't hardcode panel paths in JS. --}}
    <div id="zonePreviewEndpoints"
         data-brands="{{ route('admin.zones.preview.brands') }}"
         data-categories="{{ route('admin.zones.preview.categories') }}"
         data-banners="{{ route('admin.zones.preview.banners') }}"
         data-featured-sections="{{ route('admin.zones.preview.featured-sections') }}"
         data-products="{{ route('admin.zones.preview.products') }}"
         data-home-categories="{{ route('admin.zones.preview.home-categories') }}"
         data-i18n-no-results="{{ __('labels.no_results_in_zone') }}"
         data-i18n-loading="{{ __('labels.loading') }}"
         data-i18n-zones="{{ __('labels.zones') }}"
         data-i18n-total="{{ __('labels.total') }}"
         data-i18n-showing="{{ __('labels.showing') }}"
         data-i18n-of="{{ __('labels.of') }}"
         data-i18n-prev="{{ __('labels.previous') }}"
         data-i18n-next="{{ __('labels.next') }}"
         data-i18n-products-count="{{ __('labels.products') }}"
         data-i18n-all="{{ __('labels.all') }}"
         data-i18n-all-zones="{{ __('labels.available_in_all_zones') }}"
         hidden></div>
@endsection

@push('scripts')
    <script src="{{ hyperAsset('assets/js/zone-preview.js') }}" defer></script>
@endpush
