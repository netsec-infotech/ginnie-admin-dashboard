@if($reel->video_url)
    <a href="{{ $reel->video_url }}" target="_blank" class="watch-buy-video-preview"
        data-fslightbox="{{ $gallery }}" data-type="video" title="{{ __('labels.preview') }}">
        <video muted playsinline preload="metadata" aria-hidden="true">
            <source src="{{ $reel->video_url }}#t=0.1" type="video/mp4">
        </video>
        <span><i class="ti ti-player-play-filled"></i></span>
    </a>
@endif
