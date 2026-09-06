<?php

declare(strict_types=1);

use App\Enums\Settings\BackupSchedule;
use App\Enums\Settings\CalendarType;
use App\Enums\Settings\PaperSize;
use App\Enums\Settings\PriceDisplayMode;
use App\Enums\Settings\PrinterType;
use App\Enums\Settings\SettingGroup;
use App\Enums\Settings\TaxMode;
use App\Enums\Settings\ThemeMode;
use App\Enums\Settings\TimeFormat;

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
        | گروه مالیات / فاکتور
        |--------------------------------------------------------------
        */
        'invoice.tax_rate' => [
            'group'   => SettingGroup::Invoice,
            'type'    => 'float',
            'rules'   => ['sometimes', 'numeric', 'min:0', 'max:100'],
            'default' => 9.0,
            'label'   => 'نرخ مالیات بر ارزش افزوده (%)',
            'section' => 'tax',
        ],

        'invoice.tax_mode' => [
            'group'   => SettingGroup::Invoice,
            'type'    => 'enum',
            'enum'    => TaxMode::class,
            'rules'   => ['sometimes', 'string'],
            'default' => TaxMode::Exclusive,
            'label'   => 'نحوه‌ی محاسبه‌ی مالیات',
            'section' => 'tax',
        ],

        'invoice.tax_enabled' => [
            'group'   => SettingGroup::Invoice,
            'type'    => 'bool',
            'rules'   => ['sometimes', 'boolean'],
            'default' => true,
            'label'   => 'فعال‌سازی مالیات در فاکتورها',
            'section' => 'tax',
        ],

        'invoice.prefix' => [
            'group'   => SettingGroup::Invoice,
            'type'    => 'string',
            'rules'   => ['sometimes', 'string', 'max:10', 'regex:/^[A-Za-z0-9\-]*$/'],
            'default' => 'INV-',
            'label'   => 'پیشوند شماره فاکتور',
            'section' => 'invoice',
        ],

        'invoice.counter' => [
            'group'   => SettingGroup::Invoice,
            'type'    => 'int',
            'rules'   => ['sometimes', 'integer', 'min:0'],
            'default' => 1,
            'label'   => 'شمارنده‌ی فعلی شماره فاکتور',
            'section' => 'invoice',
        ],

        'invoice.counter_padding' => [
            'group'   => SettingGroup::Invoice,
            'type'    => 'int',
            'rules'   => ['sometimes', 'integer', 'min:3', 'max:10'],
            'default' => 6,
            'label'   => 'طول شماره فاکتور (padding با صفر)',
            'section' => 'invoice',
        ],

        'invoice.business_registration' => [
            'group'   => SettingGroup::Invoice,
            'type'    => 'string',
            'rules'   => ['sometimes', 'nullable', 'string', 'max:50'],
            'default' => null,
            'label'   => 'شماره ثبت رسمی کسب‌وکار',
            'section' => 'invoice',
        ],

        'invoice.economic_code' => [
            'group'   => SettingGroup::Invoice,
            'type'    => 'string',
            'rules'   => ['sometimes', 'nullable', 'string', 'max:50'],
            'default' => null,
            'label'   => 'کد اقتصادی',
            'section' => 'invoice',
        ],

        'invoice.footer_text' => [
            'group'   => SettingGroup::Invoice,
            'type'    => 'string',
            'rules'   => ['sometimes', 'nullable', 'string', 'max:1000'],
            'default' => "از خرید شما متشکریم.\nشرایط گارانتی و مرجوعی در انتهای فاکتور درج می‌شود.",
            'label'   => 'متن پیش‌فرض پاورقی فاکتور',
            'section' => 'invoice',
        ],

        'invoice.warranty_terms' => [
            'group'   => SettingGroup::Invoice,
            'type'    => 'string',
            'rules'   => ['sometimes', 'nullable', 'string', 'max:2000'],
            'default' => "کالاهای تعمیرشده دارای ۷ روز گارانتی تعویض هستند.\nدر صورت عدم رضایت، کالا بدون قید و شرط قابل مرجوع است.",
            'label'   => 'شرایط گارانتی پیش‌فرض',
            'section' => 'invoice',
        ],

        'invoice.printer_type' => [
            'group'   => SettingGroup::Invoice,
            'type'    => 'enum',
            'enum'    => PrinterType::class,
            'rules'   => ['sometimes', 'string'],
            'default' => PrinterType::Thermal,
            'label'   => 'نوع چاپگر پیش‌فرض',
            'section' => 'print',
        ],

        'invoice.paper_size' => [
            'group'   => SettingGroup::Invoice,
            'type'    => 'enum',
            'enum'    => PaperSize::class,
            'rules'   => ['sometimes', 'string'],
            'default' => PaperSize::Roll80,
            'label'   => 'اندازه‌ی کاغذ چاپگر',
            'section' => 'print',
        ],

        'invoice.logo_path' => [
            'group'   => SettingGroup::Invoice,
            'type'    => 'string',
            'rules'   => ['sometimes', 'nullable', 'string', 'max:500'],
            'default' => null,
            'label'   => 'مسیر لوگوی چاپ‌شونده روی فاکتور',
            'section' => 'invoice',
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

        'desktop.database_path' => [
            'group'   => SettingGroup::Desktop,
            'type'    => 'string',
            'rules'   => ['sometimes', 'string', 'max:1000'],
            'default' => null, // null یعنی مسیر پیش‌فرض NativePHP
            'label'   => 'مسیر فایل دیتابیس روی دیسک',
            'section' => 'paths',
        ],

        'desktop.backup_path' => [
            'group'   => SettingGroup::Desktop,
            'type'    => 'string',
            'rules'   => ['sometimes', 'string', 'max:1000'],
            'default' => null, // null یعنی مسیر پیش‌فرض
            'label'   => 'مسیر ذخیره‌ی بکاپ‌ها',
            'section' => 'paths',
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

        'desktop.default_printer_name' => [
            'group'   => SettingGroup::Desktop,
            'type'    => 'string',
            'rules'   => ['sometimes', 'nullable', 'string', 'max:200'],
            'default' => null, // نام چاپگر پیش‌فرض سیستم‌عامل
            'label'   => 'نام چاپگر پیش‌فرض سیستم',
            'section' => 'print',
        ],

        'desktop.backup_schedule' => [
            'group'   => SettingGroup::Desktop,
            'type'    => 'enum',
            'enum'    => BackupSchedule::class,
            'rules'   => ['sometimes', 'string'],
            'default' => BackupSchedule::Daily,
            'label'   => 'زمان‌بندی بکاپ خودکار',
            'section' => 'backup',
        ],

        'desktop.backup_retention' => [
            'group'   => SettingGroup::Desktop,
            'type'    => 'int',
            'rules'   => ['sometimes', 'integer', 'min:1', 'max:365'],
            'default' => 30,
            'label'   => 'تعداد روز نگهداری بکاپ‌ها',
            'section' => 'backup',
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
