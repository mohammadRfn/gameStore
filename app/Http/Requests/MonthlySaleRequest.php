<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class MonthlySaleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; 
    }

    public function rules(): array
    {
        return [
            'shop_id'            => ['required', 'integer', 'exists:shops,id'],
            'year'               => ['required', 'integer', 'min:2000'],
            'month'              => ['required', 'integer', 'between:1,12'],
            'total_invoices'     => ['required', 'integer'],
            'confirmed_invoices' => ['required', 'integer'],
            'total_revenue'      => ['required', 'numeric'],
            'products_revenue'   => ['required', 'numeric'],
            'services_revenue'   => ['required', 'numeric'],
            'total_cost'         => ['required', 'numeric'],
            'profit'             => ['required', 'numeric'],
            'unique_customers'   => ['required', 'integer'],
            'new_customers'      => ['required', 'integer'],
        ];
    }
}
