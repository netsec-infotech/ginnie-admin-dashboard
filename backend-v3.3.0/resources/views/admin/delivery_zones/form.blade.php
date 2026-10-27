@php use App\Enums\ActiveInactiveStatusEnum; @endphp
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
        ['title' => __('labels.delivery_zones'), 'url' => route('admin.delivery-zones.index')],
        [
            'title' => !empty($deliveryZone) ? __('labels.edit_delivery_zone') : __('labels.add_delivery_zone'),
            'url' => '',
        ],
    ];
    $indexUrl = $indexUrl ?? route('admin.delivery-zones.index');
@endphp

@section('admin-content')
    <!-- Page header -->
    <div class="page-header d-print-none">
        <div class="container-xl">
            <div class="row g-2 align-items-center">
                <div class="col">
                    <h2 class="page-title">
                        {{ __('labels.delivery_zones') }}
                    </h2>
                    <x-breadcrumb :items="$breadcrumbs" />
                </div>
            </div>
        </div>
    </div>

    <!-- Page body -->
    <div class="page-body">
        <div class="row row-cards">
            <div class="col-12">
                @if (!$googleApiKey)
                    <div class="alert alert-danger alert-dismissible" role="alert">
                        <div class="alert-icon">
                            <!-- Download SVG icon from http://tabler.io/icons/icon/alert-circle -->
                            <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24"
                                fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"
                                stroke-linejoin="round" class="icon alert-icon icon-2">
                                <path d="M3 12a9 9 0 1 0 18 0a9 9 0 0 0 -18 0" />
                                <path d="M12 8v4" />
                                <path d="M12 16h.01" />
                            </svg>
                        </div>
                        <div>
                            <h4 class="alert-heading"><a
                                    href="{{ route('admin.settings.show', ['setting' => \App\Enums\SettingTypeEnum::AUTHENTICATION()]) }}"
                                    target="_blank"> {{ __('messages.google_api_key_not_found') }} </a>
                            </h4>
                        </div>
                        <a class="btn-close" data-bs-dismiss="alert" aria-label="close"></a>
                    </div>
                @endif
                <div class="card">
                    <div class="card-header">
                        <h3 class="card-title">{{ __('labels.select_delivery_zones') }}</h3>
                        <div class="card-actions">
                            <a href="{{ $indexUrl }}" class="btn btn-secondary">
                                <i class="ti ti-arrow-left me-1"></i> {{ __('labels.back_to_zones') }}
                            </a>
                        </div>
                    </div>
                    <form
                        action="{{ empty($deliveryZone) ? route('admin.delivery-zones.store') : route('admin.delivery-zones.update', ['id' => $deliveryZone->id]) }}"
                        method="POST" class="form-submit"
                        data-redirect="{{ $indexUrl ?? route('admin.delivery-zones.index') }}">
                        @csrf
                        <div class="card-body">
                            <div
                                class="zone-toolbar d-flex flex-wrap align-items-center justify-content-between gap-2 mb-2">
                                <div class="btn-list flex-wrap" role="toolbar" aria-label="{{ __('labels.draw_zone') }}">
                                    <button type="button" class="btn btn-primary" id="draw-zone-btn">
                                        <svg xmlns="http://www.w3.org/2000/svg" class="icon" width="20" height="20"
                                            viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
                                            stroke-linecap="round" stroke-linejoin="round">
                                            <path stroke="none" d="M0 0h24v24H0z" fill="none" />
                                            <path d="M4 20h4l10.5 -10.5a1.5 1.5 0 0 0 -4 -4l-10.5 10.5v4" />
                                            <path d="M13.5 6.5l4 4" />
                                        </svg>
                                        <span>{{ __('labels.draw_zone') }}</span>
                                    </button>
                                    <button type="button" class="btn btn-outline-secondary d-none" id="undo-vertex-btn">
                                        <svg xmlns="http://www.w3.org/2000/svg" class="icon" width="20" height="20"
                                            viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
                                            stroke-linecap="round" stroke-linejoin="round">
                                            <path stroke="none" d="M0 0h24v24H0z" fill="none" />
                                            <path d="M9 14l-4 -4l4 -4" />
                                            <path d="M5 10h11a4 4 0 1 1 0 8h-1" />
                                        </svg>
                                        <span>{{ __('labels.undo_last_point') }}</span>
                                    </button>
                                    <button type="button" class="btn btn-success d-none" id="finish-draw-btn">
                                        <svg xmlns="http://www.w3.org/2000/svg" class="icon" width="20" height="20"
                                            viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
                                            stroke-linecap="round" stroke-linejoin="round">
                                            <path stroke="none" d="M0 0h24v24H0z" fill="none" />
                                            <path d="M5 12l5 5l10 -10" />
                                        </svg>
                                        <span>{{ __('labels.finish_drawing') }}</span>
                                    </button>
                                    <button type="button" class="btn btn-outline-danger d-none" id="cancel-draw-btn">
                                        <svg xmlns="http://www.w3.org/2000/svg" class="icon" width="20" height="20"
                                            viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
                                            stroke-linecap="round" stroke-linejoin="round">
                                            <path stroke="none" d="M0 0h24v24H0z" fill="none" />
                                            <path d="M18 6l-12 12" />
                                            <path d="M6 6l12 12" />
                                        </svg>
                                        <span>{{ __('labels.cancel_drawing') }}</span>
                                    </button>
                                    <button type="button" class="btn btn-outline-primary d-none" id="toggle-edit-btn"
                                        data-edit-label="{{ __('labels.edit_zone') }}"
                                        data-done-label="{{ __('labels.done_editing') }}">
                                        <svg xmlns="http://www.w3.org/2000/svg" class="icon" width="20"
                                            height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                                            stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                            <path stroke="none" d="M0 0h24v24H0z" fill="none" />
                                            <path d="M7 7h-1a2 2 0 0 0 -2 2v9a2 2 0 0 0 2 2h9a2 2 0 0 0 2 -2v-1" />
                                            <path
                                                d="M20.385 6.585a2.1 2.1 0 0 0 -2.97 -2.97l-8.415 8.385v3h3l8.385 -8.415z" />
                                            <path d="M16 5l3 3" />
                                        </svg>
                                        <span>{{ __('labels.edit_zone') }}</span>
                                    </button>
                                    <button type="button" class="btn btn-outline-danger d-none" id="clear-last"
                                        data-confirm="{{ __('labels.remove_polygon_confirm') }}"
                                        data-confirm-title="{{ __('labels.remove_polygon') }}"
                                        data-confirm-yes="{{ __('labels.remove_polygon') }}"
                                        data-confirm-no="{{ __('labels.cancel_drawing') }}">
                                        <svg xmlns="http://www.w3.org/2000/svg" class="icon" width="20"
                                            height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                                            stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                            <path stroke="none" d="M0 0h24v24H0z" fill="none" />
                                            <path d="M4 7l16 0" />
                                            <path d="M10 11l0 6" />
                                            <path d="M14 11l0 6" />
                                            <path d="M5 7l1 12a2 2 0 0 0 2 2h8a2 2 0 0 0 2 -2l1 -12" />
                                            <path d="M9 7v-3a1 1 0 0 1 1 -1h4a1 1 0 0 1 1 1v3" />
                                        </svg>
                                        <span>{{ __('labels.remove_polygon') }}</span>
                                    </button>
                                    @if (!empty($deliveryZone))
                                        <button type="button" class="btn btn-outline-warning" id="reset-zone">
                                            <svg xmlns="http://www.w3.org/2000/svg" class="icon" width="20"
                                                height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                                                stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                                <path stroke="none" d="M0 0h24v24H0z" fill="none" />
                                                <path d="M20 11a8.1 8.1 0 0 0 -15.5 -2m-.5 -4v4h4" />
                                                <path d="M4 13a8.1 8.1 0 0 0 15.5 2m.5 4v-4h-4" />
                                            </svg>
                                            <span>{{ __('labels.reset_to_original_zone') }}</span>
                                        </button>
                                    @endif
                                </div>
                                <div class="zone-readout text-secondary small d-none" id="zone-readout"
                                    aria-live="polite">
                                    <span class="me-2"><strong id="readout-vertices">0</strong>
                                        {{ __('labels.vertices') }}</span>
                                    <span class="me-2">·</span>
                                    <span class="me-2"><strong id="readout-perimeter">0</strong> km
                                        {{ __('labels.perimeter') }}</span>
                                    <span class="me-2">·</span>
                                    <span class="me-2"><strong id="readout-area">0</strong> km²
                                        {{ __('labels.area') }}</span>
                                    <span class="me-2">·</span>
                                    <span><strong id="readout-radius">0</strong> km
                                        {{ __('labels.radius') }}</span>
                                </div>
                            </div>
                            <div class="zone-status text-muted small mb-2" id="zone-status" aria-live="polite"></div>
                            <div class="zone-legend small text-secondary mb-2 d-flex align-items-center gap-2"
                                id="zone-legend" style="display:none;">
                                <span class="legend-swatch"
                                    style="display:inline-block;width:14px;height:14px;border:2px solid #0066ff;background:rgba(26,115,232,0.12);border-radius:2px;"></span>
                                {{ __('labels.other_delivery_zones') }}
                            </div>

                            <input type="hidden" name="center_latitude" id="center-latitude"
                                value="{{ $deliveryZone->center_latitude ?? '' }}">
                            <input type="hidden" name="center_longitude" id="center-longitude"
                                value="{{ $deliveryZone->center_longitude ?? '' }}">
                            <input type="hidden" name="boundary_json" id="boundary-json"
                                value="{{ !empty($deliveryZone) ? json_encode($deliveryZone->boundary_json) : '' }}">
                            <input type="hidden" name="radius_km" id="radius-km"
                                value="{{ $deliveryZone->radius_km ?? '' }}">
                            <input type="hidden" name="country_code" id="zone-country-code"
                                value="{{ $deliveryZone->country_code ?? '' }}">
                            <input type="hidden" name="currency_code" id="zone-currency-code"
                                value="{{ $deliveryZone->currency_code ?? '' }}">
                            <input type="hidden" name="currency_symbol" id="zone-currency-symbol"
                                value="{{ $deliveryZone->currency_symbol ?? '' }}">
                            <input type="hidden" name="country_detection_status" id="zone-country-detection-status"
                                value="{{ $deliveryZone->country_detection_status ?? '' }}">
                            <input type="hidden" name="detected_countries" id="zone-detected-countries" value="">
                            @if (!empty($deliveryZone))
                                <input type="hidden" id="current-zone-id" value="{{ $deliveryZone->id }}" />
                            @endif
                            @if (!empty($deliveryZone))
                                <textarea class="d-none" id="existing-delivery-zone">{{ $deliveryZone }}</textarea>
                            @endif
                            <div class="place-autocomplete-card" id="place-autocomplete-card">
                                <p>Search for a place here:</p>
                            </div>
                            <div id="map" style="height: 500px;" class="mb-3 border"
                                @if (!empty($defaultLatitude) && !empty($defaultLongitude)) data-default-lat="{{ $defaultLatitude }}"
                                     data-default-lng="{{ $defaultLongitude }}" @endif>
                            </div>
                            <div class="mb-3">
                                <label for="zone-name" class="form-label required">{{ __('labels.zone_name') }}</label>
                                <input type="text" class="form-control" name="name" id="zone-name"
                                    placeholder="{{ __('labels.placeholder_zone_name') }}"
                                    value="{{ $deliveryZone->name ?? '' }}">
                            </div>
                            <div class="row align-items-end">
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="zone-country-id" class="form-label required">Country</label>
                                        <select class="form-control" name="country_id" id="zone-country-id" required>
                                            <option value="">Select country</option>
                                            @foreach ($countries ?? [] as $country)
                                                <option value="{{ $country->id }}"
                                                    data-country-code="{{ strtoupper((string) $country->iso2) }}"
                                                    data-currency-code="{{ strtoupper((string) $country->currency) }}"
                                                    data-currency-symbol="{{ $country->currency_symbol }}"
                                                    @selected(!empty($deliveryZone) && (int) $deliveryZone->country_id === (int) $country->id)>
                                                    {{ $country->name }} - {{ strtoupper((string) $country->currency) }} {{ $country->currency_symbol }}
                                                </option>
                                            @endforeach
                                        </select>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="alert alert-info mb-3" id="zone-currency-preview">
                                        <div class="fw-semibold">Zone currency</div>
                                        <div id="zone-currency-preview-text">
                                            {{ !empty($deliveryZone?->currency_code) ? $deliveryZone->currency_code . ' ' . $deliveryZone->currency_symbol : 'Draw a zone or select a country to preview currency.' }}
                                        </div>
                                    </div>
                                    <div class="alert alert-danger d-none mb-3" id="zone-country-warning">
                                        This zone appears to cross multiple countries. Split it into separate zones.
                                    </div>
                                </div>
                            </div>
                            <div id="delivery-zone-details">
                                <h4 class="mt-4 mb-3">{{ __('labels.delivery_changes_and_details') }}</h4>
                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label for="free-delivery-amount" class="form-label">
                                                {{ __('labels.free_delivery_amount') }}
                                                <x-form-info-tooltip :title="__('messages.free_delivery_amount_info_message')" />
                                            </label>
                                            <input type="number" class="form-control" name="free_delivery_amount"
                                                id="free-delivery-amount" placeholder="e.g. 500"
                                                value="{{ $deliveryZone->free_delivery_amount ?? '' }}" min="0">
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label for="buffer-time" class="form-label required">
                                                {{ __('labels.buffer_time') }}
                                                <x-form-info-tooltip :title="__('messages.buffer_time_info_message')" />
                                            </label>
                                            <div class="input-group mb-2">
                                                <input type="number" class="form-control" name="buffer_time"
                                                    id="buffer-time" placeholder="e.g. 10"
                                                    value="{{ $deliveryZone->buffer_time ?? '' }}" min="0">
                                                <span class="input-group-text"> {{ __('labels.minutes') }} </span>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <div class="mb-3">
                                    <div class="form-check form-switch">
                                        <input class="form-check-input" type="checkbox" id="rush-delivery-enabled"
                                            name="rush_delivery_enabled" value="1"
                                            {{ !empty($deliveryZone) && $deliveryZone->rush_delivery_enabled ? 'checked' : '' }}>
                                        <label class="form-check-label" for="rush-delivery-enabled">
                                            {{ __('labels.rush_delivery_enabled') }}
                                            <x-form-info-tooltip :title="__('messages.rush_delivery_enabled_info_message')" />
                                        </label>
                                    </div>
                                </div>

                                <div id="rush-delivery-fields"
                                    class="{{ !empty($deliveryZone) && $deliveryZone->rush_delivery_enabled ? '' : 'd-none' }}">
                                    <div class="row">
                                        <div class="col-md-6">
                                            <div class="mb-3">
                                                <label for="rush-delivery-time-per-km" class="form-label required">
                                                    {{ __('labels.rush_delivery_time_per_km') }}
                                                    <x-form-info-tooltip :title="__('messages.rush_delivery_time_per_km_info_message')" />
                                                </label>
                                                <div class="input-group mb-2">
                                                    <input type="number" class="form-control"
                                                        name="rush_delivery_time_per_km" id="rush-delivery-time-per-km"
                                                        placeholder="e.g. 3"
                                                        value="{{ $deliveryZone->rush_delivery_time_per_km ?? '' }}"
                                                        min="0">
                                                    <span class="input-group-text"> {{ __('labels.minutes') }} </span>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <div class="mb-3">
                                                <label for="rush-delivery-charges" class="form-label required">
                                                    {{ __('labels.rush_delivery_charges') }}
                                                    <x-form-info-tooltip :title="__('messages.rush_delivery_charges_info_message')" />
                                                </label>
                                                <input type="number" class="form-control" name="rush_delivery_charges"
                                                    id="rush-delivery-charges" placeholder="e.g. 100"
                                                    value="{{ $deliveryZone->rush_delivery_charges ?? '' }}"
                                                    min="0">
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <div class="row">
                                    <div class="col-md-4">
                                        <div class="mb-3">
                                            <label for="delivery-time-per-km" class="form-label required">
                                                {{ __('labels.delivery_time_per_km') }}
                                                <x-form-info-tooltip :title="__('messages.delivery_time_per_km_info_message')" />
                                            </label>
                                            <div class="input-group mb-2">
                                                <input type="number" class="form-control" name="delivery_time_per_km"
                                                    id="delivery-time-per-km" placeholder="e.g. 5"
                                                    value="{{ $deliveryZone->delivery_time_per_km ?? '' }}"
                                                    min="0">
                                                <span class="input-group-text"> {{ __('labels.minutes') }} </span>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-md-4">
                                        <div class="mb-3">
                                            <label for="regular-delivery-charges" class="form-label required">
                                                {{ __('labels.regular_delivery_charges') }}
                                                <x-form-info-tooltip :title="__('messages.regular_delivery_charges_info_message')" />
                                            </label>
                                            <input type="number" class="form-control" name="regular_delivery_charges"
                                                id="regular-delivery-charges" placeholder="e.g. 50"
                                                value="{{ $deliveryZone->regular_delivery_charges ?? '' }}"
                                                min="0">
                                        </div>
                                    </div>
                                    <div class="col-md-4">
                                        <div class="mb-3">
                                            <label for="distance-based-delivery-charges" class="form-label">
                                                {{ __('labels.distance_based_delivery_charges') }}
                                                <x-form-info-tooltip :title="__('messages.distance_based_delivery_charges_info_message')" />
                                            </label>
                                            <input type="number" class="form-control"
                                                name="distance_based_delivery_charges"
                                                id="distance-based-delivery-charges" placeholder="e.g. 10"
                                                value="{{ $deliveryZone->distance_based_delivery_charges ?? '' }}"
                                                min="0">
                                        </div>
                                    </div>
                                </div>
                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label for="per-store-drop-off-fee" class="form-label">
                                                {{ __('labels.per_store_drop_off_fee') }}
                                                <x-form-info-tooltip :title="__('messages.per_store_drop_off_fee_info_message')" />
                                            </label>
                                            <input type="number" class="form-control" name="per_store_drop_off_fee"
                                                id="per-store-drop-off-fee" placeholder="e.g. 20"
                                                value="{{ $deliveryZone->per_store_drop_off_fee ?? '' }}" min="0">
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label for="handling-charges" class="form-label">
                                                {{ __('labels.handling_charges') }}
                                                <x-form-info-tooltip :title="__('messages.handling_charges_info_message')" />
                                            </label>
                                            <input type="number" class="form-control" name="handling_charges"
                                                id="handling-charges" placeholder="e.g. 15"
                                                value="{{ $deliveryZone->handling_charges ?? '' }}" min="0">
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <h4 class="mt-4 mb-3">{{ __('labels.delivery_boy_earnings') }}</h4>
                            <div id="delivery-boy-earnings">

                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label for="delivery-boy-base-fee" class="form-label">
                                                {{ __('labels.base_fee') }}
                                                <x-form-info-tooltip :title="__('messages.delivery_boy_base_fee_info_message')" />
                                            </label>
                                            <input type="number" class="form-control" name="delivery_boy_base_fee"
                                                id="delivery-boy-base-fee" placeholder="e.g. 50.00" step="0.01"
                                                value="{{ $deliveryZone->delivery_boy_base_fee ?? '' }}" min="0">
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label for="delivery-boy-per-store-pickup-fee" class="form-label">
                                                {{ __('labels.per_store_pickup_fee') }}
                                                <x-form-info-tooltip :title="__(
                                                    'messages.delivery_boy_per_store_pickup_fee_info_message',
                                                )" />
                                            </label>
                                            <input type="number" class="form-control"
                                                name="delivery_boy_per_store_pickup_fee"
                                                id="delivery-boy-per-store-pickup-fee" placeholder="e.g. 15.00"
                                                step="0.01"
                                                value="{{ $deliveryZone->delivery_boy_per_store_pickup_fee ?? '' }}"
                                                min="0">
                                        </div>
                                    </div>
                                </div>
                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label for="delivery-boy-distance-based-fee" class="form-label">
                                                {{ __('labels.distance_based_fee') }}
                                                <x-form-info-tooltip :title="__('messages.delivery_boy_distance_based_fee_info_message')" />
                                            </label>
                                            <input type="number" class="form-control"
                                                name="delivery_boy_distance_based_fee"
                                                id="delivery-boy-distance-based-fee" placeholder="e.g. 10.00"
                                                step="0.01"
                                                value="{{ $deliveryZone->delivery_boy_distance_based_fee ?? '' }}"
                                                min="0">
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label for="delivery-boy-per-order-incentive" class="form-label">
                                                {{ __('labels.per_order_incentive') }}
                                                <x-form-info-tooltip :title="__('messages.delivery_boy_per_order_incentive_info_message')" />
                                            </label>
                                            <input type="number" class="form-control"
                                                name="delivery_boy_per_order_incentive"
                                                id="delivery-boy-per-order-incentive" placeholder="e.g. 20.00"
                                                step="0.01"
                                                value="{{ $deliveryZone->delivery_boy_per_order_incentive ?? '' }}"
                                                min="0">
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="mb-3">
                                <label for="status" class="form-label">{{ __('labels.status') }}</label>
                                <select name="status" id="status" class="form-control text-capitalize" required>
                                    @foreach (ActiveInactiveStatusEnum::values() as $status)
                                        <option value="{{ $status }}"
                                            {{ !empty($deliveryZone) && $deliveryZone->status == $status ? 'selected' : '' }}>
                                            {{ $status }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                        </div>
                        <div class="card-footer text-end">
                            <button type="submit" class="btn btn-primary text-end">{{ __('labels.save') }}</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
@endsection
@push('styles')
    <style>
        #place-autocomplete-card {
            background: var(--tblr-card-bg, #fff);
            border-radius: var(--tblr-border-radius, .5rem);
            box-shadow: var(--tblr-box-shadow-sm, 0 2px 4px rgba(0, 0, 0, .1));
            margin: 10px;
            padding: .5rem .75rem;
            font-size: .875rem;
            color: var(--tblr-body-color, #232e3c);
        }

        gmp-place-autocomplete {
            width: 280px;
        }

        #infowindow-content .title {
            font-weight: 600;
        }

        #map #infowindow-content {
            display: inline;
        }

        /* Pixel-sized vertex marker (used via AdvancedMarkerElement content) */
        .zone-vertex {
            width: 12px;
            height: 12px;
            border-radius: 50%;
            background: #d63939;
            border: 2px solid #fff;
            box-shadow: 0 0 0 1px rgba(0, 0, 0, .25);
            transform: translate(-50%, -50%);
            transition: transform .15s ease, background .15s ease;
        }

        .zone-vertex.is-first {
            background: #2fb344;
        }

        .zone-vertex.is-snap {
            transform: translate(-50%, -50%) scale(1.7);
            box-shadow: 0 0 0 4px rgba(47, 179, 68, .35);
        }

        .zone-label {
            font-size: .7rem;
            background: rgba(255, 255, 255, .9);
            color: #1f2937;
            padding: 2px 6px;
            border-radius: 999px;
            border: 1px solid rgba(0, 102, 255, .35);
            white-space: nowrap;
            transform: translate(-50%, -120%);
        }
    </style>
