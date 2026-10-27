<form class="form-submit watch-buy-status-form" method="post" enctype="multipart/form-data" action="{{ $action }}">
    @csrf

    @if ($admin ?? false)
        <div class="row">
            <div class="col-md-6 mb-3">
                <label class="form-label required">{{ __('labels.watch_buy_creation_mode') }}</label>
                <select class="form-select watch-buy-status-source" name="source" required>
                    <option value="seller_behalf">{{ __('labels.watch_buy_seller_behalf') }}</option>
                    <option value="admin_direct">{{ __('labels.watch_buy_admin_direct') }}</option>
                </select>
            </div>
            <div class="col-md-6 mb-3 watch-buy-status-profile-field">
                <label class="form-label">{{ __('labels.watch_buy_seller_profile') }}</label>
                <select class="form-select" name="profile_id">
                    <option value="">{{ __('labels.select') }}</option>
                    @foreach ($sellerProfiles as $sellerProfile)
                        <option value="{{ $sellerProfile->id }}">
                            {{ '@' . $sellerProfile->username }} — {{ $sellerProfile->seller?->user?->name }}
                        </option>
                    @endforeach
                </select>
            </div>
        </div>
    @endif

    <div class="mb-3">
        <label class="form-label">{{ __('labels.watch_buy_status_text') }}</label>
        <textarea class="form-control" name="text" rows="3" maxlength="500">{{ old('text') }}</textarea>
        @error('text')
            <div class="invalid-feedback d-block">{{ $message }}</div>
        @enderror
    </div>

    <div class="mb-3">
        <label class="form-label">{{ __('labels.watch_buy_status_media') }}</label>
        <input class="form-control watch-buy-status-media" type="file" name="media"
            accept="image/jpeg,image/png,image/webp,video/mp4"
            data-max-file-size="{{ $config['effectiveMaxUploadMb'] }}MB">
        <small class="form-hint">
            {{ __('labels.watch_buy_status_media_hint', ['max' => $config['effectiveMaxUploadMb'], 'duration' => $config['maxStatusDurationSeconds']]) }}
        </small>
        @error('media')
            <div class="invalid-feedback d-block">{{ $message }}</div>
        @enderror
    </div>

    @include('watch_buy._product_picker', [
        'pickerId' => $pickerId,
        'endpoint' => $endpoint,
        'required' => false,
        'primary' => false,
        'selectedOptions' => [],
        'selected' => [],
    ])

    <button type="submit" class="btn btn-primary">
        <i class="ti ti-plus me-1"></i>{{ __('labels.watch_buy_add_status') }}
    </button>
</form>
