<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\CategoryController;
use App\Http\Controllers\DashboardController;

use App\Http\Controllers\MonthlySaleController;

use App\Http\Controllers\StatsController;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\ArchiveController;
use Inertia\Inertia;
use App\Http\Controllers\BackupController;

use App\Http\Controllers\DigitalMenuController;
use App\Http\Controllers\PublicMenuController;
// ============================================================
// Auth
// ============================================================

/*
|--------------------------------------------------------------------------
|  مسیرهای ماژول پشتیبان‌گیری  —  این بلاک را داخل routes/web.php
|  و درون گروه middleware('auth') موجود پروژه اضافه کنید.
|--------------------------------------------------------------------------
|  use App\Http\Controllers\BackupController;
*/




Route::middleware('guest')->group(function () {
    Route::get('login', [AuthController::class, 'showLogin'])->name('login');
    Route::post('login', [AuthController::class, 'login'])->name('login.store');
});

Route::post('logout', [AuthController::class, 'logout'])
    ->middleware('auth')
    ->name('logout');

// ============================================================
// Protected Routes
// ============================================================
Route::middleware('auth')->group(function () {

    // Dashboard




    // Categories
    Route::resource('categories', CategoryController::class)->only(['index', 'store', 'destroy']);
    Route::post('/backup/pick-directory', [BackupController::class, 'pickDirectory']);
    // Service Jobs
    Route::get('stats', [StatsController::class, 'index'])->name('stats.index');
    Route::get('stats/products', [StatsController::class, 'products'])->name('stats.products');
    Route::get('stats/services', [StatsController::class, 'services'])->name('stats.services');
    Route::get('stats/overview', [StatsController::class, 'overview'])->name('stats.overview');
    Route::get('stats/ranking', [StatsController::class, 'ranking'])
        ->name('stats.ranking');



    Route::post('invoices/{invoice}/digital-menu', [DigitalMenuController::class, 'activate'])->name('invoices.digital-menu.activate');
    Route::get('invoices/{invoice}/digital-menu', [DigitalMenuController::class, 'status'])->name('invoices.digital-menu.status');

    // Stats
    Route::prefix('stats')->name('stats.')->group(function () {
        Route::get('daily', [StatsController::class, 'dailyStats'])->name('daily');
        Route::get('monthly', [StatsController::class, 'monthlyStats'])->name('monthly');
    });

    Route::get('/', [DashboardController::class, 'index'])->name('dashboard');


    Route::prefix('archives')->name('archives.')->group(function () {
        Route::get('/', [ArchiveController::class, 'index'])->name('index');
        Route::get('panel', fn() => Inertia::render('Archive/Index'))
            ->name('panel');

        Route::get('/', [ArchiveController::class, 'index'])->name('index');
        Route::get('export/invoices', [ArchiveController::class, 'exportInvoices'])->name('export.invoices');
        Route::get('export/requests', [ArchiveController::class, 'exportRequests'])->name('export.requests');
        Route::get('export/service-jobs', [ArchiveController::class, 'exportServiceJobs'])->name('export.service-jobs');
        Route::get('export/all', [ArchiveController::class, 'exportAll'])->name('export.all');

        Route::get('{archivedRecordId}', [ArchiveController::class, 'show'])
            ->whereNumber('archivedRecordId')->name('show');

        Route::post('sync-paid', [ArchiveController::class, 'syncPaidCopies'])->name('sync-paid');

        Route::post('{sourceType}/{sourceId}/copy', [ArchiveController::class, 'copy'])
            ->whereIn('sourceType', ['invoice', 'request', 'service-job'])
            ->whereNumber('sourceId')
            ->name('copy');

        Route::post('{sourceType}/{sourceId}/transfer', [ArchiveController::class, 'transfer'])
            ->whereIn('sourceType', ['invoice', 'request', 'service-job'])
            ->whereNumber('sourceId')
            ->name('transfer');

        Route::post('{archivedRecordId}/transfer', [ArchiveController::class, 'transferArchiveRecord'])
            ->whereNumber('archivedRecordId')
            ->name('records.transfer');

        Route::post('{archivedRecordId}/restore', [ArchiveController::class, 'restore'])
            ->whereNumber('archivedRecordId')
            ->name('restore');

        Route::delete('{archivedRecordId}', [ArchiveController::class, 'destroy'])
            ->whereNumber('archivedRecordId')
            ->name('destroy');
    });


    Route::prefix('backups')->name('backups.')->group(function () {

        // ---- داشبورد و متادیتا -------------------------------------------------
        Route::get('panel', fn() => Inertia::render('Backup/Index'))->name('panel');
        Route::get('overview', [BackupController::class, 'overview'])->name('overview');
        Route::get('entities', [BackupController::class, 'entities'])->name('entities');

        // ---- بخش خروجی (Export) ------------------------------------------------
        Route::prefix('export')->name('export.')->group(function () {
            Route::post('validate-path', [BackupController::class, 'validateDestination'])->name('validate-path');
            Route::post('/', [BackupController::class, 'export'])->name('run');
            Route::post('database', [BackupController::class, 'exportDatabase'])->name('database');
            Route::post('media', [BackupController::class, 'exportMedia'])->name('media');
            Route::get('entity/{entityKey}.csv', [BackupController::class, 'downloadEntityCsv'])->name('entity-csv');
        });

        // ---- بخش ورودی (Import) ------------------------------------------------
        Route::prefix('import')->name('import.')->group(function () {
            Route::post('inspect', [BackupController::class, 'inspect'])->name('inspect');
            Route::post('dry-run', [BackupController::class, 'dryRun'])->name('dry-run');
            Route::post('/', [BackupController::class, 'import'])->name('run');
            Route::post('upload', [BackupController::class, 'upload'])->name('upload');
        });

        // ---- تاریخچه -----------------------------------------------------------
        Route::get('/', [BackupController::class, 'index'])->name('index');
        Route::get('settings', [BackupController::class, 'settings'])->name('settings.show');
        Route::put('settings', [BackupController::class, 'updateSettings'])->name('settings.update');
        Route::get('{runId}', [BackupController::class, 'show'])->whereNumber('runId')->name('show');
        Route::get('{runId}/files', [BackupController::class, 'files'])->whereNumber('runId')->name('files');
        Route::get('{runId}/log', [BackupController::class, 'downloadLog'])->whereNumber('runId')->name('log');
        Route::delete('{runId}', [BackupController::class, 'destroy'])->whereNumber('runId')->name('destroy');
    });
    // Monthly Sales
    Route::resource('monthly-sales', MonthlySaleController::class)->only(['index', 'store']);


  
});

// ============================================================
// Digital Menu (Public — بدون auth، فقط از طریق هات‌اسپات محلی)
// ============================================================
Route::prefix('menu')->name('menu.')->group(function () {
    Route::get('/', [PublicMenuController::class, 'entry'])->name('entry');
    Route::post('/', [PublicMenuController::class, 'verify'])->name('verify');
    Route::get('session/{token}', [PublicMenuController::class, 'session'])->name('session');
    Route::post('session/{token}/select', [PublicMenuController::class, 'select'])->name('select');
    Route::delete('session/{token}/select/{itemId}', [PublicMenuController::class, 'decrease'])->name('select.destroy');
    Route::post('session/{token}/submit', [PublicMenuController::class, 'submit'])->name('submit');
    Route::get('{code}', [PublicMenuController::class, 'show'])->where('code', '[0-9]{4}')->name('show');
});
