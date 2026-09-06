<?php

declare(strict_types=1);

use Modules\Setting\Enums\Settings\BackupSchedule;
use Modules\Setting\Enums\Settings\CalendarType;
use Modules\Setting\Enums\Settings\PaperSize;
use Modules\Setting\Enums\Settings\PriceDisplayMode;
use Modules\Setting\Enums\Settings\PrinterType;
use Modules\Setting\Enums\Settings\SettingGroup;
use Modules\Setting\Enums\Settings\TaxMode;
use Modules\Setting\Enums\Settings\ThemeMode;
use Modules\Setting\Enums\Settings\TimeFormat;
use Modules\Setting\Services\Setting\SettingService;

/*
|--------------------------------------------------------------------------
| توابع کمکی تنظیمات (Setting Helpers)
|--------------------------------------------------------------------------
|
| این فایل توابع سراسری برای دسترسی سریع به تنظیمات سیستم ارائه می‌دهد.
| همه‌ی این توابع از SettingService استفاده می‌کنند و مقدار cache شده
| را برمی‌گردانند، بنابراین استفاده‌ی مکرر از آن‌ها مشکلی از نظر پرفورمنس ندارد.
|
| نکته: برای دسترسی مستقیم‌تر به SettingService از helper() یا resolve() استفاده کنید:
|   resolve(SettingService::class)->get('general.calendar')
|
*/

if (! function_exists('setting')) {
    /**
     * گرفتن مقدار یک تنظیم یا کل سرویس تنظیمات.
     *
     * استفاده‌ها:
     *   setting()                               -> SettingService instance
     *   setting('general.calendar')             -> مقدار این کلید
     *   setting('general.calendar', 'jalali')   -> مقدار با fallback
     *
     * @return SettingService|mixed
     */
    function setting(?string $key = null, mixed $default = null): mixed
    {
        /** @var SettingService $service */
        $service = app(SettingService::class);

        if ($key === null) {
            return $service;
        }

        return $service->get($key, $default);
    }
}

if (! function_exists('setting_group')) {
    /**
     * گرفتن همه‌ی تنظیمات یک گروه به‌صورت آرایه.
     *
     * @return array<string,mixed>
     */
    function setting_group(SettingGroup|string $group): array
    {
        $g = $group instanceof SettingGroup ? $group : SettingGroup::from($group);
        return app(SettingService::class)->getGroup($g);
    }
}

if (! function_exists('setting_set')) {
    /**
     * تنظیم یک کلید.
     */
    function setting_set(string $key, mixed $value): void
    {
        app(SettingService::class)->set($key, $value);
    }
}

if (! function_exists('setting_set_many')) {
    /**
     * تنظیم چند کلید به‌صورت اتمی.
     *
     * @param array<string,mixed> $values
     */
    function setting_set_many(array $values): void
    {
        app(SettingService::class)->updateMany($values);
    }
}

if (! function_exists('setting_flush_cache')) {
    /**
     * پاک‌سازی دستی cache تنظیمات.
     */
    function setting_flush_cache(): void
    {
        app(SettingService::class)->flushCache();
    }
}

/*
|--------------------------------------------------------------------------
| توابع کمکی مخصوص دامنه (Domain Helpers)
|--------------------------------------------------------------------------
*/

if (! function_exists('app_calendar')) {
    /**
     * تقویم فعلی اپ.
     */
    function app_calendar(): CalendarType
    {
        return app(SettingService::class)->calendar();
    }
}

if (! function_exists('app_time_format')) {
    /**
     * فرمت ساعت فعلی.
     */
    function app_time_format(): TimeFormat
    {
        return app(SettingService::class)->timeFormat();
    }
}

if (! function_exists('app_theme')) {
    /**
     * تم ظاهری فعلی.
     */
    function app_theme(): ThemeMode
    {
        return app(SettingService::class)->theme();
    }
}

if (! function_exists('app_currency')) {
    /**
     * واحد پول فعلی.
     */
    function app_currency(): string
    {
        return app(SettingService::class)->currency();
    }
}

if (! function_exists('app_currency_code')) {
    /**
     * کد ارز فعلی (ISO).
     */
    function app_currency_code(): string
    {
        return app(SettingService::class)->currencyCode();
    }
}

if (! function_exists('app_price_display')) {
    /**
     * نحوه‌ی نمایش قیمت.
     */
    function app_price_display(): PriceDisplayMode
    {
        return app(SettingService::class)->priceDisplay();
    }
}

if (! function_exists('format_price')) {
    /**
     * فرمت قیمت به‌صورت رشته با رعایت جداکننده‌ها و واحد.
     *
     * @example format_price(1200000) -> "۱٬۲۰۰٬۰۰۰ تومان"
     */
    function format_price(int|float $amount, ?string $locale = null): string
    {
        return app(SettingService::class)->formatPrice($amount, $locale);
    }
}

if (! function_exists('app_tax_rate')) {
    /**
     * نرخ مالیات فعلی (درصد).
     */
    function app_tax_rate(): float
    {
        return app(SettingService::class)->taxRate();
    }
}

if (! function_exists('app_tax_mode')) {
    /**
     * نحوه‌ی محاسبه‌ی مالیات (inclusive/exclusive).
     */
    function app_tax_mode(): TaxMode
    {
        return app(SettingService::class)->taxMode();
    }
}

if (! function_exists('app_tax_enabled')) {
    /**
     * آیا مالیات فعال است؟
     */
    function app_tax_enabled(): bool
    {
        return app(SettingService::class)->taxEnabled();
    }
}

if (! function_exists('calculate_tax')) {
    /**
     * محاسبه‌ی مبلغ مالیات برای یک قیمت.
     */
    function calculate_tax(int|float $amount): float
    {
        return app(SettingService::class)->calculateTax($amount);
    }
}

