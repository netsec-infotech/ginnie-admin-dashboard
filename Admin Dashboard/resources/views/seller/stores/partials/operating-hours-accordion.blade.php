<div class="mb-3">
    <label class="form-label required">{{ __('labels.store_timings') }}</label>
    <div class="form-text mb-2">
        {{ __('labels.operating_hours_help') }}
    </div>
    <div id="accordion-time-slot-config" class="accordion">
        @php
            $daysList = [
                'monday' => 'Monday',
                'tuesday' => 'Tuesday',
                'wednesday' => 'Wednesday',
                'thursday' => 'Thursday',
                'friday' => 'Friday',
                'saturday' => 'Saturday',
                'sunday' => 'Sunday'
            ];
            $operatingHours = $store->metadata['operating_hours'] ?? [];
        @endphp
        @foreach($daysList as $dayKey => $dayLabel)
            @php
                $dayConfig = $operatingHours[$dayKey] ?? [];
                $isActive = !isset($dayConfig['is_open']) || filter_var($dayConfig['is_open'], FILTER_VALIDATE_BOOLEAN);
                $openTime = $dayConfig['open_time'] ?? '09:00';
                $closeTime = $dayConfig['close_time'] ?? '21:00';
            @endphp
            <div class="accordion-item mb-2 border rounded">
                <h2 class="accordion-header d-flex align-items-center justify-content-between px-3 py-2 bg-light"
                    id="heading-{{ $dayKey }}">
                    <div class="form-check form-switch mb-0 d-flex align-items-center">
                        <input type="hidden" name="metadata[operating_hours][{{ $dayKey }}][day]" value="{{ $dayKey }}">
                        <input type="hidden" name="metadata[operating_hours][{{ $dayKey }}][is_open]" value="0">
                        <input class="form-check-input me-2 day-switch" type="checkbox" id="{{ $dayKey }}-switch"
                            name="metadata[operating_hours][{{ $dayKey }}][is_open]" value="1" {{ $isActive ? 'checked' : '' }} data-bs-toggle="collapse" data-bs-target="#collapse-{{ $dayKey }}"
                            aria-expanded="{{ $isActive ? 'true' : 'false' }}" aria-controls="collapse-{{ $dayKey }}">
                        <label class="form-check-label fw-bold mb-0" for="{{ $dayKey }}-switch" style="cursor: pointer;">
                            {{ $dayLabel }}
                        </label>
                    </div>
                    <button class="accordion-button collapsed py-1 px-2 w-auto shadow-none bg-transparent ms-auto"
                        type="button" data-bs-toggle="collapse" data-bs-target="#collapse-{{ $dayKey }}"
                        aria-expanded="{{ $isActive ? 'true' : 'false' }}" aria-controls="collapse-{{ $dayKey }}">
                    </button>
                </h2>
                <div id="collapse-{{ $dayKey }}" class="accordion-collapse collapse {{ $isActive ? 'show' : '' }}"
                    aria-labelledby="heading-{{ $dayKey }}" data-bs-parent="#accordion-time-slot-config">
                    <div class="accordion-body py-3 px-3">
                        <div class="row align-items-center">
                            <div class="col-sm-5 mb-2 mb-sm-0">
                                <label
                                    class="form-label small text-muted mb-1">{{ __('labels.open_time') ?? 'Open Time' }}</label>
                                <input type="time" class="form-control day-open-time"
                                    name="metadata[operating_hours][{{ $dayKey }}][open_time]" value="{{ $openTime }}" {{ !$isActive ? 'disabled' : '' }} />
                            </div>
                            <div class="col-sm-2 text-center text-muted fw-bold d-none d-sm-block">
                                to
                            </div>
                            <div class="col-sm-5">
                                <label
                                    class="form-label small text-muted mb-1">{{ __('labels.close_time') ?? 'Close Time' }}</label>
                                <input type="time" class="form-control day-close-time"
                                    name="metadata[operating_hours][{{ $dayKey }}][close_time]" value="{{ $closeTime }}" {{ !$isActive ? 'disabled' : '' }} />
                            </div>
                        </div>

                        <!-- Break Time Configurations (Scheduled Orders Only) -->
                        <div class="mt-3 border-top pt-2">
                            @php
                                $hasBreak = !empty($dayConfig['has_break']) && filter_var($dayConfig['has_break'], FILTER_VALIDATE_BOOLEAN);
                                $breakStart = $dayConfig['break_start_time'] ?? '13:00';
                                $breakEnd = $dayConfig['break_end_time'] ?? '14:00';
                            @endphp
                            <div class="form-check form-switch mb-1">
                                <input type="hidden" name="metadata[operating_hours][{{ $dayKey }}][has_break]" value="0">
                                <input class="form-check-input break-switch" type="checkbox" id="{{ $dayKey }}-break-switch"
                                    name="metadata[operating_hours][{{ $dayKey }}][has_break]" value="1" {{ $hasBreak ? 'checked' : '' }}
                                    {{ !$isActive ? 'disabled' : '' }}>
                                <label class="form-check-label small fw-bold text-muted" for="{{ $dayKey }}-break-switch">
                                    Set Break / Lunch Hours (Scheduled Orders Only)
                                </label>
                            </div>
                            <div class="form-text small text-muted mb-2">
                                No scheduled delivery slots will be generated during this window. Regular instant deliveries are unaffected.
                            </div>
                            
                            <div class="row align-items-center break-inputs-container" id="break-inputs-{{ $dayKey }}" style="{{ $hasBreak && $isActive ? '' : 'display: none;' }}">
                                <div class="col-sm-5 mb-2 mb-sm-0">
                                    <label class="form-label small text-muted mb-1">Break Start</label>
                                    <input type="time" class="form-control day-break-start"
                                        name="metadata[operating_hours][{{ $dayKey }}][break_start_time]" value="{{ $breakStart }}"
                                        {{ !$isActive || !$hasBreak ? 'disabled' : '' }} />
                                </div>
                                <div class="col-sm-2 text-center text-muted fw-bold d-none d-sm-block">
                                    to
                                </div>
                                <div class="col-sm-5">
                                    <label class="form-label small text-muted mb-1">Break End</label>
                                    <input type="time" class="form-control day-break-end"
                                        name="metadata[operating_hours][{{ $dayKey }}][break_end_time]" value="{{ $breakEnd }}"
                                        {{ !$isActive || !$hasBreak ? 'disabled' : '' }} />
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        @endforeach
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', () => {
    // Handle break switch changes
    document.querySelectorAll('.break-switch').forEach(function(checkbox) {
        checkbox.addEventListener('change', function() {
            const dayKey = this.id.replace('-break-switch', '');
            const container = document.getElementById(`break-inputs-${dayKey}`);
            const inputs = container.querySelectorAll('input');
            if (this.checked) {
                container.style.display = 'flex';
                inputs.forEach(input => input.disabled = false);
            } else {
                container.style.display = 'none';
                inputs.forEach(input => input.disabled = true);
            }
        });
    });

    // Handle master day switch to disable break switches & inputs
    document.querySelectorAll('.day-switch').forEach(function(checkbox) {
        checkbox.addEventListener('change', function() {
            const dayKey = this.id.replace('-switch', '');
            const accordionItem = this.closest('.accordion-item');
            const openCloseInputs = accordionItem.querySelectorAll('.day-open-time, .day-close-time');
            const breakCheckbox = document.getElementById(`${dayKey}-break-switch`);
            const container = document.getElementById(`break-inputs-${dayKey}`);
            const breakInputs = container.querySelectorAll('input');
            
            if (this.checked) {
                openCloseInputs.forEach(input => input.disabled = false);
                breakCheckbox.disabled = false;
                if (breakCheckbox.checked) {
                    container.style.display = 'flex';
                    breakInputs.forEach(input => input.disabled = false);
                }
            } else {
                openCloseInputs.forEach(input => input.disabled = true);
                breakCheckbox.disabled = true;
                container.style.display = 'none';
                breakInputs.forEach(input => input.disabled = true);
            }
        });
    });
});
</script>
