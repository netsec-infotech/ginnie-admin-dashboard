@php
    $currentRider = $return->deliveryBoy;
@endphp
<div class="modal modal-blur fade" id="assignRiderModal" tabindex="-1" role="dialog" aria-hidden="true"
     data-assign-url="{{ route('admin.returns.assign-rider', $return->id) }}"
     data-search-url="{{ route('admin.delivery-boys.search') }}"
     data-zone-id="{{ $storeZoneId ?? '' }}"
     @if ($currentRider) data-current-rider-id="{{ $currentRider->id }}" data-current-rider-name="{{ $currentRider->name }}" @endif>
    <div class="modal-dialog modal-dialog-centered" role="document">
        <form id="assignRiderForm">
            @csrf
            <div class="modal-content">
                <div class="modal-status bg-primary"></div>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                <div class="modal-body py-4">
                    <h3 class="mb-2">
                        <i class="ti ti-users me-1"></i>{{ __('labels.assign_rider') }}
                    </h3>
                    <p class="text-secondary mb-3">{{ __('labels.assign_rider_help_text') }}</p>

                    <div class="mb-2">
                        <label class="form-label" for="assignRiderDeliveryBoyId">{{ __('labels.delivery_boy') }}</label>
                        <select name="delivery_boy_id" id="assignRiderDeliveryBoyId"
                                class="form-select"
                                placeholder="{{ __('labels.search_available_riders') }}">
                            <option value=""></option>
                        </select>
                        <small class="text-muted">{{ __('labels.leave_empty_to_unassign') }}</small>
                        <div class="invalid-feedback" data-field="delivery_boy_id"></div>
                    </div>

                    <div class="form-check mb-3">
                        <input class="form-check-input" type="checkbox" id="assignRiderAllZones">
                        <label class="form-check-label" for="assignRiderAllZones">{{ __('labels.all_zones') }}</label>
                    </div>

                    <div class="mb-1">
                        <label class="form-label required" for="assignRiderReason">{{ __('labels.reason') }}</label>
                        <textarea class="form-control" name="reason" id="assignRiderReason"
                                  rows="3" maxlength="1000" required></textarea>
                        <div class="invalid-feedback" data-field="reason"></div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">{{ __('labels.close') }}</button>
                    <button type="submit" class="btn btn-primary" id="assignRiderSubmit">
                        <i class="ti ti-users me-1"></i>{{ __('labels.assign_rider') }}
                    </button>
                </div>
            </div>
        </form>
    </div>
</div>
