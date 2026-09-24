<?php

use Illuminate\Support\Facades\Route;
use Modules\Customer\Http\Controllers\CustomerController;

Route::middleware(['auth', 'verified', 'license.module:Customer'])->group(function () {
    Route::resource('customers', CustomerController::class);
});