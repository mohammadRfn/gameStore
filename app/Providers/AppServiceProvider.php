<?php

namespace App\Providers;


use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // PHP باندل‌شدهٔ نسخهٔ دسکتاپ اکستنشن xmlwriter ندارد؛ بدون این جایگزین هیچ خروجی اکسلی ساخته نمی‌شود.
        require_once base_path('app/Support/Polyfills/XMLWriter.php');
       
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
    }
}