if (! function_exists('price_with_tax')) {
    /**
     * مبلغ نهایی شامل مالیات.
     */
    function price_with_tax(int|float $amount): float
    {
        return app(SettingService::class)->priceWithTax($amount);
    }
}

if (! function_exists('next_invoice_number')) {
    /**
     * تولید شماره فاکتور بعدی با پیشوند و padding مطابق تنظیمات.
     *
     * @param bool $increment افزایش شمارنده؟ (پیش‌فرض: بله)
     */
    function next_invoice_number(bool $increment = true): string
    {
        return app(SettingService::class)->nextInvoiceNumber($increment);
    }
}

if (! function_exists('format_date_app')) {
    /**
     * فرمت تاریخ با توجه به تقویم فعال.
     * برای تقویم جلالی از Morilog\\Jalali استفاده می‌کند.
     */
    function format_date_app(\DateTimeInterface|string $date, ?string $format = null): string
    {
        return app(SettingService::class)->formatDate($date, $format);
    }
}

if (! function_exists('format_time_app')) {
    /**
     * فرمت ساعت با توجه به تنظیمات.
     */
    function format_time_app(\DateTimeInterface|string $date, ?string $format = null): string
    {
        return app(SettingService::class)->formatTime($date, $format);
    }
}

/*
|--------------------------------------------------------------------------
| توابع چاپگر
|--------------------------------------------------------------------------
*/

if (! function_exists('default_printer_type')) {
    /**
     * نوع چاپگر پیش‌فرض (حرارتی/A4/لیبل).
     */
    function default_printer_type(): PrinterType
    {
        return app(SettingService::class)->defaultPrinterType();
    }
}

if (! function_exists('default_paper_size')) {
    /**
     * اندازه‌ی کاغذ پیش‌فرض.
     */
    function default_paper_size(): PaperSize
    {
        return app(SettingService::class)->defaultPaperSize();
    }
}

if (! function_exists('default_printer_name')) {
    /**
     * نام چاپگر پیش‌فرض سیستم (برای اپ دسکتاپ).
     */
    function default_printer_name(): ?string
    {
        $name = setting('desktop.default_printer_name');
        return is_string($name) && $name !== '' ? $name : null;
    }
}

/*
|--------------------------------------------------------------------------
| توابع مخصوص دسکتاپ
|--------------------------------------------------------------------------
*/

if (! function_exists('is_desktop_app')) {
    /**
     * تشخیص اجرای اپ در محیط دسکتاپ (NativePHP/Electron).
     */
    function is_desktop_app(): bool
    {
        if (request()->header('X-NativePHP') === '1') {
            return true;
        }
        return env('NATIVEPHP_RUNNING', false) === true
            || env('IS_DESKTOP', false) === true;
    }
}

if (! function_exists('auto_launch_enabled')) {
    /**
     * آیا اجرای خودکار در startup فعال است؟
     */
    function auto_launch_enabled(): bool
    {
        return app(SettingService::class)->autoLaunch();
    }
}

if (! function_exists('minimize_to_tray_enabled')) {
    /**
     * آیا کوچک‌شدن به Tray فعال است؟
     */
    function minimize_to_tray_enabled(): bool
    {
        return app(SettingService::class)->minimizeToTray();
    }
}

if (! function_exists('backup_schedule')) {
    /**
     * زمان‌بندی بکاپ خودکار.
     */
    function backup_schedule(): BackupSchedule
    {
        return app(SettingService::class)->backupSchedule();
    }
}

if (! function_exists('database_path_desktop')) {
    /**
     * مسیر فایل دیتابیس دسکتاپ (یا null برای مسیر پیش‌فرض).
     */
    function database_path_desktop(): ?string
    {
        $path = setting('desktop.database_path');
        return is_string($path) && $path !== '' ? $path : null;
    }
}

if (! function_exists('backup_path_desktop')) {
    /**
     * مسیر ذخیره‌ی بکاپ‌ها (یا null برای مسیر پیش‌فرض).
     */
    function backup_path_desktop(): ?string
    {
        $path = setting('desktop.backup_path');
        return is_string($path) && $path !== '' ? $path : null;
    }
}

if (! function_exists('update_server_url')) {
    /**
     * آدرس سرور آپدیت خودکار.
     */
    function update_server_url(): ?string
    {
        $url = setting('desktop.auto_update_url');
        return is_string($url) && $url !== '' ? $url : null;
    }
}

/*
|--------------------------------------------------------------------------
| توابع متفرقه
|--------------------------------------------------------------------------
*/

if (! function_exists('invoice_footer_text')) {
    /**
     * متن پاورقی پیش‌فرض فاکتور.
     */
    function invoice_footer_text(): string
    {
        return setting('invoice.footer_text', '');
    }
}

if (! function_exists('warranty_terms')) {
    /**
     * شرایط گارانتی پیش‌فرض.
     */
    function warranty_terms(): string
    {
        return setting('invoice.warranty_terms', '');
    }
}

if (! function_exists('business_registration_number')) {
    /**
     * شماره ثبت رسمی کسب‌وکار.
     */
    function business_registration_number(): ?string
    {
        $val = setting('invoice.business_registration');
        return is_string($val) && $val !== '' ? $val : null;
    }
}

if (! function_exists('economic_code')) {
    /**
     * کد اقتصادی.
     */
    function economic_code(): ?string
    {
        $val = setting('invoice.economic_code');
        return is_string($val) && $val !== '' ? $val : null;
    }
}

if (! function_exists('restart_required')) {
    /**
     * آیا اپ نیاز به restart دارد؟
     */
    function restart_required(): bool
    {
        return (bool) \Illuminate\Support\Facades\Cache::get('desktop.restart_required', false);
    }
}
