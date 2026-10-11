@php use App\Enums\Store\StoreStatusEnum; @endphp
@extends('layouts.seller.app', ['page' => $menuSeller['stores']['active'] ?? ""])

@section('title', __('labels.store_configuration'))

@section('header_data')
    @php
        $page_title = __('labels.store_configuration');
    @endphp
@endsection

@php
    $isScheduledDeliveryEnabled = \App\Models\Setting::isScheduledDeliveryEnabled();

    $breadcrumbs = [
        ['title' => __('labels.home'), 'url' => route('seller.dashboard')],
        ['title' => __('labels.store_configuration'), 'url' => '']
    ];
@endphp

@section('seller-content')
    <div class="page-wrapper">
        @include('components.page_header', ['title' => $store->name . ' Store Configuration', 'step' => 2])

        <!-- BEGIN PAGE BODY -->
        <!-- resources/views/admin/sellers/form.blade.php -->
        <div class="page-body">
            <div class="container-xl">
                <div class="row g-5">
                    <div class="col-sm-2 d-none d-lg-block">
                        <div class="sticky-top">
                            <h3>{{ __('labels.menu') }}</h3>
                            <nav class="nav nav-vertical nav-pills" id="pills">
                                <a class="nav-link" href="#pills-scheduling">{{ __('labels.scheduling') }}</a>
                                @if ($isScheduledDeliveryEnabled)
                                    <a class="nav-link" href="#pills-scheduled-delivery">{{ __('labels.scheduled_delivery_slots') }}</a>
                                @endif
                                @if (\App\Models\Setting::isPickupEnabled())
                                    <a class="nav-link" href="#pills-pickup-cod">
                                        <i class="ti ti-shopping-cart-discount me-2"></i>{{ __('labels.pickup_settings') ?? 'Self-Pickup Settings' }}
                                    </a>
                                @endif
                                {{--                                <a class="nav-link" href="#pills-delivery">{{ __('labels.delivery_settings') }}</a>--}}
                                <a class="nav-link" href="#pills-store">{{ __('labels.store_information') }}</a>
                                <a class="nav-link" href="#pills-policies">{{ __('labels.policies') }}</a>
                                <a class="nav-link" href="#pills-metadata">{{ __('labels.metadata') }}</a>
                                <a class="nav-link" href="#pills-pos">
                                    <i class="ti ti-devices-pc me-2"></i>{{ __('labels.pos_store_settings') }}
                                </a>
                            </nav>
                        </div>
                    </div>
                    <div class="col-sm" data-bs-spy="scroll" data-bs-target="#pills" data-bs-offset="0">
                        <div class="row row-cards">
                            <div class="col-12">
                                <form action="{{route('seller.stores.store_configuration', ['id' =>$store->id])}}"
                                      class="form-submit" method="post" enctype="multipart/form-data" novalidate>
                                    @csrf
                                    <div class="row">
                                        <div class="col-md-12">
                                            <!-- Scheduling -->
                                            <div class="card mb-4" id="pills-scheduling">
                                                <div class="card-header">
                                                    <h4 class="card-title">{{ __('labels.scheduling') }}</h4>
                                                </div>
                                                <div class="card-body">
                                                    <input type="hidden" name="timing" id="store_timing_hidden" value="{{ $store->timing ?? '' }}"/>
                                                    
                                                    @include('seller.stores.partials.operating-hours-accordion', ['store' => $store])

                                                    <div class="mb-3">
                                                        <label
                                                            class="form-label required">{{ __('labels.order_preparation_time') }}</label>
                                                        <input type="number" min="0" class="form-control"
                                                               name="order_preparation_time"
                                                               placeholder="{{ __('labels.enter_order_preparation_time') }}"
                                                               value="{{ old('order_preparation_time', $store->order_preparation_time ?? '') }}"/>
                                                    </div>

                                                    <div class="mb-3">
                                                        <label
                                                            class="form-label required">{{ __('labels.store_status') }}</label>
                                                        <select class="form-select text-capitalize" name="status" required>
                                                            <option value="">{{ __('labels.select_status') }}</option>
                                                            @foreach(StoreStatusEnum::values() as $status)
                                                                <option
                                                                    value="{{$status}}" {{ $store->status == $status ? 'selected' : '' }}>
                                                                    {{$status}}
                                                                </option>
                                                            @endforeach
                                                        </select>
                                                        <div
                                                            class="form-text">{{ __('labels.store_status_help') }}</div>
                                                    </div>
                                                </div>
                                            </div>
                                            
                                            <!-- Scheduled Delivery & Slots Component -->
                                            @if ($isScheduledDeliveryEnabled)
                                                @include('seller.stores.partials.scheduled-delivery-card', ['store' => $store])
                                            @endif

                                            <!-- Self-Pickup Settings -->
                                            @if (\App\Models\Setting::isPickupEnabled())
                                                <div class="card mb-4" id="pills-pickup-cod">
                                                    <div class="card-header">
                                                        <h4 class="card-title">
                                                            <i class="ti ti-shopping-cart-discount me-2 text-primary"></i>{{ __('labels.pickup_settings') ?? 'Self-Pickup Settings' }}
                                                        </h4>
                                                    </div>
                                                    <div class="card-body">
                                                        <!-- Allows Pickup Switch -->
                                                        <div class="mb-3">
                                                            <label class="form-check form-switch mb-0">
                                                                <input type="hidden" name="allows_pickup" value="0">
                                                                <input class="form-check-input" type="checkbox" role="switch" id="allows_pickup_checkbox"
                                                                       name="allows_pickup" value="1" {{ old('allows_pickup', $store->allows_pickup ?? false) ? 'checked' : '' }}>
                                                                <span class="form-check-label fw-bold">{{ __('labels.allow_pickup') ?? 'Allow Self-Pickup' }}</span>
                                                            </label>
                                                            <div class="form-text mt-1">
                                                                {{ __('messages.allow_pickup_help') ?? 'Enable self-pickup option for your customers.' }}
                                                            </div>
                                                        </div>

                                                        <!-- Sub settings (hidden if pickup is disabled) -->
                                                        <div id="pickup-sub-settings" style="display: {{ old('allows_pickup', $store->allows_pickup ?? false) ? 'block' : 'none' }};">
                                                            <hr class="my-3" />
                                                            <!-- COD for Pickup -->
                                                            <div class="mb-3">
                                                                <label class="form-check form-switch mb-0">
                                                                    <input type="hidden" name="is_pickup_cod_enabled" value="0">
                                                                    <input class="form-check-input" type="checkbox" role="switch" id="is_pickup_cod_enabled"
                                                                           name="is_pickup_cod_enabled" value="1" {{ old('is_pickup_cod_enabled', $store->is_pickup_cod_enabled ?? false) ? 'checked' : '' }}>
                                                                    <span class="form-check-label fw-bold">{{ __('labels.enable_pickup_cod') ?? 'Enable Cash on Delivery (COD) for Self-Pickup' }}</span>
                                                                </label>
                                                                <div class="form-text mt-1">
                                                                    {{ __('messages.pickup_cod_help') ?? 'Allow customers to pay using Cash on Delivery (COD) when they choose self-pickup for their orders.' }}
                                                                </div>
                                                            </div>

                                                            <!-- Pickup Instructions -->
                                                            <div class="mb-3">
                                                                <label class="form-label fw-bold">{{ __('labels.pickup_instructions') ?? 'Pickup Instructions' }}</label>
                                                                <textarea class="form-control" name="pickup_instructions" rows="3" placeholder="{{ __('labels.enter_pickup_instructions') ?? 'Enter pickup instructions, e.g., location in store, open hours, etc.' }}">{{ old('pickup_instructions', $store->pickup_instructions ?? '') }}</textarea>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                            @else
                                                <input type="hidden" name="allows_pickup" value="{{ $store->allows_pickup ? '1' : '0' }}">
                                                <input type="hidden" name="is_pickup_cod_enabled" value="{{ $store->is_pickup_cod_enabled ? '1' : '0' }}">
                                                <input type="hidden" name="pickup_instructions" value="{{ $store->pickup_instructions ?? '' }}">
                                            @endif
                                        </div>
                                        {{--                                        <div class="col-md-6">--}}

                                        {{--                                            <!-- Delivery Settings -->--}}
                                        {{--                                            <div class="card mb-4" id="pills-delivery">--}}
                                        {{--                                                <div class="card-header">--}}
                                        {{--                                                    <h4 class="card-title">{{ __('labels.delivery_settings') }}</h4>--}}
                                        {{--                                                </div>--}}
                                        {{--                                                <div class="card-body">--}}
                                        {{--                                                    <div class="mb-3">--}}
                                        {{--                                                        <label--}}
                                        {{--                                                            class="form-label required">{{ __('labels.max_delivery_distance') }}</label>--}}
                                        {{--                                                        <input type="number"  min="0" class="form-control"--}}
                                        {{--                                                               name="max_delivery_distance"--}}
                                        {{--                                                               placeholder="{{ __('labels.enter_max_delivery_distance') }}"--}}
                                        {{--                                                               value="{{ old('max_delivery_distance', $store->max_delivery_distance ?? '') }}"/>--}}
                                        {{--                                                    </div>--}}
                                        {{--                                                    <div class="mb-3">--}}
                                        {{--                                                        <label--}}
                                        {{--                                                            class="form-label required">{{ __('labels.domestic_shipping_charges') }}</label>--}}
                                        {{--                                                        <input type="number"  min="0" step="0.01" class="form-control"--}}
                                        {{--                                                               name="domestic_shipping_charges"--}}
                                        {{--                                                               placeholder="{{ __('labels.enter_domestic_shipping_charges') }}"--}}
                                        {{--                                                               value="{{ old('domestic_shipping_charges', $store->domestic_shipping_charges ?? '') }}"/>--}}
                                        {{--                                                    </div>--}}
                                        {{--                                                    <div class="mb-3">--}}
                                        {{--                                                        <label--}}
                                        {{--                                                            class="form-label required">{{ __('labels.international_shipping_charges') }}</label>--}}
                                        {{--                                                        <input type="number"  min="0" step="0.01" class="form-control"--}}
                                        {{--                                                               name="international_shipping_charges"--}}
                                        {{--                                                               placeholder="{{ __('labels.enter_international_shipping_charges') }}"--}}
                                        {{--                                                               value="{{ old('international_shipping_charges', $store->international_shipping_charges ?? '') }}"/>--}}
                                        {{--                                                    </div>--}}
                                        {{--                                                </div>--}}
                                        {{--                                            </div>--}}
                                        {{--                                        </div>--}}
                                    </div>
                                    <!-- Store Information -->
                                    <div class="card mb-4" id="pills-store">
                                        <div class="card-header">
                                            <h4 class="card-title">{{ __('labels.store_information') }}</h4>
                                        </div>
                                        <div class="card-body">
                                            <div class="mb-3">
                                                <label class="form-label">{{ __('labels.description') }}</label>
                                                <textarea class="hugerte-mytextarea"
                                                          name="description">{{ old('description', $store->description ?? '') }}</textarea>
                                            </div>
                                            <div class="mb-3">
                                                <label class="form-label">{{ __('labels.about_us') }}</label>
                                                <textarea class="hugerte-mytextarea" s
                                                          name="about_us">{{ old('about_us', $store->about_us ?? '') }}</textarea>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Policies -->
                                    <div class="card mb-4" id="pills-policies">
                                        <div class="card-header">
                                            <h4 class="card-title">{{ __('labels.policies') }}</h4>
                                        </div>
                                        <div class="card-body">
                                            <div class="row">
                                                <div class="col-md-6">
                                                    <div class="mb-3">
                                                        <label
                                                            class="form-label">{{ __('labels.promotional_text') }}</label>
                                                        <textarea class="hugerte-mytextarea" name="promotional_text"
                                                                  placeholder="{{ __('labels.enter_promotional_text') }}">{{ old('promotional_text', $store->promotional_text ?? '') }}</textarea>
                                                    </div>
                                                </div>
                                                <div class="col-md-6">
                                                    <div class="mb-3">
                                                        <label
                                                            class="form-label required">{{ __('labels.return_replacement_policy') }}</label>
                                                        <textarea class="hugerte-mytextarea"
                                                                  name="return_replacement_policy"
                                                                  placeholder="{{ __('labels.enter_return_replacement_policy') }}">{{ old('return_replacement_policy', $store->return_replacement_policy ?? '') }}</textarea>
                                                    </div>
                                                </div>
                                            </div>
                                            <div class="row">
                                                <div class="col-md-6">
                                                    <div class="mb-3">
                                                        <label
                                                            class="form-label required">{{ __('labels.refund_policy') }}</label>
                                                        <textarea class="hugerte-mytextarea" name="refund_policy"
                                                                  placeholder="{{ __('labels.enter_refund_policy') }}">{{ old('refund_policy', $store->refund_policy ?? '') }}</textarea>
                                                    </div>
                                                </div>
                                                <div class="col-md-6">
                                                    <div class="mb-3">
                                                        <label
                                                            class="form-label required">{{ __('labels.terms_and_conditions') }}</label>
                                                        <textarea class="hugerte-mytextarea" name="terms_and_conditions"
                                                                  placeholder="{{ __('labels.enter_terms_and_conditions') }}">{{ old('terms_and_conditions', $store->terms_and_conditions ?? '') }}</textarea>
                                                    </div>
                                                </div>
                                            </div>

                                            <div class="mb-3">
                                                <label
                                                    class="form-label required">{{ __('labels.delivery_policy') }}</label>
                                                <textarea class="hugerte-mytextarea" name="delivery_policy"
                                                          placeholder="{{ __('labels.enter_delivery_policy') }}">{{ old('delivery_policy', $store->delivery_policy ?? '') }}</textarea>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Metadata -->
                                    <div class="card mb-4" id="pills-metadata">
                                        <div class="card-header">
                                            <h4 class="card-title">{{ __('labels.status_and_metadata') }}</h4>
                                        </div>
                                        <div class="card-body">
                                            <x-seo-fields :metadata="$store->metadata ?? null"/>
                                        </div>
                                    </div>

                                    <!-- POS Settings -->
                                    <div class="card mb-4" id="pills-pos">
                                        <div class="card-header">
                                            <h3 class="card-title">{{ __('labels.pos_store_settings') }}</h3>
                                        </div>
                                        <div class="card-body">

                                            @php
                                                $pmtCfg = old('pos_payment_config', $store->pos_payment_config ?? []);
                                                $customMethods = $pmtCfg['custom_methods'] ?? [];
                                            @endphp

                                            <!-- Payment Methods -->
                                            <h4 class="mb-3">{{ __('labels.pos_payment_methods') }}</h4>
                                            <div class="row g-3 mb-4">
                                                <div class="col-md-6">
                                                    <label class="form-check form-switch">
                                                        <input type="hidden" name="pos_payment_config[cash]" value="0">
                                                        <input class="form-check-input" type="checkbox" name="pos_payment_config[cash]" value="1"
                                                            {{ filter_var($pmtCfg['cash'] ?? true, FILTER_VALIDATE_BOOLEAN) ? 'checked' : '' }}>
                                                        <span class="form-check-label">{{ __('labels.pos_pm_cash') }}</span>
                                                    </label>
                                                </div>
                                                <div class="col-md-6">
                                                    <label class="form-check form-switch">
                                                        <input type="hidden" name="pos_payment_config[upi]" value="0">
                                                        <input class="form-check-input" type="checkbox" name="pos_payment_config[upi]" value="1"
                                                            {{ filter_var($pmtCfg['upi'] ?? false, FILTER_VALIDATE_BOOLEAN) ? 'checked' : '' }}>
                                                        <span class="form-check-label">{{ __('labels.pos_pm_upi') }} <small class="text-muted">(India)</small></span>
                                                    </label>
                                                </div>
                                                <div class="col-md-6">
                                                    <label class="form-check form-switch">
                                                        <input type="hidden" name="pos_payment_config[online_qr]" value="0">
                                                        <input class="form-check-input" type="checkbox" name="pos_payment_config[online_qr]" value="1"
                                                            {{ filter_var($pmtCfg['online_qr'] ?? false, FILTER_VALIDATE_BOOLEAN) ? 'checked' : '' }}>
                                                        <span class="form-check-label">{{ __('labels.pos_pm_online_qr') }}</span>
                                                    </label>
                                                </div>
                                                <div class="col-md-6">
                                                    <label class="form-check form-switch">
                                                        <input type="hidden" name="pos_payment_config[split]" value="0">
                                                        <input class="form-check-input" type="checkbox" name="pos_payment_config[split]" value="1"
                                                            {{ filter_var($pmtCfg['split'] ?? false, FILTER_VALIDATE_BOOLEAN) ? 'checked' : '' }}>
                                                        <span class="form-check-label">{{ __('labels.pos_pm_split') }}</span>
                                                    </label>
                                                </div>
                                            </div>

                                            <!-- Custom Payment Methods -->
                                            <h4 class="mb-2">
                                                {{ __('labels.pos_custom_payment_methods') }}
                                                <x-form-info-tooltip :title="__('labels.pos_custom_payment_methods_tooltip')" />
                                            </h4>
                                            <p class="text-muted small mb-3">{{ __('labels.pos_custom_payment_methods_hint') }}</p>

                                            <div id="pos-custom-methods-list">
                                                @forelse($customMethods as $i => $cm)
                                                    <div class="card card-body mb-2 pos-custom-method-row">
                                                        <div class="row g-2 align-items-start">
                                                            <div class="col-auto">
                                                                <label class="form-label small">{{ __('labels.pos_cm_icon') }}</label>
                                                                <input type="text" class="form-control text-center" style="width:60px"
                                                                       name="pos_payment_config[custom_methods][{{ $i }}][icon]"
                                                                       value="{{ $cm['icon'] ?? '💳' }}" maxlength="4">
                                                            </div>
                                                            <div class="col">
                                                                <label class="form-label small">{{ __('labels.pos_cm_name') }} <span class="text-danger">*</span></label>
                                                                <input type="text" class="form-control"
                                                                       name="pos_payment_config[custom_methods][{{ $i }}][name]"
                                                                       value="{{ $cm['name'] ?? '' }}" maxlength="50" required placeholder="{{ __('labels.pos_cm_name_placeholder') }}">
                                                            </div>
                                                            <div class="col">
                                                                <label class="form-label small">{{ __('labels.pos_cm_instructions') }}</label>
                                                                <input type="text" class="form-control"
                                                                       name="pos_payment_config[custom_methods][{{ $i }}][instructions]"
                                                                       value="{{ $cm['instructions'] ?? '' }}" maxlength="250" placeholder="{{ __('labels.pos_cm_instructions_placeholder') }}">
                                                            </div>
                                                            <div class="col-auto d-flex align-items-end gap-2" style="padding-bottom:2px">
                                                                <label class="form-check form-switch mb-0" title="{{ __('labels.enabled') }}">
                                                                    <input type="hidden" name="pos_payment_config[custom_methods][{{ $i }}][enabled]" value="0">
                                                                    <input class="form-check-input" type="checkbox"
                                                                           name="pos_payment_config[custom_methods][{{ $i }}][enabled]" value="1"
                                                                        {{ filter_var($cm['enabled'] ?? true, FILTER_VALIDATE_BOOLEAN) ? 'checked' : '' }}>
                                                                </label>
                                                                <button type="button" class="btn btn-sm btn-outline-danger pos-remove-custom-method" title="{{ __('labels.remove') }}">
                                                                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none"
                                                                         stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                                                        <line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/>
                                                                    </svg>
                                                                </button>
                                                            </div>
                                                        </div>
                                                    </div>
                                                @empty
                                                @endforelse
                                            </div>
                                            <button type="button" class="btn btn-sm btn-outline-primary mb-4" id="pos-add-custom-method"
                                                data-label-icon="{{ __('labels.pos_cm_icon') }}"
                                                data-label-name="{{ __('labels.pos_cm_name') }}"
                                                data-label-name-placeholder="{{ __('labels.pos_cm_name_placeholder') }}"
                                                data-label-instructions="{{ __('labels.pos_cm_instructions') }}"
                                                data-label-instructions-placeholder="{{ __('labels.pos_cm_instructions_placeholder') }}">
                                                + {{ __('labels.pos_add_custom_method') }}
                                            </button>

                                            <hr class="my-3">

                                            <!-- UPI VPA -->
                                            <h4 class="mb-3">{{ __('labels.pos_upi_settings') }}</h4>
                                            <div class="mb-3">
                                                <label class="form-label">
                                                    {{ __('labels.pos_upi_vpa') }}
                                                    <x-form-info-tooltip :title="__('labels.pos_upi_vpa_tooltip')" />
                                                </label>
                                                <input type="text" class="form-control" name="pos_upi_vpa"
                                                       value="{{ old('pos_upi_vpa', $store->pos_upi_vpa) }}"
                                                       placeholder="merchant@upi">
                                            </div>

                                            <!-- UPI Payee Name -->
                                            <div class="mb-3">
                                                <label class="form-label">
                                                    {{ __('labels.pos_upi_payee_name') }}
                                                    <x-form-info-tooltip :title="__('labels.pos_upi_payee_name_tooltip')" />
                                                </label>
                                                <input type="text" class="form-control" name="pos_upi_payee_name"
                                                       value="{{ old('pos_upi_payee_name', $store->pos_upi_payee_name) }}"
                                                       placeholder="{{ $store->name }}">
                                            </div>

                                            <hr class="my-3">

                                            <!-- Receipt Footer Note -->
                                            <h4 class="mb-3">{{ __('labels.pos_receipt_settings') }}</h4>
                                            <div class="mb-3">
                                                <label class="form-label">
                                                    {{ __('labels.pos_receipt_footer_note') }}
                                                    <x-form-info-tooltip :title="__('labels.pos_receipt_footer_note_tooltip')" />
                                                </label>
                                                <textarea class="form-control" name="receipt_template[footer_note]" rows="3"
                                                          placeholder="{{ __('labels.pos_receipt_footer_default') }}"
                                                >{{ old('receipt_template.footer_note', $store->receipt_template['footer_note'] ?? '') }}</textarea>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="card-footer text-end">
                                        <div class="d-flex">
                                            <button type="submit"
                                                    class="btn btn-primary ms-auto">Submit
                                            </button>
                                        </div>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection

@push('styles')
    <link rel="stylesheet" href="{{ asset('assets/css/seller-scheduled-delivery.css') }}">
@endpush

@push('scripts')
    <script src="{{ asset('assets/js/store-configuration.js') }}" defer></script>
    <script src="{{ asset('assets/js/seller-scheduled-delivery.js') }}" defer></script>
@endpush
