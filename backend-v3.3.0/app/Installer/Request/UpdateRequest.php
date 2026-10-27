<?php

namespace App\Installer\Request;



class UpdateRequest extends CoreRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */

    public function authorize()
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function rules()
    {
        return [
            'hostname' => ['required', 'string', 'max:255', 'regex:/^[A-Za-z0-9._-]+$/'],
            'username' => ['required', 'string', 'max:255', 'regex:/^[^\r\n"\\\\]+$/'],
            'database' => ['required', 'string', 'max:64', 'regex:/^[A-Za-z0-9_-]+$/'],
            'password' => ['nullable', 'string', 'max:255', 'regex:/^[^\r\n]*$/'],
        ];
    }

}
