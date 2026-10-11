<div class="modal modal-blur fade" id="hbConfirmModal" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog modal-sm modal-dialog-centered" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">{{ __('labels.confirm') }}</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="{{ __('labels.cancel') }}"></button>
            </div>
            <div class="modal-body">
                <p class="mb-0" id="hbConfirmMessage"></p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-ghost-secondary" data-bs-dismiss="modal">{{ __('labels.cancel') }}</button>
                <button type="button" class="btn btn-danger" id="hbConfirmOk">{{ __('labels.confirm') }}</button>
            </div>
        </div>
    </div>
</div>
