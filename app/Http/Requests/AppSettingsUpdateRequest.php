<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AppSettingsUpdateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'setting_value' => ['nullable', 'string'],
            'is_locked'     => ['sometimes', 'boolean'],
            'is_autoload'   => ['sometimes', 'boolean'],
            'description'   => ['nullable', 'string', 'max:1000'],
        ];
    }

    public function messages(): array
    {
        return [
            'setting_value.string' => 'مقدار تنظیم باید متنی باشد.',
        ];
    }

    protected function prepareForValidation(): void
    {
        foreach (['is_locked', 'is_autoload'] as $flag) {
            if ($this->has($flag)) {
                $this->merge([$flag => filter_var($this->input($flag), FILTER_VALIDATE_BOOL)]);
            }
        }
    }
}
