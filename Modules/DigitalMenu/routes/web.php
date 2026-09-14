<?php

use Illuminate\Support\Facades\Route;
use Modules\DigitalMenu\Http\Controllers\DigitalMenuController;

Route::middleware(['auth', 'verified'])->group(function () {
    Route::resource('digitalmenus', DigitalMenuController::class)->names('digitalmenu');
});
