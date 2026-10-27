<?php

namespace App\Http\Requests\Returns;

use App\Enums\Order\AdminLossBearerEnum;
use Illuminate\Foundation\Http\FormRequest;

/**
 * ==========================================================
 * Return Dispute System
 * Phase 4
 * Form Request for Admin Dispute Resolution
 * ==========================================================
 */
class AdminResolveReturnRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        // Handled by controller/route authorization, but return true here.
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'action' => [
                'required',
                'string',
                'in:' . implode(',', AdminLossBearerEnum::values()),
            ],
            'remarks' => [
                'required',
                'string',
                'max:1000',
            ],
            'resolution_photos' => [
                'nullable',
                'array',
            ],
            'resolution_photos.*' => [
                'image',
                'mimes:jpg,jpeg,png,webp',
                'max:10240',
            ],
        ];
    }
}
