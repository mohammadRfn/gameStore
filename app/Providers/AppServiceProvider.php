<?php

namespace App\Providers;

use App\Services\CategoryService;
use App\Services\CategoryServiceInterface;
use App\Services\InvoiceService;
use App\Services\InvoiceServiceInterface;
use App\Services\MonthlySaleService;
use App\Services\MonthlySaleServiceInterface;
use App\Services\RequestService;
use App\Services\RequestServiceInterface;
use App\Services\ServiceJobItemService;
use App\Services\ServiceJobItemServiceInterface;
use App\Services\ServiceTypeService;
use App\Services\ServiceTypeServiceInterface;
use App\Services\StatsService;
use App\Services\StatsServiceInterface;
use Illuminate\Support\ServiceProvider;

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
        //
    }
}
