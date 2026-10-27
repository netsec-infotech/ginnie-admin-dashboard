@extends('layouts.admin.app', ['page' => $menuAdmin['design_appearance']['active'] ?? 'design_appearance', 'sub_page' => $menuAdmin['design_appearance']['route']['onboarding_screens']['sub_active'] ?? 'onboarding-screens'])

@php
    $isEdit = isset($onboardingScreen);
    $selectedApp = old('app_type', request('app_type', $onboardingScreen->app_type ?? \App\Enums\OnboardingAppTypeEnum::CUSTOMER()));
    $breadcrumbs = [
        ['title' => __('labels.home'), 'url' => route('admin.dashboard')],
        ['title' => __('labels.onboarding_screens'), 'url' => route('admin.onboarding-screens.index', ['app_type' => $selectedApp])],
        ['title' => $isEdit ? __('labels.edit_onboarding_screen') : __('labels.create_onboarding_screen'), 'url' => null],
    ];
@endphp

@section('title', $isEdit ? __('labels.edit_onboarding_screen') : __('labels.create_onboarding_screen'))

@section('header_data')
    @php
        $page_title = $isEdit ? __('labels.edit_onboarding_screen') : __('labels.create_onboarding_screen');
        $page_pretitle = __('labels.admin') . ' ' . __('labels.management');
    @endphp
@endsection

@push('styles')
    <link rel="stylesheet" href="{{ hyperAsset('assets/css/onboarding-screens.css') }}">
@endpush

@section('admin-content')
    <div class="page-header d-print-none">
        <div class="row g-2 align-items-center">
            <div class="col">
                <h2 class="page-title">{{ $isEdit ? __('labels.edit_onboarding_screen') : __('labels.create_onboarding_screen') }}</h2>
                <x-breadcrumb :items="$breadcrumbs" />
            </div>
            <div class="col-auto ms-auto d-print-none">
                <a href="{{ route('admin.onboarding-screens.index', ['app_type' => $selectedApp]) }}" class="btn btn-outline-secondary">
                    <i class="ti ti-arrow-left"></i>
                    {{ __('labels.back_to_list') }}
                </a>
            </div>
        </div>
    </div>

    <div class="page-body">
        <div class="row row-cards onboarding-form-page">
            <div class="col-lg-7">
                <form class="form-submit"
                    action="{{ $isEdit ? route('admin.onboarding-screens.update', $onboardingScreen) : route('admin.onboarding-screens.store') }}"
                    method="POST"
                    enctype="multipart/form-data">
                    @csrf
                    <div class="card">
                        <div class="card-header">
                            <h3 class="card-title">{{ __('labels.onboarding_screen_information') }}</h3>
                        </div>
                        <div class="card-body">
                            <div class="mb-3">
                                <label class="form-label required">{{ __('labels.app_type') }}</label>
                                <select name="app_type" class="form-select" data-onboarding-preview-app>
                                    @foreach ($appTypes as $appType)
                                        <option value="{{ $appType->value }}" @selected($selectedApp === $appType->value)>
                                            {{ $appType->label() }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>

                            <div class="mb-3">
                                <label class="form-label">{{ __('labels.title') }}</label>
                                <input type="text" class="form-control" name="title"
                                    value="{{ old('title', $onboardingScreen->title ?? '') }}"
                                    maxlength="30"
                                    placeholder="{{ __('labels.enter_title') }}"
                                    data-onboarding-preview-title
                                    data-onboarding-character-field
                                    data-onboarding-character-max="30">
                                <div class="d-flex justify-content-between mt-1 small">
                                    <span class="text-danger d-none" data-onboarding-character-message></span>
                                    <span class="text-secondary ms-auto" data-onboarding-character-counter>0/30</span>
                                </div>
                            </div>

                            <div class="mb-3">
                                <label class="form-label">{{ __('labels.subtitle') }}</label>
                                <textarea class="form-control" name="subtitle" rows="4"
                                    maxlength="70"
                                    placeholder="{{ __('labels.enter_subtitle') }}"
                                    data-onboarding-preview-subtitle
                                    data-onboarding-character-field
                                    data-onboarding-character-max="70">{{ old('subtitle', $onboardingScreen->subtitle ?? '') }}</textarea>
                                <div class="d-flex justify-content-between mt-1 small">
                                    <span class="text-danger d-none" data-onboarding-character-message></span>
                                    <span class="text-secondary ms-auto" data-onboarding-character-counter>0/70</span>
                                </div>
                            </div>

                        </div>
                        <div class="card-footer text-end">
                            <div class="d-flex">
                                <a href="{{ route('admin.onboarding-screens.index', ['app_type' => $selectedApp]) }}" class="btn btn-link">
                                    {{ __('labels.cancel') }}
                                </a>
                                <button type="submit" class="btn btn-primary ms-auto">
                                    {{ $isEdit ? __('labels.update') : __('labels.create') }}
                                </button>
                            </div>
                        </div>
                    </div>
                </form>
            </div>
            <div class="col-lg-5">
                @include('admin.onboarding-screens.partials.preview', [
                    'screen' => $onboardingScreen ?? null,
                    'selectedApp' => $selectedApp,
                ])
            </div>
        </div>
    </div>
@endsection

@push('scripts')
    <script>
        window.onboardingPreviewFallback = {
            title: @json(__('labels.no_onboarding_preview_title')),
            subtitle: @json(__('labels.no_onboarding_preview_subtitle')),
        };
        window.onboardingScreenLabels = Object.assign(window.onboardingScreenLabels || {}, {
            characterLimitMessage: @json(__('labels.character_limit_message')),
        });
    </script>
    <script src="{{ hyperAsset('assets/js/onboarding-screens.js') }}" defer></script>
@endpush
