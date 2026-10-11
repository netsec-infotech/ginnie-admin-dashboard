{{-- Confirm-receipt-of-returning-items modal. No body input — single-click
     confirmation. The form-id hooks into seller-orders.js. --}}
<div class="modal modal-blur fade"
     id="confirmReturnModal"
     tabindex="-1"
     role="dialog"
     aria-hidden="true">
    <div class="modal-dialog modal-sm modal-dialog-centered" role="document">
        <form id="confirmReturnForm">
            @csrf
            <div class="modal-content">
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                <div class="modal-status bg-success"></div>
                <div class="modal-body text-center py-4">
                    <i class="ti ti-package-import icon mb-2 text-success icon-lg"></i>
                    <h3>{{ __('labels.confirm_received') }}</h3>
                    <div class="text-secondary">
                        {{ __('labels.confirm_received_help_text') }}
                    </div>
                    <input type="hidden" name="order_item_id" id="confirmReturnId">
                </div>
                <div class="modal-footer">
                    <div class="w-100">
                        <div class="row">
                            <div class="col">
                                <button type="button" class="btn btn-outline-secondary w-100" data-bs-dismiss="modal">
                                    {{ __('labels.close') }}
                                </button>
                            </div>
                            <div class="col">
                                <button type="submit" class="btn btn-success w-100" id="confirmReturnBtn">
                                    {{ __('labels.confirm') }}
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </form>
    </div>
</div>
