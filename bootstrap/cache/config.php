<?php return array (
  'broadcasting' => 
  array (
    'default' => 'log',
    'connections' => 
    array (
      'reverb' => 
      array (
        'driver' => 'reverb',
        'key' => NULL,
        'secret' => NULL,
        'app_id' => NULL,
        'options' => 
        array (
          'host' => NULL,
          'port' => 443,
          'scheme' => 'https',
          'useTLS' => true,
        ),
        'client_options' => 
        array (
        ),
      ),
      'pusher' => 
      array (
        'driver' => 'pusher',
        'key' => NULL,
        'secret' => NULL,
        'app_id' => NULL,
        'options' => 
        array (
          'cluster' => NULL,
          'host' => 'api-mt1.pusher.com',
          'port' => 443,
          'scheme' => 'https',
          'encrypted' => true,
          'useTLS' => true,
        ),
        'client_options' => 
        array (
        ),
      ),
      'ably' => 
      array (
        'driver' => 'ably',
        'key' => NULL,
      ),
      'log' => 
      array (
        'driver' => 'log',
      ),
      'null' => 
      array (
        'driver' => 'null',
      ),
    ),
  ),
  'concurrency' => 
  array (
    'default' => 'process',
  ),
  'cors' => 
  array (
    'paths' => 
    array (
      0 => 'api/*',
      1 => 'sanctum/csrf-cookie',
    ),
    'allowed_methods' => 
    array (
      0 => '*',
    ),
    'allowed_origins' => 
    array (
      0 => '*',
    ),
    'allowed_origins_patterns' => 
    array (
    ),
    'allowed_headers' => 
    array (
      0 => '*',
    ),
    'exposed_headers' => 
    array (
    ),
    'max_age' => 0,
    'supports_credentials' => false,
  ),
  'hashing' => 
  array (
    'driver' => 'bcrypt',
    'bcrypt' => 
    array (
      'rounds' => '12',
      'verify' => true,
      'limit' => NULL,
    ),
    'argon' => 
    array (
      'memory' => 65536,
      'threads' => 1,
      'time' => 4,
      'verify' => true,
    ),
    'rehash_on_login' => true,
  ),
  'view' => 
  array (
    'paths' => 
    array (
      0 => 'C:\\Users\\moham\\Desktop\\projects\\gameshop\\resources\\views',
    ),
    'compiled' => 'C:\\Users\\moham\\Desktop\\projects\\gameshop\\storage\\framework\\views',
  ),
  'app' => 
  array (
    'name' => 'Laravel',
    'env' => 'local',
    'debug' => true,
    'url' => 'http://localhost',
    'frontend_url' => 'http://localhost:3000',
    'asset_url' => NULL,
    'timezone' => 'Asia/Tehran',
    'locale' => 'fa',
    'fallback_locale' => 'fa',
    'faker_locale' => 'fa_IR',
    'cipher' => 'AES-256-CBC',
    'key' => 'base64:hiXNHavDLHSO5pmnTKR7YhkJmjdj/PvvIVdHtNwwpxk=',
    'previous_keys' => 
    array (
    ),
    'maintenance' => 
    array (
      'driver' => 'file',
      'store' => 'database',
    ),
    'providers' => 
    array (
      0 => 'Illuminate\\Auth\\AuthServiceProvider',
      1 => 'Illuminate\\Broadcasting\\BroadcastServiceProvider',
      2 => 'Illuminate\\Bus\\BusServiceProvider',
      3 => 'Illuminate\\Cache\\CacheServiceProvider',
      4 => 'Illuminate\\Foundation\\Providers\\ConsoleSupportServiceProvider',
      5 => 'Illuminate\\Concurrency\\ConcurrencyServiceProvider',
      6 => 'Illuminate\\Cookie\\CookieServiceProvider',
      7 => 'Illuminate\\Database\\DatabaseServiceProvider',
      8 => 'Illuminate\\Encryption\\EncryptionServiceProvider',
      9 => 'Illuminate\\Filesystem\\FilesystemServiceProvider',
      10 => 'Illuminate\\Foundation\\Providers\\FoundationServiceProvider',
      11 => 'Illuminate\\Hashing\\HashServiceProvider',
      12 => 'Illuminate\\Mail\\MailServiceProvider',
      13 => 'Illuminate\\Notifications\\NotificationServiceProvider',
      14 => 'Illuminate\\Pagination\\PaginationServiceProvider',
      15 => 'Illuminate\\Auth\\Passwords\\PasswordResetServiceProvider',
      16 => 'Illuminate\\Pipeline\\PipelineServiceProvider',
      17 => 'Illuminate\\Queue\\QueueServiceProvider',
      18 => 'Illuminate\\Redis\\RedisServiceProvider',
      19 => 'Illuminate\\Session\\SessionServiceProvider',
      20 => 'Illuminate\\Translation\\TranslationServiceProvider',
      21 => 'Illuminate\\Validation\\ValidationServiceProvider',
      22 => 'Illuminate\\View\\ViewServiceProvider',
      23 => 'App\\Providers\\AppServiceProvider',
    ),
    'aliases' => 
    array (
      'App' => 'Illuminate\\Support\\Facades\\App',
      'Arr' => 'Illuminate\\Support\\Arr',
      'Artisan' => 'Illuminate\\Support\\Facades\\Artisan',
      'Auth' => 'Illuminate\\Support\\Facades\\Auth',
      'Benchmark' => 'Illuminate\\Support\\Benchmark',
      'Blade' => 'Illuminate\\Support\\Facades\\Blade',
      'Broadcast' => 'Illuminate\\Support\\Facades\\Broadcast',
      'Bus' => 'Illuminate\\Support\\Facades\\Bus',
      'Cache' => 'Illuminate\\Support\\Facades\\Cache',
      'Concurrency' => 'Illuminate\\Support\\Facades\\Concurrency',
      'Config' => 'Illuminate\\Support\\Facades\\Config',
      'Context' => 'Illuminate\\Support\\Facades\\Context',
      'Cookie' => 'Illuminate\\Support\\Facades\\Cookie',
      'Crypt' => 'Illuminate\\Support\\Facades\\Crypt',
      'Date' => 'Illuminate\\Support\\Facades\\Date',
      'DB' => 'Illuminate\\Support\\Facades\\DB',
      'Eloquent' => 'Illuminate\\Database\\Eloquent\\Model',
      'Event' => 'Illuminate\\Support\\Facades\\Event',
      'File' => 'Illuminate\\Support\\Facades\\File',
      'Gate' => 'Illuminate\\Support\\Facades\\Gate',
      'Hash' => 'Illuminate\\Support\\Facades\\Hash',
      'Http' => 'Illuminate\\Support\\Facades\\Http',
      'Js' => 'Illuminate\\Support\\Js',
      'Lang' => 'Illuminate\\Support\\Facades\\Lang',
      'Log' => 'Illuminate\\Support\\Facades\\Log',
      'Mail' => 'Illuminate\\Support\\Facades\\Mail',
      'Notification' => 'Illuminate\\Support\\Facades\\Notification',
      'Number' => 'Illuminate\\Support\\Number',
      'Password' => 'Illuminate\\Support\\Facades\\Password',
      'Process' => 'Illuminate\\Support\\Facades\\Process',
      'Queue' => 'Illuminate\\Support\\Facades\\Queue',
      'RateLimiter' => 'Illuminate\\Support\\Facades\\RateLimiter',
      'Redirect' => 'Illuminate\\Support\\Facades\\Redirect',
      'Request' => 'Illuminate\\Support\\Facades\\Request',
      'Response' => 'Illuminate\\Support\\Facades\\Response',
      'Route' => 'Illuminate\\Support\\Facades\\Route',
      'Schedule' => 'Illuminate\\Support\\Facades\\Schedule',
      'Schema' => 'Illuminate\\Support\\Facades\\Schema',
      'Session' => 'Illuminate\\Support\\Facades\\Session',
      'Storage' => 'Illuminate\\Support\\Facades\\Storage',
      'Str' => 'Illuminate\\Support\\Str',
      'Uri' => 'Illuminate\\Support\\Uri',
      'URL' => 'Illuminate\\Support\\Facades\\URL',
      'Validator' => 'Illuminate\\Support\\Facades\\Validator',
      'View' => 'Illuminate\\Support\\Facades\\View',
      'Vite' => 'Illuminate\\Support\\Facades\\Vite',
    ),
  ),
  'auth' => 
  array (
    'defaults' => 
    array (
      'guard' => 'web',
      'passwords' => 'users',
    ),
    'guards' => 
    array (
      'web' => 
      array (
        'driver' => 'session',
        'provider' => 'users',
      ),
      'sanctum' => 
      array (
        'driver' => 'sanctum',
        'provider' => NULL,
      ),
    ),
    'providers' => 
    array (
      'users' => 
      array (
        'driver' => 'eloquent',
        'model' => 'App\\Models\\User',
      ),
    ),
    'passwords' => 
    array (
      'users' => 
      array (
        'provider' => 'users',
        'table' => 'password_reset_tokens',
        'expire' => 60,
        'throttle' => 60,
      ),
    ),
    'password_timeout' => 10800,
  ),
  'backup' => 
  array (
    'format_version' => '1.0',
    'app_name' => 'GameStore',
    'native_dialog' => true,
    'paths' => 
    array (
      'export_root' => NULL,
      'import_root' => NULL,
      'folder_name' => 'GameStore-Backups',
      'inbox_name' => 'GameStore-Restore',
      'database_dir' => 'database',
      'media_dir' => 'media',
      'logs_dir' => 'logs',
      'manifest_file' => 'manifest.json',
      'checksum_file' => 'checksums.sha256',
      'readme_file' => 'README.txt',
    ),
    'csv' => 
    array (
      'delimiter' => ',',
      'enclosure' => '"',
      'escape' => '\\',
      'line_ending' => '
',
      'bom' => true,
      'null_marker' => '\\N',
      'extension' => 'csv',
    ),
    'runtime' => 
    array (
      'chunk_size' => 1000,
      'memory_limit' => '512M',
      'time_limit' => 0,
      'min_free_space_mb' => 200,
      'lock_seconds' => 1800,
      'retention_copies' => 10,
      'safety_retention_copies' => 3,
      'include_media' => true,
      'verify_checksums' => true,
      'include_soft_deleted' => true,
      'vacuum_after_import' => true,
    ),
    'media' => 
    array (
      'disk' => 'public',
      'allowed_mimes' => 
      array (
        0 => 'image/jpeg',
        1 => 'image/png',
        2 => 'image/gif',
        3 => 'image/webp',
        4 => 'image/svg+xml',
        5 => 'application/pdf',
      ),
      'max_file_mb' => 25,
      'hash_algorithm' => 'sha256',
      'deduplicate' => true,
      'relink_on_import' => true,
    ),
    'excluded_tables' => 
    array (
      0 => 'migrations',
      1 => 'sessions',
      2 => 'cache',
      3 => 'cache_locks',
      4 => 'jobs',
      5 => 'job_batches',
      6 => 'failed_jobs',
      7 => 'password_reset_tokens',
      8 => 'personal_access_tokens',
      9 => 'backup_runs',
      10 => 'backup_run_entities',
      11 => 'backup_files',
      12 => 'backup_run_events',
      13 => 'backup_settings',
    ),
    'groups' => 
    array (
      '00_core' => 'هسته و پیکربندی',
      '10_people' => 'کاربران و مشتریان',
      '20_catalog' => 'کالا و دسته‌بندی',
      '30_sales' => 'فروش و فاکتورها',
      '40_services' => 'خدمات و تعمیرات',
      '50_inventory' => 'انبار و گردش موجودی',
      '60_analytics' => 'آمار و گزارش‌ها',
      '70_archive' => 'بایگانی',
    ),
    'entities' => 
    array (
      'users' => 
      array (
        'table' => 'users',
        'model' => 'App\\Models\\User',
        'group' => '00_core',
        'label' => 'کاربران',
        'natural_key' => 
        array (
          0 => 'email',
        ),
        'soft_deletes' => true,
        'redact' => 
        array (
          0 => 'password',
          1 => 'remember_token',
        ),
        'optional' => false,
      ),
      'setting_groups' => 
      array (
        'table' => 'setting_groups',
        'model' => 'App\\Models\\SettingGroup',
        'group' => '00_core',
        'label' => 'گروه‌های تنظیمات',
        'natural_key' => 
        array (
          0 => 'code',
        ),
        'soft_deletes' => false,
      ),
      'app_settings' => 
      array (
        'table' => 'app_settings',
        'model' => 'App\\Models\\AppSetting',
        'group' => '00_core',
        'label' => 'تنظیمات برنامه',
        'natural_key' => 
        array (
          0 => 'group_id',
          1 => 'setting_key',
        ),
        'soft_deletes' => false,
      ),
      'store_profiles' => 
      array (
        'table' => 'store_profiles',
        'model' => 'Modules\\Profile\\Models\\StoreProfile',
        'group' => '00_core',
        'label' => 'پروفایل فروشگاه',
        'natural_key' => 
        array (
          0 => 'slug',
        ),
        'soft_deletes' => false,
        'media' => 
        array (
          'logo_path' => 'store/logo',
          'cover_path' => 'store/cover',
        ),
      ),
      'categories' => 
      array (
        'table' => 'categories',
        'model' => 'Modules\\Category\\Models\\Category',
        'group' => '20_catalog',
        'label' => 'دسته‌بندی‌ها',
        'natural_key' => 
        array (
          0 => 'name',
        ),
        'soft_deletes' => true,
      ),
      'customers' => 
      array (
        'table' => 'customers',
        'model' => 'Modules\\Customer\\Models\\Customer',
        'group' => '10_people',
        'label' => 'مشتریان',
        'natural_key' => 
        array (
          0 => 'phone',
          1 => 'name',
        ),
        'soft_deletes' => true,
      ),
      'adjustment_categories' => 
      array (
        'table' => 'adjustment_categories',
        'model' => 'Modules\\Invoice\\Models\\AdjustmentCategory',
        'group' => '30_sales',
        'label' => 'دسته‌بندی تعدیل‌ها',
        'natural_key' => 
        array (
          0 => 'key',
        ),
        'soft_deletes' => false,
      ),
      'items' => 
      array (
        'table' => 'items',
        'model' => 'Modules\\Stock\\Models\\Item',
        'group' => '20_catalog',
        'label' => 'کالاها',
        'natural_key' => 
        array (
          0 => 'name',
          1 => 'category_id',
        ),
        'soft_deletes' => true,
        'media' => 
        array (
          'image_path' => 'items',
        ),
      ),
      'requests' => 
      array (
        'table' => 'requests',
        'model' => 'Modules\\Request\\Models\\Request',
        'group' => '40_services',
        'label' => 'درخواست‌ها',
        'natural_key' => 
        array (
        ),
        'soft_deletes' => true,
      ),
      'request_categories' => 
      array (
        'table' => 'request_categories',
        'model' => NULL,
        'group' => '40_services',
        'label' => 'دسته‌بندی درخواست‌ها',
        'natural_key' => 
        array (
          0 => 'request_id',
          1 => 'category_id',
        ),
        'soft_deletes' => false,
      ),
      'invoices' => 
      array (
        'table' => 'invoices',
        'model' => 'Modules\\Invoice\\Models\\Invoice',
        'group' => '30_sales',
        'label' => 'فاکتورها',
        'natural_key' => 
        array (
          0 => 'invoice_number',
        ),
        'soft_deletes' => true,
        'media' => 
        array (
          'receipt_image_path' => 'invoices/receipts',
        ),
      ),
      'invoice_adjustments' => 
      array (
        'table' => 'invoice_adjustments',
        'model' => 'Modules\\Invoice\\Models\\InvoiceAdjustment',
        'group' => '30_sales',
        'label' => 'تعدیل‌های فاکتور',
        'natural_key' => 
        array (
        ),
        'soft_deletes' => false,
        'optional' => true,
      ),
      'order_items' => 
      array (
        'table' => 'order_items',
        'model' => 'Modules\\Invoice\\Models\\OrderItem',
        'group' => '30_sales',
        'label' => 'اقلام فاکتور',
        'natural_key' => 
        array (
        ),
        'soft_deletes' => true,
        'media' => 
        array (
          'image_path' => 'order-items',
        ),
      ),
      'service_types' => 
      array (
        'table' => 'service_types',
        'model' => 'Modules\\Service\\Models\\ServiceType',
        'group' => '40_services',
        'label' => 'انواع خدمات',
        'natural_key' => 
        array (
          0 => 'name',
        ),
        'soft_deletes' => true,
      ),
      'service_jobs' => 
      array (
        'table' => 'service_jobs',
        'model' => 'Modules\\Service\\Models\\ServiceJob',
        'group' => '40_services',
        'label' => 'کارهای خدماتی',
        'natural_key' => 
        array (
        ),
        'soft_deletes' => true,
      ),
      'service_job_items' => 
      array (
        'table' => 'service_job_items',
        'model' => 'Modules\\Service\\Models\\ServiceJobItem',
        'group' => '40_services',
        'label' => 'قطعات مصرفی خدمات',
        'natural_key' => 
        array (
        ),
        'soft_deletes' => true,
      ),
      'service_job_service_types' => 
      array (
        'table' => 'service_job_service_types',
        'model' => 'Modules\\Service\\Models\\ServiceJobServiceType',
        'group' => '40_services',
        'label' => 'خدمات هر کار',
        'natural_key' => 
        array (
        ),
        'soft_deletes' => false,
        'optional' => true,
      ),
      'stock_movements' => 
      array (
        'table' => 'stock_movements',
        'model' => 'Modules\\Stock\\Models\\StockMovement',
        'group' => '50_inventory',
        'label' => 'گردش انبار',
        'natural_key' => 
        array (
        ),
        'soft_deletes' => true,
      ),
      'daily_item_stats' => 
      array (
        'table' => 'daily_item_stats',
        'model' => 'Modules\\Stats\\Models\\DailyItemStat',
        'group' => '60_analytics',
        'label' => 'آمار روزانه کالا',
        'natural_key' => 
        array (
          0 => 'stat_date',
          1 => 'item_id',
        ),
        'soft_deletes' => true,
      ),
      'monthly_sales' => 
      array (
        'table' => 'monthly_sales',
        'model' => 'Modules\\Stats\\Models\\MonthlySale',
        'group' => '60_analytics',
        'label' => 'فروش ماهانه',
        'natural_key' => 
        array (
          0 => 'year',
          1 => 'month',
        ),
        'soft_deletes' => true,
      ),
      'archived_records' => 
      array (
        'table' => 'archived_records',
        'model' => 'Modules\\Archive\\Models\\ArchivedRecord',
        'group' => '70_archive',
        'label' => 'رکوردهای بایگانی',
        'natural_key' => 
        array (
          0 => 'source_type',
          1 => 'source_id',
        ),
        'soft_deletes' => true,
        'optional' => true,
      ),
      'archive_actions' => 
      array (
        'table' => 'archive_actions',
        'model' => 'Modules\\Archive\\Models\\ArchiveAction',
        'group' => '70_archive',
        'label' => 'رویدادهای بایگانی',
        'natural_key' => 
        array (
        ),
        'soft_deletes' => false,
        'optional' => true,
      ),
      'cache_maintenance_runs' => 
      array (
        'table' => 'cache_maintenance_runs',
        'model' => NULL,
        'group' => '00_core',
        'label' => 'لاگ نگهداری کش',
        'natural_key' => 
        array (
        ),
        'soft_deletes' => false,
        'optional' => true,
      ),
    ),
    'name' => 'Backup',
  ),
  'cache' => 
  array (
    'default' => 'file',
    'stores' => 
    array (
      'array' => 
      array (
        'driver' => 'array',
        'serialize' => false,
      ),
      'session' => 
      array (
        'driver' => 'session',
        'key' => '_cache',
      ),
      'database' => 
      array (
        'driver' => 'database',
        'connection' => NULL,
        'table' => 'cache',
        'lock_connection' => NULL,
        'lock_table' => NULL,
      ),
      'file' => 
      array (
        'driver' => 'file',
        'path' => 'C:\\Users\\moham\\Desktop\\projects\\gameshop\\storage\\framework/cache/data',
        'lock_path' => 'C:\\Users\\moham\\Desktop\\projects\\gameshop\\storage\\framework/cache/data',
      ),
      'memcached' => 
      array (
        'driver' => 'memcached',
        'persistent_id' => NULL,
        'sasl' => 
        array (
          0 => NULL,
          1 => NULL,
        ),
        'options' => 
        array (
        ),
        'servers' => 
        array (
          0 => 
          array (
            'host' => '127.0.0.1',
            'port' => 11211,
            'weight' => 100,
          ),
        ),
      ),
      'redis' => 
      array (
        'driver' => 'redis',
        'connection' => 'cache',
        'lock_connection' => 'default',
      ),
      'dynamodb' => 
      array (
        'driver' => 'dynamodb',
        'key' => '',
        'secret' => '',
        'region' => 'us-east-1',
        'table' => 'cache',
        'endpoint' => NULL,
      ),
      'octane' => 
      array (
        'driver' => 'octane',
      ),
      'failover' => 
      array (
        'driver' => 'failover',
        'stores' => 
        array (
          0 => 'database',
          1 => 'array',
        ),
      ),
    ),
    'prefix' => 'laravel-cache-',
  ),
  'database' => 
  array (
    'default' => 'sqlite',
    'connections' => 
    array (
      'sqlite' => 
      array (
        'driver' => 'sqlite',
        'url' => NULL,
        'database' => 'C:\\Users\\moham\\Desktop\\projects\\gameshop\\database\\database.sqlite',
        'prefix' => '',
        'foreign_key_constraints' => true,
        'busy_timeout' => NULL,
        'journal_mode' => NULL,
        'synchronous' => NULL,
        'transaction_mode' => 'DEFERRED',
      ),
      'mysql' => 
      array (
        'driver' => 'mysql',
        'url' => NULL,
        'host' => '127.0.0.1',
        'port' => '3306',
        'database' => 'laravel',
        'username' => 'root',
        'password' => '',
        'unix_socket' => '',
        'charset' => 'utf8mb4',
        'collation' => 'utf8mb4_unicode_ci',
        'prefix' => '',
        'prefix_indexes' => true,
        'strict' => true,
        'engine' => NULL,
        'options' => 
        array (
        ),
      ),
      'mariadb' => 
      array (
        'driver' => 'mariadb',
        'url' => NULL,
        'host' => '127.0.0.1',
        'port' => '3306',
        'database' => 'laravel',
        'username' => 'root',
        'password' => '',
        'unix_socket' => '',
        'charset' => 'utf8mb4',
        'collation' => 'utf8mb4_unicode_ci',
        'prefix' => '',
        'prefix_indexes' => true,
        'strict' => true,
        'engine' => NULL,
        'options' => 
        array (
        ),
      ),
      'pgsql' => 
      array (
        'driver' => 'pgsql',
        'url' => NULL,
        'host' => '127.0.0.1',
        'port' => '5432',
        'database' => 'laravel',
        'username' => 'root',
        'password' => '',
        'charset' => 'utf8',
        'prefix' => '',
        'prefix_indexes' => true,
        'search_path' => 'public',
        'sslmode' => 'prefer',
      ),
      'sqlsrv' => 
      array (
        'driver' => 'sqlsrv',
        'url' => NULL,
        'host' => 'localhost',
        'port' => '1433',
        'database' => 'laravel',
        'username' => 'root',
        'password' => '',
        'charset' => 'utf8',
        'prefix' => '',
        'prefix_indexes' => true,
      ),
    ),
    'migrations' => 
    array (
      'table' => 'migrations',
      'update_date_on_publish' => true,
    ),
    'redis' => 
    array (
      'client' => 'phpredis',
      'options' => 
      array (
        'cluster' => 'redis',
        'prefix' => 'laravel-database-',
        'persistent' => false,
      ),
      'default' => 
      array (
        'url' => NULL,
        'host' => '127.0.0.1',
        'username' => NULL,
        'password' => NULL,
        'port' => '6379',
        'database' => '0',
        'max_retries' => 3,
        'backoff_algorithm' => 'decorrelated_jitter',
        'backoff_base' => 100,
        'backoff_cap' => 1000,
      ),
      'cache' => 
      array (
        'url' => NULL,
        'host' => '127.0.0.1',
        'username' => NULL,
        'password' => NULL,
        'port' => '6379',
        'database' => '1',
        'max_retries' => 3,
        'backoff_algorithm' => 'decorrelated_jitter',
        'backoff_base' => 100,
        'backoff_cap' => 1000,
      ),
    ),
  ),
  'filesystems' => 
  array (
    'default' => 'local',
    'disks' => 
    array (
      'local' => 
      array (
        'driver' => 'local',
        'root' => 'C:\\Users\\moham\\Desktop\\projects\\gameshop\\storage\\app/private',
        'serve' => true,
        'throw' => false,
        'report' => false,
      ),
      'public' => 
      array (
        'driver' => 'local',
        'root' => 'C:\\Users\\moham\\Desktop\\projects\\gameshop\\storage\\app/public',
        'url' => 'http://localhost/storage',
        'visibility' => 'public',
        'throw' => false,
        'report' => false,
      ),
      's3' => 
      array (
        'driver' => 's3',
        'key' => '',
        'secret' => '',
        'region' => 'us-east-1',
        'bucket' => '',
        'url' => NULL,
        'endpoint' => NULL,
        'use_path_style_endpoint' => false,
        'throw' => false,
        'report' => false,
      ),
    ),
    'links' => 
    array (
      'C:\\Users\\moham\\Desktop\\projects\\gameshop\\public\\storage' => 'C:\\Users\\moham\\Desktop\\projects\\gameshop\\storage\\app/public',
    ),
  ),
  'logging' => 
  array (
    'default' => 'stack',
    'deprecations' => 
    array (
      'channel' => NULL,
      'trace' => false,
    ),
    'channels' => 
    array (
      'stack' => 
      array (
        'driver' => 'stack',
        'channels' => 
        array (
          0 => 'single',
        ),
        'ignore_exceptions' => false,
      ),
      'single' => 
      array (
        'driver' => 'single',
        'path' => 'C:\\Users\\moham\\Desktop\\projects\\gameshop\\storage\\logs/laravel.log',
        'level' => 'debug',
        'replace_placeholders' => true,
      ),
      'daily' => 
      array (
        'driver' => 'daily',
        'path' => 'C:\\Users\\moham\\Desktop\\projects\\gameshop\\storage\\logs/laravel.log',
        'level' => 'debug',
        'days' => 14,
        'replace_placeholders' => true,
      ),
      'slack' => 
      array (
        'driver' => 'slack',
        'url' => NULL,
        'username' => 'Laravel Log',
        'emoji' => ':boom:',
        'level' => 'debug',
        'replace_placeholders' => true,
      ),
      'papertrail' => 
      array (
        'driver' => 'monolog',
        'level' => 'debug',
        'handler' => 'Monolog\\Handler\\SyslogUdpHandler',
        'handler_with' => 
        array (
          'host' => NULL,
          'port' => NULL,
          'connectionString' => 'tls://:',
        ),
        'processors' => 
        array (
          0 => 'Monolog\\Processor\\PsrLogMessageProcessor',
        ),
      ),
      'stderr' => 
      array (
        'driver' => 'monolog',
        'level' => 'debug',
        'handler' => 'Monolog\\Handler\\StreamHandler',
        'handler_with' => 
        array (
          'stream' => 'php://stderr',
        ),
        'formatter' => NULL,
        'processors' => 
        array (
          0 => 'Monolog\\Processor\\PsrLogMessageProcessor',
        ),
      ),
      'syslog' => 
      array (
        'driver' => 'syslog',
        'level' => 'debug',
        'facility' => 8,
        'replace_placeholders' => true,
      ),
      'errorlog' => 
      array (
        'driver' => 'errorlog',
        'level' => 'debug',
        'replace_placeholders' => true,
      ),
      'null' => 
      array (
        'driver' => 'monolog',
        'handler' => 'Monolog\\Handler\\NullHandler',
      ),
      'emergency' => 
      array (
        'path' => 'C:\\Users\\moham\\Desktop\\projects\\gameshop\\storage\\logs/laravel.log',
      ),
      'settings' => 
      array (
        'driver' => 'single',
        'path' => 'C:\\Users\\moham\\Desktop\\projects\\gameshop\\storage\\logs/settings.log',
        'level' => 'info',
      ),
    ),
  ),
  'mail' => 
  array (
    'default' => 'log',
    'mailers' => 
    array (
      'smtp' => 
      array (
        'transport' => 'smtp',
        'scheme' => NULL,
        'url' => NULL,
        'host' => '127.0.0.1',
        'port' => '2525',
        'username' => NULL,
        'password' => NULL,
        'timeout' => NULL,
        'local_domain' => 'localhost',
      ),
      'ses' => 
      array (
        'transport' => 'ses',
      ),
      'postmark' => 
      array (
        'transport' => 'postmark',
      ),
      'resend' => 
      array (
        'transport' => 'resend',
      ),
      'sendmail' => 
      array (
        'transport' => 'sendmail',
        'path' => '/usr/sbin/sendmail -bs -i',
      ),
      'log' => 
      array (
        'transport' => 'log',
        'channel' => NULL,
      ),
      'array' => 
      array (
        'transport' => 'array',
      ),
      'failover' => 
      array (
        'transport' => 'failover',
        'mailers' => 
        array (
          0 => 'smtp',
          1 => 'log',
        ),
        'retry_after' => 60,
      ),
      'roundrobin' => 
      array (
        'transport' => 'roundrobin',
        'mailers' => 
        array (
          0 => 'ses',
          1 => 'postmark',
        ),
        'retry_after' => 60,
      ),
    ),
    'from' => 
    array (
      'address' => 'hello@example.com',
      'name' => 'Laravel',
    ),
    'markdown' => 
    array (
      'theme' => 'default',
      'paths' => 
      array (
        0 => 'C:\\Users\\moham\\Desktop\\projects\\gameshop\\resources\\views/vendor/mail',
      ),
    ),
  ),
  'nativephp' => 
  array (
    'version' => '1.0.0',
    'app_id' => 'com.nativephp.app',
    'deeplink_scheme' => NULL,
    'author' => NULL,
    'copyright' => NULL,
    'description' => 'An awesome app built with NativePHP',
    'website' => 'https://nativephp.com',
    'provider' => 'App\\Providers\\NativeAppServiceProvider',
    'cleanup_env_keys' => 
    array (
      0 => 'AWS_*',
      1 => 'AZURE_*',
      2 => 'GITHUB_*',
      3 => 'DO_SPACES_*',
      4 => '*_SECRET',
      5 => 'ZEPHPYR_*',
      6 => 'NATIVEPHP_UPDATER_PATH',
      7 => 'NATIVEPHP_APPLE_ID',
      8 => 'NATIVEPHP_APPLE_ID_PASS',
      9 => 'NATIVEPHP_APPLE_TEAM_ID',
      10 => 'NATIVEPHP_AZURE_PUBLISHER_NAME',
      11 => 'NATIVEPHP_AZURE_ENDPOINT',
      12 => 'NATIVEPHP_AZURE_CERTIFICATE_PROFILE_NAME',
      13 => 'NATIVEPHP_AZURE_CODE_SIGNING_ACCOUNT_NAME',
    ),
    'cleanup_exclude_files' => 
    array (
      0 => 'build',
      1 => 'temp',
      2 => 'content',
      3 => 'node_modules',
      4 => '*/tests',
    ),
    'updater' => 
    array (
      'enabled' => true,
      'default' => 'spaces',
      'providers' => 
      array (
        'github' => 
        array (
          'driver' => 'github',
          'repo' => NULL,
          'owner' => NULL,
          'token' => NULL,
          'vPrefixedTagName' => true,
          'private' => false,
          'channel' => 'latest',
          'releaseType' => 'draft',
        ),
        's3' => 
        array (
          'driver' => 's3',
          'key' => '',
          'secret' => '',
          'region' => 'us-east-1',
          'bucket' => '',
          'endpoint' => NULL,
          'path' => NULL,
        ),
        'spaces' => 
        array (
          'driver' => 'spaces',
          'key' => NULL,
          'secret' => NULL,
          'name' => NULL,
          'region' => NULL,
          'path' => NULL,
        ),
      ),
    ),
    'queue_workers' => 
    array (
    ),
    'prebuild' => 
    array (
      0 => 'npm run build',
      1 => 'php artisan config:clear',
      2 => 'php artisan route:clear',
      3 => 'php artisan view:clear',
      4 => 'php artisan config:cache',
      5 => 'php artisan route:cache',
      6 => 'php artisan view:cache',
    ),
    'postbuild' => 
    array (
    ),
    'binary_path' => NULL,
    'hot_reload' => 
    array (
      0 => 'C:\\Users\\moham\\Desktop\\projects\\gameshop\\app/Providers/NativeAppServiceProvider.php',
    ),
  ),
  'queue' => 
  array (
    'default' => 'database',
    'connections' => 
    array (
      'sync' => 
      array (
        'driver' => 'sync',
      ),
      'database' => 
      array (
        'driver' => 'database',
        'connection' => NULL,
        'table' => 'jobs',
        'queue' => 'default',
        'retry_after' => 90,
        'after_commit' => false,
      ),
      'beanstalkd' => 
      array (
        'driver' => 'beanstalkd',
        'host' => 'localhost',
        'queue' => 'default',
        'retry_after' => 90,
        'block_for' => 0,
        'after_commit' => false,
      ),
      'sqs' => 
      array (
        'driver' => 'sqs',
        'key' => '',
        'secret' => '',
        'prefix' => 'https://sqs.us-east-1.amazonaws.com/your-account-id',
        'queue' => 'default',
        'suffix' => NULL,
        'region' => 'us-east-1',
        'after_commit' => false,
      ),
      'redis' => 
      array (
        'driver' => 'redis',
        'connection' => 'default',
        'queue' => 'default',
        'retry_after' => 90,
        'block_for' => NULL,
        'after_commit' => false,
      ),
      'deferred' => 
      array (
        'driver' => 'deferred',
      ),
      'failover' => 
      array (
        'driver' => 'failover',
        'connections' => 
        array (
          0 => 'database',
          1 => 'deferred',
        ),
      ),
      'background' => 
      array (
        'driver' => 'background',
      ),
    ),
    'batching' => 
    array (
      'database' => 'sqlite',
      'table' => 'job_batches',
    ),
    'failed' => 
    array (
      'driver' => 'database-uuids',
      'database' => 'sqlite',
      'table' => 'failed_jobs',
    ),
  ),
  'sanctum' => 
  array (
    'stateful' => 
    array (
      0 => 'localhost',
      1 => 'localhost:3000',
      2 => '127.0.0.1',
      3 => '127.0.0.1:8000',
      4 => '::1',
      5 => 'localhost',
    ),
    'guard' => 
    array (
      0 => 'web',
    ),
    'expiration' => NULL,
    'token_prefix' => '',
    'middleware' => 
    array (
      'authenticate_session' => 'Laravel\\Sanctum\\Http\\Middleware\\AuthenticateSession',
      'encrypt_cookies' => 'Illuminate\\Cookie\\Middleware\\EncryptCookies',
      'validate_csrf_token' => 'Illuminate\\Foundation\\Http\\Middleware\\ValidateCsrfToken',
    ),
  ),
  'services' => 
  array (
    'postmark' => 
    array (
      'key' => NULL,
    ),
    'resend' => 
    array (
      'key' => NULL,
    ),
    'ses' => 
    array (
      'key' => '',
      'secret' => '',
      'region' => 'us-east-1',
    ),
    'slack' => 
    array (
      'notifications' => 
      array (
        'bot_user_oauth_token' => NULL,
        'channel' => NULL,
      ),
    ),
  ),
  'session' => 
  array (
    'driver' => 'file',
    'lifetime' => 120,
    'expire_on_close' => false,
    'encrypt' => false,
    'files' => 'C:\\Users\\moham\\Desktop\\projects\\gameshop\\storage\\framework/sessions',
    'connection' => NULL,
    'table' => 'sessions',
    'store' => NULL,
    'lottery' => 
    array (
      0 => 2,
      1 => 100,
    ),
    'cookie' => 'laravel-session',
    'path' => '/',
    'domain' => NULL,
    'secure' => NULL,
    'http_only' => true,
    'same_site' => 'lax',
    'partitioned' => false,
  ),
  'settings' => 
  array (
    'cache-maintenance' => 
    array (
      'patch' => 
      array (
        'group' => 
        array (
          'code' => 'maintenance',
          'label' => 'نگهداری سیستم',
          'icon' => '🧹',
          'sort_order' => 9,
        ),
        'settings' => 
        array (
          0 => 
          array (
            'key' => 'maintenance.cache.default_targets',
            'label' => 'Targetهای پیش‌فرض پاکسازی کش',
            'type' => 'json',
            'default' => '["app","settings","config","route","view","event","compiled","optimize","bootstrap","framework_files","expired_database_cache"]',
            'group' => 'maintenance',
            'autoload' => false,
          ),
          1 => 
          array (
            'key' => 'maintenance.cache.warm_after_clear',
            'label' => 'گرم‌سازی خودکار بعد از پاکسازی',
            'type' => 'boolean',
            'default' => false,
            'group' => 'maintenance',
            'autoload' => false,
          ),
          2 => 
          array (
            'key' => 'maintenance.cache.warm_config',
            'label' => 'بازسازی config cache بعد از پاکسازی',
            'type' => 'boolean',
            'default' => false,
            'group' => 'maintenance',
            'autoload' => false,
          ),
          3 => 
          array (
            'key' => 'maintenance.cache.warm_views',
            'label' => 'کامپایل مجدد viewها بعد از پاکسازی',
            'type' => 'boolean',
            'default' => true,
            'group' => 'maintenance',
            'autoload' => false,
          ),
          4 => 
          array (
            'key' => 'maintenance.cache.warm_settings',
            'label' => 'گرم‌سازی کش تنظیمات بعد از پاکسازی',
            'type' => 'boolean',
            'default' => true,
            'group' => 'maintenance',
            'autoload' => false,
          ),
          5 => 
          array (
            'key' => 'maintenance.cache.allow_log_cleanup',
            'label' => 'اجازه حذف لاگ‌های قدیمی',
            'type' => 'boolean',
            'default' => false,
            'group' => 'maintenance',
            'autoload' => false,
          ),
          6 => 
          array (
            'key' => 'maintenance.cache.logs_older_than_days',
            'label' => 'حداقل سن لاگ برای حذف (روز)',
            'type' => 'integer',
            'default' => 14,
            'group' => 'maintenance',
            'autoload' => false,
          ),
          7 => 
          array (
            'key' => 'maintenance.cache.allow_session_cleanup',
            'label' => 'اجازه حذف session فایل‌ها',
            'type' => 'boolean',
            'default' => false,
            'group' => 'maintenance',
            'autoload' => false,
          ),
          8 => 
          array (
            'key' => 'maintenance.cache.run_sqlite_vacuum',
            'label' => 'اجرای VACUUM/ANALYZE SQLite بعد از پاکسازی',
            'type' => 'boolean',
            'default' => false,
            'group' => 'maintenance',
            'autoload' => false,
          ),
          9 => 
          array (
            'key' => 'maintenance.cache.keep_history_days',
            'label' => 'مدت نگهداری تاریخچه پاکسازی کش (روز)',
            'type' => 'integer',
            'default' => 90,
            'group' => 'maintenance',
            'autoload' => false,
          ),
        ),
      ),
    ),
  ),
  'inertia' => 
  array (
    'ssr' => 
    array (
      'enabled' => true,
      'runtime' => 'node',
      'ensure_runtime_exists' => false,
      'url' => 'http://127.0.0.1:13714',
      'ensure_bundle_exists' => true,
      'throw_on_error' => false,
    ),
    'pages' => 
    array (
      'ensure_pages_exist' => false,
      'paths' => 
      array (
        0 => 'C:\\Users\\moham\\Desktop\\projects\\gameshop\\resources\\js/pages',
      ),
      'extensions' => 
      array (
        0 => 'js',
        1 => 'jsx',
        2 => 'svelte',
        3 => 'ts',
        4 => 'tsx',
        5 => 'vue',
      ),
    ),
    'testing' => 
    array (
      'ensure_pages_exist' => true,
    ),
    'expose_shared_prop_keys' => true,
    'history' => 
    array (
      'encrypt' => false,
    ),
  ),
  'excel' => 
  array (
    'exports' => 
    array (
      'chunk_size' => 1000,
      'pre_calculate_formulas' => false,
      'strict_null_comparison' => false,
      'csv' => 
      array (
        'delimiter' => ',',
        'enclosure' => '"',
        'line_ending' => '
',
        'use_bom' => false,
        'include_separator_line' => false,
        'excel_compatibility' => false,
        'output_encoding' => '',
        'test_auto_detect' => true,
      ),
      'properties' => 
      array (
        'creator' => '',
        'lastModifiedBy' => '',
        'title' => '',
        'description' => '',
        'subject' => '',
        'keywords' => '',
        'category' => '',
        'manager' => '',
        'company' => '',
      ),
      'source_handlers' => 
      array (
      ),
    ),
    'imports' => 
    array (
      'read_only' => true,
      'ignore_empty' => false,
      'heading_row' => 
      array (
        'formatter' => 'slug',
      ),
      'csv' => 
      array (
        'delimiter' => NULL,
        'enclosure' => '"',
        'escape_character' => '\\',
        'contiguous' => false,
        'input_encoding' => 'guess',
      ),
      'properties' => 
      array (
        'creator' => '',
        'lastModifiedBy' => '',
        'title' => '',
        'description' => '',
        'subject' => '',
        'keywords' => '',
        'category' => '',
        'manager' => '',
        'company' => '',
      ),
      'cells' => 
      array (
        'middleware' => 
        array (
        ),
      ),
    ),
    'extension_detector' => 
    array (
      'xlsx' => 'Xlsx',
      'xlsm' => 'Xlsx',
      'xltx' => 'Xlsx',
      'xltm' => 'Xlsx',
      'xls' => 'Xls',
      'xlt' => 'Xls',
      'ods' => 'Ods',
      'ots' => 'Ods',
      'slk' => 'Slk',
      'xml' => 'Xml',
      'gnumeric' => 'Gnumeric',
      'htm' => 'Html',
      'html' => 'Html',
      'csv' => 'Csv',
      'tsv' => 'Csv',
      'pdf' => 'Dompdf',
    ),
    'value_binder' => 
    array (
      'default' => 'Maatwebsite\\Excel\\DefaultValueBinder',
    ),
    'cache' => 
    array (
      'driver' => 'memory',
      'batch' => 
      array (
        'memory_limit' => 60000,
      ),
      'illuminate' => 
      array (
        'store' => NULL,
      ),
      'default_ttl' => 10800,
    ),
    'transactions' => 
    array (
      'handler' => 'db',
      'db' => 
      array (
        'connection' => NULL,
      ),
    ),
    'temporary_files' => 
    array (
      'local_path' => 'C:\\Users\\moham\\Desktop\\projects\\gameshop\\storage\\framework/cache/laravel-excel',
      'local_permissions' => 
      array (
      ),
      'remote_disk' => NULL,
      'remote_prefix' => NULL,
      'force_resync_remote' => NULL,
    ),
  ),
  'nativephp-internal' => 
  array (
    'running' => false,
    'storage_path' => NULL,
    'database_path' => NULL,
    'secret' => NULL,
    'api_url' => 'http://localhost:4000/api/',
    'zephpyr' => 
    array (
      'host' => 'https://zephpyr.com',
      'token' => NULL,
      'key' => NULL,
    ),
    'notarization' => 
    array (
      'apple_id' => NULL,
      'apple_id_pass' => NULL,
      'apple_team_id' => NULL,
    ),
    'azure_trusted_signing' => 
    array (
      'tenant_id' => NULL,
      'client_id' => NULL,
      'client_secret' => NULL,
      'publisher_name' => NULL,
      'endpoint' => NULL,
      'certificate_profile_name' => NULL,
      'code_signing_account_name' => NULL,
    ),
    'php_binary_path' => NULL,
  ),
  'modules' => 
  array (
    'namespace' => 'Modules',
    'vapor_maintenance_mode' => false,
    'stubs' => 
    array (
      'enabled' => false,
      'path' => 'C:\\Users\\moham\\Desktop\\projects\\gameshop\\vendor/nwidart/laravel-modules/src/Commands/stubs',
      'files' => 
      array (
        'routes/web' => 'routes/web.php',
        'routes/api' => 'routes/api.php',
        'views/index' => 'resources/views/index.blade.php',
        'views/master' => 'resources/views/components/layouts/master.blade.php',
        'scaffold/config' => 'config/config.php',
        'composer' => 'composer.json',
        'assets/js/app' => 'resources/assets/js/app.js',
        'assets/sass/app' => 'resources/assets/sass/app.scss',
        'vite' => 'vite.config.js',
        'package' => 'package.json',
      ),
      'replacements' => 
      array (
        'routes/web' => 
        array (
          0 => 'LOWER_NAME',
          1 => 'STUDLY_NAME',
          2 => 'PLURAL_LOWER_NAME',
          3 => 'KEBAB_NAME',
          4 => 'MODULE_NAMESPACE',
          5 => 'CONTROLLER_NAMESPACE',
        ),
        'routes/api' => 
        array (
          0 => 'LOWER_NAME',
          1 => 'STUDLY_NAME',
          2 => 'PLURAL_LOWER_NAME',
          3 => 'KEBAB_NAME',
          4 => 'MODULE_NAMESPACE',
          5 => 'CONTROLLER_NAMESPACE',
        ),
        'vite' => 
        array (
          0 => 'LOWER_NAME',
          1 => 'STUDLY_NAME',
          2 => 'KEBAB_NAME',
        ),
        'json' => 
        array (
          0 => 'LOWER_NAME',
          1 => 'STUDLY_NAME',
          2 => 'KEBAB_NAME',
          3 => 'MODULE_NAMESPACE',
          4 => 'PROVIDER_NAMESPACE',
        ),
        'views/index' => 
        array (
          0 => 'LOWER_NAME',
        ),
        'views/master' => 
        array (
          0 => 'LOWER_NAME',
          1 => 'STUDLY_NAME',
          2 => 'KEBAB_NAME',
        ),
        'scaffold/config' => 
        array (
          0 => 'STUDLY_NAME',
        ),
        'composer' => 
        array (
          0 => 'LOWER_NAME',
          1 => 'STUDLY_NAME',
          2 => 'VENDOR',
          3 => 'AUTHOR_NAME',
          4 => 'AUTHOR_EMAIL',
          5 => 'MODULE_NAMESPACE',
          6 => 'PROVIDER_NAMESPACE',
          7 => 'APP_FOLDER_NAME',
        ),
      ),
      'gitkeep' => true,
    ),
    'paths' => 
    array (
      'modules' => 'C:\\Users\\moham\\Desktop\\projects\\gameshop\\Modules',
      'assets' => 'C:\\Users\\moham\\Desktop\\projects\\gameshop\\public\\modules',
      'migration' => 'C:\\Users\\moham\\Desktop\\projects\\gameshop\\database/migrations',
      'app_folder' => 'app/',
      'generator' => 
      array (
        'actions' => 
        array (
          'path' => 'app/Actions',
          'generate' => false,
        ),
        'casts' => 
        array (
          'path' => 'app/Casts',
          'generate' => false,
        ),
        'channels' => 
        array (
          'path' => 'app/Broadcasting',
          'generate' => false,
        ),
        'class' => 
        array (
          'path' => 'app/Classes',
          'generate' => false,
        ),
        'command' => 
        array (
          'path' => 'app/Console',
          'generate' => false,
        ),
        'command_replacements' => 
        array (
          'path' => 'app/Console/Replacements',
          'generate' => false,
        ),
        'component-class' => 
        array (
          'path' => 'app/View/Components',
          'generate' => false,
        ),
        'emails' => 
        array (
          'path' => 'app/Emails',
          'generate' => false,
        ),
        'event' => 
        array (
          'path' => 'app/Events',
          'generate' => false,
        ),
        'enums' => 
        array (
          'path' => 'app/Enums',
          'generate' => false,
        ),
        'exceptions' => 
        array (
          'path' => 'app/Exceptions',
          'generate' => false,
        ),
        'jobs' => 
        array (
          'path' => 'app/Jobs',
          'generate' => false,
        ),
        'helpers' => 
        array (
          'path' => 'app/Helpers',
          'generate' => false,
        ),
        'interfaces' => 
        array (
          'path' => 'app/Interfaces',
          'generate' => false,
        ),
        'listener' => 
        array (
          'path' => 'app/Listeners',
          'generate' => false,
        ),
        'model' => 
        array (
          'path' => 'app/Models',
          'generate' => false,
        ),
        'notifications' => 
        array (
          'path' => 'app/Notifications',
          'generate' => false,
        ),
        'observer' => 
        array (
          'path' => 'app/Observers',
          'generate' => false,
        ),
        'policies' => 
        array (
          'path' => 'app/Policies',
          'generate' => false,
        ),
        'provider' => 
        array (
          'path' => 'app/Providers',
          'generate' => true,
        ),
        'repository' => 
        array (
          'path' => 'app/Repositories',
          'generate' => false,
        ),
        'resource' => 
        array (
          'path' => 'app/Transformers',
          'generate' => false,
        ),
        'route-provider' => 
        array (
          'path' => 'app/Providers',
          'generate' => true,
        ),
        'rules' => 
        array (
          'path' => 'app/Rules',
          'generate' => false,
        ),
        'services' => 
        array (
          'path' => 'app/Services',
          'generate' => false,
        ),
        'scopes' => 
        array (
          'path' => 'app/Models/Scopes',
          'generate' => false,
        ),
        'traits' => 
        array (
          'path' => 'app/Traits',
          'generate' => false,
        ),
        'controller' => 
        array (
          'path' => 'app/Http/Controllers',
          'generate' => true,
        ),
        'filter' => 
        array (
          'path' => 'app/Http/Middleware',
          'generate' => false,
        ),
        'request' => 
        array (
          'path' => 'app/Http/Requests',
          'generate' => false,
        ),
        'config' => 
        array (
          'path' => 'config',
          'generate' => true,
        ),
        'factory' => 
        array (
          'path' => 'database/factories',
          'generate' => true,
        ),
        'migration' => 
        array (
          'path' => 'database/migrations',
          'generate' => true,
        ),
        'seeder' => 
        array (
          'path' => 'database/seeders',
          'generate' => true,
        ),
        'lang' => 
        array (
          'path' => 'lang',
          'generate' => false,
        ),
        'assets' => 
        array (
          'path' => 'resources/assets',
          'generate' => true,
        ),
        'component-view' => 
        array (
          'path' => 'resources/views/components',
          'generate' => false,
        ),
        'views' => 
        array (
          'path' => 'resources/views',
          'generate' => true,
        ),
        'inertia' => 
        array (
          'path' => 'resources/js/Pages',
          'generate' => false,
        ),
        'inertia-components' => 
        array (
          'path' => 'resources/js/Components',
          'generate' => false,
        ),
        'routes' => 
        array (
          'path' => 'routes',
          'generate' => true,
        ),
        'test-feature' => 
        array (
          'path' => 'tests/Feature',
          'generate' => true,
        ),
        'test-unit' => 
        array (
          'path' => 'tests/Unit',
          'generate' => true,
        ),
      ),
    ),
    'auto-discover' => 
    array (
      'migrations' => true,
      'translations' => false,
    ),
    'commands' => 
    array (
      0 => 'Nwidart\\Modules\\Commands\\Actions\\CheckLangCommand',
      1 => 'Nwidart\\Modules\\Commands\\Actions\\DisableCommand',
      2 => 'Nwidart\\Modules\\Commands\\Actions\\DumpCommand',
      3 => 'Nwidart\\Modules\\Commands\\Actions\\EnableCommand',
      4 => 'Nwidart\\Modules\\Commands\\Actions\\InstallCommand',
      5 => 'Nwidart\\Modules\\Commands\\Actions\\ListCommand',
      6 => 'Nwidart\\Modules\\Commands\\Actions\\ListCommands',
      7 => 'Nwidart\\Modules\\Commands\\Actions\\ModelPruneCommand',
      8 => 'Nwidart\\Modules\\Commands\\Actions\\ModelShowCommand',
      9 => 'Nwidart\\Modules\\Commands\\Actions\\ModuleDeleteCommand',
      10 => 'Nwidart\\Modules\\Commands\\Actions\\UnUseCommand',
      11 => 'Nwidart\\Modules\\Commands\\Actions\\UpdateCommand',
      12 => 'Nwidart\\Modules\\Commands\\Actions\\UseCommand',
      13 => 'Nwidart\\Modules\\Commands\\Database\\MigrateCommand',
      14 => 'Nwidart\\Modules\\Commands\\Database\\MigrateRefreshCommand',
      15 => 'Nwidart\\Modules\\Commands\\Database\\MigrateResetCommand',
      16 => 'Nwidart\\Modules\\Commands\\Database\\MigrateRollbackCommand',
      17 => 'Nwidart\\Modules\\Commands\\Database\\MigrateStatusCommand',
      18 => 'Nwidart\\Modules\\Commands\\Database\\SeedCommand',
      19 => 'Nwidart\\Modules\\Commands\\Make\\ActionMakeCommand',
      20 => 'Nwidart\\Modules\\Commands\\Make\\CastMakeCommand',
      21 => 'Nwidart\\Modules\\Commands\\Make\\ChannelMakeCommand',
      22 => 'Nwidart\\Modules\\Commands\\Make\\ClassMakeCommand',
      23 => 'Nwidart\\Modules\\Commands\\Make\\CommandMakeCommand',
      24 => 'Nwidart\\Modules\\Commands\\Make\\ComponentClassMakeCommand',
      25 => 'Nwidart\\Modules\\Commands\\Make\\ComponentViewMakeCommand',
      26 => 'Nwidart\\Modules\\Commands\\Make\\ControllerMakeCommand',
      27 => 'Nwidart\\Modules\\Commands\\Make\\EventMakeCommand',
      28 => 'Nwidart\\Modules\\Commands\\Make\\EventProviderMakeCommand',
      29 => 'Nwidart\\Modules\\Commands\\Make\\EnumMakeCommand',
      30 => 'Nwidart\\Modules\\Commands\\Make\\ExceptionMakeCommand',
      31 => 'Nwidart\\Modules\\Commands\\Make\\FactoryMakeCommand',
      32 => 'Nwidart\\Modules\\Commands\\Make\\InterfaceMakeCommand',
      33 => 'Nwidart\\Modules\\Commands\\Make\\HelperMakeCommand',
      34 => 'Nwidart\\Modules\\Commands\\Make\\InertiaComponentMakeCommand',
      35 => 'Nwidart\\Modules\\Commands\\Make\\InertiaPageMakeCommand',
      36 => 'Nwidart\\Modules\\Commands\\Make\\JobMakeCommand',
      37 => 'Nwidart\\Modules\\Commands\\Make\\ListenerMakeCommand',
      38 => 'Nwidart\\Modules\\Commands\\Make\\MailMakeCommand',
      39 => 'Nwidart\\Modules\\Commands\\Make\\MiddlewareMakeCommand',
      40 => 'Nwidart\\Modules\\Commands\\Make\\MigrationMakeCommand',
      41 => 'Nwidart\\Modules\\Commands\\Make\\ModelMakeCommand',
      42 => 'Nwidart\\Modules\\Commands\\Make\\ModuleMakeCommand',
      43 => 'Nwidart\\Modules\\Commands\\Make\\NotificationMakeCommand',
      44 => 'Nwidart\\Modules\\Commands\\Make\\ObserverMakeCommand',
      45 => 'Nwidart\\Modules\\Commands\\Make\\PolicyMakeCommand',
      46 => 'Nwidart\\Modules\\Commands\\Make\\ProviderMakeCommand',
      47 => 'Nwidart\\Modules\\Commands\\Make\\RepositoryMakeCommand',
      48 => 'Nwidart\\Modules\\Commands\\Make\\RequestMakeCommand',
      49 => 'Nwidart\\Modules\\Commands\\Make\\ResourceMakeCommand',
      50 => 'Nwidart\\Modules\\Commands\\Make\\RouteProviderMakeCommand',
      51 => 'Nwidart\\Modules\\Commands\\Make\\RuleMakeCommand',
      52 => 'Nwidart\\Modules\\Commands\\Make\\ReplacementMakeCommand',
      53 => 'Nwidart\\Modules\\Commands\\Make\\ScopeMakeCommand',
      54 => 'Nwidart\\Modules\\Commands\\Make\\SeedMakeCommand',
      55 => 'Nwidart\\Modules\\Commands\\Make\\ServiceMakeCommand',
      56 => 'Nwidart\\Modules\\Commands\\Make\\TraitMakeCommand',
      57 => 'Nwidart\\Modules\\Commands\\Make\\TestMakeCommand',
      58 => 'Nwidart\\Modules\\Commands\\Make\\ViewMakeCommand',
      59 => 'Nwidart\\Modules\\Commands\\Publish\\PublishCommand',
      60 => 'Nwidart\\Modules\\Commands\\Publish\\PublishConfigurationCommand',
      61 => 'Nwidart\\Modules\\Commands\\Publish\\PublishInertiaCommand',
      62 => 'Nwidart\\Modules\\Commands\\Publish\\PublishMigrationCommand',
      63 => 'Nwidart\\Modules\\Commands\\Publish\\PublishTranslationCommand',
      64 => 'Nwidart\\Modules\\Commands\\ComposerUpdateCommand',
      65 => 'Nwidart\\Modules\\Commands\\LaravelModulesV6Migrator',
      66 => 'Nwidart\\Modules\\Commands\\SetupCommand',
      67 => 'Nwidart\\Modules\\Commands\\UpdatePhpunitCoverage',
      68 => 'Nwidart\\Modules\\Commands\\Database\\MigrateFreshCommand',
    ),
    'scan' => 
    array (
      'enabled' => false,
      'paths' => 
      array (
        0 => 'C:\\Users\\moham\\Desktop\\projects\\gameshop\\vendor/*/*',
      ),
    ),
    'composer' => 
    array (
      'vendor' => 'nwidart',
      'author' => 
      array (
        'name' => 'Nicolas Widart',
        'email' => 'n.widart@gmail.com',
      ),
      'composer-output' => false,
    ),
    'register' => 
    array (
      'translations' => true,
      'files' => 'register',
    ),
    'activators' => 
    array (
      'file' => 
      array (
        'class' => 'Nwidart\\Modules\\Activators\\FileActivator',
        'statuses-file' => 'C:\\Users\\moham\\Desktop\\projects\\gameshop\\modules_statuses.json',
      ),
    ),
    'activator' => 'file',
    'inertia' => 
    array (
      'frontend' => 'vue',
    ),
  ),
  'auditlog' => 
  array (
    'enabled' => true,
    'channels' => 
    array (
      'http' => true,
      'model' => true,
      'auth' => true,
      'security' => true,
      'job' => true,
      'console' => true,
      'error' => true,
      'system' => true,
      'business' => true,
      'sync' => true,
    ),
    'minimum_level' => 'info',
    'capture' => 
    array (
      'http' => 
      array (
        'enabled' => true,
        'methods' => 
        array (
          0 => 'POST',
          1 => 'PUT',
          2 => 'PATCH',
          3 => 'DELETE',
        ),
        'log_reads' => false,
        'slow_ms' => 1500,
        'error_status' => 400,
        'except' => 
        array (
          0 => 'api/auditlog*',
          1 => 'up',
          2 => 'telescope*',
          3 => 'horizon*',
          4 => '_debugbar*',
          5 => 'build/*',
          6 => 'storage/*',
        ),
        'store_payload' => true,
        'store_response' => false,
        'max_body_kb' => 64,
      ),
      'model' => 
      array (
        'enabled' => true,
        'events' => 
        array (
          0 => 'created',
          1 => 'updated',
          2 => 'deleted',
          3 => 'restored',
          4 => 'forceDeleted',
        ),
        'observe' => 
        array (
          0 => 'App\\Models\\User',
          1 => 'Modules\\Customer\\Models\\Customer',
          2 => 'Modules\\Invoice\\Models\\Invoice',
          3 => 'Modules\\Invoice\\Models\\OrderItem',
          4 => 'Modules\\Invoice\\Models\\InvoiceAdjustment',
          5 => 'Modules\\Invoice\\Models\\AdjustmentCategory',
          6 => 'Modules\\Request\\Models\\Request',
          7 => 'Modules\\Service\\Models\\ServiceJob',
          8 => 'Modules\\Service\\Models\\ServiceJobItem',
          9 => 'Modules\\Service\\Models\\ServiceType',
          10 => 'Modules\\Stock\\Models\\Item',
          11 => 'Modules\\Stock\\Models\\ItemSerialNumber',
          12 => 'Modules\\Stock\\Models\\StockMovement',
          13 => 'Modules\\Category\\Models\\Category',
          14 => 'Modules\\Warranty\\Models\\Warranty',
          15 => 'Modules\\Warranty\\Models\\WarrantyProvider',
          16 => 'Modules\\Profile\\Models\\StoreProfile',
          17 => 'Modules\\Setting\\Models\\Setting',
          18 => 'Modules\\Archive\\Models\\ArchivedRecord',
          19 => 'Modules\\Archive\\Models\\ArchiveAction',
          20 => 'Modules\\Backup\\Models\\BackupRun',
          21 => 'Modules\\Backup\\Models\\BackupFile',
          22 => 'Modules\\CacheMaintenance\\Models\\CacheMaintenanceRun',
          23 => 'Modules\\DigitalMenu\\Models\\DigitalMenuSession',
          24 => 'Modules\\DigitalMenu\\Models\\DigitalMenuSelection',
        ),
        'ignore_attributes' => 
        array (
          0 => 'password',
          1 => 'remember_token',
          2 => 'updated_at',
          3 => 'created_at',
          4 => 'api_token',
          5 => 'recovery_code',
          6 => 'recovery_code_hash',
        ),
        'label_attributes' => 
        array (
          0 => 'name',
          1 => 'title',
          2 => 'invoice_number',
          3 => 'code',
          4 => 'full_name',
          5 => 'phone',
          6 => 'email',
          7 => 'key',
        ),
      ),
      'auth' => 
      array (
        'enabled' => true,
        'log_failed' => true,
        'log_logout' => true,
        'failed_is_security' => true,
      ),
      'queue' => 
      array (
        'enabled' => true,
        'log_processed' => false,
        'log_failed' => true,
      ),
      'console' => 
      array (
        'enabled' => true,
        'except' => 
        array (
          0 => 'auditlog:*',
          1 => 'schedule:run',
          2 => 'schedule:finish',
          3 => 'queue:*',
          4 => 'list',
          5 => 'inspire',
        ),
        'only_failed_or_mutating' => true,
      ),
      'error' => 
      array (
        'enabled' => true,
        'levels' => 
        array (
          0 => 'error',
          1 => 'critical',
          2 => 'alert',
          3 => 'emergency',
        ),
        'trace_lines' => 20,
      ),
      'warning' => 
      array (
        'enabled' => true,
        'flush_minutes' => 60,
        'max_tracked' => 200,
        'max_summaries' => 20,
      ),
      'slow_query' => 
      array (
        'enabled' => false,
        'threshold_ms' => 500,
      ),
    ),
    'redaction' => 
    array (
      'keys' => 
      array (
        0 => 'password',
        1 => 'password_confirmation',
        2 => 'current_password',
        3 => 'new_password',
        4 => 'token',
        5 => 'access_token',
        6 => 'refresh_token',
        7 => 'api_token',
        8 => 'api_key',
        9 => 'secret',
        10 => 'authorization',
        11 => 'cookie',
        12 => 'remember_token',
        13 => 'license_key',
        14 => 'license_token',
        15 => 'private_key',
        16 => 'card_number',
        17 => 'cvv',
        18 => 'iban',
        19 => 'otp',
        20 => 'recovery_code',
      ),
      'mask' => '***REDACTED***',
      'max_value_length' => 2000,
      'max_array_depth' => 6,
      'max_array_items' => 200,
    ),
    'storage' => 
    array (
      'connection' => NULL,
      'buffer' => true,
      'buffer_limit' => 200,
      'queue_writes' => false,
    ),
    'integrity' => 
    array (
      'enabled' => true,
      'algo' => 'sha256',
      'secret' => 'base64:hiXNHavDLHSO5pmnTKR7YhkJmjdj/PvvIVdHtNwwpxk=',
    ),
    'shipping' => 
    array (
      'enabled' => true,
      'base_url' => 'http://appserver.mohammadentertainment.ir',
      'endpoints' => 
      array (
        'ingest' => '/api/v1/logs/ingest',
        'ack' => '/api/v1/logs/ack',
        'ping' => '/api/v1/logs/ping',
      ),
      'channels' => 
      array (
        0 => '*',
      ),
      'minimum_level' => 'info',
      'batch_size' => 200,
      'max_batches_per_run' => 5,
      'timeout' => 20,
      'connect_timeout' => 5,
      'retries' => 1,
      'retry_delay_ms' => 750,
      'verify_ssl' => true,
      'gzip' => true,
      'gzip_threshold_bytes' => 16384,
      'max_attempts' => 8,
      'backoff' => 
      array (
        0 => 60,
        1 => 180,
        2 => 600,
        3 => 1800,
        4 => 3600,
        5 => 10800,
        6 => 21600,
        7 => 43200,
      ),
      'auth' => 
      array (
        'license_token' => NULL,
        'license_uuid' => NULL,
        'fingerprint' => NULL,
      ),
      'headers' => 
      array (
        'timestamp' => 'X-GS-Timestamp',
        'nonce' => 'X-GS-Nonce',
        'fingerprint' => 'X-GS-Fingerprint',
        'client' => 'X-GS-Client',
        'token' => 'X-GS-License-Token',
        'batch' => 'X-GS-Batch-Id',
        'idempotency' => 'Idempotency-Key',
      ),
      'auto' => 
      array (
        'enabled' => true,
        'on_boot' => true,
        'interval_seconds' => 300,
        'queue' => 'database',
        'lock_seconds' => 120,
      ),
    ),
    'retention' => 
    array (
      'enabled' => true,
      'days' => 120,
      'keep_unsynced' => true,
      'chunk' => 1000,
      'level_overrides' => 
      array (
        'critical' => 365,
        'alert' => 365,
        'error' => 240,
      ),
      'batches_days' => 30,
    ),
    'api' => 
    array (
      'prefix' => 'auditlog',
      'middleware' => 
      array (
        0 => 'auth:sanctum',
        1 => 'license.module:AuditLog',
      ),
      'per_page' => 30,
      'max_per_page' => 200,
      'export_limit' => 50000,
    ),
    'name' => 'AuditLog',
  ),
  'licensing' => 
  array (
    'server' => 
    array (
      'base_url' => 'http://appserver.mohammadentertainment.ir',
      'endpoints' => 
      array (
        'activation_request' => '/api/v1/activation/request',
        'activation_status' => '/api/v1/activation/status/{uuid}',
        'activation_redeem' => '/api/v1/activation/redeem',
        'heartbeat' => '/api/v1/heartbeat',
        'state' => '/api/v1/license/state',
        'keys' => '/api/v1/keys',
        'patches' => '/api/v1/patches',
        'patch_status' => '/api/v1/patches/{code}/status',
      ),
      'timeout' => 15,
      'connect_timeout' => 5,
      'retries' => 1,
      'verify_ssl' => true,
    ),
    'headers' => 
    array (
      'timestamp' => 'X-GS-Timestamp',
      'nonce' => 'X-GS-Nonce',
      'fingerprint' => 'X-GS-Fingerprint',
      'client' => 'X-GS-Client',
      'token' => 'X-GS-License-Token',
    ),
    'default_heartbeat_minutes' => 60,
    'locked_heartbeat_minutes' => 5,
    'default_poll_seconds' => 30,
    'public_keys' => 
    array (
      'k-20260926-113051' => 'Zmu6sjgUM6yRcMERXXPef54PcZZGmhiAoQcUSOAv1cU',
    ),
    'patch' => 
    array (
      'enabled' => true,
      'target_path' => '',
      'allow_git_target' => false,
      'allow_tofu' => false,
      'allowed_roots' => 
      array (
        0 => 'app',
        1 => 'resources',
        2 => 'public',
        3 => 'config',
        4 => 'routes',
        5 => 'database',
        6 => 'lang',
        7 => 'Modules',
      ),
      'keep_backups' => 3,
      'php_binary' => '',
      'health_timeout' => 120,
      'stale_minutes' => 15,
      'maintenance_ttl_minutes' => 10,
    ),
    'name' => 'Licensing',
  ),
  'setting' => 
  array (
    'driver' => 'database',
    'cache' => 
    array (
      'enabled' => true,
      'store' => 'file',
      'key' => 'app.settings.v1',
      'ttl' => 86400,
    ),
    'table' => 'settings',
    'meta' => 
    array (
      'general.theme' => 
      array (
        'group' => 
        \Modules\Setting\Enums\Settings\SettingGroup::General,
        'type' => 'enum',
        'enum' => 'Modules\\Setting\\Enums\\Settings\\ThemeMode',
        'rules' => 
        array (
          0 => 'sometimes',
          1 => 'string',
        ),
        'default' => 
        \Modules\Setting\Enums\Settings\ThemeMode::Light,
        'label' => 'تم ظاهری',
        'section' => 'appearance',
      ),
      'desktop.auto_launch' => 
      array (
        'group' => 
        \Modules\Setting\Enums\Settings\SettingGroup::Desktop,
        'type' => 'bool',
        'rules' => 
        array (
          0 => 'sometimes',
          1 => 'boolean',
        ),
        'default' => false,
        'label' => 'اجرای خودکار هنگام روشن‌شدن سیستم',
        'section' => 'startup',
      ),
      'desktop.minimize_to_tray' => 
      array (
        'group' => 
        \Modules\Setting\Enums\Settings\SettingGroup::Desktop,
        'type' => 'bool',
        'rules' => 
        array (
          0 => 'sometimes',
          1 => 'boolean',
        ),
        'default' => true,
        'label' => 'کوچک‌شدن به Tray به‌جای خروج کامل',
        'section' => 'startup',
      ),
      'desktop.auto_update_url' => 
      array (
        'group' => 
        \Modules\Setting\Enums\Settings\SettingGroup::Desktop,
        'type' => 'string',
        'rules' => 
        array (
          0 => 'sometimes',
          1 => 'nullable',
          2 => 'url',
          3 => 'max:500',
        ),
        'default' => NULL,
        'label' => 'آدرس سرور بروزرسانی خودکار',
        'section' => 'updates',
      ),
      'desktop.auto_update_check' => 
      array (
        'group' => 
        \Modules\Setting\Enums\Settings\SettingGroup::Desktop,
        'type' => 'bool',
        'rules' => 
        array (
          0 => 'sometimes',
          1 => 'boolean',
        ),
        'default' => true,
        'label' => 'بررسی خودکار بروزرسانی در شروع',
        'section' => 'updates',
      ),
    ),
    'group_index' => 
    array (
    ),
  ),
  'archive' => 
  array (
    'name' => 'Archive',
  ),
  'authentication' => 
  array (
    'name' => 'Authentication',
  ),
  'cachemaintenance' => 
  array (
    'name' => 'CacheMaintenance',
  ),
  'category' => 
  array (
    'name' => 'Category',
  ),
  'customer' => 
  array (
    'name' => 'Customer',
  ),
  'dashboard' => 
  array (
    'name' => 'Dashboard',
  ),
  'digitalmenu' => 
  array (
    'name' => 'DigitalMenu',
  ),
  'invoice' => 
  array (
    'name' => 'Invoice',
  ),
  'profile' => 
  array (
    'name' => 'Profile',
  ),
  'request' => 
  array (
    'name' => 'Request',
  ),
  'schemamanager' => 
  array (
    'name' => 'SchemaManager',
    'enabled' => true,
    'dir' => 'C:\\Users\\moham\\Desktop\\projects\\gameshop\\database\\schema',
    'seed_tables' => 
    array (
      0 => 'migrations',
    ),
    'exclude' => 
    array (
    ),
  ),
  'service' => 
  array (
    'name' => 'Service',
  ),
  'stats' => 
  array (
    'name' => 'Stats',
  ),
  'stock' => 
  array (
    'name' => 'Stock',
  ),
  'warranty' => 
  array (
    'name' => 'Warranty',
  ),
  'tinker' => 
  array (
    'commands' => 
    array (
    ),
    'alias' => 
    array (
    ),
    'dont_alias' => 
    array (
      0 => 'App\\Nova',
    ),
  ),
);
