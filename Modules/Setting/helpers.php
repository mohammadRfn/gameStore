<?php

declare(strict_types=1);

use Modules\Setting\Enums\Settings\SettingGroup;
use Modules\Setting\Enums\Settings\ThemeMode;
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
|   resolve(SettingService::class)->get('general.theme')
|
*/

if (! function_exists('setting')) {
    /**
     * گرفتن مقدار یک تنظیم یا کل سرویس تنظیمات.
     *
     * استفاده‌ها:
     *   setting()                               -> SettingService instance
     *   setting('general.theme')             -> مقدار این کلید
     *   setting('general.theme', 'dark')     -> مقدار با fallback
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

if (! function_exists('app_theme')) {
    /**
     * تم ظاهری فعلی.
     */
    function app_theme(): ThemeMode
    {
        return app(SettingService::class)->theme();
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