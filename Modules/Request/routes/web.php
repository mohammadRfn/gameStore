<?php

use Illuminate\Support\Facades\Route;
use Modules\Request\Http\Controllers\RequestController;
use Modules\Request\Http\Controllers\RequestBuilderController;

Route::middleware(['auth', 'verified', 'license.module:Request'])->group(function () {
    // باید قبل از resource بیایند تا با requests/{request} قاطی نشوند
    Route::get('requests/category-items', [RequestBuilderController::class, 'categoryItems'])->name('requests.category-items');
    Route::post('requests/quick-customer', [RequestBuilderController::class, 'quickCustomer'])->name('requests.quick-customer');
    Route::patch('requests/items/{item}/price', [RequestBuilderController::class, 'updateItemPrice'])->name('requests.items.price');

    Route::resource('requests', RequestController::class);
});
