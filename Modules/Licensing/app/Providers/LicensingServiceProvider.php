<?php

declare(strict_types=1);

namespace Modules\Licensing\Providers;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Routing\Router;
use Modules\Licensing\Console\Commands\SendHeartbeatCommand;
use Modules\Licensing\Http\Middleware\EnsureModuleLicensed;
use Modules\Licensing\Http\Middleware\RequireActiveLicense;
use Modules\Licensing\Services\LicenseGate;
use Modules\Licensing\Providers\EventServiceProvider;
use Modules\Licensing\Providers\RouteServiceProvider;
use Modules\Licensing\Services\DeviceFingerprint;
use Modules\Licensing\Services\LicensingService;
use Modules\Licensing\Services\StoreServerLicenseClient;
use Nwidart\Modules\Support\ModuleServiceProvider;
use Throwable;

/**
 * سرویس‌پروایدر ماژول Licensing.
 *
 * مسئولیت‌ها:
 *  - ثبت singletonها و کانفیگ
 *  - نصب middleware RequireActiveLicense روی گروه web (بدون نیاز به ویرایش bootstrap/app.php)
 *  - ارسال heartbeat در پایان هر چرخه (اپ دسکتاپ) + زمان‌بندی استاندارد ساعتی (اگر schedule:run در دسترس باشد)
 */
class LicensingServiceProvider extends ModuleServiceProvider
{
    protected string $name = 'Licensing';

    protected string $nameLower = 'licensing';

    /** @var list<class-string> */
    protected array $commands = [
        SendHeartbeatCommand::class,
    ];

    /**
     * این آرایه است که parent::register()/boot() را وادار می‌کند routes/web.php
     * را واقعاً بارگذاری کند - بدونش روت‌های licensing.* اصلاً ثبت نمی‌شوند،
     * حتی اگر middleware درست کار کند (دقیقاً همون الگوی Authentication).
     */
    protected array $providers = [
        EventServiceProvider::class,
        RouteServiceProvider::class,
    ];

    public function register(): void
    {
        parent::register();

        $this->mergeConfigFrom(module_path($this->name, 'config/licensing.php'), 'licensing');

        $this->app->singleton(DeviceFingerprint::class);
        $this->app->singleton(StoreServerLicenseClient::class);
        $this->app->singleton(LicensingService::class);
        $this->app->singleton(LicenseGate::class);
    }

    public function boot(): void
    {
        parent::boot();

        $this->mergeConfigFrom(module_path($this->name, 'config/licensing.php'), 'licensing');

        $this->publishes([
            module_path($this->name, 'config/licensing.php') => config_path('licensing.php'),
        ], 'licensing-config');

        $this->registerMiddleware();
        $this->registerTerminationHooks();
        $this->registerSchedule();
    }

    /**
     * قفل کل اپ روی همه‌ی درخواست‌های web؛ خود روت‌های licensing.* در
     * میدلور استثنا شده‌اند تا حلقه‌ی ریدایرکت پیش نیاید.
     */
    private function registerMiddleware(): void
    {
        /** @var Router $router */
        $router = $this->app['router'];

        $router->pushMiddlewareToGroup('web', RequireActiveLicense::class);
        $router->aliasMiddleware('license.active', RequireActiveLicense::class);
        $router->aliasMiddleware('license.module', EnsureModuleLicensed::class);
    }

    /**
     * چون اپ دسکتاپ همیشه روشن نیست، در پایان هر چرخه (هر بار که کاربر
     * صفحه‌ای را باز می‌کند) هم بررسی می‌شود که آیا موعد heartbeat رسیده.
     */
    private function registerTerminationHooks(): void
    {
        $this->app->terminating(function (): void {
            try {
                $this->app->make(LicensingService::class)->sendHeartbeatIfDue();
            } catch (Throwable) {
                // heartbeat در چرخه‌ی بعدی دوباره تلاش می‌شود
            }
        });
    }

    /**
     * زمان‌بندی استاندارد (اگر cron/schedule:run در دسترس باشد).
     */
    private function registerSchedule(): void
    {
        if (! $this->app->runningInConsole()) {
            return;
        }

        $this->app->booted(function (): void {
            try {
                /** @var Schedule $schedule */
                $schedule = $this->app->make(Schedule::class);

                $schedule->command(SendHeartbeatCommand::class)
                    ->hourly()
                    ->withoutOverlapping()
                    ->runInBackground();
            } catch (Throwable) {
                // اگر scheduler در دسترس نبود، نادیده گرفته می‌شود
            }
        });
    }
}