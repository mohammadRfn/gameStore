<?php

namespace App\Services;

use App\Models\CacheMaintenanceRun;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;
use RuntimeException;
use Throwable;

/**
 * Enterprise-level cache maintenance service for local Laravel + Electron + SQLite.
 *
 * قابلیت‌ها:
 * - Inspect کامل وضعیت کش‌ها و فایل‌های تولیدی لاراول
 * - Dry-run قبل از حذف واقعی
 * - پاکسازی granular: app/settings/config/route/view/event/compiled/bootstrap/framework/logs/sessions
 * - پاکسازی cache table منقضی‌شده برای DB cache driver
 * - Warm-up امن: settings، config، views
 * - SQLite VACUUM اختیاری
 * - ثبت history کامل برای audit
 * - محافظت در برابر اجرای همزمان
 */
class CacheMaintenanceService
{
    public const TARGET_APP                    = 'app';
    public const TARGET_SETTINGS               = 'settings';
    public const TARGET_CONFIG                 = 'config';
    public const TARGET_ROUTE                  = 'route';
    public const TARGET_VIEW                   = 'view';
    public const TARGET_EVENT                  = 'event';
    public const TARGET_COMPILED               = 'compiled';
    public const TARGET_OPTIMIZE               = 'optimize';
    public const TARGET_BOOTSTRAP              = 'bootstrap';
    public const TARGET_FRAMEWORK_FILES        = 'framework_files';
    public const TARGET_EXPIRED_DATABASE_CACHE = 'expired_database_cache';
    public const TARGET_LOGS                   = 'logs';
    public const TARGET_SESSIONS               = 'sessions';
    public const TARGET_ALL                    = 'all';

    public const DEFAULT_TARGETS = [
        self::TARGET_APP,
        self::TARGET_SETTINGS,
        self::TARGET_CONFIG,
        self::TARGET_ROUTE,
        self::TARGET_VIEW,
        self::TARGET_EVENT,
        self::TARGET_COMPILED,
        self::TARGET_OPTIMIZE,
        self::TARGET_BOOTSTRAP,
        self::TARGET_FRAMEWORK_FILES,
        self::TARGET_EXPIRED_DATABASE_CACHE,
    ];

    public function __construct(
        protected SettingsService $settings
    ) {}

    /**
     * وضعیت فعلی کش‌ها و فایل‌های مرتبط را برمی‌گرداند.
     */
    public function inspect(?int $userId = null): array
    {
        $run = CacheMaintenanceRun::create([
            'operation'   => CacheMaintenanceRun::OPERATION_INSPECT,
            'status'      => CacheMaintenanceRun::STATUS_RUNNING,
            'is_dry_run'  => true,
            'targets_json'=> ['inspect'],
            'user_id'     => $userId,
            'started_at'  => now(),
        ]);

        $metrics = $this->collectMetrics();

        $run->update([
            'status'              => CacheMaintenanceRun::STATUS_COMPLETED,
            'before_metrics_json' => $metrics,
            'after_metrics_json'  => $metrics,
            'summary_json'        => ['message' => 'Inspection completed.'],
            'finished_at'         => now(),
            'duration_ms'         => $run->started_at->diffInMilliseconds(now()),
        ]);

        return [
            'run'     => $run->fresh(),
            'metrics' => $metrics,
            'targets' => $this->availableTargets(),
            'recommendations' => $this->recommendations($metrics),
        ];
    }

