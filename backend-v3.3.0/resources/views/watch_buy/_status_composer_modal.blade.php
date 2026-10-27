<div class="modal modal-blur fade" id="{{ $modalId }}" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
        <form class="modal-content form-submit watch-buy-status-form" method="post" enctype="multipart/form-data" action="{{ $action }}">
            @csrf
            <div class="modal-header">
                <h5 class="modal-title">{{ __('labels.watch_buy_add_status') }}</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="{{ __('labels.close') }}"></button>
            </div>
            <div class="modal-body">
                @if ($admin ?? false)
                    <div class="row g-3 mb-3">
                        <div class="col-md-5">
                            <label class="form-label required">{{ __('labels.watch_buy_status_publish_as') }}</label>
                            <select class="form-select watch-buy-status-source" name="source" id="{{ $modalId }}-source" required>
                                <option value="admin_direct" @selected(old('source', 'admin_direct') === 'admin_direct')>{{ __('labels.watch_buy_official_profile') }}</option>
                                <option value="seller_behalf" @selected(old('source') === 'seller_behalf')>{{ __('labels.watch_buy_status_seller_behalf') }}</option>
                            </select>
                        </div>
                        <div class="col-md-7 d-none watch-buy-status-profile-field">
                            <label class="form-label required">{{ __('labels.watch_buy_seller_profile') }}</label>
                            <select class="form-select watch-buy-status-profile" name="profile_id" id="{{ $modalId }}-profile"
                                data-placeholder="{{ __('labels.watch_buy_search_seller_profiles') }}">
                                <option value="">{{ __('labels.select') }}</option>
                                @foreach ($sellerProfiles as $sellerProfile)
                                    <option value="{{ $sellerProfile->id }}" @selected((int) old('profile_id') === $sellerProfile->id)>
                                        {{ '@'.$sellerProfile->username }} — {{ $sellerProfile->seller?->user?->name }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                    </div>
                @else
                    <input type="hidden" name="source" value="seller">
                @endif

                <div class="mb-3">
                    <label class="form-label">{{ __('labels.watch_buy_status_media') }}</label>
                    <input class="watch-buy-status-media" type="file" name="media"
                        accept="image/jpeg,image/png,image/webp,video/mp4,video/quicktime,.mov"
                        data-max-file-size="{{ $config['effectiveMaxUploadMb'] }}MB"
                        data-idle-label="{{ __('labels.watch_buy_status_media_upload_hint') }}">
                    <small class="form-hint">
                        {{ __('labels.watch_buy_status_media_hint', ['max' => $config['effectiveMaxUploadMb'], 'duration' => $config['maxStatusDurationSeconds']]) }}
                    </small>
                    @error('media')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                </div>

                <div class="mb-3">
                    <label class="form-label">{{ __('labels.watch_buy_status_text') }}</label>
                    <textarea class="form-control watch-buy-status-text" name="text" rows="4" maxlength="500"
                        placeholder="{{ __('labels.watch_buy_status_text_placeholder') }}">{{ old('text') }}</textarea>
                    <small class="form-hint">{{ __('labels.watch_buy_status_text_hint') }}</small>
                    @error('text')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                </div>

                <div>
                    <label class="form-label">{{ __('labels.watch_buy_product_attachments') }}</label>
                    <select class="form-select watch-buy-status-products" name="attachment_ids[]" id="{{ $pickerId }}" multiple
                        data-endpoint="{{ $endpoint }}"
                        data-max-items="{{ $config['maxProductsPerStatus'] }}"
                        data-source-selector="#{{ $modalId }}-source"
                        data-profile-selector="#{{ $modalId }}-profile"
                        data-placeholder="{{ __('labels.watch_buy_search_products') }}">
                    </select>
                    <small class="form-hint">
                        {{ __('labels.watch_buy_status_products_optional', ['max' => $config['maxProductsPerStatus']]) }}
                    </small>
                    @error('attachment_ids')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                </div>

                <div class="alert alert-info mt-3 mb-0 py-2">
                    <i class="ti ti-clock me-1"></i>{{ __('labels.watch_buy_status_expires_note') }}
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-link link-secondary" data-bs-dismiss="modal">{{ __('labels.cancel') }}</button>
                <button type="submit" class="btn btn-primary watch-buy-status-publish">
                    <i class="ti ti-send me-1"></i>{{ __('labels.watch_buy_publish_status') }}
                </button>
            </div>
        </form>
    </div>
</div>
