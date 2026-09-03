<?php

use App\Models\ArchiveAction;
use App\Models\ArchivedRecord;
use App\Models\Category;
use App\Models\Customer;
use App\Models\DailyItemStat;
use App\Models\Invoice;
use App\Models\InvoiceAdjustment;
use App\Models\Item;
use App\Models\MonthlySale;
use App\Models\OrderItem;
use App\Models\Request as ServiceRequest;
use App\Models\ServiceJob;
use App\Models\ServiceJobItem;
use App\Models\ServiceJobServiceType;
use App\Models\ServiceType;
use App\Models\StockMovement;
use App\Models\User;
use App\Models\AppSetting;
use App\Models\SettingGroup;
use App\Models\StoreProfile;
use App\Models\AdjustmentCategory;
use App\Models\RequestCategory;

return [

    /*
    |--------------------------------------------------------------------------
    | نسخه‌ی فرمت بکاپ
    |--------------------------------------------------------------------------
    | با تغییر ساختار CSV ها این عدد را بالا ببرید تا هنگام ایمپورت،
    | نسخه‌های ناسازگار شناسایی و رد شوند.
    */
    'format_version' => '1.0',

    'app_name' => env('BACKUP_APP_NAME', 'GameStore'),

    /*
    |--------------------------------------------------------------------------
    | آیا اپ داخل پوسته‌ی واقعیِ Electron/NativePHP اجرا می‌شود؟
    |--------------------------------------------------------------------------
    | روی وب‌سرور محلی (php artisan serve) این را false کن؛ چون دیالوگ
    | NativePHP بدون خطا فقط null برمی‌گرداند و نمی‌شود از رویِ نتیجه تشخیص داد.
    */
    'native_dialog' => (bool) env('BACKUP_NATIVE_DIALOG', true),

    /*
    |--------------------------------------------------------------------------
    | مسیرهای پیش‌فرض روی سیستم کاربر (اپ Electron / NativePHP)
    |--------------------------------------------------------------------------
    | اگر کاربر مسیری انتخاب نکرده باشد، از این مسیرها استفاده می‌شود.
    | BackupPathResolver در زمان اجرا مسیر Documents/Home سیستم‌عامل را
    | تشخیص می‌دهد؛ این مقادیر صرفاً override هستند.
    */
    'paths' => [
        'export_root'   => env('BACKUP_EXPORT_ROOT'),   // مثال: C:\Users\ali\Documents\GameStore\Backups
        'import_root'   => env('BACKUP_IMPORT_ROOT'),   // مثال: C:\Users\ali\Documents\GameStore\Restore
        'folder_name'   => env('BACKUP_FOLDER_NAME', 'GameStore-Backups'),
        'inbox_name'    => env('BACKUP_INBOX_NAME', 'GameStore-Restore'),
        // نام دایرکتوری‌های داخل هر بسته‌ی بکاپ
        'database_dir'  => 'database',
        'media_dir'     => 'media',
        'logs_dir'      => 'logs',
        'manifest_file' => 'manifest.json',
        'checksum_file' => 'checksums.sha256',
        'readme_file'   => 'README.txt',
    ],

    /*
    |--------------------------------------------------------------------------
    | تنظیمات CSV
    |--------------------------------------------------------------------------
    */
    'csv' => [
        'delimiter'   => env('BACKUP_CSV_DELIMITER', ','),
        'enclosure'   => '"',
        'escape'      => '\\',
        'line_ending' => "\r\n",
        'bom'         => true,      // برای نمایش درست فارسی در Excel
        'null_marker' => '\N',      // تفکیک NULL از رشته‌ی خالی
        'extension'   => 'csv',
    ],

    /*
    |--------------------------------------------------------------------------
    | رفتار اجرا
    |--------------------------------------------------------------------------
    */
    'runtime' => [
        'chunk_size'          => (int) env('BACKUP_CHUNK_SIZE', 1000),
        'memory_limit'        => env('BACKUP_MEMORY_LIMIT', '512M'),
        'time_limit'          => (int) env('BACKUP_TIME_LIMIT', 0), // 0 = بدون محدودیت
        'min_free_space_mb'   => 200,
        'lock_seconds'        => 1800,
        'retention_copies'         => (int) env('BACKUP_RETENTION', 10),
        'safety_retention_copies'  => (int) env('BACKUP_SAFETY_RETENTION', 3),
        'auto_safety_backup'  => true,   // قبل از ایمپورت، بکاپ ایمنی بگیر
        'verify_checksums'    => true,
        'include_soft_deleted' => true,
        'vacuum_after_import' => true,
    ],

    /*
    |--------------------------------------------------------------------------
    | دیسک تصاویر برنامه
    |--------------------------------------------------------------------------
    | تمام تصاویر پروژه روی دیسک public ذخیره می‌شوند:
    |   images/items/*, images/order_items/*, images/receipts/*
    */
    'media' => [
        'disk'              => 'public',
        'allowed_mimes'     => ['image/jpeg', 'image/png', 'image/gif', 'image/webp', 'image/svg+xml', 'application/pdf'],
        'max_file_mb'       => 25,
        'hash_algorithm'    => 'sha256',
        'deduplicate'       => true,   // فایل‌های تکراری فقط یک‌بار کپی می‌شوند
        'relink_on_import'  => true,   // مسیر ستون‌های تصویری پس از ایمپورت اصلاح شود
    ],

    /*
    |--------------------------------------------------------------------------
    | جداولی که هرگز بکاپ/ایمپورت نمی‌شوند
    |--------------------------------------------------------------------------
    */
    'excluded_tables' => [
        'migrations',
        'sessions',
        'cache',
        'cache_locks',
        'jobs',
        'job_batches',
        'failed_jobs',
        'password_reset_tokens',
        'personal_access_tokens',
        'backup_runs',
        'backup_run_entities',
        'backup_files',
        'backup_run_events',
        'backup_settings',
    ],

    /*
    |--------------------------------------------------------------------------
    | گروه‌بندی پوشه‌ها (طبقه‌بندی حرفه‌ای خروجی)
    |--------------------------------------------------------------------------
    */
    'groups' => [
        '00_core'      => 'هسته و پیکربندی',
        '10_people'    => 'کاربران و مشتریان',
        '20_catalog'   => 'کالا و دسته‌بندی',
        '30_sales'     => 'فروش و فاکتورها',
        '40_services'  => 'خدمات و تعمیرات',
        '50_inventory' => 'انبار و گردش موجودی',
        '60_analytics' => 'آمار و گزارش‌ها',
        '70_archive'   => 'بایگانی',
    ],

    /*
    |--------------------------------------------------------------------------
    | مانیفست موجودیت‌ها
    |--------------------------------------------------------------------------
    | ترتیب آرایه = ترتیب درج هنگام ایمپورت (رعایت وابستگی کلید خارجی).
    | هر آیتم:
    |   table         : نام جدول
    |   model         : کلاس مدل (برای رویدادها و relink تصاویر)
    |   group         : پوشه‌ی مقصد
    |   label         : نام فارسی
    |   natural_key   : ستون‌های شناسایی یکتا برای upsert (fallback: id)
    |   soft_deletes  : جدول ستون deleted_at دارد؟
    |   media         : نگاشت ستون تصویری => پوشه‌ی مقصد داخل media/
    |   redact        : ستون‌هایی که در حالت «خروجی امن» حذف می‌شوند
    |   optional      : اگر جدول وجود نداشت، بی‌صدا رد شود
    */
    'entities' => [

        'users' => [
            'table'        => 'users',
            'model'        => User::class,
            'group'        => '00_core',
            'label'        => 'کاربران',
            'natural_key'  => ['email'],
            'soft_deletes' => true,
            'redact'       => ['password', 'remember_token'],
            'optional'     => false,
        ],

        'setting_groups' => [
            'table'        => 'setting_groups',
            'model'        => SettingGroup::class,
            'group'        => '00_core',
            'label'        => 'گروه‌های تنظیمات',
            'natural_key'  => ['code'],
            'soft_deletes' => false,
        ],

        'app_settings' => [
            'table'        => 'app_settings',
            'model'        => AppSetting::class,
            'group'        => '00_core',
            'label'        => 'تنظیمات برنامه',
            'natural_key'  => ['group_id', 'setting_key'],
            'soft_deletes' => false,
        ],

        'store_profiles' => [
            'table'        => 'store_profiles',
            'model'        => StoreProfile::class,
            'group'        => '00_core',
            'label'        => 'پروفایل فروشگاه',
            'natural_key'  => ['slug'],
            'soft_deletes' => false,
            'media'        => ['logo_path' => 'store/logo', 'cover_path' => 'store/cover'],
        ],
        'categories' => [
            'table'        => 'categories',
            'model'        => Category::class,
            'group'        => '20_catalog',
            'label'        => 'دسته‌بندی‌ها',
            'natural_key'  => ['name'],
            'soft_deletes' => true,
        ],

        'customers' => [
            'table'        => 'customers',
            'model'        => Customer::class,
            'group'        => '10_people',
            'label'        => 'مشتریان',
            'natural_key'  => ['phone', 'name'],
            'soft_deletes' => true,
        ],
        'adjustment_categories' => [
            'table'        => 'adjustment_categories',
            'model'        => AdjustmentCategory::class,
            'group'        => '30_sales',
            'label'        => 'دسته‌بندی تعدیل‌ها',
            'natural_key'  => ['key'],
            'soft_deletes' => false,
        ],
        'items' => [
            'table'        => 'items',
            'model'        => Item::class,
            'group'        => '20_catalog',
            'label'        => 'کالاها',
            'natural_key'  => ['name', 'category_id'],
            'soft_deletes' => true,
            'media'        => ['image_path' => 'items'],
        ],

        'requests' => [
            'table'        => 'requests',
            'model'        => ServiceRequest::class,
            'group'        => '40_services',
            'label'        => 'درخواست‌ها',
            'natural_key'  => [],
            'soft_deletes' => true,
        ],
        'request_categories' => [
            'table'        => 'request_categories',
            'model'        => null,
            'group'        => '40_services',
            'label'        => 'دسته‌بندی درخواست‌ها',
            'natural_key'  => ['request_id', 'category_id'],
            'soft_deletes' => false,
        ],
        'invoices' => [
            'table'        => 'invoices',
            'model'        => Invoice::class,
            'group'        => '30_sales',
            'label'        => 'فاکتورها',
            'natural_key'  => ['invoice_number'],
            'soft_deletes' => true,
            'media'        => ['receipt_image_path' => 'invoices/receipts'],
        ],

        'invoice_adjustments' => [
            'table'        => 'invoice_adjustments',
            'model'        => InvoiceAdjustment::class,
            'group'        => '30_sales',
            'label'        => 'تعدیل‌های فاکتور',
            'natural_key'  => [],
            'soft_deletes' => false,
            'optional'     => true,
        ],

        'order_items' => [
            'table'        => 'order_items',
            'model'        => OrderItem::class,
            'group'        => '30_sales',
            'label'        => 'اقلام فاکتور',
            'natural_key'  => [],
            'soft_deletes' => true,
            'media'        => ['image_path' => 'order-items'],
        ],

        'service_types' => [
            'table'        => 'service_types',
            'model'        => ServiceType::class,
            'group'        => '40_services',
            'label'        => 'انواع خدمات',
            'natural_key'  => ['name'],
            'soft_deletes' => true,
        ],

        'service_jobs' => [
            'table'        => 'service_jobs',
            'model'        => ServiceJob::class,
            'group'        => '40_services',
            'label'        => 'کارهای خدماتی',
            'natural_key'  => [],
            'soft_deletes' => true,
        ],

        'service_job_items' => [
            'table'        => 'service_job_items',
            'model'        => ServiceJobItem::class,
            'group'        => '40_services',
            'label'        => 'قطعات مصرفی خدمات',
            'natural_key'  => [],
            'soft_deletes' => true,
        ],

        'service_job_service_types' => [
            'table'        => 'service_job_service_types',
            'model'        => ServiceJobServiceType::class,
            'group'        => '40_services',
            'label'        => 'خدمات هر کار',
            'natural_key'  => [],
            'soft_deletes' => false,
            'optional'     => true,
        ],

        'stock_movements' => [
            'table'        => 'stock_movements',
            'model'        => StockMovement::class,
            'group'        => '50_inventory',
            'label'        => 'گردش انبار',
            'natural_key'  => [],
            'soft_deletes' => true,
        ],

        'daily_item_stats' => [
            'table'        => 'daily_item_stats',
            'model'        => DailyItemStat::class,
            'group'        => '60_analytics',
            'label'        => 'آمار روزانه کالا',
            'natural_key'  => ['stat_date', 'item_id'],
            'soft_deletes' => true,
        ],

        'monthly_sales' => [
            'table'        => 'monthly_sales',
            'model'        => MonthlySale::class,
            'group'        => '60_analytics',
            'label'        => 'فروش ماهانه',
            'natural_key'  => ['year', 'month'],
            'soft_deletes' => true,
        ],

        'archived_records' => [
            'table'        => 'archived_records',
            'model'        => ArchivedRecord::class,
            'group'        => '70_archive',
            'label'        => 'رکوردهای بایگانی',
            'natural_key'  => ['source_type', 'source_id'],
            'soft_deletes' => true,
            'optional'     => true,
        ],

        'archive_actions' => [
            'table'        => 'archive_actions',
            'model'        => ArchiveAction::class,
            'group'        => '70_archive',
            'label'        => 'رویدادهای بایگانی',
            'natural_key'  => [],
            'soft_deletes' => false,
            'optional'     => true,
        ],
        'cache_maintenance_runs' => [
            'table'        => 'cache_maintenance_runs',
            'model'        => null,
            'group'        => '00_core',
            'label'        => 'لاگ نگهداری کش',
            'natural_key'  => [],
            'soft_deletes' => false,
            'optional'     => true,
        ],
    ],
];
