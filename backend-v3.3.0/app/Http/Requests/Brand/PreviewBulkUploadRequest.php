<?php

namespace App\Http\Requests\Brand;

use Illuminate\Foundation\Http\FormRequest;

class PreviewBulkUploadRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // Controller does the policy check.
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $maxKb = (int) config('bulk_upload.max_file_kb', 10240);

        return [
            'csv_file' => ['required', 'file', 'mimes:csv,txt', 'max:' . $maxKb],
        ];
    }
}