    /**
     * اجرای پاکسازی کش‌ها.
     */
    public function clear(array $payload, ?int $userId = null): CacheMaintenanceRun
    {
        $targets = $this->normalizeTargets($payload['targets'] ?? self::DEFAULT_TARGETS);
        $options = $this->normalizeOptions($payload);

        if (! $options['force'] && $this->hasRunningRun()) {
            throw new RuntimeException('یک عملیات پاکسازی کش در حال اجراست. کمی بعد دوباره تلاش کنید یا force=true ارسال کنید.');
        }

        $run = CacheMaintenanceRun::create([
            'operation'    => CacheMaintenanceRun::OPERATION_CLEAR,
            'status'       => CacheMaintenanceRun::STATUS_PENDING,
            'is_dry_run'   => $options['dry_run'],
            'targets_json' => $targets,
            'options_json' => $options,
            'user_id'      => $userId,
        ]);

        $run->markRunning();

        $before = $this->collectMetrics();
        $summary = [];
        $errors = [];
        $output = [];

        try {
            if ($options['dry_run']) {
                $summary = $this->dryRunSummary($targets, $options, $before);
                $after = $before;
            } else {
                foreach ($targets as $target) {
                    try {
                        $result = $this->clearTarget($target, $options);
                        $summary[$target] = $result;
                        if (! empty($result['output'])) {
                            $output[] = "[{$target}] " . $result['output'];
                        }
                    } catch (Throwable $e) {
                        report($e);
                        $errors[$target] = $e->getMessage();
                    }
                }

                if ($options['warm_after_clear']) {
                    $warm = $this->warm($options);
                    $summary['warm'] = $warm;
                }

                if ($options['run_sqlite_vacuum']) {
                    $summary['sqlite_vacuum'] = $this->runSqliteVacuum();
                }

                clearstatcache(true);
                $after = $this->collectMetrics();
            }

            $status = empty($errors)
                ? CacheMaintenanceRun::STATUS_COMPLETED
                : (count($summary) > 0 ? CacheMaintenanceRun::STATUS_PARTIAL : CacheMaintenanceRun::STATUS_FAILED);

            $run->update([
                'before_metrics_json' => $before,
                'after_metrics_json'  => $after,
            ]);

            $run->markFinished($status, $summary, $errors, implode(PHP_EOL, $output));
        } catch (Throwable $e) {
            report($e);
            $run->update([
                'before_metrics_json' => $before,
                'after_metrics_json'  => $this->collectMetrics(),
            ]);
            $run->markFinished(CacheMaintenanceRun::STATUS_FAILED, $summary, ['fatal' => $e->getMessage()], implode(PHP_EOL, $output));
        }

        return $run->fresh();
    }

    /**
     * Warm-up بدون پاکسازی؛ مناسب بعد از نصب/آپدیت.
     */
    public function optimize(array $payload, ?int $userId = null): CacheMaintenanceRun
    {
        $options = $this->normalizeOptions($payload + [
            'warm_after_clear' => true,
            'warm_config' => true,
            'warm_views' => true,
            'warm_settings' => true,
        ]);

        $run = CacheMaintenanceRun::create([
            'operation'    => CacheMaintenanceRun::OPERATION_OPTIMIZE,
            'status'       => CacheMaintenanceRun::STATUS_RUNNING,
            'is_dry_run'   => $options['dry_run'],
            'targets_json' => ['warm'],
            'options_json' => $options,
            'user_id'      => $userId,
            'started_at'   => now(),
        ]);

        $before = $this->collectMetrics();
        $summary = [];
        $errors = [];

        try {
            if ($options['dry_run']) {
                $summary['dry_run'] = 'Warm-up commands were not executed.';
                $after = $before;
            } else {
                $summary['warm'] = $this->warm($options);
                $after = $this->collectMetrics();
            }

            $run->update([
                'before_metrics_json' => $before,
                'after_metrics_json'  => $after,
            ]);

            $run->markFinished(CacheMaintenanceRun::STATUS_COMPLETED, $summary, $errors);
        } catch (Throwable $e) {
            report($e);
            $run->markFinished(CacheMaintenanceRun::STATUS_FAILED, $summary, ['fatal' => $e->getMessage()]);
        }

        return $run->fresh();
    }

    /**
     * آخرین اجراها.
     */
    public function paginateRuns(array $filters = [])
    {
        return CacheMaintenanceRun::query()
            ->when($filters['operation'] ?? null, fn ($q, $v) => $q->where('operation', $v))
            ->when($filters['status'] ?? null, fn ($q, $v) => $q->where('status', $v))
            ->latest()
            ->paginate(min(100, max(1, (int) ($filters['per_page'] ?? 20))));
    }

