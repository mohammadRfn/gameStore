<?php

declare(strict_types=1);

namespace Modules\Setting\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Request اعتبارسنجی به‌روزرسانی تنظیمات.
 *
 * ساختار payload:
 * {
 *   "settings": {
 *     "general.calendar": "jalali",
 *     "invoice.tax_rate": 10
 *   }
 * }
 *
 * یا به‌صورت flat:
 * {
 *   "general.calendar": "jalali",
 *   "invoice.tax_rate": 10
 * }
 */
class UpdateSettingRequest extends FormRequest
{
    public function authorize(): bool
    {
        // فقط مدیران می‌توانند تنظیمات را تغییر دهند.
        // این شرط را بر اساس سیاست‌های اپلیکیشن خود تنظیم کنید.
        return true; // یا: return auth()->user()?->can('manage-settings');
    }

    /**
     * @return array<string,\Illuminate\Contracts\Validation\ValidationRule|array|string>
     */
    public function rules(): array
    {
        // اجازه می‌دهیم هم با کلید settings و هم flat payload ارسال شود.
        $settings = $this->input('settings', []);
        if (! is_array($settings) || empty($settings)) {
            // کلیدهای سطح root را به‌عنوان settings در نظر می‌گیریم.
            $settings = $this->except(['settings', '_token', '_method']);
        }

        // برای هر کلید، rules مخصوص خودش را از config می‌گیریم.
        $rules = [];
        $meta = config('setting.meta', []);

        foreach (array_keys($settings) as $key) {
            if (isset($meta[$key]) && ! empty($meta[$key]['rules'])) {
                $rules["settings.{$key}"] = $meta[$key]['rules'];
            } else {
                // کلید نامعتبر را reject می‌کنیم تا جلوی injection گرفته شود.
                $rules["settings.{$key}"] = ['prohibited'];
            }
        }

        if (empty($rules)) {
            $rules['settings'] = ['required', 'array', 'min:1'];
        }

        return $rules;
    }

    /**
     * پیام‌های خطای فارسی برای UX بهتر.
     */
    public function messages(): array
    {
        return [
            'settings.required' => 'هیچ تنظیمی برای به‌روزرسانی ارسال نشده است.',
            'settings.min' => 'حداقل یک تنظیم باید ارسال شود.',
            '*.prohibited' => 'کلید تنظیم نامعتبر است.',
        ];
    }

    /**
     * نام‌های attribute برای نمایش در پیام خطا.
     */
    public function attributes(): array
    {
        $attrs = [];
        $meta = config('setting.meta', []);
        foreach ($meta as $key => $entry) {
            $attrs["settings.{$key}"] = $entry['label'] ?? $key;
        }
        return $attrs;
    }
}
