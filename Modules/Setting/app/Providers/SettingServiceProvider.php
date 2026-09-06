<?php

declare(strict_types=1);

namespace Modules\Setting\Providers;

use App\Enums\Settings\BackupSchedule;
use App\Events\SettingsChanged;
use App\Listeners\InvalidateSettingCache;
use App\Services\Setting\Contracts\SettingRepositoryContract;
use App\Services\Setting\Repositories\DatabaseSettingRepository;
use App\Services\Setting\SettingDefaults;
use App\Services\Setting\SettingService;
use Illuminate\Foundation\Support\Providers\EventServiceProvider as ServiceProvider;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * SettingServiceProvider
 * ============================================================================
 * ثبت سرویس‌های ماژول تنظیمات و شنونده‌های مربوطه.
 *
 * همچنین scheduler بکاپ خودکار را بر اساس تنظیمات زمان‌بندی می‌کند.
 */
class SettingServiceProvider extends ServiceProvider
{
    /**
     * رویدادها و شنونده‌های ماژول تنظیمات.
     *
     * @var array<class-string,array<int,class-string>>
     */
    protected $listen = [
        SettingsChanged::class => [
            InvalidateSettingCache::class,
        ],
    ];

    public function register(): void
    {
        // 1) Merge config
        $this->mergeConfigFrom(__DIR__ . '/../../config/setting.php', 'setting');

        // 2) Singleton برای SettingDefaults
        $this->app->singleton(SettingDefaults::class);

        // 3) binding برای repository
        $this->app->singleton(SettingRepositoryContract::class, function ($app) {
            $driver = $app['config']->get('setting.driver', 'database');

            return match ($driver) {
                'database' => new DatabaseSettingRepository(),
                default => throw new \RuntimeException("Unknown setting driver: {$driver}"),
            };
        });

        // 4) Singleton برای SettingService
        $this->app->singleton(SettingService::class, function ($app) {
            return new SettingService(
                repository: $app->make(SettingRepositoryContract::class),
                cache: Cache::store($app['config']->get('setting.cache.store', 'file')),
                events: $app['events'],
                defaults: $app->make(SettingDefaults::class),
            );
        });

        // 5) alias برای دسترسی راحت
        $this->app->alias(SettingService::class, 'settings');
    }

    public function boot(): void
    {
        // Publish کردن config برای customize توسط کاربر
        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__ . '/../../config/setting.php' => config_path('setting.php'),
            ], 'setting-config');
        }

        // ثبت channel لاگ اختصاصی
        $this->registerLogChannel();

        // زمان‌بندی بکاپ خودکار
        $this->scheduleAutoBackup();
    }

    /**
     * ثبت channel لاگ اختصاصی برای تغییرات حساس تنظیمات.
     */
    private function registerLogChannel(): void
    {
        $config = config('logging.channels');
        if (! isset($config['settings'])) {
            config([
                'logging.channels.settings' => [
                    'driver' => 'single',
                    'path' => storage_path('logs/settings.log'),
                    'level' => 'info',
                ],
            ]);
        }
    }

    /**
     * زمان‌بندی بکاپ خودکار بر اساس تنظیم desktop.backup_schedule.
     */
    private function scheduleAutoBackup(): void
    {
        if (! $this->app->runningInConsole()) {
            return;
        }

        try {
            $settings = $this->app->make(SettingService::class);
            $schedule = $settings->backupSchedule();

            if ($schedule === BackupSchedule::Disabled) {
                return;
            }

            $cron = $schedule->cronExpression();
            if ($cron === null) {
                return;
            }

            $this->app->booted(function () use ($schedule, $cron) {
                /** @var \Illuminate\Console\Scheduling\Schedule $scheduler */
                $scheduler = app(\Illuminate\Console\Scheduling\Schedule::class);

                $scheduler->call(function () {
                    try {
                        $settings = app(SettingService::class);
                        $dbPath = $settings->getString('desktop.database_path', '');
                        $backupPath = $settings->getString('desktop.backup_path', '');

                        if (empty($dbPath) || empty($backupPath) || ! file_exists($dbPath)) {
                            Log::channel('settings')->warning('Auto-backup skipped: paths not configured or DB missing.');
                            return;
                        }

                        if (! is_dir($backupPath)) {
                            @mkdir($backupPath, 0755, true);
                        }

                        $timestamp = now()->format('Ymd_His');
                        $dest = rtrim($backupPath, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR
                            . 'auto_backup_' . $timestamp . '.db';

                        if (! copy($dbPath, $dest)) {
                            throw new \RuntimeException('copy() failed.');
                        }

                        Log::channel('settings')->info('Auto-backup created: ' . $dest);
                    } catch (\Throwable $e) {
                        Log::channel('settings')->error('Auto-backup failed: ' . $e->getMessage());
                    }
                })->cron($cron)->name('settings.auto-backup')->withoutOverlapping();
            });
        } catch (\Throwable $e) {
            // در زمان boot، ممکن است تنظیمات هنوز در دسترس نباشند.
            // لاگ می‌کنیم اما فرآیند را متوقف نمی‌کنیم.
            Log::warning('SettingServiceProvider: could not schedule auto-backup: ' . $e->getMessage());
        }
    }
}
