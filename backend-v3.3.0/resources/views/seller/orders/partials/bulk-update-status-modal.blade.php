{{-- Seller Bulk Update Status modal — same structure as the admin counterpart so
     both panels feel identical. The target-status dropdown is populated by JS
     from the intersection of allowed transitions across selected items. --}}
<div class="modal modal-blur fade" id="sellerBulkUpdateStatusModal" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered" role="document">
        <form id="sellerBulkUpdateStatusForm">
            @csrf
            <div class="modal-content">
                <div class="modal-status bg-primary"></div>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                <div class="modal-body py-4">
                    <h3 class="mb-2">
                        <i class="ti ti-refresh me-1"></i>{{ __('labels.bulk_update_status') }}
                    </h3>
                    <p class="text-secondary mb-3" id="sellerBulkSelectionSummary">
                        {{ __('labels.bulk_update_help_text') }}
                    </p>

                    <div class="mb-3">
                        <label class="form-label required" for="sellerBulkStatusSelect">
                            {{ __('labels.target_status') }}
                        </label>
                        <select class="form-select" id="sellerBulkStatusSelect" name="status" required>
                            <option value="">{{ __('labels.select_target_status') }}</option>
                        </select>
                        <small class="text-muted d-block mt-1" id="sellerBulkStatusHint" hidden>
                            {{ __('labels.bulk_no_common_actions') }}
                        </small>
                        <div class="invalid-feedback" data-field="status"></div>
                    </div>

                    {{-- Failure summary rendered after submit when any item rejects. --}}
                    <div class="mt-3" id="sellerBulkResultBlock" hidden>
                        <hr class="my-2">
                        <div class="fw-semibold mb-2">{{ __('labels.bulk_update_results') }}</div>
                        <ul class="list-unstyled small mb-0" id="sellerBulkResultList"></ul>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">
                        {{ __('labels.close') }}
                    </button>
                    <button type="submit" class="btn btn-primary" id="sellerBulkUpdateSubmit" disabled>
                        <i class="ti ti-refresh me-1"></i>{{ __('labels.apply') }}
                    </button>
                </div>
            </div>
        </form>
    </div>
</div>
