<?php

declare(strict_types=1);

namespace Modules\AuditLog\Providers;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Routing\Router;
use Modules\AuditLog\Console\Commands\AuditLogStatusCommand;
use Modules\AuditLog\Console\Commands\PruneAuditLogsCommand;
use Modules\AuditLog\Console\Commands\ShipAuditLogsCommand;
use Modules\AuditLog\Console\Commands\VerifyAuditLogChainCommand;
use Modules\AuditLog\Http\Middleware\AssignAuditContext;
use Modules\AuditLog\Http\Middleware\LogHttpRequests;
use Modules\AuditLog\Observers\AuditableObserver;
use Modules\AuditLog\Services\AuditLogger;
use Modules\AuditLog\Services\AuditRecorder;
use Modules\AuditLog\Services\LogShippingService;
use Modules\AuditLog\Support\AuditContext;
use Modules\AuditLog\Support\ClientIdentity;
use Modules\AuditLog\Support\HashChain;
use Modules\AuditLog\Support\LogSanitizer;
use Nwidart\Modules\Support\ModuleServiceProvider;
use Throwable;

/**
 * سرویس‌پروایدر اصلی ماژول لاگ‌گیری.
 *
 * مسئولیت‌ها:
 *  - ثبت singletonها و کانفیگ
 *  - نصب middlewareها روی گروه‌های web/api
 *  - اتصال Observer عمومی به مدل‌های پروژه
 *  - flush بافر در پایان چرخه و ارسال دوره‌ای به StoreServer
 */
class AuditLogServiceProvider extends ModuleServiceProvider
{
    protected string $name = 'AuditLog';

    protected string $nameLower = 'auditlog';

    /** @var list<class-string> */
    protected array $providers = [
        EventServiceProvider::class,
        RouteServiceProvider::class,
    ];

    /** @var list<class-string> */
    protected array $commands = [
        ShipAuditLogsCommand::class,
        PruneAuditLogsCommand::class,
        AuditLogStatusCommand::class,
        VerifyAuditLogChainCommand::class,
    ];

    public function register(): void
    {
        parent::register();

        $this->mergeConfigFrom(module_path($this->name, 'config/auditlog.php'), 'auditlog');

        $this->app->singleton(ClientIdentity::class);
        $this->app->singleton(HashChain::class);
        $this->app->singleton(LogSanitizer::class);
        $this->app->singleton(AuditContext::class);
        $this->app->singleton(AuditRecorder::class);
        $this->app->singleton(AuditLogger::class);
        $this->app->singleton(LogShippingService::class);

        // فساد Audit
        $this->app->alias(AuditLogger::class, 'auditlog');
    }

    public function boot(): void
    {
        parent::boot();

        // تضمین وجود کلیدهای کانفیگ حتی اگر ترتیب merge ماژول‌ها تغییر کند
        $this->mergeConfigFrom(module_path($this->name, 'config/auditlog.php'), 'auditlog');

        $this->publishes([
            module_path($this->name, 'config/auditlog.php') => config_path('auditlog.php'),
        ], 'auditlog-config');

        if (! (bool) config('auditlog.enabled', true)) {
            return;
        }

        $this->registerMiddleware();
        $this->registerObservers();
        $this->registerTerminationHooks();
        $this->registerSchedule();
    }

    /**
     * نصب middlewareها روی تمام گروه‌ها؛ نیازی به ویرایش bootstrap/app.php نیست.
     */
    private function registerMiddleware(): void
    {
        /** @var Router $router */
        $router = $this->app['router'];

        foreach (['web', 'api'] as $group) {
            $router->prependMiddlewareToGroup($group, AssignAuditContext::class);
            $router->pushMiddlewareToGroup($group, LogHttpRequests::class);
        }

        // برای استفاده‌ی دستی روی روت‌های خارج از گروه‌های بالا
        $router->aliasMiddleware('audit.context', AssignAuditContext::class);
        $router->aliasMiddleware('audit.http', LogHttpRequests::class);
    }

    /**
     * اتصال Observer عمومی به مدل‌های پیکربندی‌شده.
     * مدل‌هایی که وجود ندارند (ماژول غیرفعال) نادیده گرفته می‌شوند.
     */
    private function registerObservers(): void
    {
        if (! (bool) config('auditlog.capture.model.enabled', true)) {
            return;
        }

        /** @var list<class-string<Model>> $models */
        $models = (array) config('auditlog.capture.model.observe', []);

        foreach ($models as $model) {
            if (! is_string($model) || ! class_exists($model) || ! is_subclass_of($model, Model::class)) {
                continue;
            }

            $model::observe(AuditableObserver::class);
        }
    }

    /**
     * در پایان هر چرخه (HTTP/CLI/Queue) بافر خالی و در صورت نیاز ارسال انجام می‌شود.
     */
    private function registerTerminationHooks(): void
    {
        $this->app->terminating(function (): void {
            try {
                $this->app->make(AuditRecorder::class)->flush();
            } catch (Throwable) {
                // خطای flush نباید پایان چرخه را بشکند
            }

            if ((bool) config('auditlog.shipping.auto.on_boot', true)) {
                try {
                    $this->app->make(LogShippingService::class)->shipDueIfNeeded();
                } catch (Throwable) {
                    // ارسال در اجرای بعدی تکرار می‌شود
                }
            }
        });
    }

    /**
     * زمان‌بندی استاندارد (اگر cron/schedule:run در دسترس باشد).
     * اپ دسکتاپ NativePHP از مسیر terminating بالا پوشش داده می‌شود.
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

                $schedule->command(ShipAuditLogsCommand::class, ['--release-stuck'])
                    ->everyFiveMinutes()
                    ->withoutOverlapping()
                    ->runInBackground();

                $schedule->command(PruneAuditLogsCommand::class, ['--force'])
                    ->dailyAt('03:30')
                    ->withoutOverlapping();
            } catch (Throwable) {
                // اگر scheduler در دسترس نبود، نادیده گرفته می‌شود
            }
        });
    }
}
