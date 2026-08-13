<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class OrderItemRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'invoice_id'         => ['required', 'integer', 'exists:invoices,id'],
            'item_id'            => ['required', 'integer', 'exists:items,id'],
            'quantity'           => ['required', 'integer', 'min:1'],
            'image'              => ['nullable', 'image', 'max:4096'],
            'deduct_from_stock'  => ['nullable', 'boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'item_id.required'     => 'انتخاب محصول الزامی است.',
            'item_id.exists'       => 'محصول انتخاب‌شده معتبر نیست.',
            'quantity.required'    => 'تعداد الزامی است.',
            'quantity.min'         => 'تعداد باید حداقل ۱ باشد.',
            'invoice_id.required'  => 'فاکتور مشخص نشده است.',
            'invoice_id.exists'    => 'فاکتور معتبر نیست.',
        ];
    }
}
