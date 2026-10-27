@props(['name' => 'schedule', 'schedule' => [], 'timezone' => null])
@php
    $scheduleId = 'weekly-schedule-'.str_replace(['[', ']', '.'], '-', $name ?: 'default');
    $fieldName = fn ($field) => $name === '' ? $field : $name.'['.$field.']';
@endphp
<div data-weekly-schedule {{ $attributes->class(['d-grid gap-3']) }}>
    <fieldset class="mb-0">
        <legend class="form-label">{{ __('Availability') }}</legend>
        <div class="d-flex flex-wrap gap-3">
            <label class="form-check mb-0">
                <input type="radio" name="{{ $fieldName('enabled') }}" value="1" class="form-check-input" data-schedule-mode @checked($schedule['enabled'] ?? true)>
                <span class="form-check-label">{{ __('Scheduled hours') }}</span>
            </label>
            <label class="form-check mb-0">
                <input type="radio" name="{{ $fieldName('enabled') }}" value="0" class="form-check-input" data-schedule-mode @checked(!($schedule['enabled'] ?? true))>
                <span class="form-check-label">{{ __('Always available (24/7)') }}</span>
            </label>
        </div>
    </fieldset>
    <fieldset data-schedule-fields class="mb-0 {{ !($schedule['enabled'] ?? true) ? 'd-none' : '' }}" @disabled(!($schedule['enabled'] ?? true))>
        <legend class="form-label">{{ __('Support days') }}</legend>
        <div class="d-flex gap-1 mb-2">
            @foreach(\App\Enums\WeekDayEnum::cases() as $day)
                <button type="button" class="btn btn-outline-primary flex-fill px-1" data-schedule-edit="{{ $day->value }}" aria-expanded="false" aria-controls="{{ $scheduleId }}-{{ $day->value }}" aria-label="{{ __('Edit hours for :day', ['day' => __(ucfirst($day->value))]) }}" aria-describedby="{{ $scheduleId }}-{{ $day->value }}-availability">
                    {{ __(ucfirst(substr($day->value, 0, 3))) }}
                    <span id="{{ $scheduleId }}-{{ $day->value }}-availability" class="visually-hidden" data-schedule-indicator data-available-label="{{ __('Available') }}" data-closed-label="{{ __('Closed') }}">{{ in_array($day->value, $schedule['days'] ?? [], true) ? __('Available') : __('Closed') }}</span>
                </button>
            @endforeach
        </div>
        <div class="form-hint">{{ __('Choose a day to edit its availability and hours.') }}</div>
        @foreach(\App\Enums\WeekDayEnum::cases() as $day)
            @php
                $selected = in_array($day->value, $schedule['days'] ?? [], true);
                $hours = $schedule['hours'][$day->value] ?? ['start_time' => '09:00', 'end_time' => '18:00'];
            @endphp
            <div id="{{ $scheduleId }}-{{ $day->value }}" class="border rounded-3 p-3 mt-3 d-none" data-schedule-row>
                <div class="d-flex align-items-center justify-content-between gap-3 mb-3">
                    <span class="fw-semibold">{{ __(ucfirst($day->value)) }}</span>
                    <label class="form-check form-switch mb-0">
                        <input type="checkbox" name="{{ $fieldName('days') }}[]" value="{{ $day->value }}" class="form-check-input" aria-label="{{ __(ucfirst($day->value)) }}" data-schedule-day @checked($selected)>
                        <span class="form-check-label">{{ __('Available') }}</span>
                    </label>
                </div>
                <div class="row g-3">
                    <div class="col-6 {{ !$selected ? 'd-none' : '' }}" data-schedule-input>
                        <label class="form-label" for="{{ $scheduleId }}-{{ $day->value }}-start">{{ __('Start time') }}</label>
                        <input id="{{ $scheduleId }}-{{ $day->value }}-start" type="time" name="{{ $fieldName('hours') }}[{{ $day->value }}][start_time]" class="form-control" data-schedule-time value="{{ $hours['start_time'] }}" @required($selected) @disabled(!$selected)>
                    </div>
                    <div class="col-6 {{ !$selected ? 'd-none' : '' }}" data-schedule-input>
                        <label class="form-label" for="{{ $scheduleId }}-{{ $day->value }}-end">{{ __('End time') }}</label>
                        <input id="{{ $scheduleId }}-{{ $day->value }}-end" type="time" name="{{ $fieldName('hours') }}[{{ $day->value }}][end_time]" class="form-control" data-schedule-time data-schedule-end data-time-error="{{ __('End time must be after start time.') }}" value="{{ $hours['end_time'] }}" @required($selected) @disabled(!$selected)>
                    </div>
                    <div class="col text-secondary small {{ $selected ? 'd-none' : '' }}" data-schedule-closed>{{ __('Support is unavailable on this day.') }}</div>
                </div>
            </div>
        @endforeach
        <div class="text-danger small d-none" data-schedule-error role="status">{{ __('Select at least one day, or choose Always available (24/7).') }}</div>
        <div class="text-secondary small mt-2">{{ __('Timezone') }}: {{ $timezone ?? config('app.timezone') }}</div>
    </fieldset>
    <div class="text-secondary small {{ ($schedule['enabled'] ?? true) ? 'd-none' : '' }}" data-schedule-always>{{ __('Available every day, at any time. Your saved hours are kept for later.') }}</div>
</div>
@once
    @push('scripts')
        <script src="{{ asset('assets/js/weekly-schedule.js') }}?v={{ filemtime(public_path('assets/js/weekly-schedule.js')) }}" defer></script>
    @endpush
@endonce