    public function findRun(int $id): CacheMaintenanceRun
    {
        return CacheMaintenanceRun::findOrFail($id);
    }

    public function availableTargets(): array
    {
        return [
            self::TARGET_APP => [
                'label' => 'Application Cache',
                'description' => 'پاکسازی cache عمومی Laravel از طریق cache:clear.',
                'safe' => true,
            ],
            self::TARGET_SETTINGS => [
                'label' => 'Settings Cache',
                'description' => 'پاکسازی کش تنظیمات AppSetting/SettingsService.',
                'safe' => true,
            ],
            self::TARGET_CONFIG => [
                'label' => 'Config Cache',
                'description' => 'حذف فایل bootstrap/cache/config.php.',
                'safe' => true,
            ],
            self::TARGET_ROUTE => [
                'label' => 'Route Cache',
                'description' => 'حذف route cache. با route:cache اشتباه نگیرید؛ فقط clear می‌کند.',
                'safe' => true,
            ],
            self::TARGET_VIEW => [
                'label' => 'Compiled Views',
                'description' => 'پاکسازی viewهای کامپایل‌شده Blade.',
                'safe' => true,
            ],
            self::TARGET_EVENT => [
                'label' => 'Event Cache',
                'description' => 'حذف event discovery cache.',
                'safe' => true,
            ],
            self::TARGET_COMPILED => [
                'label' => 'Compiled Classes',
                'description' => 'پاکسازی فایل‌های compiled/optimized framework.',
                'safe' => true,
            ],
            self::TARGET_OPTIMIZE => [
                'label' => 'Optimize Cache',
                'description' => 'اجرای optimize:clear برای پاکسازی جامع cacheهای لاراول.',
                'safe' => true,
            ],
            self::TARGET_BOOTSTRAP => [
                'label' => 'Bootstrap Cache Files',
                'description' => 'حذف فایل‌های تولیدی bootstrap/cache به‌جز .gitignore.',
                'safe' => true,
            ],
            self::TARGET_FRAMEWORK_FILES => [
                'label' => 'Framework File Cache',
                'description' => 'حذف فایل‌های storage/framework/cache/data.',
                'safe' => true,
            ],
            self::TARGET_EXPIRED_DATABASE_CACHE => [
                'label' => 'Expired DB Cache Rows',
                'description' => 'حذف رکوردهای منقضی‌شده جدول cache.',
                'safe' => true,
            ],
            self::TARGET_LOGS => [
                'label' => 'Old Logs',
                'description' => 'حذف لاگ‌های قدیمی بر اساس logs_older_than_days.',
                'safe' => false,
            ],
            self::TARGET_SESSIONS => [
                'label' => 'File Sessions',
                'description' => 'حذف session فایل‌ها؛ برای session_driver=file.',
                'safe' => false,
            ],
            self::TARGET_ALL => [
                'label' => 'All Safe Targets',
                'description' => 'همه targetهای امن؛ logs و sessions فقط با option جدا فعال می‌شوند.',
                'safe' => true,
            ],
        ];
    }

    protected function clearTarget(string $target, array $options): array
    {
        return match ($target) {
            self::TARGET_APP      => $this->artisan('cache:clear'),
            self::TARGET_CONFIG   => $this->artisan('config:clear'),
            self::TARGET_ROUTE    => $this->artisan('route:clear'),
            self::TARGET_VIEW     => $this->artisan('view:clear'),
            self::TARGET_EVENT    => $this->artisan('event:clear'),
            self::TARGET_COMPILED => $this->artisan('clear-compiled'),
            self::TARGET_OPTIMIZE => $this->artisan('optimize:clear'),
            self::TARGET_SETTINGS => $this->clearSettingsCache(),
            self::TARGET_BOOTSTRAP => $this->clearBootstrapCacheFiles(),
            self::TARGET_FRAMEWORK_FILES => $this->clearFrameworkCacheFiles(),
            self::TARGET_EXPIRED_DATABASE_CACHE => $this->clearExpiredDatabaseCache(),
            self::TARGET_LOGS => $options['include_logs']
                ? $this->clearOldLogs((int) $options['logs_older_than_days'])
                : ['skipped' => true, 'reason' => 'include_logs=false'],
            self::TARGET_SESSIONS => $options['include_sessions']
                ? $this->clearFileSessions()
                : ['skipped' => true, 'reason' => 'include_sessions=false'],
            default => ['skipped' => true, 'reason' => "Unknown target: {$target}"],
        };
    }

