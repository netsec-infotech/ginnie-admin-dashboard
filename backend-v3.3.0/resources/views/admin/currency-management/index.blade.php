@extends('layouts.admin.app', ['page' => $menuAdmin['delivery_zones']['active'] ?? '', 'sub_page' => $menuAdmin['delivery_zones']['route']['country_management']['sub_active'] ?? ''])

@section('title', 'Country Management')

@section('header_data')
    @php
        $page_title = 'Country Management';
        $page_pretitle = 'Regional Settings';
    @endphp
@endsection

@php
    $breadcrumbs = [
        ['title' => __('labels.home'), 'url' => route('admin.dashboard')],
        ['title' => 'Regional Settings', 'url' => route('admin.delivery-zones.index')],
        ['title' => 'Country Management', 'url' => null],
    ];
@endphp

@section('admin-content')
    <div class="page-body currency-management-page">
        <div class="container-xl">
            <div class="row row-cards">
                <div class="col-12">
                    <div class="card">
                        <div class="card-header">
                            <div>
                                <h3 class="card-title">Country Management</h3>
                                <x-breadcrumb :items="$breadcrumbs" />
                            </div>
                            <div class="card-actions">
                                <span class="badge bg-blue-lt">Base: {{ $systemCurrency['code'] }}</span>
                                @if ($systemCurrency['symbol'])
                                    <span class="badge bg-secondary-lt">{{ $systemCurrency['symbol'] }}</span>
                                @endif
                            </div>
                        </div>
                        <div class="card-body">
                            <div class="alert alert-info mb-0">
                                This read-only view shows which countries and currencies are used by delivery zones, linked stores, and safe gateway currency settings.
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-sm-6 col-xl-3">
                    <div class="card">
                        <div class="card-body">
                            <div class="subheader">Countries in zones</div>
                            <div class="h1 mb-2">{{ $summary['countries_in_zones'] }}</div>
                            <div class="text-secondary">Assigned on delivery zones</div>
                        </div>
                    </div>
                </div>
                <div class="col-sm-6 col-xl-3">
                    <div class="card">
                        <div class="card-body">
                            <div class="subheader">Delivery zones</div>
                            <div class="h1 mb-2">{{ $summary['delivery_zones'] }}</div>
                            <div class="text-secondary">{{ $summary['zones_needing_setup'] }} need country or currency setup</div>
                        </div>
                    </div>
                </div>
                <div class="col-sm-6 col-xl-3">
                    <div class="card">
                        <div class="card-body">
                            <div class="subheader">Store mismatches</div>
                            <div class="h1 mb-2">{{ $summary['store_mismatches'] }}</div>
                            <div class="text-secondary">Stores using a different zone currency</div>
                        </div>
                    </div>
                </div>
                <div class="col-sm-6 col-xl-3">
                    <div class="card">
                        <div class="card-body">
                            <div class="subheader">Payment differences</div>
                            <div class="h1 mb-2">{{ $summary['gateway_differences'] }}</div>
                            <div class="text-secondary">Gateway currency differs from base currency</div>
                        </div>
                    </div>
                </div>

                <div class="col-12">
                    <div class="card">
                        <div class="card-header">
                            <ul class="nav nav-tabs card-header-tabs" data-bs-toggle="tabs">
                                <li class="nav-item"><a href="#countries-tab" class="nav-link active" data-bs-toggle="tab">Countries Used</a></li>
                                <li class="nav-item"><a href="#zones-tab" class="nav-link" data-bs-toggle="tab">Zones</a></li>
                                <li class="nav-item"><a href="#stores-tab" class="nav-link" data-bs-toggle="tab">Stores</a></li>
                                <li class="nav-item"><a href="#gateways-tab" class="nav-link" data-bs-toggle="tab">Gateways</a></li>
                                <li class="nav-item"><a href="#currencies-tab" class="nav-link" data-bs-toggle="tab">Currency Usage</a></li>
                                <li class="nav-item"><a href="#gateway-readiness-tab" class="nav-link" data-bs-toggle="tab">Gateway Readiness</a></li>
                            </ul>
                        </div>
                        <div class="card-body">
                            <div class="tab-content">
                                <div class="tab-pane active show" id="countries-tab">
                                    <x-datatable id="country-management-countries" :route="route('admin.country-management.countries.datatable')" :columns="$columns['countries']" :lazy="true" />
                                </div>

                                <div class="tab-pane" id="zones-tab">
                                    <x-datatable id="country-management-zones" :route="route('admin.country-management.zones.datatable')" :columns="$columns['zones']" :lazy="true" />
                                </div>

                                <div class="tab-pane" id="stores-tab">
                                    <x-datatable id="country-management-stores" :route="route('admin.country-management.stores.datatable')" :columns="$columns['stores']" :lazy="true" />
                                </div>

                                <div class="tab-pane" id="gateways-tab">
                                    <div class="alert alert-warning">Only safe currency configuration fields are shown. API keys, secrets, tokens, webhooks, and payment payloads are never displayed here.</div>
                                    <x-datatable id="country-management-gateways" :route="route('admin.country-management.gateways.datatable')" :columns="$columns['gateways']" :lazy="true" />
                                </div>

                                <div class="tab-pane" id="currencies-tab">
                                    <x-datatable id="country-management-currencies" :route="route('admin.country-management.currencies.datatable')" :columns="$columns['currencies']" :lazy="true" />
                                </div>

                                <div class="tab-pane" id="gateway-readiness-tab">
                                    <div class="alert alert-info">Gateway readiness is calculated from enabled payment settings and explicit zone currency metadata. Checkout still validates the selected payment method again before payment.</div>
                                    <x-datatable id="country-management-gateway-readiness" :route="route('admin.country-management.gateway-readiness.datatable')" :columns="$columns['gateway_readiness']" :lazy="true" />
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection

@push('styles')
    <link rel="stylesheet" href="{{ hyperAsset('assets/css/currency-management.css') }}">
@endpush

@push('scripts')
    <script src="{{ hyperAsset('assets/js/currency-management.js') }}" defer></script>
@endpush
