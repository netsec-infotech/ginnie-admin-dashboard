<?php

namespace App\Http\Requests\Returns;

use Illuminate\Foundation\Http\FormRequest;

class StoreConfirmReturnRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        // ==========================================================
        // Return Dispute System
        // Phase 3
        // Seller Inspection & Dispute Escalation Validation
        // ==========================================================
        $isEscalate = $this->input('action') === 'escalate';

        return [
            'action' => ['required', 'string', 'in:approve,escalate'],
            'remarks' => [$isEscalate ? 'required' : 'nullable', 'string', 'max:1000'],
            'inspection_photos' => [$isEscalate ? 'required' : 'nullable', 'array', $isEscalate ? 'min:1' : 'nullable'],
            'inspection_photos.*' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:10240'],
            'dispute_reason' => [$isEscalate ? 'required' : 'nullable', 'string', 'max:255'],
        ];
    }

    public function messages(): array
    {
        return [
            'action.required' => 'The action field is required.',
            'action.in' => 'The selected action must be approve or escalate.',
            'remarks.required' => 'Remarks are required when escalating a dispute.',
            'inspection_photos.required' => 'At least one inspection photo is required when escalating a dispute.',
            'dispute_reason.required' => 'A dispute reason is required when escalating a dispute.',
        ];
    }

    public function attributes(): array
    {
        return [
            'action' => 'decision action',
            'remarks' => 'seller remarks',
            'inspection_photos' => 'inspection photos',
            'dispute_reason' => 'dispute reason',
        ];
    }
}