    protected function artisan(string $command, array $parameters = []): array
    {
        $exit = Artisan::call($command, $parameters);
        return [
            'command' => $command,
            'exit_code' => $exit,
            'ok' => $exit === 0,
            'output' => trim(Artisan::output()),
        ];
    }

    protected function clearSettingsCache(): array
    {
        $this->settings->flush();
        Cache::forget(config('settings.cache_key', 'app_settings.all'));

        return [
            'ok' => true,
            'message' => 'SettingsService cache flushed.',
        ];
    }

    protected function clearBootstrapCacheFiles(): array
    {
        $dir = base_path('bootstrap/cache');
        $deleted = 0;
        $bytes = 0;

        foreach (File::files($dir) as $file) {
            if ($file->getFilename() === '.gitignore') {
                continue;
            }
            $bytes += $file->getSize();
            File::delete($file->getPathname());
            $deleted++;
        }

        return compact('deleted', 'bytes') + ['ok' => true];
    }

    protected function clearFrameworkCacheFiles(): array
    {
        $dir = storage_path('framework/cache/data');
        return $this->deleteDirectoryContents($dir, keepGitignore: true);
    }

    protected function clearExpiredDatabaseCache(): array
    {
        if (! Schema::hasTable('cache')) {
            return ['skipped' => true, 'reason' => 'cache table does not exist'];
        }

        $deleted = DB::table('cache')
            ->where('expiration', '<', time())
            ->delete();

        return ['ok' => true, 'deleted_rows' => $deleted];
    }

    protected function clearOldLogs(int $olderThanDays = 14): array
    {
        $dir = storage_path('logs');
        $threshold = now()->subDays($olderThanDays)->timestamp;
        $deleted = 0;
        $bytes = 0;

        foreach (File::files($dir) as $file) {
            if (! str_ends_with($file->getFilename(), '.log')) {
                continue;
            }
            if ($olderThanDays > 0 && $file->getMTime() > $threshold) {
                continue;
            }
            $bytes += $file->getSize();
            File::delete($file->getPathname());
            $deleted++;
        }

        return ['ok' => true, 'deleted' => $deleted, 'bytes' => $bytes, 'older_than_days' => $olderThanDays];
    }

    protected function clearFileSessions(): array
    {
        if (config('session.driver') !== 'file') {
            return ['skipped' => true, 'reason' => 'session driver is not file'];
        }

        return $this->deleteDirectoryContents(storage_path('framework/sessions'), keepGitignore: true);
    }

    protected function warm(array $options): array
    {
        $results = [];

        if ($options['warm_settings']) {
            $this->settings->flush();
            $this->settings->autoload();
            $results['settings'] = ['ok' => true, 'message' => 'Settings autoload cache warmed.'];
        }

        if ($options['warm_config']) {
            $results['config'] = $this->artisan('config:cache');
        }

        if ($options['warm_views']) {
            $results['views'] = $this->artisan('view:cache');
        }

        // route:cache عمداً اجرا نمی‌شود چون پروژه route closure دارد و ممکن است fail شود.
        $results['route_cache'] = ['skipped' => true, 'reason' => 'route:cache is intentionally skipped because the project uses route closures.'];

        return $results;
    }

