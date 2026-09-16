<?php

declare(strict_types=1);

namespace Modules\Setting\Providers;

use Modules\Setting\Events\SettingsChanged;
use Modules\Setting\Listeners\InvalidateSettingCache;
use Modules\Setting\Services\Setting\Contracts\SettingRepositoryContract;
use Modules\Setting\Services\Setting\Repositories\DatabaseSettingRepository;
use Modules\Setting\Services\Setting\SettingDefaults;
use Modules\Setting\Services\Setting\SettingService;
use Illuminate\Foundation\Support\Providers\EventServiceProvider as ServiceProvider;
use Illuminate\Support\Facades\Cache;

/**
 * SettingServiceProvider
 * ============================================================================
 * ثبت سرویس‌های ماژول تنظیمات و شنونده‌های مربوطه.
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

}