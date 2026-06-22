<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ServiceJobRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; 
    }

    public function rules(): array
    {
        return [
            'shop_id'         => ['required', 'integer', 'exists:shops,id'],
            'customer_id'     => ['nullable', 'integer', 'exists:customers,id'],
            'request_id'      => ['nullable', 'integer', 'exists:requests,id'],
            'service_type_id' => ['nullable', 'integer', 'exists:service_types,id'],

            'device_type'   => ['nullable', 'string', 'max:255'],
            'device_serial' => ['nullable', 'string', 'max:255'],

            'customer_problem_description' => ['nullable', 'string'],
            'diagnosis_description'        => ['nullable', 'string'],

            'status'        => ['nullable', 'string', 'max:32'],
            'invoice_id'    => ['nullable', 'integer', 'exists:invoices,id'],
            'estimated_price' => ['nullable', 'numeric', 'min:0'],
            'final_price'     => ['nullable', 'numeric', 'min:0'],

            'received_at'  => ['nullable', 'date'],
            'started_at'   => ['nullable', 'date'],
            'completed_at' => ['nullable', 'date'],
            'delivered_at' => ['nullable', 'date'],

            'items'                => ['nullable', 'array'],
            'items.*.item_id'      => ['required_with:items', 'integer', 'exists:items,id'],
            'items.*.quantity'     => ['required_with:items', 'integer', 'min:1'],
            'items.*.unit_price'   => ['required_with:items', 'numeric', 'min:0'],
        ];
    }
}
