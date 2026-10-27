<div>
    @if ($screen->background_image)
        <a href="{{ $screen->background_image }}" class="img-box-60" target="_blank" data-fslightbox="gallery">
            <img src="{{ $screen->background_image }}" alt="{{ $screen->title ?? __('labels.background_image') }}" width="100" height="50">
        </a>
    @else
        <span class="text-muted">-</span>
    @endif
</div>
