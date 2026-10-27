<!-- ==========================================================
     Return Dispute System
     Phase 3
     Seller Dispute Escalation Modal View
     ========================================================== -->
<div
    class="modal modal-blur fade"
    id="storeEscalateModel"
    tabindex="-1"
    role="dialog"
    aria-hidden="true"
>
    <div class="modal-dialog modal-dialog-centered" role="document">
        <div class="modal-content">
            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            <div class="modal-status bg-danger"></div>
            <div class="modal-body py-4">
                <div class="text-center mb-3">
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
                        class="icon mb-2 text-danger icon-lg"
                    >
                        <path d="M12 9v4" />
                        <path d="M12 17h.01" />
                        <path d="M5 19h14a2 2 0 0 0 1.84 -2.75L13.84 4.25a2 2 0 0 0 -3.68 0L3.16 16.25A2 2 0 0 0 5 19z" />
                    </svg>
                    <h3>Escalate to Support</h3>
                    <p class="text-secondary small">
                        Are you experiencing issues with this returned package? Escalate this request to Support. An administrator will review your evidence and make the final settlement decision.
                    </p>
                </div>

                <div class="mb-3">
                    <label class="form-label required" for="store_escalate_reason">Dispute Reason</label>
                    <select class="form-select" id="store_escalate_reason" name="dispute_reason" required>
                        <option value="">-- Select Reason --</option>
                        <option value="Wrong Product Returned">Wrong Product Returned</option>
                        <option value="Different Product">Different Product / Substituted</option>
                        <option value="Used/Damaged by Customer">Used or Damaged by Customer</option>
                        <option value="Empty Box">Empty Box</option>
                        <option value="Missing Items">Missing Items / Incomplete Return</option>
                        <option value="Tampered Package">Tampered Package</option>
                        <option value="Other">Other (Please describe below)</option>
                    </select>
                </div>

                <div class="mb-3">
                    <label class="form-label required" for="store_escalate_remarks">Seller Inspection Remarks</label>
                    <textarea class="form-control" id="store_escalate_remarks" name="remarks" rows="4" 
                              placeholder="Please describe in detail the condition of the package and why you are disputing this return." required></textarea>
                </div>

                <div class="mb-3">
                    <label class="form-label required" for="store_escalate_photos">Evidence Photos</label>
                    <input type="file" class="form-control" id="store_escalate_photos" name="inspection_photos[]" multiple accept="image/*" required>
                    <small class="text-muted d-block mt-1">At least one clear photo of the packaging and product is required as dispute evidence.</small>
                </div>
            </div>
            <div class="modal-footer">
                <div class="w-100">
                    <div class="row">
                        <div class="col">
                            <a href="#" class="btn btn-outline-secondary w-100" data-bs-dismiss="modal">Cancel</a>
                        </div>
                        <div class="col">
                            <button type="button" class="btn btn-danger w-100" id="confirmStoreEscalateBtn">
                                Submit Escalation
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
