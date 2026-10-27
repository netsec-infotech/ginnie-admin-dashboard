@extends('layouts.admin.app', ['page' => $menuAdmin['design_appearance']['active'] ?? 'design_appearance', 'sub_page' => $menuAdmin['design_appearance']['route']['splash_screens']['sub_active'] ?? 'splash-screens'])

@section('title', __('labels.splash_screens'))

@section('header_data')
    @php
        $page_title = __('labels.splash_screens');
        $page_pretitle = __('labels.admin') . ' ' . __('labels.management');
    @endphp
@endsection

@push('styles')
    <link rel="stylesheet" href="{{ hyperAsset('assets/css/splash-screens.css') }}">
@endpush

@section('admin-content')
    <div class="splash-admin" data-splash-admin data-splash-app-state-url="{{ route('admin.splash-screens.app-state') }}"
        data-splash-selected-app="{{ $selectedApp }}">
        <div class="splash-page-heading">
            <div class="splash-heading-copy">
                <span class="splash-heading-icon">
                    <i class="ti ti-device-mobile"></i>
                </span>
                <div>
                    <h2 class="splash-page-title">{{ __('labels.splash_screens') }}</h2>
                    <p class="splash-page-subtitle">Manage saved launch screens for customer, seller, and rider apps.</p>
                </div>
            </div>
        </div>

        <div class="card splash-tabs-card splash-elevated-card">
            <div class="card-body">
                <div class="splash-tabs-row">
                    <div class="splash-app-tabs" role="group" aria-label="{{ __('labels.app_type') }}">
                        @foreach ($appTypes as $appType)
                            @php
                                $appIcon = match ($appType->value) {
                                    \App\Enums\SplashAppTypeEnum::SELLER() => 'ti-building-store',
                                    \App\Enums\SplashAppTypeEnum::RIDER() => 'ti-bike',
                                    default => 'ti-user',
                                };
                            @endphp
                            <a href="{{ route('admin.splash-screens.index', ['app_type' => $appType->value]) }}"
                                class="btn splash-app-tab {{ $selectedApp === $appType->value ? 'active' : '' }}">
                                <i class="ti {{ $appIcon }}"></i>
                                {{ $appType->label() }}
                            </a>
                        @endforeach
                    </div>
                    <div class="splash-selected-app">
                        <span>Managing</span>
                        <strong>{{ collect($appTypes)->firstWhere('value', $selectedApp)?->label() }}</strong>
                    </div>
                </div>
            </div>
        </div>

        @if ($editPermission)
            <div class="card splash-slot-card splash-config-card splash-elevated-card">
                <div class="card-header splash-card-header">
                    <div class="splash-card-title-wrap">
                        <span class="splash-form-icon splash-form-icon-staged">
                            <i class="ti ti-adjustments"></i>
                        </span>
                        <div>
                            <h3 class="card-title">Configure Splash</h3>
                            <span class="text-secondary small">Choose the slot you want to update, then preview the draft
                                before saving.</span>
                        </div>
                    </div>
                    <div class="splash-slot-tabs" role="tablist" aria-label="Splash configuration slot">
                        <button type="button" class="splash-slot-tab active" data-splash-slot-tab="current">
                            <span class="splash-status-dot splash-status-dot-live"></span>
                            Current
                        </button>
                        <button type="button" class="splash-slot-tab" data-splash-slot-tab="staged">
                            <span class="splash-status-dot splash-status-dot-staged"></span>
                            Staged
                        </button>
                    </div>
                </div>
                <div class="card-body">
                    @foreach ([\App\Enums\SplashSlotEnum::CURRENT() => __('labels.replace_current_splash'), \App\Enums\SplashSlotEnum::STAGED_NEXT() => __('labels.replace_staged_splash')] as $slot => $title)
                        @php
                            $isStagedSlot = $slot === \App\Enums\SplashSlotEnum::STAGED_NEXT();
                            $configuredSplash = $isStagedSlot ? $stagedSplash : $currentSplash;
                        @endphp
                        <form class="splash-form splash-config-form splash-slot-pane {{ $isStagedSlot ? '' : 'active' }}"
                            data-splash-slot-pane="{{ $isStagedSlot ? 'staged' : 'current' }}"
                            data-splash-saved-bg-image-url="{{ $configuredSplash?->background_image_url }}"
                            data-splash-saved-bg-video-url="{{ $configuredSplash?->background_video_url }}"
                            data-splash-saved-overlay-url="{{ $configuredSplash?->overlay_url }}"
                            data-splash-saved-overlay-type="{{ $configuredSplash?->overlay_type }}"
                            data-splash-has-screen="{{ $configuredSplash ? '1' : '0' }}"
                            action="{{ route('admin.splash-screens.store') }}" method="POST"
                            enctype="multipart/form-data">
                            @csrf
                            <input type="hidden" name="app_type" value="{{ $selectedApp }}">
                            <input type="hidden" name="slot" value="{{ $slot }}">
                            <div class="splash-config-pane-header">
                                <div class="splash-card-title-wrap">
                                    <span class="splash-form-icon {{ $isStagedSlot ? 'splash-form-icon-staged' : '' }}">
                                        <i class="ti {{ $isStagedSlot ? 'ti-calendar-plus' : 'ti-upload' }}"></i>
                                    </span>
                                    <div>
                                        <h3 class="card-title">{{ $title }}</h3>
                                        <span
                                            class="text-secondary small">{{ $isStagedSlot ? 'Configure the next splash and optionally schedule activation.' : 'Use this when you need to replace the live splash immediately.' }}</span>
                                    </div>
                                </div>
                                <span
                                    class="badge {{ $isStagedSlot ? 'bg-purple-lt' : 'bg-primary-lt' }}">{{ $isStagedSlot ? 'Staged' : 'Current' }}</span>
                            </div>
                            <div class="splash-builder-layout">
                                <div class="splash-builder-fields">
                                    <div class="splash-panel-heading">
                                        <div>
                                            <h4>Splash Screen Configuration</h4>
                                            <p>Customize the selected slot. Changes reflect in the preview instantly.</p>
                                        </div>
                                        <i class="ti ti-settings"></i>
                                    </div>
                                    {{-- Layout presets are admin-only shortcuts that update the existing splash fields and live preview. --}}
                                    <div class="splash-layout-presets mb-3" data-splash-preset-group>
                                        <div class="splash-layout-presets-heading">
                                            <label class="form-label mb-0">Layout Preset</label>
                                            <span class="badge bg-secondary-lt d-none"
                                                data-splash-custom-preset>Custom</span>
                                        </div>
                                        <div class="splash-preset-grid">
                                            <button type="button" class="splash-preset-tile active"
                                                data-splash-layout-preset="centered" data-bg-type="color"
                                                data-overlay-enabled="1" data-overlay-x="50" data-overlay-y="45"
                                                data-overlay-width="55">
                                                <span class="splash-preset-phone splash-preset-phone-centered"></span>
                                                <span class="splash-preset-copy">
                                                    <strong>Centered Brand</strong>
                                                    <small>Logo centered on color.</small>
                                                </span>
                                            </button>
                                            <button type="button" class="splash-preset-tile"
                                                data-splash-layout-preset="top" data-bg-type="image"
                                                data-overlay-enabled="1" data-overlay-x="50" data-overlay-y="28"
                                                data-overlay-width="45">
                                                <span class="splash-preset-phone splash-preset-phone-top"></span>
                                                <span class="splash-preset-copy">
                                                    <strong>Top Brand</strong>
                                                    <small>Logo high over artwork.</small>
                                                </span>
                                            </button>
                                            <button type="button" class="splash-preset-tile"
                                                data-splash-layout-preset="bottom" data-bg-type="image"
                                                data-overlay-enabled="1" data-overlay-x="50" data-overlay-y="72"
                                                data-overlay-width="45">
                                                <span class="splash-preset-phone splash-preset-phone-bottom"></span>
                                                <span class="splash-preset-copy">
                                                    <strong>Bottom Brand</strong>
                                                    <small>Logo low over artwork.</small>
                                                </span>
                                            </button>
                                            <button type="button" class="splash-preset-tile"
                                                data-splash-layout-preset="full" data-bg-type="media"
                                                data-overlay-enabled="0" data-overlay-x="50" data-overlay-y="45"
                                                data-overlay-width="60">
                                                <span class="splash-preset-phone splash-preset-phone-full"></span>
                                                <span class="splash-preset-copy">
                                                    <strong>Full Background</strong>
                                                    <small>Complete image/video splash.</small>
                                                </span>
                                            </button>
                                            <button type="button" class="splash-preset-tile"
                                                data-splash-layout-preset="minimal" data-bg-type="color"
                                                data-overlay-enabled="0" data-overlay-x="50" data-overlay-y="45"
                                                data-overlay-width="60">
                                                <span class="splash-preset-phone splash-preset-phone-minimal"></span>
                                                <span class="splash-preset-copy">
                                                    <strong>Minimal Color</strong>
                                                    <small>Clean color-only launch.</small>
                                                </span>
                                            </button>
                                        </div>
                                    </div>
                                    <div class="splash-guidance-note">
                                        <i class="ti ti-info-circle"></i>
                                        Use portrait 1080x1920+ backgrounds. Keep logos/animations transparent for the
                                        overlay layer.
                                    </div>
                                    <div class="mb-3">
                                        <label class="form-label required">{{ __('labels.background_type') }}</label>
                                        <select class="form-select" name="bg_type" data-splash-bg-type required>
                                            <option value="color" @selected(($configuredSplash?->bg_type ?? 'color') === 'color')>Color</option>
                                            <option value="image" @selected($configuredSplash?->bg_type === 'image')>Image</option>
                                            <option value="video" @selected($configuredSplash?->bg_type === 'video')>Video</option>
                                        </select>
                                    </div>
                                    <div class="mb-3" data-splash-field="bg_color">
                                        <label class="form-label required">{{ __('labels.background_color') }}</label>
                                        <input type="color" class="form-control form-control-color" name="bg_color"
                                            value="{{ $configuredSplash?->bg_color ?? '#1B5E20' }}" data-splash-bg-color>
                                    </div>
                                    <div class="mb-3 d-none" data-splash-field="bg_image">
                                        <label class="form-label required">{{ __('labels.background_image') }}</label>
                                        <input type="file" class="filepond splash-media-input" name="bg_image"
                                            accept="image/jpeg,image/png,image/webp" data-splash-upload
                                            data-splash-preview="background"
                                            data-accepted-file-types="image/jpeg,image/png,image/webp"
                                            data-max-file-size="1536KB">
                                        <small class="form-hint text-secondary">Portrait JPG/PNG/WebP, minimum 1080x1920,
                                            up to 1.5 MB.</small>
                                    </div>
                                    <div class="mb-3 d-none" data-splash-field="bg_video">
                                        <label class="form-label required">{{ __('labels.background_video') }}</label>
                                        <input type="file" class="filepond splash-media-input" name="bg_video"
                                            accept="video/mp4" data-splash-upload data-splash-preview="background"
                                            data-accepted-file-types="video/mp4" data-max-file-size="6MB">
                                        <small class="form-hint text-secondary">MP4 only, up to 6 MB.</small>
                                    </div>
                                    <div class="mb-3">
                                        <input type="hidden" name="overlay_enabled" value="0">
                                        <label class="form-check form-switch">
                                            <input class="form-check-input" type="checkbox" name="overlay_enabled"
                                                value="1" data-splash-overlay-enabled @checked($configuredSplash?->overlay_enabled)>
                                            <span class="form-check-label">{{ __('labels.overlay_enabled') }}</span>
                                        </label>
                                    </div>
                                    <div class="splash-overlay-fields d-none" data-splash-overlay-fields>
                                        <div class="mb-3">
                                            <label class="form-label required">{{ __('labels.overlay_type') }}</label>
                                            <select class="form-select" name="overlay_type" data-splash-overlay-type>
                                                <option value="image" @selected(($configuredSplash?->overlay_type ?? 'image') === 'image')>Image</option>
                                                <option value="lottie" @selected($configuredSplash?->overlay_type === 'lottie')>Lottie</option>
                                            </select>
                                        </div>
                                        <div class="mb-3" data-splash-field="overlay_image">
                                            <label class="form-label required">{{ __('labels.overlay_image') }}</label>
                                            <input type="file" class="filepond splash-media-input"
                                                name="overlay_image" accept="image/png,image/webp" data-splash-upload
                                                data-splash-preview="overlay"
                                                data-accepted-file-types="image/png,image/webp"
                                                data-max-file-size="512KB">
                                            <small class="form-hint text-secondary">Transparent PNG/WebP, up to 512
                                                KB.</small>
                                        </div>
                                        <div class="mb-3 d-none" data-splash-field="overlay_lottie">
                                            <label class="form-label required">{{ __('labels.overlay_lottie') }}</label>
                                            <input type="file" class="filepond splash-media-input"
                                                name="overlay_lottie" accept="application/json,.json,text/plain"
                                                data-splash-upload data-splash-preview="overlay"
                                                data-accepted-file-types="application/json,text/plain,application/octet-stream,.json"
                                                data-max-file-size="512KB">
                                            <small class="form-hint text-secondary">Valid Lottie JSON with v and layers
                                                keys, up to 512 KB.</small>
                                        </div>
                                        <div class="row g-3">
                                            <div class="col-md-4">
                                                <label class="form-label">{{ __('labels.horizontal_position') }} <span
                                                        data-splash-range-value="overlay_x_percent">{{ $configuredSplash?->overlay_x_percent ?? 50 }}%</span></label>
                                                <input type="range" class="form-range" name="overlay_x_percent"
                                                    min="10" max="90"
                                                    value="{{ $configuredSplash?->overlay_x_percent ?? 50 }}"
                                                    data-splash-overlay-x>
                                            </div>
                                            <div class="col-md-4">
                                                <label class="form-label">{{ __('labels.vertical_position') }} <span
                                                        data-splash-range-value="overlay_y_percent">{{ $configuredSplash?->overlay_y_percent ?? 45 }}%</span></label>
                                                <input type="range" class="form-range" name="overlay_y_percent"
                                                    min="10" max="90"
                                                    value="{{ $configuredSplash?->overlay_y_percent ?? 45 }}"
                                                    data-splash-overlay-y>
                                            </div>
                                            <div class="col-md-4">
                                                <label class="form-label">{{ __('labels.overlay_size') }} <span
                                                        data-splash-range-value="overlay_width_percent">{{ $configuredSplash?->overlay_width_percent ?? 60 }}%</span></label>
                                                <input type="range" class="form-range" name="overlay_width_percent"
                                                    min="20" max="85"
                                                    value="{{ $configuredSplash?->overlay_width_percent ?? 60 }}"
                                                    data-splash-overlay-width>
                                            </div>
                                        </div>
                                        <div class="d-flex flex-wrap gap-2 mt-3">
                                            <button type="button" class="btn btn-outline-secondary btn-sm"
                                                data-splash-reset-overlay>
                                                <i class="ti ti-refresh me-1"></i>Reset Position
                                            </button>
                                            <button type="button" class="btn btn-outline-danger btn-sm"
                                                data-splash-clear-overlay>
                                                <i class="ti ti-x me-1"></i>Clear Overlay
                                            </button>
                                        </div>
                                    </div>
                                    <div class="mb-3">
                                        <label
                                            class="form-label required">{{ __('labels.minimum_display_seconds') }}</label>
                                        <input type="number" class="form-control" name="min_display_seconds"
                                            min="1" max="6"
                                            value="{{ $slot === \App\Enums\SplashSlotEnum::CURRENT() ? $currentSplash->min_display_seconds ?? 2 : $stagedSplash->min_display_seconds ?? 2 }}"
                                            required>
                                    </div>
                                    @if ($slot === \App\Enums\SplashSlotEnum::STAGED_NEXT())
                                        <div class="mb-3">
                                            <label class="form-label">{{ __('labels.activate_at') }}</label>
                                            <input type="datetime-local" class="form-control" name="activate_at"
                                                value="{{ $stagedSplash?->activate_at?->format('Y-m-d\TH:i') }}">
                                        </div>
                                    @endif
                                </div>
                                <div class="splash-builder-preview">
                                    <div class="splash-panel-heading splash-preview-heading">
                                        <div>
                                            <h4>Preview</h4>
                                            <p>Live preview for the selected splash slot.</p>
                                        </div>
                                        <i class="ti ti-eye"></i>
                                    </div>
                                    @include('admin.splash-screens.partials.media-preview', [
                                        'screen' => $configuredSplash,
                                        'emptyLabel' => __('labels.no_current_splash'),
                                    ])
                                </div>
                            </div>
                            <div class="splash-config-footer">
                                <div class="d-flex flex-wrap gap-2">
                                    @if (!$isStagedSlot && $deletePermission)
                                        <button type="button"
                                            class="btn btn-outline-danger {{ $currentSplash ? '' : 'd-none' }}"
                                            data-splash-delete="{{ $currentSplash ? route('admin.splash-screens.destroy', $currentSplash) : '' }}"
                                            data-splash-action-button="current-delete">
                                            {{ __('labels.delete_splash') }}
                                        </button>
                                    @endif
                                    @if ($isStagedSlot)
                                        @if ($editPermission)
                                            <button type="button"
                                                class="btn btn-success {{ $stagedSplash ? '' : 'd-none' }}"
                                                data-splash-promote="{{ $stagedSplash ? route('admin.splash-screens.promote', $stagedSplash) : '' }}"
                                                data-splash-action-button="staged-promote">
                                                {{ __('labels.promote_to_current') }}
                                            </button>
                                        @endif
                                        @if ($deletePermission)
                                            <button type="button"
                                                class="btn btn-outline-danger {{ $stagedSplash ? '' : 'd-none' }}"
                                                data-splash-delete="{{ $stagedSplash ? route('admin.splash-screens.destroy', $stagedSplash) : '' }}"
                                                data-splash-action-button="staged-delete">
                                                {{ __('labels.delete_splash') }}
                                            </button>
                                        @endif
                                    @endif
                                </div>
                                <button type="submit"
                                    class="btn {{ $isStagedSlot ? 'btn-purple' : 'btn-primary' }} splash-submit-button">
                                    <i class="ti {{ $isStagedSlot ? 'ti-device-floppy' : 'ti-upload' }} me-1"></i>
                                    {{ $title }}
                                </button>
                            </div>
                        </form>
                    @endforeach
                </div>
            </div>
        @endif
    </div>
@endsection

@push('scripts')
    <script src="{{ hyperAsset('assets/js/splash-screens.js') }}" defer></script>
@endpush
