{{-- Splash previews are shown in an app-style phone mockup so admins can judge the real launch-screen fit. --}}
<div class="splash-phone-preview">
    <span class="splash-phone-button splash-phone-button-left"></span>
    <span class="splash-phone-button splash-phone-button-right"></span>
    <div class="splash-phone-screen">
        <div class="splash-phone-status">
            <span>9:41</span>
            <span class="splash-phone-island"></span>
            <span>5G</span>
        </div>
        <div class="splash-phone-stage" @if ($screen?->bg_type === 'color') style="background-color: {{ $screen->bg_color }};" @endif>
            @if ($screen?->bg_type === 'image' && $screen->background_image_url)
                <img class="splash-phone-background" src="{{ $screen->background_image_url }}" alt="{{ __('labels.media_preview') }}">
            @elseif ($screen?->bg_type === 'video' && $screen->background_video_url)
                <video class="splash-phone-background" src="{{ $screen->background_video_url }}" muted loop controls playsinline></video>
            @elseif ($screen?->bg_type === 'color')
                <span class="splash-phone-color-label">{{ $screen->bg_color }}</span>
            @else
                <div class="splash-media-empty">
                    {{ $emptyLabel }}
                </div>
            @endif
            @if ($screen?->overlay_enabled && $screen->overlay_url)
                <div class="splash-phone-overlay"
                    style="left: {{ $screen->overlay_x_percent }}%; top: {{ $screen->overlay_y_percent }}%; width: {{ $screen->overlay_width_percent }}%;">
                    @if ($screen->overlay_type === 'image')
                        <img src="{{ $screen->overlay_url }}" alt="{{ __('labels.media_preview') }}">
                    @elseif ($screen->overlay_type === 'lottie')
                        <lottie-player src="{{ $screen->overlay_url }}" background="transparent" speed="1" loop autoplay></lottie-player>
                    @endif
                </div>
            @endif
        </div>
    </div>
</div>
