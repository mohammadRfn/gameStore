<?php

use Illuminate\Support\Facades\Route;
use Modules\Licensing\Http\Controllers\ActivationController;

Route::prefix('activation')->name('licensing.')->group(function (): void {
    Route::get('/', [ActivationController::class, 'show'])->name('activate');
    Route::post('/request', [ActivationController::class, 'request'])->name('request');
    Route::get('/poll', [ActivationController::class, 'poll'])->name('poll');
    Route::post('/redeem', [ActivationController::class, 'redeem'])->name('redeem');
});