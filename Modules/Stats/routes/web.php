<?php

use Illuminate\Support\Facades\Route;
use Modules\Stats\Http\Controllers\MonthlySaleController;
use Modules\Stats\Http\Controllers\StatsController;

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('stats', [StatsController::class, 'index'])->name('stats.index');
    Route::get('stats/products', [StatsController::class, 'products'])->name('stats.products');
    Route::get('stats/services', [StatsController::class, 'services'])->name('stats.services');
    Route::get('stats/overview', [StatsController::class, 'overview'])->name('stats.overview');
    Route::get('stats/ranking', [StatsController::class, 'ranking'])->name('stats.ranking');

    Route::prefix('stats')->name('stats.')->group(function () {
        Route::get('daily', [StatsController::class, 'dailyStats'])->name('daily');
        Route::get('monthly', [StatsController::class, 'monthlyStats'])->name('monthly');
    });

    Route::resource('monthly-sales', MonthlySaleController::class)->only(['index', 'store']);
});