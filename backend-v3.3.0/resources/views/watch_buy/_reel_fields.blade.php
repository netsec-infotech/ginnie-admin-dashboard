<div class="mb-3">
    <label class="form-label">{{ __('labels.watch_buy_caption') }}</label>
    <textarea class="form-control" name="caption" maxlength="2000" rows="4">{{ old('caption', $reel->caption) }}</textarea>
    @error('caption')
        <div class="invalid-feedback d-block">{{ $message }}</div>
    @enderror
</div>

<div class="row">
    <div class="col-md-6 mb-3">
        <label class="form-label {{ $reel->exists ? '' : 'required' }}">{{ __('labels.watch_buy_video') }}</label>
        <input class="form-control" type="file" name="video" accept="video/mp4,video/quicktime,.mp4,.mov"
            data-max-file-size="{{ $config['effectiveMaxUploadMb'] ?? 50 }}MB" @required(!$reel->exists)>
        <small class="form-hint">
            MP4/MOV (H.264 or HEVC){{ isset($config) ? '; max ' . $config['effectiveMaxUploadMb'] . ' MB / ' . $config['maxDurationSeconds'] . ' sec' : '' }}
        </small>
        @error('video')
            <div class="invalid-feedback d-block">{{ $message }}</div>
        @enderror
    </div>

    <div class="col-md-6 mb-3">
        <label class="form-label">{{ __('labels.watch_buy_cover_image') }}</label>
        <input class="form-control" type="file" name="cover" accept="image/jpeg,image/png,image/webp"
            data-image-url="{{ $reel->cover_url ?? '' }}" data-max-file-size="10MB">
        <small class="form-hint">JPG, PNG or WebP; maximum 10 MB.</small>
        @error('cover')
            <div class="invalid-feedback d-block">{{ $message }}</div>
        @enderror
    </div>
</div>

@if ($reel->exists && $reel->video_url)
    <a href="{{ $reel->video_url }}" target="_blank" class="btn btn-sm btn-outline-info mb-3"
        data-fslightbox="watch-buy-current-reel" data-type="video">
        <i class="ti ti-player-play me-1"></i>{{ __('labels.watch_buy_preview_current_video') }}
    </a>
@endif
