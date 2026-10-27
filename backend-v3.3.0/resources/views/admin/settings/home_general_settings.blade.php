@php use Illuminate\Support\Str; @endphp
@extends('layouts.admin.app', ['page' => $menuAdmin['settings']['active'] ?? '', 'sub_page' => $menuAdmin['settings']['route']['home_general_settings']['sub_active'] ?? ''])

@section('title', __('labels.home_general_settings'))

@section('header_data')
    @php
        $page_title = __('labels.home_general_settings');
        $page_pretitle = __('labels.admin') . ' ' . __('labels.settings');
    @endphp
@endsection

@php
    $breadcrumbs = [
        ['title' => __('labels.home'), 'url' => route('admin.dashboard')],
        ['title' => __('labels.settings'), 'url' => route('admin.settings.index')],
        ['title' => __('labels.home_general_settings'), 'url' => null],
    ];
@endphp

@section('admin-content')
    <div class="page-header d-print-none">
        <div class="row g-2 align-items-center">
            <div class="col">
                <h2 class="page-title">{{ __('labels.home_general_settings') }}</h2>
                <x-breadcrumb :items="$breadcrumbs" />
            </div>
        </div>
    </div>
    <!-- BEGIN PAGE BODY -->
    <div class="page-body">
        <div class="container-xl">
            <div class="row g-5">
                <div class="col-sm-2 d-none d-lg-block">
                    <div class="sticky-top">
                        <h3>{{ __('labels.menu') }}</h3>
                        <nav class="nav nav-vertical nav-pills" id="pills">
                            <a class="nav-link" href="#pills-general">{{ __('labels.general') }}</a>
                            <a class="nav-link" href="#pills-appearance">{{ __('labels.appearance') }}</a>
                        </nav>
                    </div>
                </div>
                <div class="col-sm" data-bs-spy="scroll" data-bs-target="#pills" data-bs-offset="0">
                    <div class="row row-cards">
                        <div class="col-12">
                            <form action="{{ route('admin.settings.store') }}" class="form-submit" method="post"
                                enctype="multipart/form-data">
                                @csrf
                                <input type="hidden" name="type" value="home_general_settings">

                                <div class="card mb-4" id="pills-general">
                                    <div class="card-header">
                                        <h4 class="card-title">{{ __('labels.general') }}</h4>
                                    </div>
                                    <div class="card-body">
                                        <div class="mb-3">
                                            <label class="form-label required">{{ __('labels.category_title') }}</label>
                                            <input type="text" class="form-control" name="title"
                                                placeholder="{{ __('labels.enter_category_title') }}"
                                                value="{{ $settings['title'] ?? 'All Categories' }}" />
                                            <small
                                                class="form-text text-muted">{{ __('labels.category_title_help') }}</small>
                                        </div>
                                        <div class="mb-3">
                                            <label class="form-label">{{ __('labels.search_labels') }}</label>
                                            <div class="input-group mb-2">
                                                <select class="form-select search-labels" name="searchLabels[]" multiple>
                                                    <option value="">{{ __('labels.eg_search_labels') }}</option>
                                                    @if (!empty($settings['searchLabels']))
                                                        @foreach ($settings['searchLabels'] as $label)
                                                            <option value="{{ $label }}" selected>
                                                                {{ Str::replace('_', ' ', $label) }}</option>
                                                        @endforeach
                                                    @endif
                                                </select>
                                                <button class="btn generate-search-labels-button" type="button"
                                                    title="generate search labels">
                                                    <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24"
                                                        viewBox="0 0 24 24" fill="none" stroke="currentColor"
                                                        stroke-width="2" stroke-linecap="round" stroke-linejoin="round"
                                                        class="icon icon-tabler icons-tabler-outline icon-tabler-sparkles m-0">
                                                        <path stroke="none" d="M0 0h24v24H0z" fill="none" />
                                                        <path
                                                            d="M16 18a2 2 0 0 1 2 2a2 2 0 0 1 2 -2a2 2 0 0 1 -2 -2a2 2 0 0 1 -2 2zm0 -12a2 2 0 0 1 2 2a2 2 0 0 1 2 -2a2 2 0 0 1 -2 -2a2 2 0 0 1 -2 2zm-7 12a6 6 0 0 1 6 -6a6 6 0 0 1 -6 -6a6 6 0 0 1 -6 6a6 6 0 0 1 6 6z" />
                                                    </svg>
                                                </button>
                                            </div>
                                            <small class="form-hint">{{ __('labels.search_labels_help') }}</small>
                                        </div>
                                    </div>
                                </div>

                                <div class="card mb-4" id="pills-appearance">
                                    <div class="card-header">
                                        <h4 class="card-title">{{ __('labels.appearance') }}</h4>
                                    </div>
                                    <div class="card-body">
                                        <div class="mb-3">
                                            <label class="form-label">{{ __('labels.background_type') }}</label>
                                            <select class="form-select" name="backgroundType" id="background-type-select">
                                                <option value="">{{ __('labels.select_background_type') }}</option>
                                                <option value="color"
                                                    {{ isset($settings['backgroundType']) && $settings['backgroundType'] === 'color' ? 'selected' : '' }}>
                                                    {{ __('labels.color') }}</option>
                                                <option value="image"
                                                    {{ isset($settings['backgroundType']) && $settings['backgroundType'] === 'image' ? 'selected' : '' }}>
                                                    {{ __('labels.image') }}</option>
                                                <option value="animation"
                                                    {{ isset($settings['backgroundType']) && $settings['backgroundType'] === 'animation' ? 'selected' : '' }}>
                                                    {{ __('labels.animation') ?? 'Animation' }}</option>
                                            </select>
                                            <small
                                                class="form-text text-muted">{{ __('labels.background_type_help') }}</small>
                                        </div>
                                        <div class="mb-3">
                                            <label class="form-label">{{ __('labels.icon') }}</label>
                                            <input type="file" class="form-control" id="icon-upload" name="icon"
                                                data-image-url="{{ $settings['icon'] ?? '' }}" />
                                            {{-- @if (!empty($settings['icon']))
                                                <div class="mt-1 small">
                                                    <span class="text-success"><i class="ti ti-check me-1"></i>Current File:</span>
                                                    <a href="{{ $settings['icon'] }}" target="_blank" class="text-decoration-underline">{{ basename(parse_url($settings['icon'], PHP_URL_PATH)) }}</a>
                                                </div>
                                            @endif --}}
                                        </div>

                                        <div class="mb-3">
                                            <label class="form-label">{{ __('labels.active_icon') }}</label>
                                            <input type="file" class="form-control" id="active-icon-upload"
                                                name="activeIcon" data-image-url="{{ $settings['activeIcon'] ?? '' }}" />
                                            {{-- @if (!empty($settings['activeIcon']))
                                                <div class="mt-1 small">
                                                    <span class="text-success"><i class="ti ti-check me-1"></i>Current File:</span>
                                                    <a href="{{ $settings['activeIcon'] }}" target="_blank" class="text-decoration-underline">{{ basename(parse_url($settings['activeIcon'], PHP_URL_PATH)) }}</a>
                                                </div>
                                            @endif --}}
                                        </div>
                                        {{-- Background Color (Web & App) --}}
                                        <div class="mb-3" id="background-color-field"
                                            style="{{ isset($settings['backgroundType']) && $settings['backgroundType'] === 'color' ? 'display: block;' : 'display: none;' }}">
                                            <div class="row g-3">
                                                <div class="col-md-6">
                                                    <label class="form-label">{{ __('labels.background_color') ?? 'Background Color' }} (Web)</label>
                                                    <input type="color" class="form-control form-control-color w-100"
                                                        name="backgroundColorWeb" id="background-color-web-input"
                                                        value="{{ $settings['backgroundColorWeb'] ?? ($settings['backgroundColor'] ?? '#ffffff') }}" />
                                                    <small class="form-text text-muted">Web background color</small>
                                                </div>
                                                <div class="col-md-6">
                                                    <label class="form-label">{{ __('labels.background_color') ?? 'Background Color' }} (App)</label>
                                                    <input type="color" class="form-control form-control-color w-100"
                                                        name="backgroundColorApp" id="background-color-app-input"
                                                        value="{{ $settings['backgroundColorApp'] ?? ($settings['backgroundColor'] ?? '#ffffff') }}" />
                                                    <small class="form-text text-muted">Mobile app background color</small>
                                                </div>
                                            </div>
                                        </div>

                                        {{-- Background Image (Web & App) --}}
                                        <div class="mb-3" id="background-image-field"
                                            style="{{ isset($settings['backgroundType']) && $settings['backgroundType'] === 'image' ? 'display: block;' : 'display: none;' }}">
                                            <div class="row g-3">
                                                <div class="col-md-4">
                                                    <label class="form-label">{{ __('labels.background_image_website') ?? 'Background Image' }} (Desktop)</label>
                                                    <input type="file" class="form-control" id="background-image-web-upload"
                                                        name="backgroundImageWeb"
                                                        data-image-url="{{ $settings['backgroundImageWeb'] ?? ($settings['backgroundImage'] ?? '') }}" />
                                                    <small class="form-text text-muted">Recommended for desktop screens</small>
                                                </div>
                                                <div class="col-md-4">
                                                    <label class="form-label">{{ __('labels.background_image_website') ?? 'Background Image' }} (Mobile)</label>
                                                    <input type="file" class="form-control" id="background-image-web-mobile-upload"
                                                        name="backgroundImageWebMobile"
                                                        data-image-url="{{ $settings['backgroundImageWebMobile'] ?? '' }}" />
                                                    <small class="form-text text-muted">Recommended for mobile web screens</small>
                                                </div>
                                                <div class="col-md-4">
                                                    <label class="form-label">{{ __('labels.background_image_app') ?? 'Background Image' }}</label>
                                                    <input type="file" class="form-control" id="background-image-app-upload"
                                                        name="backgroundImageApp"
                                                        data-image-url="{{ $settings['backgroundImageApp'] ?? ($settings['backgroundImage'] ?? '') }}" />
                                                    <small class="form-text text-muted">Recommended for mobile app screens</small>
                                                </div>
                                            </div>
                                        </div>

                                        {{-- Background Animation (Web & App) --}}
                                        <div class="mb-3" id="background-animation-field"
                                            style="{{ isset($settings['backgroundType']) && $settings['backgroundType'] === 'animation' ? 'display: block;' : 'display: none;' }}">
                                            <div class="row g-3">
                                                <div class="col-md-6">
                                                    <label class="form-label">{{ __('labels.background_animation') ?? 'Background Animation (JSON)' }} (Web)</label>
                                                    <input type="file" class="form-control" id="background-animation-web-upload"
                                                        name="backgroundAnimationWeb" accept=".json,application/json"
                                                        data-image-url="{{ $settings['backgroundAnimationWeb'] ?? ($settings['backgroundAnimation'] ?? '') }}" />
                                                    @php
                                                        $webAnim = $settings['backgroundAnimationWeb'] ?? ($settings['backgroundAnimation'] ?? '');
                                                    @endphp
                                                    <div class="mt-2 border rounded p-2 text-center bg-light {{ empty($webAnim) ? 'd-none' : '' }}" id="web-lottie-preview-wrap">
                                                        <div class="d-flex align-items-center justify-content-between mb-2 px-1">
                                                            <span class="text-success small"><i class="ti ti-check me-1"></i>Web Animation:</span>
                                                            <a href="{{ $webAnim ?: '#' }}" target="_blank" id="web-lottie-filename"
                                                                class="small text-decoration-underline {{ empty($webAnim) ? 'd-none' : '' }}">{{ $webAnim ? basename(parse_url($webAnim, PHP_URL_PATH)) : '' }}</a>
                                                        </div>
                                                        <div style="height: 180px; width: 100%;" class="d-flex align-items-center justify-content-center">
                                                            <lottie-player id="web-lottie-player" src="{{ $webAnim }}" background="transparent" speed="1" style="width: 100%; height: 180px; object-fit: contain;" loop autoplay></lottie-player>
                                                        </div>
                                                    </div>
                                                    <small class="form-text text-muted d-block mt-1">Lottie JSON file for Web.</small>
                                                </div>
                                                <div class="col-md-6">
                                                    <label class="form-label">{{ __('labels.background_animation') ?? 'Background Animation (JSON)' }} (App)</label>
                                                    <input type="file" class="form-control" id="background-animation-app-upload"
                                                        name="backgroundAnimationApp" accept=".json,application/json"
                                                        data-image-url="{{ $settings['backgroundAnimationApp'] ?? '' }}" />
                                                    @php
                                                        $appAnim = $settings['backgroundAnimationApp'] ?? '';
                                                    @endphp
                                                    <div class="mt-2 border rounded p-2 text-center bg-light {{ empty($appAnim) ? 'd-none' : '' }}" id="app-lottie-preview-wrap">
                                                        <div class="d-flex align-items-center justify-content-between mb-2 px-1">
                                                            <span class="text-success small"><i class="ti ti-check me-1"></i>App Animation:</span>
                                                            <a href="{{ $appAnim ?: '#' }}" target="_blank" id="app-lottie-filename"
                                                                class="small text-decoration-underline {{ empty($appAnim) ? 'd-none' : '' }}">{{ $appAnim ? basename(parse_url($appAnim, PHP_URL_PATH)) : '' }}</a>
                                                        </div>
                                                        <div style="height: 180px; width: 100%;" class="d-flex align-items-center justify-content-center">
                                                            <lottie-player id="app-lottie-player" src="{{ $appAnim }}" background="transparent" speed="1" style="width: 100%; height: 180px; object-fit: contain;" loop autoplay></lottie-player>
                                                        </div>
                                                    </div>
                                                    <small class="form-text text-muted d-block mt-1">Lottie JSON file for Mobile App.</small>
                                                </div>
                                            </div>
                                        </div>

                                        <div class="mb-3">
                                            <label class="form-label">{{ __('labels.font_color') }}</label>
                                            <input type="color" class="form-control form-control-color w-100"
                                                name="fontColor" id="font-color-input"
                                                value="{{ $settings['fontColor'] ?? '#000000' }}" />
                                            <small class="form-text text-muted">{{ __('labels.font_color_help') }}</small>
                                        </div>
                                    </div>
                                </div>

                                <div class="card-footer text-end">
                                    @can('updateSetting', [\App\Models\Setting::class, 'home_general_settings'])
                                        <button type="submit" class="btn btn-primary">
                                            <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24"
                                                viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
                                                stroke-linecap="round" stroke-linejoin="round"
                                                class="icon icon-tabler icons-tabler-outline icon-tabler-device-floppy">
                                                <path stroke="none" d="M0 0h24v24H0z" fill="none" />
                                                <path
                                                    d="M6 4h10l4 4v10a2 2 0 0 1 -2 2h-12a2 2 0 0 1 -2 -2v-12a2 2 0 0 1 2 -2" />
                                                <path d="M12 14m-2 0a2 2 0 1 0 4 0a2 2 0 1 0 -4 0" />
                                                <path d="M14 4l0 4l-6 0l0 -4" />
                                            </svg>
                                            {{ __('labels.save') }}
                                        </button>
                                    @endcan
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
            function bindLottiePreview(inputSelector, playerSelector, wrapSelector, filenameSelector) {
                const inputEl = document.querySelector(inputSelector);
                const player = document.querySelector(playerSelector);
                const wrap = document.querySelector(wrapSelector);
                const filenameLink = document.querySelector(filenameSelector);

                function loadFile(file) {
                    if (!file || !player || !wrap) return;
                    const reader = new FileReader();
                    reader.onload = function (e) {
                        try {
                            player.load(e.target.result);
                            wrap.classList.remove('d-none');
                            if (filenameLink) {
                                filenameLink.textContent = file.name || 'New Lottie JSON';
                                filenameLink.classList.remove('d-none');
                                filenameLink.href = '#';
                            }
                        } catch (err) {
                            console.error('Failed to load lottie JSON into preview:', err);
                        }
                    };
                    reader.readAsText(file);
                }

                // Standard input change
                if (inputEl) {
                    inputEl.addEventListener('change', function (e) {
                        if (e.target.files && e.target.files[0]) {
                            loadFile(e.target.files[0]);
                        }
                    });
                }

                // FilePond instance hook (checked after FilePond boots)
                setTimeout(function () {
                    if (window.FilePond && inputEl) {
                        const pond = FilePond.find(inputEl);
                        if (pond) {
                            pond.on('addfile', function (error, fileItem) {
                                if (!error && fileItem && fileItem.file) {
                                    loadFile(fileItem.file);
                                }
                            });
                            pond.on('removefile', function () {
                                if (wrap && !player.getAttribute('src')) {
                                    wrap.classList.add('d-none');
                                }
                            });
                        }
                    }
                }, 300);
            }

            bindLottiePreview(
                '#background-animation-web-upload',
                '#web-lottie-player',
                '#web-lottie-preview-wrap',
                '#web-lottie-filename'
            );

            bindLottiePreview(
                '#background-animation-app-upload',
                '#app-lottie-player',
                '#app-lottie-preview-wrap',
                '#app-lottie-filename'
            );
        });
    </script>
@endsection