@endpush
@push('scripts')
    <script async defer>
        (g => {
            var h, a, k, p = "The Google Maps JavaScript API",
                c = "google",
                l = "importLibrary",
                q = "__ib__",
                m = document,
                b = window;
            b = b[c] || (b[c] = {});
            var d = b.maps || (b.maps = {}),
                r = new Set,
                e = new URLSearchParams,
                u = () => h || (h = new Promise(async (f, n) => {
                    await (a = m.createElement("script"));
                    e.set("libraries", [...r] + "");
                    for (k in g) e.set(k.replace(/[A-Z]/g, t => "_" + t[0].toLowerCase()), g[k]);
                    e.set("callback", c + ".maps." + q);
                    a.src = `https://maps.${c}apis.com/maps/api/js?` + e;
                    d[q] = f;
                    a.onerror = () => h = n(Error(p + " could not load."));
                    a.nonce = m.querySelector("script[nonce]")?.nonce || "";
                    m.head.append(a)
                }));
            d[l] ? console.warn(p + " only loads once. Ignoring:", g) : d[l] = (f, ...n) => r.add(f) && u().then(() =>
                d[l](f, ...n))
        })
        ({
            key: "{{ $googleApiKey }}",
            v: "weekly"
        });
    </script>
    <script>
        @php
            $deliveryZoneCountries = collect($countries ?? [])->map(function ($country) {
                return [
                    'id' => $country->id,
                    'name' => $country->name,
                    'iso2' => strtoupper((string) $country->iso2),
                    'currency' => strtoupper((string) $country->currency),
                    'currency_symbol' => $country->currency_symbol,
                ];
            })->values();
        @endphp

        window.zoneStatusClickToPlace = @json(__('labels.click_map_to_place_vertices'));
        window.zoneStatusClickToClose = @json(__('labels.click_first_point_to_close'));
        window.confirmRemovePolygonText = @json(__('labels.remove_polygon'));
        window.deliveryZoneCountries = @json($deliveryZoneCountries);

        document.addEventListener('DOMContentLoaded', function() {
            const rushToggle = document.getElementById('rush-delivery-enabled');
            const rushFields = document.getElementById('rush-delivery-fields');
            if (rushToggle && rushFields) {
                rushToggle.addEventListener('change', function() {
                    if (this.checked) {
                        rushFields.classList.remove('d-none');
                    } else {
                        rushFields.classList.add('d-none');
                    }
                });
            }
        });
    </script>
    <script src="{{ hyperAsset('assets/js/delivery-zone.js') }}"></script>
@endpush
