<?php

use Illuminate\Support\Facades\Route;
use Modules\Warranty\Http\Controllers\WarrantyController;

Route::middleware(['auth', 'verified', 'license.module:Warranty'])->group(function () {
    Route::get('/warranty-providers', [WarrantyController::class, 'providers'])->name('warranty-providers.index');
    Route::post('/warranty-providers', [WarrantyController::class, 'storeProvider'])->name('warranty-providers.store');
    Route::post('/order-items/{orderItem}/warranty', [WarrantyController::class, 'store'])->name('order-items.warranty.store');
    Route::post('/item-serial-numbers/{itemSerialNumber}/warranty', [WarrantyController::class, 'storeForSerial'])->name('item-serial-numbers.warranty.store');
    Route::get('/items/{item}/warranty-candidates', [WarrantyController::class, 'candidatesForItem'])->name('items.warranty-candidates');
});
