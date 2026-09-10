<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class WarrantyRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'duration_value'       => ['required', 'integer', 'min:1'],
            'duration_unit'        => ['required', 'in:day,month,year'],
            'warranty_provider_id' => ['nullable', 'integer', 'exists:warranty_providers,id'],
            'notes'                => ['nullable', 'string'],
        ];
    }
}