<?php

namespace App\Http\Requests\Pos;

use Illuminate\Foundation\Http\FormRequest;

class SearchPosProductRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'store_id'             => ['required', 'integer', 'exists:stores,id'],
            'q'                    => ['nullable', 'string', 'max:255'],
            'per_page'             => ['nullable', 'integer', 'min:1', 'max:100'],
            'include_out_of_stock' => ['nullable'],
            'category_id'          => ['nullable', 'integer', 'exists:categories,id'],
        ];
    }

    public function attributes(): array
    {
        return [
            'store_id'             => 'store',
            'q'                    => 'search term',
            'per_page'             => 'items per page',
            'include_out_of_stock' => 'include out-of-stock',
            'category_id'          => 'category',
        ];
    }
}
