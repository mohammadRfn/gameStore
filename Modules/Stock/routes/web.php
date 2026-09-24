<?php

use Illuminate\Support\Facades\Route;
use Modules\Stock\Http\Controllers\ItemController;
use Modules\Stock\Http\Controllers\StockMovementController;

Route::middleware(['auth', 'verified', 'license.module:Stock'])->group(function () {
    Route::resource('items', ItemController::class);

    Route::get('stock-movements', [StockMovementController::class, 'index'])->name('stock-movements.index');
    Route::post('stock-movements', [StockMovementController::class, 'storeManualMovement'])->name('stock-movements.store');
    Route::get('stock-movements/item/{itemId}', [StockMovementController::class, 'getItemStock'])->name('stock-movements.item-stock');
    Route::get('/items/{item}/available-serials', [StockMovementController::class, 'getAvailableSerialNumbers'])->name('items.available-serials');
    Route::get('/items/{item}/missing-serials', [StockMovementController::class, 'getMissingSerialSlots'])->name('items.missing-serials');
    Route::patch('/item-serial-numbers/{itemSerialNumber}/assign', [StockMovementController::class, 'assignSerialNumber'])->name('item-serial-numbers.assign');
    Route::put('/serial-numbers/{itemSerialNumberId}', [StockMovementController::class, 'updateSerialNumber'])->name('serial-numbers.update');
    Route::delete('/serial-numbers/{itemSerialNumberId}', [StockMovementController::class, 'deleteSerialSlot'])->name('serial-numbers.destroy');
});
