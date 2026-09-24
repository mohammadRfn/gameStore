<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| پیکربندی ماژول AuditLog
|--------------------------------------------------------------------------
| این فایل با کلید ریشه‌ی «auditlog» در کانتینر کانفیگ merge می‌شود:
|   config('auditlog.shipping.endpoint')
*/

return [

    // کلید اصلی خاموش/روشن کردن کل ماژول (بدون حذف کد)
    'enabled' => (bool) env('AUDITLOG_ENABLED', true),

    /*
    |--------------------------------------------------------------------------
    | کانال‌های فعال
    |--------------------------------------------------------------------------
    | هر رکورد لاگ دقیقاً یک کانال دارد. کانال غیرفعال اصلاً نوشته نمی‌شود.
    */
    'channels' => [
        'http'     => (bool) env('AUDITLOG_CHANNEL_HTTP', true),
        'model'    => (bool) env('AUDITLOG_CHANNEL_MODEL', true),
        'auth'     => (bool) env('AUDITLOG_CHANNEL_AUTH', true),
        'security' => (bool) env('AUDITLOG_CHANNEL_SECURITY', true),
        'job'      => (bool) env('AUDITLOG_CHANNEL_JOB', true),
        'console'  => (bool) env('AUDITLOG_CHANNEL_CONSOLE', true),
        'error'    => (bool) env('AUDITLOG_CHANNEL_ERROR', true),
        'system'   => (bool) env('AUDITLOG_CHANNEL_SYSTEM', true),
        'business' => (bool) env('AUDITLOG_CHANNEL_BUSINESS', true),
        'sync'     => (bool) env('AUDITLOG_CHANNEL_SYNC', true),
    ],

    // حداقل سطحی که ذخیره می‌شود (debug < info < notice < warning < error < critical < alert)
    'minimum_level' => env('AUDITLOG_MIN_LEVEL', 'info'),

    /*
    |--------------------------------------------------------------------------
    | جمع‌آوری خودکار
    |--------------------------------------------------------------------------
    */
    'capture' => [

        // لاگ درخواست‌های HTTP (middleware ترمینیبل)
        'http' => [
            'enabled'      => true,
            'methods'      => ['POST', 'PUT', 'PATCH', 'DELETE'],
            'log_reads'    => false,          // GET/HEAD فقط وقتی کند یا خطادار باشند
            'slow_ms'      => 1500,           // آستانه‌ی لاگ کردن درخواست کند (GET هم شامل می‌شود)
            'error_status' => 400,            // از این کد به بالا همیشه لاگ می‌شود
            'except'       => [
                'api/auditlog*',
                'up',
                'telescope*',
                'horizon*',
                '_debugbar*',
                'build/*',
                'storage/*',
            ],
            'store_payload'  => true,
            'store_response' => false,
            'max_body_kb'    => 64,
        ],

        // لاگ تغییرات مدل‌ها (Observer عمومی + trait Auditable)
        'model' => [
            'enabled' => true,
            'events'  => ['created', 'updated', 'deleted', 'restored', 'forceDeleted'],

            // مدل‌هایی که به‌صورت خودکار رصد می‌شوند (بدون نیاز به تغییر کد آن ماژول‌ها)
            'observe' => [
                \App\Models\User::class,
                \Modules\Customer\Models\Customer::class,
                \Modules\Invoice\Models\Invoice::class,
                \Modules\Invoice\Models\OrderItem::class,
                \Modules\Invoice\Models\InvoiceAdjustment::class,
                \Modules\Invoice\Models\AdjustmentCategory::class,
                \Modules\Request\Models\Request::class,
                \Modules\Service\Models\ServiceJob::class,
                \Modules\Service\Models\ServiceJobItem::class,
                \Modules\Service\Models\ServiceType::class,
                \Modules\Stock\Models\Item::class,
                \Modules\Stock\Models\ItemSerialNumber::class,
                \Modules\Stock\Models\StockMovement::class,
                \Modules\Category\Models\Category::class,
                \Modules\Warranty\Models\Warranty::class,
                \Modules\Warranty\Models\WarrantyProvider::class,
                \Modules\Profile\Models\StoreProfile::class,
                \Modules\Setting\Models\Setting::class,
                \Modules\Archive\Models\ArchivedRecord::class,
                \Modules\Archive\Models\ArchiveAction::class,
                \Modules\Backup\Models\BackupRun::class,
                \Modules\Backup\Models\BackupFile::class,
                \Modules\CacheMaintenance\Models\CacheMaintenanceRun::class,
                \Modules\DigitalMenu\Models\DigitalMenuSession::class,
                \Modules\DigitalMenu\Models\DigitalMenuSelection::class,
            ],

            // این ستون‌ها هرگز در old/new ذخیره نمی‌شوند
            'ignore_attributes' => [
                'password',
                'remember_token',
                'updated_at',
                'created_at',
                'api_token',
                'recovery_code',
                'recovery_code_hash',
            ],

            // برچسب قابل‌نمایش رکورد از اولین ستون موجود خوانده می‌شود
            'label_attributes' => ['name', 'title', 'invoice_number', 'code', 'full_name', 'phone', 'email', 'key'],
        ],

        // رویدادهای احراز هویت
        'auth' => [
            'enabled'         => true,
            'log_failed'      => true,
            'log_logout'      => true,
            'failed_is_security' => true,
        ],

        // صف‌ها
        'queue' => [
            'enabled'        => true,
            'log_processed'  => false,
            'log_failed'     => true,
        ],

        // دستورات کنسول / آرتیزان
        'console' => [
            'enabled' => true,
            'except'  => ['auditlog:*', 'schedule:run', 'schedule:finish', 'queue:*', 'list', 'inspire'],
            'only_failed_or_mutating' => true,
        ],

        // خطاهای برنامه (از طریق Illuminate\Log\Events\MessageLogged)
        'error' => [
            'enabled'   => true,
            'levels'    => ['error', 'critical', 'alert', 'emergency'],
            'trace_lines' => 20,
        ],

        // کوئری‌های کند دیتابیس
        'slow_query' => [
            'enabled'      => (bool) env('AUDITLOG_SLOW_QUERY', false),
            'threshold_ms' => 500,
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | پاک‌سازی داده‌های حساس
    |--------------------------------------------------------------------------
    */
    'redaction' => [
        'keys' => [
            'password', 'password_confirmation', 'current_password', 'new_password',
            'token', 'access_token', 'refresh_token', 'api_token', 'api_key', 'secret',
            'authorization', 'cookie', 'remember_token', 'license_key', 'license_token',
            'private_key', 'card_number', 'cvv', 'iban', 'otp', 'recovery_code',
        ],
        'mask'              => '***REDACTED***',
        'max_value_length'  => 2000,
        'max_array_depth'   => 6,
        'max_array_items'   => 200,
    ],

    /*
    |--------------------------------------------------------------------------
    | ذخیره‌سازی محلی
    |--------------------------------------------------------------------------
    */
    'storage' => [
        'connection'   => env('AUDITLOG_DB_CONNECTION'), // null = کانکشن پیش‌فرض
        'buffer'       => true,   // تجمیع رکوردها و درج دسته‌ای در پایان ریکوئست
        'buffer_limit' => 200,
        'queue_writes' => false,  // اگر true باشد نوشتن از طریق صف انجام می‌شود
    ],

    /*
    |--------------------------------------------------------------------------
    | زنجیره‌ی یکپارچگی (tamper-evident)
    |--------------------------------------------------------------------------
    | هر رکورد هش رکورد قبلی را در خود دارد؛ دستکاری یا حذف رکورد با
    | «php artisan auditlog:verify» قابل تشخیص است.
    */
    'integrity' => [
        'enabled' => true,
        'algo'    => 'sha256',
        'secret'  => env('AUDITLOG_INTEGRITY_SECRET', env('APP_KEY')),
    ],

    /*
    |--------------------------------------------------------------------------
    | ارسال لاگ‌ها به StoreServer
    |--------------------------------------------------------------------------
    | اندپوینت سمت سرور توسط تیم StoreServer تعریف می‌شود؛ قرارداد کامل
    | Request/Response در docs/storeserver-endpoint.md آمده است.
    */
    'shipping' => [
        'enabled'  => (bool) env('AUDITLOG_SHIPPING_ENABLED', true),
        'base_url' => rtrim((string) env('STORE_SERVER_URL', 'https://store-server.local'), '/'),

        // مسیرهای نسبی روی StoreServer
        'endpoints' => [
            'ingest' => env('AUDITLOG_INGEST_PATH', '/api/v1/logs/ingest'),
            'ack'    => env('AUDITLOG_ACK_PATH', '/api/v1/logs/ack'),
            'ping'   => env('AUDITLOG_PING_PATH', '/api/v1/logs/ping'),
        ],

        // فقط این کانال‌ها ارسال می‌شوند ('*' یعنی همه)
        'channels'      => ['*'],
        'minimum_level' => env('AUDITLOG_SHIP_MIN_LEVEL', 'info'),

        'batch_size'           => (int) env('AUDITLOG_BATCH_SIZE', 200),
        'max_batches_per_run'  => (int) env('AUDITLOG_MAX_BATCHES', 5),
        'timeout'              => (int) env('AUDITLOG_HTTP_TIMEOUT', 20),
        'connect_timeout'      => (int) env('AUDITLOG_HTTP_CONNECT_TIMEOUT', 5),
        'retries'              => (int) env('AUDITLOG_HTTP_RETRIES', 2),
        'retry_delay_ms'       => 750,
        'verify_ssl'           => (bool) env('AUDITLOG_VERIFY_SSL', true),
        'gzip'                 => (bool) env('AUDITLOG_GZIP', true),
        'gzip_threshold_bytes' => 16384,
        'max_attempts'         => (int) env('AUDITLOG_MAX_ATTEMPTS', 8),

        // فاصله‌ی تلاش مجدد بر حسب ثانیه (نمایی/پلکانی)
        'backoff' => [60, 180, 600, 1800, 3600, 10800, 21600, 43200],

        // احراز هویت (هم‌راستا با ClientApi\Http\Middleware\AuthenticateLicenseToken +
        // VerifyClientSignature در StoreServer؛ سرور راز مشترک/HMAC جداگانه‌ای ندارد)
        'auth' => [
            'license_token' => env('STORE_SERVER_LICENSE_TOKEN'),
            'license_uuid'  => env('STORE_SERVER_LICENSE_UUID'),
            'fingerprint'   => env('STORE_SERVER_FINGERPRINT'),
        ],

        'headers' => [
            'timestamp'   => 'X-GS-Timestamp',
            'nonce'       => 'X-GS-Nonce',
            'fingerprint' => 'X-GS-Fingerprint',
            'client'      => 'X-GS-Client',
            'batch'       => 'X-GS-Batch-Id',
            'idempotency' => 'Idempotency-Key',
        ],

        // چون اپ دسکتاپ 24 ساعته روشن نیست، ارسال روی boot اپ هم بررسی می‌شود
        'auto' => [
            'enabled'          => (bool) env('AUDITLOG_AUTO_SHIP', true),
            'on_boot'          => true,
            'interval_seconds' => (int) env('AUDITLOG_SHIP_INTERVAL', 300),
            'queue'            => env('AUDITLOG_QUEUE_CONNECTION'), // null = اجرای همزمان
            'lock_seconds'     => 120,
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | نگه‌داری و پاک‌سازی
    |--------------------------------------------------------------------------
    */
    'retention' => [
        'enabled'        => true,
        'days'           => (int) env('AUDITLOG_RETENTION_DAYS', 120),
        'keep_unsynced'  => true,     // رکورد ارسال‌نشده هرگز حذف نمی‌شود
        'chunk'          => 1000,
        'level_overrides' => [
            'critical' => 365,
            'alert'    => 365,
            'error'    => 240,
        ],
        'batches_days'   => 30,
    ],

    /*
    |--------------------------------------------------------------------------
    | API محلی (پنل مدیریت گیم‌استور)
    |--------------------------------------------------------------------------
    */
    'api' => [
        'prefix'      => 'auditlog',
        'middleware'  => ['auth:sanctum', 'license.module:AuditLog'],
        'per_page'    => 30,
        'max_per_page' => 200,
        'export_limit' => 50000,
    ],
];
