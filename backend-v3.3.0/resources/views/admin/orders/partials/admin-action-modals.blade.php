{{-- Admin status update confirmation modal --}}
<div class="modal modal-blur fade" id="adminStatusUpdateModal" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog modal-sm modal-dialog-centered" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">{{ __('labels.update_item_status') }}</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <p class="mb-3" id="adminStatusUpdateMessage"></p>

                {{-- Delivery fail reason (shown only for delivery_failed) --}}
                <div class="mb-3 d-none" id="adminDeliveryFailReasonGroup">
                    <label class="form-label required">{{ __('labels.delivery_fail_reason') }}</label>
                    <select class="form-select" id="adminDeliveryFailReason">
                        <option value="">{{ __('labels.select_reason') }}</option>
                        <option value="customer_unavailable">{{ __('labels.customer_unavailable') }}</option>
                        <option value="customer_refused">{{ __('labels.customer_refused') }}</option>
                        <option value="wrong_address">{{ __('labels.wrong_address') }}</option>
                        <option value="unsafe_location">{{ __('labels.unsafe_location') }}</option>
                    </select>
                </div>

                {{-- Optional remark --}}
                <div class="mb-3">
                    <label class="form-label">{{ __('labels.remark') }} <span class="text-muted">({{ __('labels.optional') }})</span></label>
                    <textarea class="form-control" id="adminStatusRemark" rows="2"
                              maxlength="1000"
                              placeholder="{{ __('labels.admin_remark_placeholder') }}"></textarea>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-ghost-secondary" data-bs-dismiss="modal">{{ __('labels.cancel') }}</button>
                <button type="button" class="btn btn-primary" id="adminStatusUpdateConfirm">
                    {{ __('labels.confirm') }}
                </button>
            </div>
        </div>
    </div>
</div>

{{-- Mark Payment Received confirmation modal --}}
<div class="modal modal-blur fade" id="adminMarkPaymentModal" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog modal-sm modal-dialog-centered" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">{{ __('labels.mark_payment_received') }}</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <p class="mb-3">{{ __('labels.mark_payment_received_confirm') }}</p>
                <div class="mb-3">
                    <label class="form-label">{{ __('labels.remark') }} <span class="text-muted">({{ __('labels.optional') }})</span></label>
                    <textarea class="form-control" id="adminPaymentRemark" rows="2"
                              maxlength="1000"
                              placeholder="{{ __('labels.admin_remark_placeholder') }}"></textarea>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-ghost-secondary" data-bs-dismiss="modal">{{ __('labels.cancel') }}</button>
                <button type="button" class="btn btn-warning" id="adminPaymentReceivedConfirm">
                    <i class="ti ti-cash me-1"></i>{{ __('labels.confirm') }}
                </button>
            </div>
        </div>
    </div>
</div>
