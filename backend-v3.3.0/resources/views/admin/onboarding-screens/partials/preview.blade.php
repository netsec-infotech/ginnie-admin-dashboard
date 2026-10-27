@php
    $framed = $framed ?? true;
@endphp

@if ($framed)
    <div class="card onboarding-preview-card">
        <div class="card-header">
            <h3 class="card-title">{{ __('labels.preview') }}</h3>
        </div>
        <div class="card-body">
@endif
        <div class="onboarding-device-shell">
            <div class="onboarding-device-preview device device-iphone-14-pro device-black" data-device="ios">
                <div class="device-frame">
                    <div class="device-screen onboarding-device-screen">
                <div class="onboarding-preview-overlay" data-onboarding-preview-overlay></div>
                <div class="onboarding-device-status">
                    <span>9:41</span>
                    <span class="onboarding-device-island"></span>
                    <span class="onboarding-device-icons">
                        <i class="ti ti-antenna-bars-5"></i>
                        <i class="ti ti-wifi"></i>
                        <i class="ti ti-battery-3"></i>
                    </span>
                </div>
                <div class="onboarding-preview-topbar">
                    <span class="onboarding-preview-app" data-onboarding-preview-app-label>
                        {{ ucfirst(str_replace('_', ' ', (string) $selectedApp)) }}
                    </span>
                    <button type="button" class="onboarding-preview-skip">Skip</button>
                </div>
                <div class="onboarding-preview-content">
                    <div class="onboarding-preview-media">
                        <img src="{{ $screen?->image ?? '' }}"
                            alt="{{ $screen?->title ?? __('labels.preview') }}"
                            class="{{ ($screen?->image && ($screen?->media_type ?? 'image') === 'image') ? '' : 'd-none' }}"
                            data-onboarding-preview-img>
                        <lottie-player
                            @if (($screen?->media_type ?? '') === 'lottie') src="{{ $screen?->image }}" @endif
                            background="transparent"
                            speed="1"
                            loop
                            autoplay
                            class="{{ (($screen?->media_type ?? '') === 'lottie') ? '' : 'd-none' }}"
                            data-onboarding-preview-lottie></lottie-player>
                        <video src="{{ (($screen?->media_type ?? '') === 'video') ? $screen?->image : '' }}"
                            class="{{ (($screen?->media_type ?? '') === 'video') ? '' : 'd-none' }}"
                            autoplay
                            loop
                            muted
                            playsinline
                            data-onboarding-preview-video></video>
                        <div class="onboarding-preview-placeholder {{ $screen?->image ? 'd-none' : '' }}" data-onboarding-preview-placeholder></div>
                    </div>
                    <div class="onboarding-preview-copy">
                        <h2 data-onboarding-preview-title-text>{{ $screen?->title ?: __('labels.no_onboarding_preview_title') }}</h2>
                        <p data-onboarding-preview-subtitle-text>{{ $screen?->subtitle ?: __('labels.no_onboarding_preview_subtitle') }}</p>
                    </div>
                </div>
                <div class="onboarding-preview-footer">
                    <div class="onboarding-preview-dots" data-onboarding-preview-dots>
                        <span class="active"></span>
                    </div>
                    <button type="button" class="btn btn-primary w-100" data-onboarding-preview-next>{{ __('labels.next') }}</button>
                </div>
            </div>
                </div>
                <div class="device-stripe"></div>
                <div class="device-header"></div>
                <div class="device-sensors"></div>
                <div class="device-btns"></div>
                <div class="device-power"></div>
                <div class="device-home"></div>
            </div>
        </div>
@if ($framed)
        </div>
    </div>
@endif
