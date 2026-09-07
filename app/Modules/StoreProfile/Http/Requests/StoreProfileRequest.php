<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreProfileRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $profileId = $this->route('id');

        return [
            'legal_name'        => ['required', 'string', 'max:255'],
            'brand_name'        => ['nullable', 'string', 'max:255'],
            'slug'              => ['required', 'string', 'max:255', 'alpha_dash', Rule::unique('store_profiles', 'slug')->ignore($profileId)],
            'tax_id'            => ['nullable', 'string', 'max:50'],
            'registration_no'   => ['nullable', 'string', 'max:50'],
            'founding_date'     => ['nullable', 'date'],
            'phone'             => ['nullable', 'string', 'max:50'],
            'secondary_phone'   => ['nullable', 'string', 'max:50'],
            'email'             => ['nullable', 'email', 'max:255'],
            'website'           => ['nullable', 'url', 'max:255'],
            'instagram'         => ['nullable', 'string', 'max:255'],
            'telegram'          => ['nullable', 'string', 'max:255'],
            'address_street'    => ['nullable', 'string', 'max:255'],
            'address_city'      => ['nullable', 'string', 'max:100'],
            'address_province'  => ['nullable', 'string', 'max:100'],
            'address_postal'    => ['nullable', 'string', 'max:20'],
            'address_country'   => ['nullable', 'string', 'max:100'],
            'owner_first_name'  => ['nullable', 'string', 'max:100'],
            'owner_last_name'   => ['nullable', 'string', 'max:100'],
            'owner_national_id' => ['nullable', 'string', 'max:20'],
            'owner_phone'       => ['nullable', 'string', 'max:50'],
            'owner_email'       => ['nullable', 'email', 'max:255'],
            'currency_code'     => ['nullable', 'string', 'size:3'],
            'currency_symbol'   => ['nullable', 'string', 'max:10'],
            'fiscal_year_start' => ['nullable', 'integer', 'between:1,12'],
            'logo_path'         => ['nullable', 'string', 'max:500'],
            'cover_path'        => ['nullable', 'string', 'max:500'],
            'receipt_footer'    => ['nullable', 'string', 'max:500'],
            'working_hours'     => ['nullable', 'array'],
            'is_primary'        => ['nullable', 'boolean'],
            'status'            => ['nullable', Rule::in(['active', 'inactive', 'pending'])],
        ];
    }

    public function messages(): array
    {
        return [
            'legal_name.required'  => 'نام حقوقی فروشگاه الزامی است.',
            'slug.required'        => 'شناسه (slug) فروشگاه الزامی است.',
            'slug.unique'          => 'این شناسه قبلاً ثبت شده است.',
            'slug.alpha_dash'      => 'شناسه فقط می‌تواند شامل حروف، عدد، خط تیره و زیرخط باشد.',
            'fiscal_year_start.between' => 'ماه شروع سال مالی باید بین ۱ تا ۱۲ باشد.',
        ];
    }
}
