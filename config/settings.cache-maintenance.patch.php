<?php

/**
 * Patch برای config/settings.php
 *
 * ۱) داخل آرایه groups این گروه را اضافه کن:
 *
 * ['code' => 'maintenance', 'label' => 'نگهداری سیستم', 'icon' => '🧹', 'sort_order' => 9],
 *
 * ۲) داخل آرایه settings این موارد را اضافه کن:
 */

return [
    'group' => ['code' => 'maintenance', 'label' => 'نگهداری سیستم', 'icon' => '🧹', 'sort_order' => 9],

    'settings' => [
        [
            'key' => 'maintenance.cache.default_targets',
            'label' => 'Targetهای پیش‌فرض پاکسازی کش',
            'type' => 'json',
            'default' => json_encode([
                'app', 'settings', 'config', 'route', 'view', 'event',
                'compiled', 'optimize', 'bootstrap', 'framework_files',
                'expired_database_cache',
            ], JSON_UNESCAPED_UNICODE),
            'group' => 'maintenance',
            'autoload' => false,
        ],
        [
            'key' => 'maintenance.cache.warm_after_clear',
            'label' => 'گرم‌سازی خودکار بعد از پاکسازی',
            'type' => 'boolean',
            'default' => false,
            'group' => 'maintenance',
            'autoload' => false,
        ],
        [
            'key' => 'maintenance.cache.warm_config',
            'label' => 'بازسازی config cache بعد از پاکسازی',
            'type' => 'boolean',
            'default' => false,
            'group' => 'maintenance',
            'autoload' => false,
        ],
        [
            'key' => 'maintenance.cache.warm_views',
            'label' => 'کامپایل مجدد viewها بعد از پاکسازی',
            'type' => 'boolean',
            'default' => true,
            'group' => 'maintenance',
            'autoload' => false,
        ],
        [
            'key' => 'maintenance.cache.warm_settings',
            'label' => 'گرم‌سازی کش تنظیمات بعد از پاکسازی',
            'type' => 'boolean',
            'default' => true,
            'group' => 'maintenance',
            'autoload' => false,
        ],
        [
            'key' => 'maintenance.cache.allow_log_cleanup',
            'label' => 'اجازه حذف لاگ‌های قدیمی',
            'type' => 'boolean',
            'default' => false,
            'group' => 'maintenance',
            'autoload' => false,
        ],
        [
            'key' => 'maintenance.cache.logs_older_than_days',
            'label' => 'حداقل سن لاگ برای حذف (روز)',
            'type' => 'integer',
            'default' => 14,
            'group' => 'maintenance',
            'autoload' => false,
        ],
        [
            'key' => 'maintenance.cache.allow_session_cleanup',
            'label' => 'اجازه حذف session فایل‌ها',
            'type' => 'boolean',
            'default' => false,
            'group' => 'maintenance',
            'autoload' => false,
        ],
        [
            'key' => 'maintenance.cache.run_sqlite_vacuum',
            'label' => 'اجرای VACUUM/ANALYZE SQLite بعد از پاکسازی',
            'type' => 'boolean',
            'default' => false,
            'group' => 'maintenance',
            'autoload' => false,
        ],
        [
            'key' => 'maintenance.cache.keep_history_days',
            'label' => 'مدت نگهداری تاریخچه پاکسازی کش (روز)',
            'type' => 'integer',
            'default' => 90,
            'group' => 'maintenance',
            'autoload' => false,
        ],
    ],
];
