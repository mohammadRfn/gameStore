<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ShopRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name'            => ['required', 'string', 'max:255'],
            'slug'            => ['required', 'string', 'max:255'],
            'owner_user_id'   => ['nullable', 'integer', 'exists:users,id'],
            'plan_id'         => ['nullable', 'integer', 'exists:plans,id'],
            'is_active'       => ['required', 'boolean'],
            'subscription_ends_at' => ['nullable', 'date'],
            'settings'        => ['nullable', 'array'],
        ];
    }
}
