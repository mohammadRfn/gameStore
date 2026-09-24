<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Modules\Setting\Http\Controllers\AppSettingController;
use Inertia\Inertia;

Route::prefix('settings')
    ->middleware(['auth', 'license.module:Setting'])
    ->name('settings.')
    ->group(function () {
        Route::get('/panel', fn() => Inertia::render('Settings/Index'))->name('panel');

        // ثابت‌ها — باید همه قبل از {key} باشن
        Route::get('/meta', [AppSettingController::class, 'meta'])->name('meta');
        Route::get('/export', [AppSettingController::class, 'export'])->name('export');
        Route::get('/group/{group}', [AppSettingController::class, 'byGroup'])->name('group');

        // نوشتن (POST/PUT/DELETE - تداخلی با GET {key} ندارن)
        Route::put('/', [AppSettingController::class, 'update'])->name('update');
        Route::post('/bulk', [AppSettingController::class, 'update'])->name('bulk');
        Route::post('/reset-group/{group}', [AppSettingController::class, 'resetGroup'])->name('reset-group');
        Route::post('/reset-all', [AppSettingController::class, 'resetAll'])->name('reset-all');
        Route::post('/import', [AppSettingController::class, 'import'])->name('import');
        Route::post('/check-updates', [AppSettingController::class, 'checkForUpdates'])->name('check-updates');
        Route::post('/install-update', [AppSettingController::class, 'installUpdate'])->name('install-update');

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