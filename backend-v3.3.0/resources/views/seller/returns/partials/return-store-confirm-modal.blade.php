<!-- STORE CONFIRM MODAL -->
<div
    class="modal modal-blur fade"
    id="storeConfirmModel"
    tabindex="-1"
    role="dialog"
    aria-hidden="true"
>
    <div class="modal-dialog modal-sm modal-dialog-centered" role="document">
        <div class="modal-content">
            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            <div class="modal-status bg-success"></div>
            <div class="modal-body text-center py-4">
                <svg
                    xmlns="http://www.w3.org/2000/svg"
                    width="24"
                    height="24"
                    viewBox="0 0 24 24"
                    fill="none"
                    stroke="currentColor"
                    stroke-width="2"
                    stroke-linecap="round"
                    stroke-linejoin="round"
                    class="icon mb-2 text-green icon-lg"
                >
                    <path d="M12 12m-9 0a9 9 0 1 0 18 0a9 9 0 1 0 -18 0"/>
                    <path d="M9 12l2 2l4 -4"/>
                </svg>
                <h3>{{ __('labels.confirm_store_receipt') ?? 'Confirm Store Receipt' }}</h3>
                <div class="text-secondary">
                    {{ __('labels.confirm_store_receipt_msg') ?? 'Have you received and verified this return item? Confirming will complete the return and process the refund to the customer.' }}
                </div>
                <div class="form-group mt-3">
                    <label for="store_confirm_comment" class="form-label">{{ __('labels.comment') }} ({{ __('labels.optional') }})</label>
                    <textarea class="form-control" id="store_confirm_comment" rows="3"
                              placeholder="Add your note here"></textarea>
                </div>
                <!-- ==========================================================
                     Return Dispute System
                     Phase 3
                     Optional Seller Inspection Photos
                     ========================================================== -->
                <div class="form-group mt-3">
                    <label for="store_confirm_photos" class="form-label">Inspection Photos (Optional)</label>
                    <input type="file" class="form-control" id="store_confirm_photos" name="inspection_photos[]" multiple accept="image/*">
                    <small class="text-muted d-block text-start mt-1">You can upload multiple photos of the package condition.</small>
                </div>
            </div>
            <div class="modal-footer">
                <div class="w-100">
                    <div class="row">
                        <div class="col">
                            <a href="#" class="btn btn-outline-secondary w-100" data-bs-dismiss="modal">{{ __('labels.cancel') }}</a>
                        </div>
                        <div class="col">
                            <a href="#" class="btn btn-success w-100" id="confirmStoreReceiptBtn" data-bs-dismiss="modal">{{ __('labels.confirm') }}</a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
<!-- END STORE CONFIRM MODAL -->
