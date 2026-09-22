<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Modules\AuditLog\Http\Controllers\AuditLogController;
use Modules\AuditLog\Http\Controllers\AuditLogSyncController;

/*
|--------------------------------------------------------------------------
| روت‌های داخلی ماژول AuditLog (پنل گیم‌استور)
|--------------------------------------------------------------------------
| پیشوند نهایی: /api/auditlog
| این روت‌ها فقط برای مشاهده و مدیریت لاگ‌های محلی هستند؛
| ارسال به StoreServer از طریق سرویس/کامند انجام می‌شود.
*/

Route::prefix((string) config('auditlog.api.prefix', 'auditlog'))
    ->middleware((array) config('auditlog.api.middleware', ['auth:sanctum']))
    ->name('auditlog.')
    ->group(function (): void {

        Route::get('/logs', [AuditLogController::class, 'index'])->name('logs.index');
        Route::get('/logs/filters', [AuditLogController::class, 'filters'])->name('logs.filters');
        Route::get('/logs/stats', [AuditLogController::class, 'stats'])->name('logs.stats');
        Route::get('/logs/export', [AuditLogController::class, 'export'])->name('logs.export');
        Route::get('/logs/verify', [AuditLogController::class, 'verify'])->name('logs.verify');
        Route::get('/logs/timeline/{requestId}', [AuditLogController::class, 'timeline'])->name('logs.timeline');
        Route::get('/logs/entity/{type}/{id}', [AuditLogController::class, 'forEntity'])->name('logs.entity');
        Route::get('/logs/{id}', [AuditLogController::class, 'show'])->whereNumber('id')->name('logs.show');

        Route::prefix('sync')->name('sync.')->group(function (): void {
            Route::get('/status', [AuditLogSyncController::class, 'status'])->name('status');
            Route::get('/batches', [AuditLogSyncController::class, 'batches'])->name('batches');
            Route::post('/ship', [AuditLogSyncController::class, 'ship'])->name('ship');
            Route::post('/retry', [AuditLogSyncController::class, 'retry'])->name('retry');
            Route::post('/ping', [AuditLogSyncController::class, 'ping'])->name('ping');
        });
    });
