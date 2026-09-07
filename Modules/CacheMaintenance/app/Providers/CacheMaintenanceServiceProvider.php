<?php

namespace Modules\CacheMaintenance\Providers;

use Illuminate\Console\Scheduling\Schedule;
use Modules\CacheMaintenance\Services\CacheMaintenanceService;
use Nwidart\Modules\Support\ModuleServiceProvider;

class CacheMaintenanceServiceProvider extends ModuleServiceProvider
{

    public function boot(): void
    {
        parent::boot();

        $this->app->booted(function () {
            app(CacheMaintenanceService::class)->runDueMaintenanceIfNeeded();
        });
    }

    protected string $name = 'CacheMaintenance';

    /**
     * The lowercase version of the module name.
     */
    protected string $nameLower = 'cachemaintenance';

    /**
     * Command classes to register.
     *
     * @var string[]
     */
    // protected array $commands = [];

    /**
     * Provider classes to register.
     *
     * @var string[]
     */
    protected array $providers = [
        EventServiceProvider::class,
        RouteServiceProvider::class,
    ];

    /**
     * این ماژول از Schedule استاندارد لاراول استفاده نمی‌کند، چون اپ به‌صورت
     * 24 ساعته روشن نیست و cron/schedule:run پیوسته‌ای وجود ندارد.
     * به‌جایش نگاه کن به CacheMaintenanceService::runDueMaintenanceIfNeeded()
     * که روی هر boot اپ (از طریق این service provider) صدا زده می‌شود.
     */
}
