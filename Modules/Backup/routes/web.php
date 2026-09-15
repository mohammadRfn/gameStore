<?php

use Illuminate\Support\Facades\Route;
use Inertia\Inertia;
use Modules\Backup\Http\Controllers\BackupController;

Route::middleware(['auth', 'verified'])->group(function () {

    Route::post('/backup/pick-directory', [BackupController::class, 'pickDirectory']);

    Route::prefix('backups')->name('backups.')->group(function () {

        Route::get('panel', fn() => Inertia::render('Backup/Index'))->name('panel');
        Route::get('overview', [BackupController::class, 'overview'])->name('overview');
        Route::get('entities', [BackupController::class, 'entities'])->name('entities');

        Route::prefix('export')->name('export.')->group(function () {
            Route::post('validate-path', [BackupController::class, 'validateDestination'])->name('validate-path');
            Route::post('/', [BackupController::class, 'export'])->name('run');
            Route::post('database', [BackupController::class, 'exportDatabase'])->name('database');
            Route::post('media', [BackupController::class, 'exportMedia'])->name('media');
            Route::get('entity/{entityKey}.csv', [BackupController::class, 'downloadEntityCsv'])->name('entity-csv');
        });

        Route::prefix('import')->name('import.')->group(function () {
            Route::post('inspect', [BackupController::class, 'inspect'])->name('inspect');
            Route::post('dry-run', [BackupController::class, 'dryRun'])->name('dry-run');
            Route::post('/', [BackupController::class, 'import'])->name('run');
            Route::post('upload', [BackupController::class, 'upload'])->name('upload');
        });

        Route::get('/', [BackupController::class, 'index'])->name('index');
        Route::get('settings', [BackupController::class, 'settings'])->name('settings.show');
        Route::put('settings', [BackupController::class, 'updateSettings'])->name('settings.update');
        Route::get('{runId}', [BackupController::class, 'show'])->whereNumber('runId')->name('show');
        Route::get('{runId}/files', [BackupController::class, 'files'])->whereNumber('runId')->name('files');
        Route::get('{runId}/log', [BackupController::class, 'downloadLog'])->whereNumber('runId')->name('log');
        Route::delete('{runId}', [BackupController::class, 'destroy'])->whereNumber('runId')->name('destroy');
    });
});