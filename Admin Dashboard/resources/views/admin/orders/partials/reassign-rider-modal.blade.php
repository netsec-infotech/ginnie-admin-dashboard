<div class="modal modal-blur fade" id="adminReassignRiderModal" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered" role="document">
        <form id="adminReassignRiderForm">
            @csrf
            <div class="modal-content">
                <div class="modal-status bg-info"></div>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                <div class="modal-body py-4">
                    <h3 class="mb-2">
                        <i class="ti ti-users me-1"></i>{{ __('labels.reassign_rider') }}
                    </h3>
                    <p class="text-secondary mb-3">
                        {{ __('labels.reassign_rider_help_text') }}
                    </p>

                    <div class="mb-3">
                        <label class="form-label" for="adminReassignDeliveryBoyId">
                            {{ __('labels.delivery_boy') }}
                        </label>
                        <select name="delivery_boy_id"
                                id="adminReassignDeliveryBoyId"
                                class="form-select"
                                placeholder="{{ __('labels.search_available_riders') }}">
                            <option value=""></option>
                        </select>
                        <small class="text-muted">{{ __('labels.leave_empty_to_unassign') }}</small>
                        <div class="invalid-feedback" data-field="delivery_boy_id"></div>
                    </div>

                    <div class="mb-1">
                        <label class="form-label required" for="adminReassignReason">
                            {{ __('labels.reason') }}
                        </label>
                        <textarea class="form-control"
                                  name="reason"
                                  id="adminReassignReason"
                                  rows="3"
                                  maxlength="1000"
                                  required
                                  placeholder="{{ __('labels.reassign_rider_reason_placeholder') }}"></textarea>
                        <div class="invalid-feedback" data-field="reason"></div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">
                        {{ __('labels.close') }}
                    </button>
                    <button type="submit" class="btn btn-info text-white" id="adminReassignRiderSubmit">
                        <i class="ti ti-users me-1"></i>{{ __('labels.reassign_rider') }}
                    </button>
                </div>
            </div>
        </form>
    </div>
</div>
