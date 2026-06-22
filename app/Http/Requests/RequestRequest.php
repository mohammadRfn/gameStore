<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class RequestRequest extends FormRequest
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
            'customer_name' => 'required|string|max:255',
            'customer_id' => 'nullable|exists:customers,id',
            'category_id' => 'required|array', 
            'category_id.*' => 'exists:categories,id',
            'description' => 'required|string',
            'status' => 'required|string|in:new,processing,completed,canceled',
        ];
    }
}
