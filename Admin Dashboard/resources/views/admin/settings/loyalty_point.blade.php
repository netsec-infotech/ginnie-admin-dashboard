@extends('layouts.admin.app', ['page' => $menuAdmin['settings']['active'] ?? '', 'sub_page' => ($menuAdmin['settings']['route']['loyalty_point'] ?? [])['sub_active'] ?? ''])

@section('title', __('labels.loyalty_points_settings') ?? 'Loyalty Points Settings')

@section('header_data')
    @php
        $page_title = __('labels.loyalty_points_settings') ?? 'Loyalty Points Settings';
        $page_pretitle = __('labels.admin') . ' ' . __('labels.settings');
    @endphp
@endsection

@php
    $breadcrumbs = [
        ['title' => __('labels.home'), 'url' => route('admin.dashboard')],
        ['title' => __('labels.settings'), 'url' => route('admin.settings.index')],
        ['title' => __('labels.loyalty_points_settings') ?? 'Loyalty Points Settings', 'url' => null],
    ];
@endphp

@section('admin-content')
    <div class="page-header d-print-none">
        <div class="row g-2 align-items-center">
            <div class="col">
                <h2 class="page-title">{{ __('labels.loyalty_points_settings') ?? 'Loyalty Points Settings' }}</h2>
                <x-breadcrumb :items="$breadcrumbs" />
            </div>
        </div>
    </div>
    <div class="page-body">
        <div class="container-xl">
            <div class="row g-5">
                <div class="col-sm-2 d-none d-lg-block">
                    <div class="sticky-top">
                        <h3>{{ __('labels.menu') }}</h3>
                        <nav class="nav nav-vertical nav-pills" id="pills">
                            <a class="nav-link active" href="#pills-general">{{ __('labels.general') ?? 'General' }}</a>
                        </nav>
                    </div>
                </div>
                <div class="col-sm" data-bs-spy="scroll" data-bs-target="#pills" data-bs-offset="0">
                    <div class="row row-cards">
                        <div class="col-12">
                            <form action="{{ route('admin.settings.store') }}" class="form-submit" method="post"
                                id="loyalty-point-settings-form">
                                @csrf
                                <input type="hidden" name="type" value="loyalty_point">
                                <div class="card mb-4" id="pills-general">
                                    <div class="card-header">
                                        <h4 class="card-title">
                                            {{ __('labels.loyalty_points_settings') ?? 'Loyalty Points Settings' }}</h4>
                                    </div>
                                    <div class="card-body">
                                        <div class="mb-3">
                                            <label class="form-check form-switch">
                                                <input type="hidden" name="is_enabled" value="0">
                                                <input class="form-check-input" type="checkbox" name="is_enabled"
                                                    value="1" {{ !empty($settings['is_enabled']) ? 'checked' : '' }}>
                                                <span
                                                    class="form-check-label">{{ __('labels.enable_loyalty_points') ?? 'Enable Loyalty Points Program' }}</span>
                                            </label>
                                            <span class="text-muted d-block mt-1">
                                                {{ __('labels.enable_loyalty_points_description') ?? 'Allow customers to earn and redeem loyalty points on their orders.' }}
                                            </span>
                                        </div>

                                        <div class="row">
                                            <div class="col-md-6 mb-3">
                                                <label
                                                    class="form-label required">{{ __('labels.point_rate') ?? 'Point Rate (Order Amount per 1 Point)' }}</label>
                                                <input type="number" step="0.01" min="0.01" class="form-control"
                                                    name="point_rate" value="{{ $settings['point_rate'] ?? 100.0 }}"
                                                    required>
                                                <small
                                                    class="form-hint">{{ __('labels.point_rate_hint') ?? 'Example: 100 means spending 100 currency units earns 1 point.' }}</small>
                                            </div>

                                            <div class="col-md-6 mb-3">
                                                <label
                                                    class="form-label required">{{ __('labels.redemption_value') ?? 'Redemption Value (Value per 1 Point)' }}</label>
                                                <input type="number" step="0.01" min="0.01" class="form-control"
                                                    id="redemption_value_input"
                                                    name="redemption_value" value="{{ $settings['redemption_value'] ?? 1.0 }}"
                                                    required>
                                                <small
                                                    class="form-hint">{{ __('labels.redemption_value_hint') ?? 'Amount deducted per point redeemed (e.g. 0.50 means 1 point = 0.50 discount).' }}</small>

                                                <div id="redemption-live-preview" class="alert alert-info py-2 mt-2 mb-0" style="font-size: 0.875rem;">
                                                    <i class="ti ti-calculator me-1"></i>
                                                    <strong>Live Demo:</strong> <span id="preview-text">1 Point = 1.00 Deduction | 5 Points = 5.00 Deduction</span>
                                                </div>
                                            </div>

                                            <div class="col-md-6 mb-3">
                                                <label
                                                    class="form-label required">{{ __('labels.max_redemption_percentage') ?? 'Max Redemption Cap (%)' }}</label>
                                                <input type="number" step="0.1" min="0" max="100"
                                                    class="form-control" name="max_redemption_percentage"
                                                    value="{{ $settings['max_redemption_percentage'] ?? 10.0 }}" required>
                                                <small
                                                    class="form-hint">{{ __('labels.max_redemption_percentage_hint') ?? 'Maximum percentage of order total that can be paid using points (e.g. 10%).' }}</small>
                                            </div>

                                            <div class="col-md-6 mb-3">
                                                <label
                                                    class="form-label required">{{ __('labels.min_order_value_to_redeem') ?? 'Minimum Order Value to Redeem' }}</label>
                                                <input type="number" step="0.01" min="0" class="form-control"
                                                    name="min_order_value_to_redeem"
                                                    value="{{ $settings['min_order_value_to_redeem'] ?? 0.0 }}" required>
                                                <small
                                                    class="form-hint">{{ __('labels.min_order_value_to_redeem_hint') ?? 'Minimum order subtotal required before points redemption is allowed.' }}</small>
                                            </div>

                                            <div class="col-md-6 mb-3">
                                                <label
                                                    class="form-label required">{{ __('labels.expiry_days') ?? 'Point Expiry Limit (Days)' }}</label>
                                                <input type="number" step="1" min="0" class="form-control"
                                                    name="expiry_days" value="{{ $settings['expiry_days'] ?? 0 }}" required>
                                                <small
                                                    class="form-hint">{{ __('labels.expiry_days_hint') ?? 'Number of days after which earned points will expire. Set to 0 for no expiry.' }}</small>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="card-footer text-end">
                                        <button type="submit" class="btn btn-primary">{{ __('labels.submit') }}</button>
                                    </div>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function () {
            var redemptionInput = document.getElementById('redemption_value_input');
            var previewText = document.getElementById('preview-text');

            function updateLivePreview() {
                var rate = parseFloat(redemptionInput.value) || 0;
                var sample5 = (5 * rate).toFixed(2);
                previewText.textContent = '1 Point = ' + rate.toFixed(2) + ' Deduction | 5 Points = ' + sample5 + ' Deduction (e.g. 100 Item Cost with 5 Points = ' + (100 - parseFloat(sample5)).toFixed(2) + ')';
            }

            if (redemptionInput) {
                redemptionInput.addEventListener('input', updateLivePreview);
                updateLivePreview();
            }

            var form = document.getElementById('loyalty-point-settings-form');
            if (form) {
                form.addEventListener('submit', function (e) {
                    var redemptionInput = form.querySelector('input[name="redemption_value"]');
                    var capInput = form.querySelector('input[name="max_redemption_percentage"]');
                    var minOrderInput = form.querySelector('input[name="min_order_value_to_redeem"]');

                    var redemptionVal = parseFloat(redemptionInput ? redemptionInput.value : 0) || 0;
                    var maxCap = parseFloat(capInput ? capInput.value : 0) || 0;
                    var minOrder = parseFloat(minOrderInput ? minOrderInput.value : 0) || 0;

                    if (redemptionVal > 0 && maxCap > 0) {
                        var requiredMinOrder = redemptionVal / (maxCap / 100);
                        if (minOrder < (requiredMinOrder - 0.0001)) {
                            e.preventDefault();
                            e.stopImmediatePropagation();

                            var formattedRequired = Number.isInteger(requiredMinOrder) ? requiredMinOrder : parseFloat(requiredMinOrder.toFixed(2));
                            var formattedRedemption = Number.isInteger(redemptionVal) ? redemptionVal : redemptionVal;
                            var formattedCap = Number.isInteger(maxCap) ? maxCap : maxCap;

                            var msg = 'Minimum Order Value of ' + formattedRequired + ' is required if you want to keep ' + formattedRedemption + ' Points value for ' + formattedCap + '%';

                            if (typeof Toast !== 'undefined') {
                                Toast.fire({
                                    icon: 'error',
                                    title: msg
                                });
                            } else {
                                alert(msg);
                            }
                            return false;
                        }
                    }
                }, true);
            }
        });
    </script>
@endsection
