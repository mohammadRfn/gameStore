<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Contracts\Validation\Validator;

class ItemRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * قبل از validation، ارقام فارسی/عربی رو به انگلیسی تبدیل می‌کنیم
     * (مشکل رایج کیبورد موبایل روی input type="number")
     */
    protected function prepareForValidation(): void
    {
        if ($this->has('purchase_price')) {
            $this->merge([
                'purchase_price' => $this->normalizeDigits($this->input('purchase_price')),
            ]);
        }

        if ($this->has('sale_price')) {
            $this->merge([
                'sale_price' => $this->normalizeDigits($this->input('sale_price')),
            ]);
        }
    }

    private function normalizeDigits(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $persian = ['۰', '۱', '۲', '۳', '۴', '۵', '۶', '۷', '۸', '۹'];
        $arabic  = ['٠', '١', '٢', '٣', '٤', '٥', '٦', '٧', '٨', '٩'];
        $english = ['0', '1', '2', '3', '4', '5', '6', '7', '8', '9'];

        $value = str_replace($persian, $english, $value);
        $value = str_replace($arabic, $english, $value);

        return $value;
    }

    public function rules(): array
    {
        return [
            'name' => 'required|string|max:255',
            'purchase_price' => 'required|numeric|min:0',
            'sale_price'     => 'required|numeric|min:0',
            'description' => 'nullable|string|max:1000',
            'image' => 'nullable|image|mimes:jpeg,png,jpg,gif,svg|max:2048',
            'category_id'  => 'nullable|exists:categories,id',
            'tracks_stock' => 'boolean',
        ];
    }
    protected function failedValidation(Validator $validator)
    {
        \Log::error('=== VALIDATION FAILED ===', [
            'errors' => $validator->errors()->toArray(),
            'input'  => $this->all(),
        ]);

        parent::failedValidation($validator);
    }
}
