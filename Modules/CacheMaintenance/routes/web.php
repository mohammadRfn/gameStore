<?php

use Illuminate\Support\Facades\Route;
use Inertia\Inertia;
use Modules\CacheMaintenance\Http\Controllers\CacheMaintenanceController;

Route::middleware('auth')->prefix('settings/cache')->name('settings.cache.')->group(function () {
    Route::get('/', function () {
        return Inertia::render('CacheMaintenance/Index');
    })->name('panel');
    Route::get('overview', [CacheMaintenanceController::class, 'overview'])->name('overview');
    Route::get('targets',  [CacheMaintenanceController::class, 'targets'])->name('targets');
    Route::post('clear',   [CacheMaintenanceController::class, 'clear'])->name('clear');
    Route::post('optimize', [CacheMaintenanceController::class, 'optimize'])->name('optimize');
    Route::get('runs',     [CacheMaintenanceController::class, 'index'])->name('runs.index');
    Route::get('runs/{runId}', [CacheMaintenanceController::class, 'show'])
        ->whereNumber('runId')->name('runs.show');
});