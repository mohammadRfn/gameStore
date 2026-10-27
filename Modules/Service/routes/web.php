<?php

use Illuminate\Support\Facades\Route;
use Modules\Service\Http\Controllers\ServiceJobController;
use Modules\Service\Http\Controllers\ServiceJobItemController;
use Modules\Service\Http\Controllers\ServiceTypeController;

Route::middleware(['auth', 'verified', 'license.module:Service'])->group(function () {
    Route::post('service-jobs/{id}/attach-invoice', [ServiceJobController::class, 'attachToInvoice'])
        ->name('service-jobs.attach-invoice');
    Route::get('service-jobs/{id}/pdf', [ServiceJobController::class, 'exportPdf'])
        ->name('service-jobs.pdf');
    Route::resource('service-jobs', ServiceJobController::class);

    Route::post('service-types/quick', [ServiceTypeController::class, 'quickStore'])
        ->name('service-types.quick-store');
    Route::resource('service-types', ServiceTypeController::class);

    Route::resource('service-jobs.items', ServiceJobItemController::class)
        ->shallow()
        ->names('service-job-items')
        ->only(['index', 'store', 'update', 'destroy']);
});