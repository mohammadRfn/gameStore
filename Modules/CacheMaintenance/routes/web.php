<?php

use Illuminate\Support\Facades\Route;
use Modules\CacheMaintenance\Http\Controllers\CacheMaintenanceController;

Route::middleware(['auth', 'verified'])->group(function () {
    Route::resource('cachemaintenances', CacheMaintenanceController::class)->names('cachemaintenance');
});
