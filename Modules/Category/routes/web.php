<?php

use Illuminate\Support\Facades\Route;
use Modules\Category\Http\Controllers\CategoryController;

Route::middleware(['auth', 'verified', 'license.module:Category'])->group(function () {
    Route::resource('categories', CategoryController::class)->only(['index', 'store', 'destroy']);
});

