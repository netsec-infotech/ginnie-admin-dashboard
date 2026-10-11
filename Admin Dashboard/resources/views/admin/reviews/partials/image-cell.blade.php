@if(count($images))
    @php $gallery = 'review-gallery-' . md5(json_encode($images)); @endphp
    <div class="review-image-stack d-flex align-items-center gap-1 flex-wrap">
        @foreach(array_slice($images, 0, 3) as $image)
            <a href="{{ $image }}" target="_blank" rel="noopener" data-fslightbox="{{ $gallery }}" class="review-image-stack__thumb avatar avatar-md rounded overflow-hidden" style="width: 40px; height: 40px; min-width: 40px;">
                <img src="{{ $image }}" alt="Review image" style="width: 40px; height: 40px; object-fit: cover; display: block;">
            </a>
        @endforeach
        @if(count($images) > 3)
            <a href="{{ $images[3] }}" target="_blank" rel="noopener" data-fslightbox="{{ $gallery }}" class="review-image-stack__more avatar avatar-md rounded bg-secondary-lt text-secondary fw-semibold d-inline-flex align-items-center justify-content-center text-decoration-none" style="width: 40px; height: 40px; min-width: 40px; font-size: 0.75rem;">+{{ count($images) - 3 }}</a>
            @foreach(array_slice($images, 4) as $hiddenImage)
                <a href="{{ $hiddenImage }}" data-fslightbox="{{ $gallery }}" class="d-none"></a>
            @endforeach
        @endif
    </div>
@else
    <span class="badge bg-secondary-lt">{{ __('labels.no_images') }}</span>
@endif
