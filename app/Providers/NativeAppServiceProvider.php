<?php

namespace App\Providers;

use Native\Laravel\Contracts\ProvidesPhpIni;
use Native\Laravel\Facades\ChildProcess;
use Native\Laravel\Facades\Window;
use Throwable;

class NativeAppServiceProvider implements ProvidesPhpIni
{
    /**
     * Executed once the native application has been booted.
     */
    public function boot(): void
    {
        Window::open();

        $this->startQueueWorker();
        $this->startLanServer();

        if (class_exists(\Native\Laravel\Facades\App::class)) {
            try {
                \Native\Laravel\Facades\App::openAtLogin(app('settings')->autoLaunch());
            } catch (Throwable $e) {
                //
            }
        }
    }

    /** php artisan queue:work --tries=1 (با همان alias قبلی تا چیزی به آن وابسته نشکند) */
    private function startQueueWorker(): void
    {
        try {
            ChildProcess::artisan(
                ['queue:work', '--tries=1', '--queue=default', '--memory=128', '--timeout=60', '--sleep=3'],
                'queue_default',
                persistent: true,
                iniSettings: ['memory_limit' => '128M'],
            );
        } catch (Throwable $e) {
            report($e);
        }
    }

    /** php artisan serve --host=0.0.0.0 --port=8000 برای دسترسی از شبکه‌ی محلی */
    private function startLanServer(): void
    {
        try {
            ChildProcess::artisan(
                ['serve', '--host=0.0.0.0', '--port=8000', '--no-reload'],
                'lan_server',
                env: ['GAMESHOP_LAN_SERVER' => '1'],
                persistent: true,
            );
        } catch (Throwable $e) {
            report($e);
        }
    }

    public function phpIni(): array
    {
        return [];
    }
}