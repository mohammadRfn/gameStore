<?php

declare(strict_types=1);

namespace Modules\Setting\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Validator;

/**
 * Request اعتبارسنجی به‌روزرسانی تنظیمات.
 *
 * ساختار payload:
 * {
 *   "settings": {
 *     "general.theme": "dark",
 *     "desktop.auto_launch": true
 *   }
 * }
 *
 * یا به‌صورت flat:
 * {
 *   "general.theme": "dark",
 *   "desktop.auto_launch": true
 * }
 *
 * نکتهٔ مهم: چون کلیدهای تنظیمات خودشان نقطه دارند (general.theme)،
 * نمی‌شود آن‌ها را مستقیماً اسم rule در لاراول قرار داد — لاراول نقطه را
 * همیشه جداکنندهٔ آرایهٔ تودرتو می‌خواند، نه بخشی از نام کلید. به همین
 * دلیل اعتبارسنجی هر کلید را دستی و با یک اسم فیلد امن (بدون نقطه)
 * انجام می‌دهیم (متد withValidator پایین‌تر).
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
     * پیش از اعتبارسنجی، payload فلت (بدون کلید settings) را به همان
     * ساختار settings:{...} نرمال می‌کنیم تا بقیهٔ کد یک ورودی یکنواخت ببیند.
     */
    protected function prepareForValidation(): void
    {
        $settings = $this->input('settings');
        if (! is_array($settings) || empty($settings)) {
            $flat = $this->except(['settings', '_token', '_method']);
            if (! empty($flat)) {
                $this->merge(['settings' => $flat]);
            }
        }
    }

    /**
     * فقط شکل کلی payload را اینجا چک می‌کنیم؛ اعتبارسنجی تک‌تک مقادیر
     * در withValidator انجام می‌شود تا مشکل dot-notation پیش نیاید.
     */
    public function rules(): array
    {
        return [
            'settings' => ['required', 'array', 'min:1'],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            $settings = $this->input('settings', []);
            if (! is_array($settings)) {
                return;
            }

            $meta = config('setting.meta', []);

            foreach ($settings as $key => $value) {
                if (! isset($meta[$key])) {
                    $validator->errors()->add("settings.{$key}", 'کلید تنظیم نامعتبر است.');
                    continue;
                }

                $rules = $meta[$key]['rules'] ?? [];
                if (empty($rules)) {
                    continue;
                }

                // اسم فیلد 'value' عمداً بدون نقطه است تا با Arr::get
                // (که نقطه را جداکنندهٔ تودرتو می‌خواند) تداخل نکند.
                $inner = Validator::make(['value' => $value], ['value' => $rules]);
                if ($inner->fails()) {
                    foreach ($inner->errors()->get('value') as $msg) {
                        $validator->errors()->add("settings.{$key}", $msg);
                    }
                }
            }
        });
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