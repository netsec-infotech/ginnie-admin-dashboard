{{-- Admin Bulk Update Status modal.
     Lets the admin pick one target status and apply it to all selected items
     in a single round-trip. The status dropdown is populated client-side from
     the intersection of allowed transitions on the selected items, so the user
     can't pick an action that's invalid for one of the rows. --}}
<div class="modal modal-blur fade" id="adminBulkUpdateStatusModal" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered" role="document">
        <form id="adminBulkUpdateStatusForm">
            @csrf
            <div class="modal-content">
                <div class="modal-status bg-primary"></div>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                <div class="modal-body py-4">
                    <h3 class="mb-2">
                        <i class="ti ti-refresh me-1"></i>{{ __('labels.bulk_update_status') }}
                    </h3>
                    <p class="text-secondary mb-3" id="adminBulkSelectionSummary">
                        {{ __('labels.bulk_update_help_text') }}
                    </p>

                    <div class="mb-3">
                        <label class="form-label required" for="adminBulkStatusSelect">
                            {{ __('labels.target_status') }}
                        </label>
                        <select class="form-select" id="adminBulkStatusSelect" name="status" required>
                            <option value="">{{ __('labels.select_target_status') }}</option>
                        </select>
                        <small class="text-muted d-block mt-1" id="adminBulkStatusHint" hidden>
                            {{ __('labels.bulk_no_common_actions') }}
                        </small>
                        <div class="invalid-feedback" data-field="status"></div>
                    </div>

                    {{-- Only visible when status === delivery_failed. --}}
                    <div class="mb-3" id="adminBulkFailReasonField" hidden>
                        <label class="form-label required" for="adminBulkFailReason">
                            {{ __('labels.delivery_fail_reason') }}
                        </label>
                        <select class="form-select"
                                id="adminBulkFailReason"
                                name="delivery_fail_reason">
                            <option value="">—</option>
                            <option value="customer_unavailable">{{ __('labels.customer_unavailable') }}</option>
                            <option value="customer_refused">{{ __('labels.customer_refused') }}</option>
                            <option value="wrong_address">{{ __('labels.wrong_address') }}</option>
                            <option value="unsafe_location">{{ __('labels.unsafe_location') }}</option>
                        </select>
                        <div class="invalid-feedback" data-field="delivery_fail_reason"></div>
                    </div>

                    <div class="mb-1">
                        <label class="form-label" for="adminBulkRemark">
                            {{ __('labels.remark') }}
                        </label>
                        <textarea class="form-control"
                                  name="remark"
                                  id="adminBulkRemark"
                                  rows="3"
                                  maxlength="1000"
                                  placeholder="{{ __('labels.bulk_remark_placeholder') }}"></textarea>
                        <div class="invalid-feedback" data-field="remark"></div>
                    </div>

                    {{-- Summary list rendered after submit when any items fail. --}}
                    <div class="mt-3" id="adminBulkResultBlock" hidden>
                        <hr class="my-2">
                        <div class="fw-semibold mb-2">{{ __('labels.bulk_update_results') }}</div>
                        <ul class="list-unstyled small mb-0" id="adminBulkResultList"></ul>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">
                        {{ __('labels.close') }}
                    </button>
                    <button type="submit" class="btn btn-primary" id="adminBulkUpdateSubmit" disabled>
                        <i class="ti ti-refresh me-1"></i>{{ __('labels.apply') }}
                    </button>
                </div>
            </div>
        </form>
    </div>
</div>
