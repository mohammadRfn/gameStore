<?php

use Illuminate\Support\Facades\Route;
use Modules\Request\Http\Controllers\RequestController;

Route::middleware(['auth', 'verified'])->group(function () {
    Route::resource('requests', RequestController::class);
});