    protected function runSqliteVacuum(): array
    {
        if (config('database.default') !== 'sqlite') {
            return ['skipped' => true, 'reason' => 'database driver is not sqlite'];
        }

        DB::statement('VACUUM');
        DB::statement('ANALYZE');

        return ['ok' => true, 'message' => 'SQLite VACUUM and ANALYZE executed.'];
    }

    protected function collectMetrics(): array
    {
        $bootstrap = $this->dirStats(base_path('bootstrap/cache'));
        $views = $this->dirStats(storage_path('framework/views'));
        $frameworkCache = $this->dirStats(storage_path('framework/cache'));
        $logs = $this->dirStats(storage_path('logs'));
        $sessions = $this->dirStats(storage_path('framework/sessions'));

        return [
            'environment' => [
                'app_env' => app()->environment(),
                'cache_driver' => config('cache.default'),
                'session_driver' => config('session.driver'),
                'queue_connection' => config('queue.default'),
                'database_driver' => config('database.default'),
                'php_version' => PHP_VERSION,
                'laravel_version' => app()->version(),
                'os' => PHP_OS_FAMILY,
            ],
            'bootstrap_cache' => $bootstrap,
            'compiled_views' => $views,
            'framework_cache' => $frameworkCache,
            'logs' => $logs,
            'sessions' => $sessions,
            'database_cache' => $this->databaseCacheMetrics(),
            'settings_cache_key' => config('settings.cache_key', 'app_settings.all'),
            'generated_at' => now()->toIso8601String(),
        ];
    }

    protected function databaseCacheMetrics(): array
    {
        if (! Schema::hasTable('cache')) {
            return ['available' => false];
        }

        return [
            'available' => true,
            'rows' => DB::table('cache')->count(),
            'expired_rows' => DB::table('cache')->where('expiration', '<', time())->count(),
        ];
    }

    protected function dirStats(string $path): array
    {
        if (! is_dir($path)) {
            return ['exists' => false, 'files' => 0, 'directories' => 0, 'bytes' => 0, 'human_size' => '0 B'];
        }

        $files = 0;
        $dirs = 0;
        $bytes = 0;

        foreach (File::allFiles($path) as $file) {
            $files++;
            $bytes += $file->getSize();
        }

        foreach (File::directories($path) as $_) {
            $dirs++;
        }

        return [
            'exists' => true,
            'path' => $path,
            'files' => $files,
            'directories' => $dirs,
            'bytes' => $bytes,
            'human_size' => $this->humanBytes($bytes),
            'writable' => is_writable($path),
        ];
    }

    protected function deleteDirectoryContents(string $dir, bool $keepGitignore = true): array
    {
        if (! is_dir($dir)) {
            return ['skipped' => true, 'reason' => "Directory does not exist: {$dir}"];
        }

        $deletedFiles = 0;
        $deletedDirs = 0;
        $bytes = 0;

        foreach (File::allFiles($dir) as $file) {
            if ($keepGitignore && $file->getFilename() === '.gitignore') {
                continue;
            }
            $bytes += $file->getSize();
            File::delete($file->getPathname());
            $deletedFiles++;
        }

        foreach (array_reverse(File::directories($dir)) as $subDir) {
            if ($this->isDirEmpty($subDir)) {
                File::deleteDirectory($subDir);
                $deletedDirs++;
            }
        }

        return ['ok' => true, 'deleted_files' => $deletedFiles, 'deleted_directories' => $deletedDirs, 'bytes' => $bytes];
    }

    protected function isDirEmpty(string $dir): bool
    {
        return is_dir($dir) && count(scandir($dir) ?: []) <= 2;
    }

