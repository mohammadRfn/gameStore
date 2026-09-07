<?php

use Illuminate\Support\Facades\Route;
use Modules\CacheMaintenance\Http\Controllers\CacheMaintenanceController;

Route::middleware(['auth:sanctum'])->prefix('v1')->group(function () {
    Route::apiResource('cachemaintenances', CacheMaintenanceController::class)->names('cachemaintenance');
});
