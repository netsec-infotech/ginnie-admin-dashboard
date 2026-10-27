{{-- Admin Settle Rider Earnings modal.
     One modal serves both Approve and Reject decisions — the button that
     triggers the modal seeds the hidden `decision` field via show.bs.modal
     and the header / status strip colour adjusts accordingly. Backend at
     POST /admin/orders/assignments/{assignmentId}/settle-rider-earnings. --}}
<div class="modal modal-blur fade" id="adminSettleRiderEarningsModal" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered" role="document">
        <form id="adminSettleRiderEarningsForm">
            @csrf
            <div class="modal-content">
                <div class="modal-status" id="adminSettleStatusStrip"></div>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                <div class="modal-body py-4">
                    <h3 class="mb-2">
                        <i class="ti ti-cash-banknote me-1"></i>
                        <span id="adminSettleHeading">{{ __('labels.settle_earnings') }}</span>
                    </h3>
                    <p class="text-secondary mb-3" id="adminSettleSubtext">
                        {{ __('labels.settle_help_text') }}
                    </p>

                    <div class="alert mb-3" id="adminSettleAmountAlert">
                        <div class="d-flex align-items-center justify-content-between">
                            <div class="fw-semibold pe-2">{{ __('labels.total_earnings') }} </div>
                            <div class="h3 mb-0" id="adminSettleAmount">—</div>
                        </div>
                    </div>

                    <input type="hidden" name="decision" id="adminSettleDecision" value="">
                    <input type="hidden" name="assignment_id" id="adminSettleAssignmentId" value="">

                    <div class="mb-1">
                        <label class="form-label required" for="adminSettleReason">
                            {{ __('labels.reason') }}
                        </label>
                        <textarea class="form-control"
                                  name="reason"
                                  id="adminSettleReason"
                                  rows="3"
                                  maxlength="1000"
                                  required
                                  placeholder="{{ __('labels.settle_reason_placeholder') }}"></textarea>
                        <div class="invalid-feedback" data-field="reason"></div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">
                        {{ __('labels.close') }}
                    </button>
                    <button type="submit" class="btn" id="adminSettleSubmit">
                        <i class="ti ti-check me-1"></i><span id="adminSettleSubmitLabel">{{ __('labels.settle_approve') }}</span>
                    </button>
                </div>
            </div>
        </form>
    </div>
</div>
