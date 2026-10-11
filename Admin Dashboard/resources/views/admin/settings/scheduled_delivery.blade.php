@extends('layouts.admin.app', ['page' => $menuAdmin['settings']['active'] ?? "", 'sub_page' => ($menuAdmin['settings']['route']['scheduled_delivery'] ?? [])['sub_active'] ?? "" ])

@section('title', __('labels.scheduled_delivery_title') ?? 'Scheduled Delivery Settings')

@section('header_data')
    @php
        $page_title = __('labels.scheduled_delivery_title') ?? 'Scheduled Delivery Settings';
        $page_pretitle = __('labels.admin') . " " . __('labels.settings');
    @endphp
@endsection

@php
    $breadcrumbs = [
        ['title' => __('labels.home'), 'url' => route('admin.dashboard')],
        ['title' => __('labels.settings'), 'url' => route('admin.settings.index')],
        ['title' => __('labels.scheduled_delivery_title') ?? 'Scheduled Delivery Settings', 'url' => null],
    ];
@endphp

@section('admin-content')
    <!-- Header layout title -->
    <div class="page-header d-print-none">
        <div class="row g-2 align-items-center">
            <div class="col">
                <h2 class="page-title">{{ __('labels.scheduled_delivery_title') ?? 'Scheduled Delivery Settings' }}</h2>
                <x-breadcrumb :items="$breadcrumbs"/>
            </div>
        </div>
    </div>
    <!-- Settings Form and Navigation -->
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
                            <!-- Action routes to admin.settings.store with csrf token -->
                            <form action="{{ route('admin.settings.store') }}" class="form-submit" method="post" id="scheduled-delivery-settings-form">
                                @csrf
                                <input type="hidden" name="type" value="scheduled_delivery">
                                <div class="card mb-4" id="pills-general">
                                    <div class="card-header">
                                        <h4 class="card-title">{{ __('labels.scheduled_delivery_settings') }}</h4>
                                    </div>
                                    <div class="card-body">
                                        <!-- Global Availability Toggle Switch -->
                                        <div class="mb-3">
                                            <label class="row">
                                                <span class="col">{{ __('labels.enable_scheduled_delivery') }}</span>
                                                <span class="col-auto">
                                                    <label class="form-check form-check-single form-switch">
                                                        <!-- Uses id for javascript reference to dynamic show/hide below days limit -->
                                                        <input class="form-check-input" type="checkbox" id="global-scheduled-delivery-toggle"
                                                            name="enableScheduledDelivery" value="1" {{ !isset($settings['enableScheduledDelivery']) || $settings['enableScheduledDelivery'] ? 'checked' : '' }} />
                                                    </label>
                                                </span>
                                            </label>
                                        </div>
                                        <!-- Max Days Input (Hidden if toggle is unchecked) -->
                                        <div class="mb-3" id="max-advance-booking-days-container" style="transition: all 0.3s ease;">
                                            <label class="form-label required">{{ __('labels.max_advance_booking_days_limit') }}</label>
                                            <input type="number" min="1" class="form-control"
                                                name="maxAdvanceBookingDaysLimit"
                                                placeholder="{{ __('labels.max_advance_booking_days_limit_placeholder') }}"
                                                value="{{ $settings['maxAdvanceBookingDaysLimit'] ?? '7' }}" />
                                        </div>
                                    </div>
                                </div>
                                <!-- Submit actions card footer -->
                                <div class="card-footer text-end">
                                    <div class="d-flex">
                                        @can('updateSetting', [\App\Models\Setting::class, 'scheduled_delivery'])
                                            <button type="submit" class="btn btn-primary ms-auto">{{ __('labels.submit') }}</button>
                                        @endcan
                                    </div>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection

@push('js')
    <!-- Separate JS script containing toggle interactions -->
    <script src="{{ asset('assets/js/admin-scheduled-delivery-settings.js') }}"></script>
@endpush
