@extends('layouts.admin.app', ['page' => $menuAdmin['delivery_zones']['active'] ?? '', 'sub_page' => $menuAdmin['delivery_zones']['route']['index']['sub_active'] ?? ''])

@section('title', __('labels.delivery_zones'))

@section('header_data')
    @php
        $page_title = __('labels.delivery_zones');
        $page_pretitle = __('labels.list');
    @endphp
@endsection

@php
    $breadcrumbs = [
        ['title' => __('labels.home'), 'url' => route('admin.dashboard')],
        ['title' => __('labels.delivery_zones'), 'url' => null],
    ];
@endphp

@section('admin-content')
    <div class="row row-cards">
        <div class="col-12">
            <div class="card">
                <div class="card-header">
                    @php
                        $actionRequiredCount = \App\Helpers\SidebarBadgeHelper::getBadgeCounts('admin')['delivery_zones'] ?? 0;
                    @endphp
                    <div>
                        <h3 class="card-title">
                            {{ __('labels.delivery_zones_list') }}
                            @if ($actionRequiredCount > 0)
                                <span class="badge bg-danger text-white ms-2">
                                    <span class="status-dot status-dot-animated bg-white me-1"></span>{{ $actionRequiredCount }}
                                    {{ __('labels.action_required') }}
                                </span>
                            @endif
                        </h3>
                        <x-breadcrumb :items="$breadcrumbs" />
                    </div>
                    <div class="card-actions">
                        <div class="row g-2">
                            <div class="col-auto">
                                <select id="countryStatusFilter" class="form-select">
                                    <option value="">{{ __('labels.all_zones') ?? 'All Zones' }}</option>
                                    <option value="needs_country">{{ __('labels.needs_country') ?? 'Needs Country' }} ({{ __('labels.action_required') }})</option>
                                    <option value="configured">{{ __('labels.country_configured') ?? 'Configured' }}</option>
                                </select>
                            </div>
                            <div class="col-auto">
                                @if ($createPermission ?? false)
                                    <div class="col text-end">
                                        <a href="{{ route('admin.delivery-zones.create') }}"
                                            class="btn btn-6 btn-outline-primary">
                                            <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24"
                                                viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
                                                stroke-linecap="round" stroke-linejoin="round" class="icon icon-2">
                                                <path d="M12 5l0 14" />
                                                <path d="M5 12l14 0" />
                                            </svg>
                                            {{ __('labels.add_delivery_zone') }}
                                        </a>
                                    </div>
                                @endif
                            </div>
                            <div class="col-auto">
                                <button class="btn btn-outline-primary" id="refresh">
                                    <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24"
                                        viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
                                        stroke-linecap="round" stroke-linejoin="round"
                                        class="icon icon-tabler icons-tabler-outline icon-tabler-refresh">
                                        <path stroke="none" d="M0 0h24v24H0z" fill="none" />
                                        <path d="M20 11a8.1 8.1 0 0 0 -15.5 -2m-.5 -4v4h4" />
                                        <path d="M4 13a8.1 8.1 0 0 0 15.5 2m.5 4v-4h-4" />
                                    </svg>
                                    {{ __('labels.refresh') }}
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="card-table">
                    <div class="row w-full p-3">
                        <x-datatable id="delivery-zones-table" :columns="$columns"
                            route="{{ route('admin.delivery-zones.datatable') }}" :options="['order' => [[0, 'desc']], 'pageLength' => 10]" />
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection
@push('script')
    <script src="{{ hyperAsset('assets/js/delivery-zone.js') }}"></script>
    <script>
        $(document).ready(function() {
            const tableId = '#delivery-zones-table';
            $(tableId).on('preXhr.dt', function(e, settings, data) {
                data.country_status = $('#countryStatusFilter').val();
            });

            $('#countryStatusFilter').on('change', function() {
                if ($.fn.DataTable.isDataTable(tableId)) {
                    $(tableId).DataTable().ajax.reload();
                }
            });

            $('#refresh').on('click', function() {
                if ($.fn.DataTable.isDataTable(tableId)) {
                    $(tableId).DataTable().ajax.reload();
                }
            });
        });
    </script>
@endpush
