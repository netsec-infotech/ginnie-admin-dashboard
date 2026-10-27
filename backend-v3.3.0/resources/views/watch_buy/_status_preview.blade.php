<div class="modal modal-blur fade watch-buy-status-preview-modal" id="watch-buy-status-preview-modal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <div>
                    <h5 class="modal-title">{{ __('labels.watch_buy_preview_status') }}</h5>
                    <div class="text-secondary small watch-buy-status-preview-profile"></div>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="{{ __('labels.close') }}"></button>
            </div>
            <div class="modal-body p-0">
                <div class="row g-0 watch-buy-status-preview-layout">
                    <div class="col-lg-8 watch-buy-status-preview-stage">
                        <img class="watch-buy-status-preview-image d-none" alt="{{ __('labels.watch_buy_status_image') }}">
                        <video class="watch-buy-status-preview-video d-none" controls playsinline preload="metadata"></video>
                        <div class="watch-buy-status-preview-text-only d-none"></div>
                        <div class="watch-buy-status-preview-unavailable d-none">
                            <i class="ti ti-photo-off"></i>
                            <span>{{ __('labels.watch_buy_status_media_unavailable') }}</span>
                        </div>
                    </div>
                    <aside class="col-lg-4 watch-buy-status-preview-details">
                        <div>
                            <span class="badge bg-primary-lt watch-buy-status-preview-type"></span>
                            <h3 class="mt-3 mb-2">{{ __('labels.watch_buy_status_details') }}</h3>
                            <p class="watch-buy-status-preview-copy mb-4 d-none"></p>
                            <div class="watch-buy-status-preview-meta">
                                <div><i class="ti ti-clock"></i><span class="watch-buy-status-preview-expiry"></span></div>
                                <div><i class="ti ti-user"></i><span class="watch-buy-status-preview-creator"></span></div>
                                <div><i class="ti ti-shopping-bag"></i><span class="watch-buy-status-preview-products"></span></div>
                            </div>
                        </div>
                        <div class="watch-buy-status-preview-actions">
                            <a class="btn btn-outline-primary watch-buy-status-preview-open d-none" href="#" target="_blank" rel="noopener">
                                <i class="ti ti-external-link me-1"></i>{{ __('labels.watch_buy_open_original_media') }}
                            </a>
                            <button type="button" class="btn btn-outline-danger watch-buy-status-delete watch-buy-status-preview-delete d-none">
                                <i class="ti ti-trash me-1"></i>{{ __('labels.watch_buy_delete_permanently') }}
                            </button>
                        </div>
                    </aside>
                </div>
            </div>
        </div>
    </div>
</div>
