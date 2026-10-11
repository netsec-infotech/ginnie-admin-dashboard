<div class="modal modal-blur fade" id="pickup-refund-modal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Return Items <span class="text-secondary fw-normal">#{{ $order['order_id'] }}</span></h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div id="pickup-refund-loading" class="text-secondary py-3">Loading…</div>
                <div id="pickup-refund-empty" class="empty py-4 d-none">
                    <p class="empty-title">Nothing to return</p>
                    <p class="empty-subtitle text-secondary">Every eligible item from this order has already been returned.</p>
                </div>
                <div id="pickup-refund-already" class="alert alert-info d-none mb-3"></div>
                <div id="pickup-refund-form-wrap" class="d-none">
                    <p class="text-secondary small mb-2">
                        Enter the quantity you want to return per item. This restores stock and records a refund audit trail. Please process the customer refund manually via the chosen method.
                    </p>
                    <div class="table-responsive">
                        <table class="table table-sm align-middle mb-0">
                            <thead>
                                <tr>
                                    <th>Item</th>
                                    <th class="text-center" style="width: 110px">Quantity</th>
                                    <th class="text-center" style="width: 130px">Return qty</th>
                                    <th class="text-end" style="width: 110px">Amount</th>
                                </tr>
                            </thead>
                            <tbody id="pickup-refund-rows"></tbody>
                        </table>
                    </div>
                    <div class="mt-3">
                        <label class="form-label">{{ __('labels.pos_refund_method_label') }}</label>
                        <div class="btn-group w-100" role="group" aria-label="Refund method">
                            <input type="radio" class="btn-check" name="pickup-refund-method" id="pickup-refund-method-cash" value="cash" checked>
                            <label class="btn btn-outline-secondary" for="pickup-refund-method-cash">{{ __('labels.pos_refund_cash') }}</label>
                            <input type="radio" class="btn-check" name="pickup-refund-method" id="pickup-refund-method-other" value="other">
                            <label class="btn btn-outline-secondary" for="pickup-refund-method-other">{{ __('labels.pos_refund_other') }}</label>
                        </div>
                        <input type="text" id="pickup-refund-method-note" class="form-control form-control-sm mt-2" maxlength="200" placeholder="{{ __('labels.pos_refund_note_placeholder') }}">
                    </div>
                    <div class="mt-3">
                        <label for="pickup-refund-reason" class="form-label">{{ __('labels.pos_refund_reason_label') }} <span class="text-secondary">({{ __('labels.pos_refund_reason_hint') }})</span></label>
                        <textarea id="pickup-refund-reason" class="form-control" rows="2" maxlength="500" placeholder="{{ __('labels.pos_refund_reason_placeholder') }}"></textarea>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <div class="me-auto">
                    <span class="text-secondary small">Refund total</span>
                    <span class="h4 mb-0 ms-2" id="pickup-refund-total">{{ $systemSettings['currencySymbol'] }} 0.00</span>
                </div>
                <button type="button" class="btn" data-bs-dismiss="modal">Cancel</button>
                <button type="button" id="pickup-refund-submit" class="btn btn-danger" disabled>
                    Process Return
                </button>
            </div>
        </div>
    </div>
</div>
