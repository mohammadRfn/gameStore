<?php

declare(strict_types=1);

use Modules\Setting\Enums\Settings\SettingGroup;
use Modules\Setting\Enums\Settings\ThemeMode;

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
        'general.theme' => [
            'group'   => SettingGroup::General,
            'type'    => 'enum',
            'enum'    => ThemeMode::class,
            'rules'   => ['sometimes', 'string'],
            'default' => ThemeMode::Light,
            'label'   => 'تم ظاهری',
            'section' => 'appearance',
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