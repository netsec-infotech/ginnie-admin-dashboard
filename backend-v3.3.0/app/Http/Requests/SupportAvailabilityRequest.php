<?php

namespace App\Http\Requests;

use App\Enums\WeekDayEnum;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SupportAvailabilityRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $rules = [
            'enabled' => ['required', 'boolean'],
            'days' => ['exclude_if:enabled,false', 'required', 'array', 'min:1', 'max:7'],
            'days.*' => ['required', 'string', 'distinct', Rule::in(WeekDayEnum::values())],
            'hours' => ['exclude_if:enabled,false', 'required', 'array:'.implode(',', WeekDayEnum::values())],
            'message' => ['exclude_if:enabled,false', 'required', 'string', 'max:2000'],
        ];

        $days = $this->input('days', []);
        foreach (WeekDayEnum::values() as $day) {
            $excluded = ! $this->boolean('enabled') || ! is_array($days) || ! in_array($day, $days, true);
            $rules["hours.$day"] = [Rule::excludeIf($excluded), 'required', 'array:start_time,end_time'];
            $rules["hours.$day.start_time"] = [Rule::excludeIf($excluded), 'required', 'date_format:H:i'];
            $rules["hours.$day.end_time"] = [Rule::excludeIf($excluded), 'required', 'date_format:H:i', "after:hours.$day.start_time"];
        }

        return $rules;
    }

    protected function prepareForValidation(): void
    {
        if (! $this->has('hours') && $this->has(['start_time', 'end_time'])) {
            $this->merge(['hours' => array_fill_keys(WeekDayEnum::values(), [
                'start_time' => $this->input('start_time'),
                'end_time' => $this->input('end_time'),
            ])]);
        }
    }
}
