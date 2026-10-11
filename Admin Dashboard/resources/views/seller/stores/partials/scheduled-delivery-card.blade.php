<div class="card mb-4" id="pills-scheduled-delivery" data-store-id="{{ $store->id }}">
    <div class="card-header d-flex justify-content-between align-items-center">
        <h4 class="card-title mb-0">
            <i class="ti ti-clock-hour-4 me-2 text-primary"></i>{{ __('labels.scheduled_delivery_slots') }}
        </h4>
        <div class="form-check form-switch mb-0">
            <input type="hidden" name="is_scheduled_delivery_enabled" value="0">
            <input class="form-check-input" type="checkbox" role="switch" id="is_scheduled_delivery_enabled"
                name="is_scheduled_delivery_enabled" value="1" {{ $store->is_scheduled_delivery_enabled ? 'checked' : '' }}>
            <label class="form-check-label fw-bold"
                for="is_scheduled_delivery_enabled">{{ __('labels.enable_scheduled_delivery') }}</label>
        </div>
    </div>

    <div id="scheduled_delivery_body" style="{{ $store->is_scheduled_delivery_enabled ? '' : 'display: none;' }}">
        <!-- Tabs Navigation -->
        <nav class="nav sd-tab-nav" role="tablist">
            <button class="nav-link active" id="nav-sd-settings-tab" data-bs-toggle="tab" data-bs-target="#nav-sd-settings" type="button" role="tab" aria-controls="nav-sd-settings" aria-selected="true">
                <i class="ti ti-settings me-1"></i> General Settings
            </button>
            <button class="nav-link" id="nav-sd-calendar-tab" data-bs-toggle="tab" data-bs-target="#nav-sd-calendar" type="button" role="tab" aria-controls="nav-sd-calendar" aria-selected="false">
                <i class="ti ti-calendar me-1"></i> Slot Overrides & Blocking
            </button>
        </nav>

        <div class="tab-content p-4">
            <!-- TAB 1: GENERAL SETTINGS -->
            <div class="tab-pane fade show active" id="nav-sd-settings" role="tabpanel" aria-labelledby="nav-sd-settings-tab">
                <div class="alert alert-info border-0 bg-info-lt mb-4 d-flex align-items-center" role="alert">
                    <i class="ti ti-info-circle fs-2 me-3"></i>
                    <div>
                        <strong>How Scheduled Delivery Works:</strong>
                        Dynamic virtual slots are automatically generated based on your store operating hours. Manage scheduling buffers, concurrency, and deadlines below.
                    </div>
                </div>

                <div class="row g-3 mb-4">
                    <div class="col-md-6">
                        <label class="form-label required">
                            Fulfillment Buffer Minutes
                            <x-form-info-tooltip :title="'Minimum preparation/delivery buffer in minutes required before a slot can be booked.'" />
                        </label>
                        <div class="input-group">
                            <span class="input-group-text"><i class="ti ti-hourglass"></i></span>
                            <input type="number" min="0" max="1440" class="form-control"
                                name="scheduled_delivery_buffer_minutes" placeholder="60"
                                value="{{ old('scheduled_delivery_buffer_minutes', $store->scheduled_delivery_buffer_minutes ?? 60) }}" />
                            <span class="input-group-text">mins</span>
                        </div>
                        @error('scheduled_delivery_buffer_minutes')
                            <div class="text-danger mt-1 small">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="col-md-6">
                        @php
                            $settingService = app(\App\Services\SettingService::class);
                            $settingsResource = $settingService->getSettingByVariable(\App\Enums\SettingTypeEnum::SCHEDULED_DELIVERY());
                            $settings = $settingsResource?->toArray(request())['value'] ?? [];
                            $globalMaxDays = (int) ($settings['maxAdvanceBookingDaysLimit'] ?? 7);
                        @endphp
                        <label class="form-label required">
                            Max Advance Booking Days
                            <x-form-info-tooltip :title="'How many days in advance customers are allowed to schedule their deliveries.'" />
                        </label>
                        <div class="input-group">
                            <span class="input-group-text"><i class="ti ti-calendar-event"></i></span>
                            <input type="number" min="1" max="{{ $globalMaxDays }}" class="form-control" name="max_advance_booking_days"
                                placeholder="{{ $globalMaxDays }}"
                                value="{{ min((int) old('max_advance_booking_days', $store->max_advance_booking_days ?? 7), $globalMaxDays) }}" />
                            <span class="input-group-text">days</span>
                        </div>
                        @error('max_advance_booking_days')
                            <div class="text-danger mt-1 small">{{ $message }}</div>
                        @enderror
                    </div>
                </div>

                <div class="row g-3 mb-4">
                    <div class="col-md-6">
                        <label class="form-label required">
                            Slot Duration
                            <x-form-info-tooltip :title="'The length of each delivery time window (60 minutes).'" />
                        </label>
                        <!-- By default, this generates 1 hour slots. Flexibility for other durations is disabled. -->
                        <input type="hidden" name="scheduled_delivery_slot_duration" value="60" />
                        <input type="text" class="form-control text-secondary" value="60 Minutes (1 Hour)" readonly disabled />
                        <small class="form-hint mt-1 text-secondary">
                            <i class="ti ti-info-circle me-1"></i> By default, this generates 1-hour slots.
                        </small>
                        @error('scheduled_delivery_slot_duration')
                            <div class="text-danger mt-1 small">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="col-md-6">
                        <label class="form-label required">
                            Default Slot Capacity
                            <x-form-info-tooltip :title="'Maximum number of orders allowed per individual delivery slot.'" />
                        </label>
                        <input type="number" min="1" class="form-control" name="scheduled_delivery_slot_capacity"
                            placeholder="10"
                            value="{{ old('scheduled_delivery_slot_capacity', $store->scheduled_delivery_slot_capacity ?? 10) }}" />
                        @error('scheduled_delivery_slot_capacity')
                            <div class="text-danger mt-1 small">{{ $message }}</div>
                        @enderror
                    </div>
                </div>

                <div class="row g-3 mb-4">
                    <div class="col-md-6">
                        <label class="form-label required">
                            Preparation Concurrency Limit
                            <x-form-info-tooltip :title="'Maximum number of scheduled orders this store can prepare or process at the same time.'" />
                        </label>
                        <input type="number" min="1" class="form-control" name="prep_concurrency"
                            placeholder="5"
                            value="{{ old('prep_concurrency', $store->prep_concurrency ?? 5) }}" />
                        @error('prep_concurrency')
                            <div class="text-danger mt-1 small">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="col-md-6">
                        <label class="form-label required">
                            Reschedule Deadline Hours
                            <x-form-info-tooltip :title="'Hours prior to the delivery slot start when reschedule choices freeze and auto-cancel kicks in.'" />
                        </label>
                        <div class="input-group">
                            <input type="number" min="1" class="form-control" name="reschedule_deadline_hours"
                                placeholder="24"
                                value="{{ old('reschedule_deadline_hours', $store->reschedule_deadline_hours ?? 24) }}" />
                            <span class="input-group-text">hours</span>
                        </div>
                        @error('reschedule_deadline_hours')
                            <div class="text-danger mt-1 small">{{ $message }}</div>
                        @enderror
                    </div>
                </div>

                <hr class="my-4">

                <!-- Temporary Closure Card Option -->
                <div class="card border-danger-subtle bg-danger-lt">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <div>
                                <h5 class="mb-1 text-danger fw-bold">
                                    <i class="ti ti-alert-triangle me-1"></i> Temporary Store Closure
                                </h5>
                                <p class="text-muted small mb-0">
                                    Unexpected emergency closures will stop new checkouts and flag affected future bookings.
                                </p>
                            </div>
                            <div class="form-check form-switch mb-0">
                                <input type="hidden" name="is_temporarily_closed" value="0">
                                <input class="form-check-input" type="checkbox" role="switch" id="is_temporarily_closed"
                                    name="is_temporarily_closed" value="1" {{ $store->is_temporarily_closed ? 'checked' : '' }}>
                            </div>
                        </div>

                        <div id="temporary_closure_reason_container" style="{{ $store->is_temporarily_closed ? '' : 'display: none;' }}">
                            <label class="form-label required">Closure Reason Message</label>
                            <textarea class="form-control" name="temporary_closure_reason" rows="2" placeholder="e.g. Closed for maintenance. We will reopen tomorrow.">{{ old('temporary_closure_reason', $store->temporary_closure_reason ?? '') }}</textarea>
                        </div>
                    </div>
                </div>
            </div>

            <!-- TAB 2: SLOT OVERRIDES & BLOCKING CALENDAR -->
            <div class="tab-pane fade" id="nav-sd-calendar" role="tabpanel" aria-labelledby="nav-sd-calendar-tab">
                <div class="row g-4">
                    <!-- Left: Interactive Calendar Grid -->
                    <div class="col-lg-7">
                        <div class="sd-calendar-container">
                            <div class="sd-calendar-header">
                                <button type="button" class="btn btn-icon btn-outline-secondary" id="sd-prev-month">
                                    <i class="ti ti-chevron-left"></i>
                                </button>
                                <h3 class="mb-0 fw-bold" id="sd-calendar-month-year">August 2026</h3>
                                <button type="button" class="btn btn-icon btn-outline-secondary" id="sd-next-month">
                                    <i class="ti ti-chevron-right"></i>
                                </button>
                            </div>
                            <div class="sd-calendar-grid" id="sd-calendar-days-grid">
                                <!-- Day Names Headers -->
                                <div class="sd-calendar-day-label">Mon</div>
                                <div class="sd-calendar-day-label">Tue</div>
                                <div class="sd-calendar-day-label">Wed</div>
                                <div class="sd-calendar-day-label">Thu</div>
                                <div class="sd-calendar-day-label">Fri</div>
                                <div class="sd-calendar-day-label">Sat</div>
                                <div class="sd-calendar-day-label">Sun</div>

                                <!-- Dynamic Calendar Days Grid will go here -->
                            </div>
                        </div>
                    </div>

                    <!-- Right: Slot overrides List and Form -->
                    <div class="col-lg-5">
                        <div class="sd-slots-panel">
                            <h4 class="fw-bold mb-1" id="sd-selected-date-title">Select a Date</h4>
                            <p class="text-muted small mb-3">Manage slot blocks and custom capacity overrides for this date.</p>
                            
                            <div id="sd-slots-list-loading" class="text-center py-5" style="display: none;">
                                <div class="spinner-border text-primary" role="status"></div>
                                <p class="text-muted mt-2">Loading slots...</p>
                            </div>

                            <div id="sd-slots-list-empty" class="text-center py-5 text-muted">
                                <i class="ti ti-calendar-event fs-1 mb-2 d-block text-secondary"></i>
                                Select a date on the calendar to manage its slots.
                            </div>

                            <div id="sd-slots-list-container" style="display: none;">
                                <!-- Dynamic slot override items cards will go here -->
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Modal for setting slot override capacity -->
<div class="modal fade" id="sdOverrideModal" tabindex="-1" aria-labelledby="sdOverrideModalLabel" aria-hidden="true" data-bs-backdrop="static">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title d-flex align-items-center" id="sdOverrideModalLabel">
                    <i class="ti ti-edit me-2"></i> Configure Custom Capacity
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-4">
                <input type="hidden" id="override_slot_start" value="">
                <input type="hidden" id="override_slot_end" value="">
                
                <div class="mb-3">
                    <label class="form-label fw-bold">Slot Time Window</label>
                    <input type="text" class="form-control bg-light" id="override_slot_time_display" readonly>
                </div>

                <div class="mb-3">
                    <label class="form-label required fw-bold">Custom Max Orders Capacity</label>
                    <input type="number" min="0" class="form-control" id="override_slot_capacity" value="{{ $store->scheduled_delivery_slot_capacity ?? 10 }}">
                    <div class="form-text">Set custom capacity for this slot on the selected day only.</div>
                </div>
            </div>
            <div class="modal-footer bg-light">
                <button type="button" class="btn btn-link link-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-primary d-flex align-items-center" id="btn-save-sd-override">
                    <i class="ti ti-device-floppy me-1"></i> Save Custom Capacity
                </button>
            </div>
        </div>
    </div>
</div>
