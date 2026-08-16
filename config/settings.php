<?php

/*
|--------------------------------------------------------------------------
| GameStore — Enterprise Settings Registry
|--------------------------------------------------------------------------
| Single Source of Truth for every application setting.
| Used by: SettingsService (cache/typed getters), install/sync commands,
| AppSettingsController (form schemas), Requests (validation rules).
|
| type: string | integer | float | boolean | json | select
| locked:  only install/sync may touch it (system-critical)
| autoload: loaded into memory cache on first request
| encrypted: stored encrypted (Crypt::encryptString) — secrets only
*/

return [

    'cache_key' => 'app_settings.all',

    'groups' => [
        ['code' => 'general',       'label' => 'تنظیمات عمومی',     'icon' => '⚙️', 'sort_order' => 1],
        ['code' => 'store',         'label' => 'مشخصات فروشگاه',   'icon' => '🏪', 'sort_order' => 2],
        ['code' => 'invoice',       'label' => 'فاکتور و رسید',     'icon' => '🧾', 'sort_order' => 3],
        ['code' => 'inventory',     'label' => 'انبارداری',         'icon' => '📦', 'sort_order' => 4],
        ['code' => 'notification',  'label' => 'اعلان‌ها',           'icon' => '🔔', 'sort_order' => 5],
        ['code' => 'backup',        'label' => 'پشتیبان‌گیری',      'icon' => '💾', 'sort_order' => 6],
        ['code' => 'security',      'label' => 'امنیت',             'icon' => '🔐', 'sort_order' => 7],
        ['code' => 'electron',      'label' => 'اپ دسکتاپ',         'icon' => '🖥️', 'sort_order' => 8],
    ],

    /*
    | Each item: [key, label, type, default, group, options...]
    */
    'settings' => [

        // ---------------------------------------------------------- GENERAL
        ['key' => 'general.app_name',        'label' => 'نام برنامه',              'type' => 'string',  'default' => 'GameStore Pro', 'group' => 'general', 'locked' => false, 'autoload' => true],
        ['key' => 'general.app_locale',      'label' => 'زبان پیش‌فرض',             'type' => 'select',  'default' => 'fa', 'options' => ['fa' => 'فارسی', 'en' => 'English'], 'group' => 'general', 'autoload' => true],
        ['key' => 'general.timezone',        'label' => 'منطقه زمانی',             'type' => 'select',  'default' => 'Asia/Tehran', 'options' => ['Asia/Tehran' => 'تهران', 'UTC' => 'UTC'], 'group' => 'general', 'autoload' => true],
        ['key' => 'general.date_format',     'label' => 'فرمت تاریخ',              'type' => 'select',  'default' => 'jalali', 'options' => ['jalali' => 'شمسی', 'gregorian' => 'میلادی'], 'group' => 'general'],
        ['key' => 'general.decimal_precision','label' => 'دقت اعشار قیمت',         'type' => 'integer', 'default' => 0, 'group' => 'general'],
        ['key' => 'general.currency_code',   'label' => 'کد ارز',                  'type' => 'string',  'default' => 'IRR', 'group' => 'general', 'autoload' => true],
        ['key' => 'general.currency_symbol', 'label' => 'نماد ارز',                'type' => 'string',  'default' => 'تومان', 'group' => 'general', 'autoload' => true],
        ['key' => 'general.direction',       'label' => 'جهت رابط کاربری',         'type' => 'select',  'default' => 'rtl', 'options' => ['rtl' => 'راست‌به‌چپ', 'ltr' => 'چپ‌به‌راست'], 'group' => 'general', 'autoload' => true],

        // ------------------------------------------------------------ STORE
        ['key' => 'store.name',              'label' => 'نام نمایشی فروشگاه',      'type' => 'string',  'default' => 'فروشگاه من', 'group' => 'store', 'autoload' => true],
        ['key' => 'store.phone',             'label' => 'تلفن فروشگاه',            'type' => 'string',  'default' => '', 'group' => 'store', 'autoload' => true],
        ['key' => 'store.address',           'label' => 'آدرس فروشگاه',            'type' => 'string',  'default' => '', 'group' => 'store'],
        ['key' => 'store.fiscal_year_start', 'label' => 'ماه شروع سال مالی',        'type' => 'integer', 'default' => 1, 'group' => 'store'],

        // ----------------------------------------------------------- INVOICE
        ['key' => 'invoice.prefix',          'label' => 'پیشوند شماره فاکتور',     'type' => 'string',  'default' => 'INV-', 'group' => 'invoice', 'locked' => false],
        ['key' => 'invoice.suffix',          'label' => 'پسوند شماره فاکتور',      'type' => 'string',  'default' => '', 'group' => 'invoice'],
        ['key' => 'invoice.start_number',    'label' => 'شماره شروع فاکتور',       'type' => 'integer', 'default' => 1001, 'group' => 'invoice'],
        ['key' => 'invoice.tax_rate',        'label' => 'نرخ مالیات بر ارزش افزوده (%)', 'type' => 'integer', 'default' => 9, 'group' => 'invoice', 'locked' => true],
        ['key' => 'invoice.show_tax',        'label' => 'نمایش مالیات در فاکتور',  'type' => 'boolean', 'default' => true, 'group' => 'invoice'],
        ['key' => 'invoice.footer_text',     'label' => 'متن پایانی فاکتور',       'type' => 'string',  'default' => 'با تشکر از خرید شما', 'group' => 'invoice'],
        ['key' => 'invoice.auto_deduct_stock','label' => 'کسر خودکار موجودی',      'type' => 'boolean', 'default' => true, 'group' => 'invoice', 'locked' => false],
        ['key' => 'invoice.receipt_width_mm','label' => 'عرض رسید حرارتی (میلی‌متر)', 'type' => 'integer', 'default' => 80, 'group' => 'invoice'],
        ['key' => 'invoice.receipt_show_logo','label' => 'چاپ لوگو روی رسید',      'type' => 'boolean', 'default' => true, 'group' => 'invoice'],
        ['key' => 'invoice.receipt_copies',  'label' => 'تعداد کپی رسید',          'type' => 'integer', 'default' => 1, 'group' => 'invoice'],

        // --------------------------------------------------------- INVENTORY
        ['key' => 'inventory.low_stock_threshold','label' => 'آستانه هشدار موجودی', 'type' => 'integer', 'default' => 5, 'group' => 'inventory'],
        ['key' => 'inventory.allow_negative_stock','label' => 'مجاز بودن موجودی منفی', 'type' => 'boolean', 'default' => false, 'group' => 'inventory', 'locked' => true],
        ['key' => 'inventory.default_movement_note','label' => 'یادداشت پیش‌فرض نقل‌وانتقال', 'type' => 'string', 'default' => '', 'group' => 'inventory'],
        ['key' => 'inventory.barcode_prefix', 'label' => 'پیشوند بارکد محصولات',    'type' => 'string',  'default' => '626', 'group' => 'inventory'],

        // ------------------------------------------------------ NOTIFICATION
        ['key' => 'notification.low_stock_enabled','label' => 'هشدار موجودی کم',   'type' => 'boolean', 'default' => true, 'group' => 'notification'],
        ['key' => 'notification.daily_summary_enabled','label' => 'خلاصه فروش روزانه','type' => 'boolean','default' => false, 'group' => 'notification'],
        ['key' => 'notification.daily_summary_time','label' => 'ساعت خلاصه روزانه', 'type' => 'string',  'default' => '20:00', 'group' => 'notification'],

        // ----------------------------------------------------------- BACKUP
        ['key' => 'backup.auto_enabled',     'label' => 'پشتیبان‌گیری خودکار',     'type' => 'boolean', 'default' => true, 'group' => 'backup'],
        ['key' => 'backup.frequency',        'label' => 'بازه پشتیبان‌گیری',       'type' => 'select',  'default' => 'daily', 'options' => ['daily' => 'روزانه', 'weekly' => 'هفتگی', 'monthly' => 'ماهانه'], 'group' => 'backup'],
        ['key' => 'backup.retention_days',   'label' => 'مدت نگهداری نسخه‌ها (روز)', 'type' => 'integer', 'default' => 30, 'group' => 'backup'],
        ['key' => 'backup.encrypt',          'label' => 'رمزنگاری فایل بکاپ',      'type' => 'boolean', 'default' => true, 'group' => 'backup'],

        // --------------------------------------------------------- SECURITY
        ['key' => 'security.session_timeout_minutes','label' => 'زمان انقضای نشست (دقیقه)','type' => 'integer','default' => 60, 'group' => 'security'],
        ['key' => 'security.max_login_attempts','label' => 'حداکثر تلاش ورود',     'type' => 'integer', 'default' => 5, 'group' => 'security', 'locked' => true],
        ['key' => 'security.password_min_length','label' => 'حداقل طول رمز عبور',  'type' => 'integer', 'default' => 8, 'group' => 'security'],
        ['key' => 'security.require_strong_password','label' => 'الزام رمز قوی',   'type' => 'boolean', 'default' => true, 'group' => 'security'],

        // --------------------------------------------------------- ELECTRON
        ['key' => 'electron.window_width',   'label' => 'عرض پنجره',               'type' => 'integer', 'default' => 1280, 'group' => 'electron'],
        ['key' => 'electron.window_height',  'label' => 'ارتفاع پنجره',            'type' => 'integer', 'default' => 800, 'group' => 'electron'],
        ['key' => 'electron.minimize_to_tray','label' => 'کوچک‌سازی به سینی',      'type' => 'boolean', 'default' => true, 'group' => 'electron'],
        ['key' => 'electron.start_minimized','label' => 'اجرای اولیه کمینه',       'type' => 'boolean', 'default' => false, 'group' => 'electron'],
        ['key' => 'electron.printer_name',   'label' => 'نام چاپگر رسید',          'type' => 'string',  'default' => '', 'group' => 'electron'],
        ['key' => 'electron.update_channel', 'label' => 'کانال به‌روزرسانی',       'type' => 'select',  'default' => 'stable', 'options' => ['stable' => 'پایدار', 'beta' => 'بتا'], 'group' => 'electron'],
    ],
];
