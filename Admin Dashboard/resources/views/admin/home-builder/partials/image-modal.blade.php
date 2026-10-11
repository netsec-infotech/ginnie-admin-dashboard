<div class="modal modal-blur fade" id="hbImageModal" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">{{ __('labels.hb_upload') }}</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="{{ __('labels.cancel') }}"></button>
            </div>
            <div class="modal-body">
                <input type="file" id="hbImagePond" name="hbImagePond">
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-ghost-secondary" data-bs-dismiss="modal">{{ __('labels.cancel') }}</button>
                <button type="button" class="btn btn-primary" id="hbImageDone">{{ __('labels.confirm') }}</button>
            </div>
        </div>
    </div>
</div>
