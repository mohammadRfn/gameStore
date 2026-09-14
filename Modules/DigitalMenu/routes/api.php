<?php

use Illuminate\Support\Facades\Route;
use Modules\DigitalMenu\Http\Controllers\DigitalMenuController;

Route::middleware(['auth:sanctum'])->prefix('v1')->group(function () {
    Route::apiResource('digitalmenus', DigitalMenuController::class)->names('digitalmenu');
});
