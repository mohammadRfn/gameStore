<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ServiceTypeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name'        => ['required', 'string', 'max:255'],
            'platform'    => ['nullable', 'string'],
            'category'    => ['nullable', 'string'],
            'base_price'  => ['nullable', 'numeric'],
            'description' => ['nullable', 'string'],
            'is_active'   => ['required', 'boolean'],
        ];
    }
}