    protected function dryRunSummary(array $targets, array $options, array $metrics): array
    {
        return [
            'message' => 'Dry-run only. No files, cache rows, or compiled artifacts were deleted.',
            'targets' => $targets,
            'options' => $options,
            'estimated' => [
                'bootstrap_cache_files' => $metrics['bootstrap_cache']['files'] ?? 0,
                'compiled_view_files' => $metrics['compiled_views']['files'] ?? 0,
                'framework_cache_files' => $metrics['framework_cache']['files'] ?? 0,
                'log_files' => $metrics['logs']['files'] ?? 0,
                'session_files' => $metrics['sessions']['files'] ?? 0,
                'expired_database_cache_rows' => $metrics['database_cache']['expired_rows'] ?? 0,
            ],
        ];
    }

    protected function recommendations(array $metrics): array
    {
        $items = [];

        if (($metrics['database_cache']['expired_rows'] ?? 0) > 100) {
            $items[] = 'تعداد cacheهای منقضی‌شده دیتابیس زیاد است؛ target expired_database_cache را اجرا کنید.';
        }

        if (($metrics['compiled_views']['bytes'] ?? 0) > 20 * 1024 * 1024) {
            $items[] = 'حجم viewهای کامپایل‌شده بالاست؛ target view را پاک کنید.';
        }

        if (($metrics['logs']['bytes'] ?? 0) > 100 * 1024 * 1024) {
            $items[] = 'حجم لاگ‌ها بالاست؛ include_logs=true با logs_older_than_days مناسب اجرا کنید.';
        }

        if (empty($items)) {
            $items[] = 'وضعیت کش‌ها طبیعی است؛ برای نگهداری دوره‌ای از dry-run و سپس all استفاده کنید.';
        }

        return $items;
    }

    protected function normalizeTargets(array|string|null $targets): array
    {
        $configuredDefaults = $this->settings->getJson(
            'maintenance.cache.default_targets',
            self::DEFAULT_TARGETS
        ) ?: self::DEFAULT_TARGETS;

        if ($targets === null || $targets === []) {
            $targets = $configuredDefaults;
        }

        $targets = is_string($targets) ? [$targets] : $targets;

        if (in_array(self::TARGET_ALL, $targets, true)) {
            $targets = $configuredDefaults;
        }

        return array_values(array_unique($targets));
    }

    protected function normalizeOptions(array $payload): array
    {
        return [
            'dry_run' => (bool) ($payload['dry_run'] ?? false),
            'include_logs' => (bool) ($payload['include_logs'] ?? $this->settings->getBool('maintenance.cache.allow_log_cleanup', false)),
            'logs_older_than_days' => (int) ($payload['logs_older_than_days'] ?? $this->settings->getInt('maintenance.cache.logs_older_than_days', 14)),
            'include_sessions' => (bool) ($payload['include_sessions'] ?? $this->settings->getBool('maintenance.cache.allow_session_cleanup', false)),
            'warm_after_clear' => (bool) ($payload['warm_after_clear'] ?? $this->settings->getBool('maintenance.cache.warm_after_clear', false)),
            'warm_config' => (bool) ($payload['warm_config'] ?? $this->settings->getBool('maintenance.cache.warm_config', false)),
            'warm_views' => (bool) ($payload['warm_views'] ?? $this->settings->getBool('maintenance.cache.warm_views', true)),
            'warm_settings' => (bool) ($payload['warm_settings'] ?? $this->settings->getBool('maintenance.cache.warm_settings', true)),
            'run_sqlite_vacuum' => (bool) ($payload['run_sqlite_vacuum'] ?? $this->settings->getBool('maintenance.cache.run_sqlite_vacuum', false)),
            'force' => (bool) ($payload['force'] ?? false),
        ];
    }

    protected function hasRunningRun(): bool
    {
        return CacheMaintenanceRun::query()
            ->where('status', CacheMaintenanceRun::STATUS_RUNNING)
            ->where('started_at', '>=', now()->subMinutes(10))
            ->exists();
    }

    protected function humanBytes(int|float $bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $bytes = max(0, (float) $bytes);
        $power = $bytes > 0 ? min((int) floor(log($bytes, 1024)), count($units) - 1) : 0;
        return round($bytes / (1024 ** $power), $power ? 2 : 0) . ' ' . $units[$power];
    }
}
