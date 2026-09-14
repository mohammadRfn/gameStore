<?php

use Illuminate\Support\Facades\Route;
use Modules\Request\Http\Controllers\RequestController;

Route::middleware(['auth:sanctum'])->prefix('v1')->group(function () {
});
