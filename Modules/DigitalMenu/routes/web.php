<?php

use Illuminate\Support\Facades\Route;
use Modules\DigitalMenu\Http\Controllers\DigitalMenuController;
use Modules\DigitalMenu\Http\Controllers\PublicMenuController;

Route::middleware(['auth', 'verified', 'license.module:DigitalMenu'])->group(function () {
    Route::post('invoices/{invoice}/digital-menu', [DigitalMenuController::class, 'activate'])->name('invoices.digital-menu.activate');
    Route::get('invoices/{invoice}/digital-menu', [DigitalMenuController::class, 'status'])->name('invoices.digital-menu.status');
});

// Digital Menu (Public — بدون auth، فقط از طریق هات‌اسپات محلی)
Route::middleware('license.module:DigitalMenu')->prefix('menu')->name('menu.')->group(function () {
    Route::get('/', [PublicMenuController::class, 'entry'])->name('entry');
    Route::post('/', [PublicMenuController::class, 'verify'])->name('verify');
    Route::get('session/{token}', [PublicMenuController::class, 'session'])->name('session');
    Route::post('session/{token}/select', [PublicMenuController::class, 'select'])->name('select');
    Route::delete('session/{token}/select/{itemId}', [PublicMenuController::class, 'decrease'])->name('select.destroy');
    Route::post('session/{token}/submit', [PublicMenuController::class, 'submit'])->name('submit');
    Route::get('{code}', [PublicMenuController::class, 'show'])->where('code', '[0-9]{4}')->name('show');
});