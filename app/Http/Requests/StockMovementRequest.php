<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StockMovementRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
          return [
            'shop_id'       => ['required', 'integer', 'exists:shops,id'],
            'item_id'       => ['required', 'integer', 'exists:items,id'],
            'movement_type' => ['required', 'in:in,out,adjust_in,adjust_out'],
            'quantity'      => ['required', 'integer', 'min:1'],
            'unit_cost'     => ['nullable', 'numeric', 'min:0'],
            'reason'        => ['nullable', 'string', 'max:255'],
            'note'          => ['nullable', 'string'],
        ];
    }
}
