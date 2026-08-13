<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class InvoiceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'customer_id'    => ['nullable', 'integer', 'exists:customers,id'],
            'request_id'     => ['nullable', 'integer', 'exists:requests,id'],
            'invoice_number' => ['nullable', 'string', 'max:255'],
            'total_amount'   => ['nullable', 'numeric', 'min:0'],
            'is_confirmed'   => ['nullable', 'boolean'],
        ];
    }
}