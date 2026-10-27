{{-- Admin Force Cancel modal. Backend at POST /admin/orders/{itemId}/force-cancel.
     If the picked item is currently COLLECTED, the service routes it through
     RETURNING_TO_STORE (rider physically returns); refund + restock fire
     when the seller hits Confirm Received. --}}
<div class="modal modal-blur fade" id="adminForceCancelModal" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered" role="document">
        <form id="adminForceCancelForm">
            @csrf
            <div class="modal-content">
                <div class="modal-status bg-danger"></div>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                <div class="modal-body py-4">
                    <h3 class="mb-2">
                        <i class="ti ti-ban me-1"></i>{{ __('labels.force_cancel_item') }}
                    </h3>
                    <p class="text-secondary mb-3">
                        {{ __('labels.force_cancel_help_text') }}
                    </p>

                    <div class="mb-3">
                        <label class="form-label required" for="adminForceCancelItemId">
                            {{ __('labels.order_item') }}
                        </label>
                        <select class="form-select" id="adminForceCancelItemId" required>
                            <option value="">{{ __('labels.select_order_item') }}</option>
                        </select>
                        <div class="invalid-feedback" data-field="order_item_id"></div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label required" for="adminForceCancelReason">
                            {{ __('labels.reason') }}
                        </label>
                        <textarea class="form-control"
                                  name="reason"
                                  id="adminForceCancelReason"
                                  rows="3"
                                  maxlength="1000"
                                  required
                                  placeholder="{{ __('labels.force_cancel_reason_placeholder') }}"></textarea>
                        <div class="invalid-feedback" data-field="reason"></div>
                    </div>

                    {{-- The pay_rider toggle was removed. Rider compensation is
                         no longer decided at force-cancel time — when the order
                         fully cancels, the rider's assignment flips to
                         CANCELLED_BY_ADMIN + payment_status PENDING, and admin
                         settles via the Settle Earnings panel on the Delivery card. --}}
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">
                        {{ __('labels.close') }}
                    </button>
                    <button type="submit" class="btn btn-danger" id="adminForceCancelSubmit">
                        <i class="ti ti-ban me-1"></i>{{ __('labels.force_cancel') }}
                    </button>
                </div>
            </div>
        </form>
    </div>
</div>
