{{-- Cancel-after-accept modal. Triggered from the listing action dropdown
     and from the order detail page. Submits a free-text reason via axios
     through the page's seller-orders.js handler. --}}
<div class="modal modal-blur fade"
     id="cancelItemModal"
     tabindex="-1"
     role="dialog"
     aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered" role="document">
        <form id="cancelItemForm">
            @csrf
            <div class="modal-content">
                <div class="modal-status bg-danger"></div>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                <div class="modal-body py-4">
                    <h3 class="mb-2 text-center">{{ __('labels.cancel_item') }}</h3>
                    <p class="text-secondary text-center mb-3">
                        {{ __('labels.cancel_item_help_text') }}
                    </p>
                    <div class="mb-3">
                        <label class="form-label required" for="cancelItemReason">
                            {{ __('labels.reason') }}
                        </label>
                        <textarea id="cancelItemReason"
                                  name="reason"
                                  class="form-control"
                                  rows="3"
                                  maxlength="500"
                                  required
                                  placeholder="{{ __('labels.cancel_item_reason_placeholder') }}"></textarea>
                        <div class="invalid-feedback"></div>
                    </div>
                    <input type="hidden" name="order_item_id" id="cancelItemId">
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">
                        {{ __('labels.close') }}
                    </button>
                    <button type="submit" class="btn btn-danger" id="confirmCancelItem">
                        {{ __('labels.cancel_item') }}
                    </button>
                </div>
            </div>
        </form>
    </div>
</div>
