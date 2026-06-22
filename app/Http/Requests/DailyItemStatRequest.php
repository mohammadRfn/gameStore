<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class DailyItemStatRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'shop_id'       => ['required', 'integer', 'exists:shops,id'],
            'item_id'       => ['required', 'integer', 'exists:items,id'],
            'stat_date'     => ['required', 'date'],
            'opening_stock' => ['required', 'integer'],
            'closing_stock' => ['required', 'integer'],
            'sold_quantity' => ['required', 'integer'],
            'purchased_quantity' => ['required', 'integer'],
            'adjusted_in_quantity' => ['required', 'integer'],
            'adjusted_out_quantity' => ['required', 'integer'],
            'revenue'       => ['required', 'numeric'],
            'total_cost'    => ['required', 'numeric'],
            'profit'        => ['required', 'numeric'],
        ];
    }
}
