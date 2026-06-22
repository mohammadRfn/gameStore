<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class PlanRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name'                  => ['required', 'string', 'max:255'],
            'code'                  => ['required', 'string', 'max:100'],
            'monthly_price'         => ['required', 'numeric', 'min:0'],
            'yearly_price'          => ['nullable', 'numeric', 'min:0'],
            'has_inventory_module'  => ['required', 'boolean'],
            'has_services_module'   => ['required', 'boolean'],
            'has_analytics_module'  => ['required', 'boolean'],
            'max_users'             => ['nullable', 'integer', 'min:1'],
            'max_items'             => ['nullable', 'integer', 'min:1'],
            'is_active'             => ['required', 'boolean'],
        ];
    }
}
