<?php

namespace App\Providers;


use Illuminate\Support\ServiceProvider;
use App\Services\WindowsFontInstaller;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
       
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // فونت وزیرمتن برای فایل‌های اکسل روی سیستم مشتری (فقط ویندوز، یک‌بار)
        if (!$this->app->runningUnitTests()) {
            WindowsFontInstaller::ensure();
        }
    }
}
