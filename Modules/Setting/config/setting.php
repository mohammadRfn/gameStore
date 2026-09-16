<?php

declare(strict_types=1);

use Modules\Setting\Enums\Settings\CalendarType;
use Modules\Setting\Enums\Settings\PriceDisplayMode;
use Modules\Setting\Enums\Settings\SettingGroup;
use Modules\Setting\Enums\Settings\ThemeMode;
use Modules\Setting\Enums\Settings\TimeFormat;

/**
 * پیکربندی مرکزی ماژول تنظیمات.
 *
 * این فایل دو وظیفه دارد:
 *  1) تعریف «متادیتای» هر تنظیم (کلید، نوع، گروه، اعتبارسنجی، مقدار پیش‌فرض).
 *  2) نگه‌داری مقادیر پیش‌فرض به‌عنوان single source of truth؛ در نبود مقدار
 *     ذخیره‌شده در دیتابیس، همین مقادیر استفاده می‌شوند.
 *
 * نکته‌ی مهم: مقادیر «واقعی» در جدول settings نگهداری می‌شوند، نه در این فایل.
 * این فایل فقط ساختار و defaults را توصیف می‌کند.
 */
return [

    /*
    |--------------------------------------------------------------------------
    | ران Driver برای ذخیره‌سازی
    |--------------------------------------------------------------------------
    |
    | - database: تنظیمات در جدول `settings` ذخیره می‌شوند (پیشنهادی).
    | - file:     در فایل bootstrap/cache/settings.php (فقط در حالت read-only).
    |
    */
    'driver' => env('SETTING_DRIVER', 'database'),

    /*
    |--------------------------------------------------------------------------
    | کلید کش تنظیمات
    |--------------------------------------------------------------------------
    */
    'cache' => [
        'enabled' => (bool) env('SETTING_CACHE_ENABLED', true),
        'store'   => env('SETTING_CACHE_STORE', 'file'),
        'key'     => 'app.settings.v1',
        'ttl'     => (int) env('SETTING_CACHE_TTL', 86400), // یک روز
    ],

    /*
    |--------------------------------------------------------------------------
    | نام جدول تنظیمات
    |--------------------------------------------------------------------------
    */
    'table' => 'settings',

    /*
    |--------------------------------------------------------------------------
    | Meta هر تنظیم
    |--------------------------------------------------------------------------
    |
    | هر ورودی شامل:
    |   key     -> کلید یکتا (dot-notation برای زیرمجموعه مجاز است)
    |   group   -> گروه تنظیم (SettingGroup)
    |   type    -> نوع داده برای cast هنگام خواندن
    |   rules   -> Laravel validation rules
    |   default -> مقدار پیش‌فرض
    |   label   -> برچسب فارسی
    |   section -> تب/بخش UI برای گروه‌بندی دقیق‌تر
    */
    'meta' => [

        /*
        |--------------------------------------------------------------
        | گروه عمومی / محلی‌سازی
        |--------------------------------------------------------------
        */
        'general.calendar' => [
            'group'   => SettingGroup::General,
            'type'    => 'enum',
            'enum'    => CalendarType::class,
            'rules'   => ['sometimes', 'string'],
            'default' => CalendarType::Jalali,
            'label'   => 'تقویم',
            'section' => 'locale',
        ],

        'general.decimal_separator' => [
            'group'   => SettingGroup::General,
            'type'    => 'string',
            'rules'   => ['sometimes', 'string', 'in:.,،'],
            'default' => '.',
            'label'   => 'جداکننده‌ی اعشار',
            'section' => 'locale',
        ],

        'general.thousand_separator' => [
            'group'   => SettingGroup::General,
            'type'    => 'string',
            'rules'   => ['sometimes', 'string', 'in:,,،,\xA0'],
            'default' => ',',
            'label'   => 'جداکننده‌ی هزارگان',
            'section' => 'locale',
        ],

        'general.time_format' => [
            'group'   => SettingGroup::General,
            'type'    => 'enum',
            'enum'    => TimeFormat::class,
            'rules'   => ['sometimes', 'string'],
            'default' => TimeFormat::H24,
            'label'   => 'فرمت نمایش ساعت',
            'section' => 'locale',
        ],

        'general.currency' => [
            'group'   => SettingGroup::General,
            'type'    => 'string',
            'rules'   => ['sometimes', 'string', 'max:10'],
            'default' => 'تومان',
            'label'   => 'واحد پول',
            'section' => 'locale',
        ],

        'general.currency_code' => [
            'group'   => SettingGroup::General,
            'type'    => 'string',
            'rules'   => ['sometimes', 'string', 'max:10'],
            'default' => 'IRT',
            'label'   => 'کد ارز (ISO)',
            'section' => 'locale',
        ],

        'general.price_display' => [
            'group'   => SettingGroup::General,
            'type'    => 'enum',
            'enum'    => PriceDisplayMode::class,
            'rules'   => ['sometimes', 'string'],
            'default' => PriceDisplayMode::WithUnit,
            'label'   => 'نحوه‌ی نمایش قیمت',
            'section' => 'locale',
        ],

        'general.theme' => [
            'group'   => SettingGroup::General,
            'type'    => 'enum',
            'enum'    => ThemeMode::class,
            'rules'   => ['sometimes', 'string'],
            'default' => ThemeMode::Light,
            'label'   => 'تم ظاهری',
            'section' => 'appearance',
        ],

        'general.locale' => [
            'group'   => SettingGroup::General,
            'type'    => 'string',
            'rules'   => ['sometimes', 'string', 'max:10'],
            'default' => 'fa',
            'label'   => 'زبان پیش‌فرض',
            'section' => 'locale',
        ],

        /*
        |--------------------------------------------------------------
        | گروه دسکتاپ (NativePHP / Electron)
        |--------------------------------------------------------------
        */
        'desktop.auto_launch' => [
            'group'   => SettingGroup::Desktop,
            'type'    => 'bool',
            'rules'   => ['sometimes', 'boolean'],
            'default' => false,
            'label'   => 'اجرای خودکار هنگام روشن‌شدن سیستم',
            'section' => 'startup',
        ],

        'desktop.minimize_to_tray' => [
            'group'   => SettingGroup::Desktop,
            'type'    => 'bool',
            'rules'   => ['sometimes', 'boolean'],
            'default' => true,
            'label'   => 'کوچک‌شدن به Tray به‌جای خروج کامل',
            'section' => 'startup',
        ],

        'desktop.auto_update_url' => [
            'group'   => SettingGroup::Desktop,
            'type'    => 'string',
            'rules'   => ['sometimes', 'nullable', 'url', 'max:500'],
            'default' => null,
            'label'   => 'آدرس سرور بروزرسانی خودکار',
            'section' => 'updates',
        ],

        'desktop.auto_update_check' => [
            'group'   => SettingGroup::Desktop,
            'type'    => 'bool',
            'rules'   => ['sometimes', 'boolean'],
            'default' => true,
            'label'   => 'بررسی خودکار بروزرسانی در شروع',
            'section' => 'updates',
        ],

    ],

    /*
    |--------------------------------------------------------------------------
    | نگاشت سریع گروه -> تنظیمات
    |--------------------------------------------------------------------------
    | این مقدار در bootstrap توسط SettingService ساخته می‌شود و در config
    | باقی می‌ماند تا lookup سریع‌تر باشد.
    */
    'group_index' => [],
];