<?php

use Illuminate\Support\Facades\Route;
use Modules\SchemaManager\Http\Controllers\SchemaManagerController;

Route::middleware(['auth:sanctum'])->prefix('v1')->group(function () {
    Route::apiResource('schemamanagers', SchemaManagerController::class)->names('schemamanager');
});
