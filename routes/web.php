<?php

use App\Http\Controllers\MonthlySaleController;
use App\Http\Controllers\StatsController;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\ArchiveController;
use Inertia\Inertia;

Route::middleware('auth')->group(function () {

    Route::get('stats', [StatsController::class, 'index'])->name('stats.index');
    Route::get('stats/products', [StatsController::class, 'products'])->name('stats.products');
    Route::get('stats/services', [StatsController::class, 'services'])->name('stats.services');
    Route::get('stats/overview', [StatsController::class, 'overview'])->name('stats.overview');
    Route::get('stats/ranking', [StatsController::class, 'ranking'])
        ->name('stats.ranking');

    Route::prefix('stats')->name('stats.')->group(function () {
        Route::get('daily', [StatsController::class, 'dailyStats'])->name('daily');
        Route::get('monthly', [StatsController::class, 'monthlyStats'])->name('monthly');
    });

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

    Route::resource('monthly-sales', MonthlySaleController::class)->only(['index', 'store']);
});