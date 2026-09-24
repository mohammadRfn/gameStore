<?php

use Illuminate\Support\Facades\Route;
use Inertia\Inertia;
use Modules\Archive\Http\Controllers\ArchiveController;

Route::middleware(['auth', 'verified', 'license.module:Invoice'])->group(function () {
    Route::prefix('archives')->name('archives.')->group(function () {
        Route::get('/', [ArchiveController::class, 'index'])->name('index');
        Route::get('panel', fn() => Inertia::render('Archive/Index'))->name('panel');

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
});