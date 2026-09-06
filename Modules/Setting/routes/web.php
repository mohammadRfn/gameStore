<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Modules\Setting\Http\Controllers\AppSettingController;

Route::prefix('settings')
    ->middleware(['auth'])
    ->name('settings.')
    ->group(function () {
        // ثابت‌ها — باید همه قبل از {key} باشن
        Route::get('/meta', [AppSettingController::class, 'meta'])->name('meta');
        Route::get('/export', [AppSettingController::class, 'export'])->name('export');
        Route::get('/restart-status', [AppSettingController::class, 'restartStatus'])->name('restart-status');
        Route::get('/system-printers', [AppSettingController::class, 'systemPrinters'])->name('system-printers');
        Route::get('/group/{group}', [AppSettingController::class, 'byGroup'])->name('group');

        // نوشتن (POST/PUT/DELETE - تداخلی با GET {key} ندارن)
        Route::put('/', [AppSettingController::class, 'update'])->name('update');
        Route::post('/bulk', [AppSettingController::class, 'update'])->name('bulk');
        Route::post('/reset-group/{group}', [AppSettingController::class, 'resetGroup'])->name('reset-group');
        Route::post('/reset-all', [AppSettingController::class, 'resetAll'])->name('reset-all');
        Route::post('/import', [AppSettingController::class, 'import'])->name('import');
        Route::post('/test-printer', [AppSettingController::class, 'testPrinter'])->name('test-printer');
        Route::post('/trigger-backup', [AppSettingController::class, 'triggerBackup'])->name('trigger-backup');
        Route::post('/check-updates', [AppSettingController::class, 'checkForUpdates'])->name('check-updates');
        Route::post('/install-update', [AppSettingController::class, 'installUpdate'])->name('install-update');
        Route::post('/acknowledge-restart', [AppSettingController::class, 'acknowledgeRestart'])->name('acknowledge-restart');

        // عمومی
        Route::get('/', [AppSettingController::class, 'index'])->name('index');

        // داینامیک‌ها — همیشه آخر
        Route::get('/{key}', [AppSettingController::class, 'show'])
            ->where('key', '[a-zA-Z0-9\.\-_]+')
            ->name('show');
        Route::delete('/{key}', [AppSettingController::class, 'reset'])
            ->where('key', '[a-zA-Z0-9\.\-_]+')
            ->name('reset');
    });