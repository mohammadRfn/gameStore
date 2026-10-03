<?php

use Illuminate\Support\Facades\Route;
use Modules\SchemaManager\Http\Controllers\SchemaManagerController;

Route::middleware(['auth', 'verified'])->group(function () {
    Route::resource('schemamanagers', SchemaManagerController::class)->names('schemamanager');
});
