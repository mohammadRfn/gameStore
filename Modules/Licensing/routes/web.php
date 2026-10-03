<?php

use Illuminate\Support\Facades\Route;
use Modules\Licensing\Http\Controllers\ActivationController;

Route::prefix('activation')->name('licensing.')->group(function (): void {
    Route::get('/', [ActivationController::class, 'show'])->name('activate');
    Route::post('/request', [ActivationController::class, 'request'])->name('request');
    Route::get('/poll', [ActivationController::class, 'poll'])->name('poll');
    Route::post('/redeem', [ActivationController::class, 'redeem'])->name('redeem');
});

// بخش «بروزرسانی» در تنظیمات (پچ‌های StoreServer). نام settings.updates.* تا قفل لایسنس اعمال شود.
Route::prefix('settings/updates')
    ->middleware(['auth', 'license.module:Setting'])
    ->name('settings.updates.')
    ->group(function (): void {
        Route::get('/status', [\Modules\Licensing\Http\Controllers\PatchController::class, 'index'])->name('status');
        Route::post('/check', [\Modules\Licensing\Http\Controllers\PatchController::class, 'check'])->name('check');
        Route::post('/restart', [\Modules\Licensing\Http\Controllers\PatchController::class, 'restart'])->name('restart');
        Route::post('/{code}/install', [\Modules\Licensing\Http\Controllers\PatchController::class, 'install'])->where('code', '[A-Za-z0-9._\-]+')->name('install');
        Route::post('/{code}/rollback', [\Modules\Licensing\Http\Controllers\PatchController::class, 'rollback'])->where('code', '[A-Za-z0-9._\-]+')->name('rollback');
    });
