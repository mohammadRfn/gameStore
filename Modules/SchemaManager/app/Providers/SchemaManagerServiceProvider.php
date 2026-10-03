<?php

namespace Modules\SchemaManager\Providers;

use Illuminate\Console\Events\CommandStarting;
use Illuminate\Routing\Router;
use Illuminate\Support\Facades\Event;
use Modules\SchemaManager\Console\Commands\SchemaApplyCommand;
use Modules\SchemaManager\Console\Commands\SchemaExportCommand;
use Modules\SchemaManager\Console\Commands\SchemaVerifyCommand;
use Modules\SchemaManager\Http\Middleware\EnsureSchema;
use Modules\SchemaManager\Services\SchemaRunner;
use Nwidart\Modules\Support\ModuleServiceProvider;
use Throwable;

class SchemaManagerServiceProvider extends ModuleServiceProvider
{
    protected string $name = 'SchemaManager';

    protected string $nameLower = 'schemamanager';

    protected array $commands = [
        SchemaExportCommand::class,
        SchemaVerifyCommand::class,
        SchemaApplyCommand::class,
    ];

    protected array $providers = [
        EventServiceProvider::class,
        RouteServiceProvider::class,
    ];

    public function register(): void
    {
        parent::register();

        $this->app->singleton(SchemaRunner::class);
    }

    public function boot(): void
    {
        parent::boot();

        // اسکیما باید پیش از هر چیز (احراز هویت، گیت لایسنس) کامل باشد
        /** @var Router $router */
        $router = $this->app['router'];
        $router->prependMiddlewareToGroup('web', EnsureSchema::class);

        // NativePHP قبل از اولین درخواست وب `migrate` را اجرا می‌کند؛ صف و دستورهای licensing هم ممکن است
        // زودتر از اولین درخواست وب اجرا شوند. baseline باید پیش از همه‌ی آن‌ها اعمال شود.
        Event::listen(CommandStarting::class, function (CommandStarting $event): void {
            $cmd = (string) $event->command;
            if (preg_match('/^(migrate|db:seed|queue:(work|listen)|schedule:run|native:|licensing:)/', $cmd) !== 1) {
                return;
            }
            try {
                $this->app->make(SchemaRunner::class)->ensure();
            } catch (Throwable) {
                // خطا در لاگ ثبت می‌شود؛ دستور اصلی ادامه می‌یابد
            }
        });
    }
}