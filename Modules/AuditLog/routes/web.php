<?php

declare(strict_types=1);



use Illuminate\Support\Facades\Route;
use Modules\AuditLog\Http\Controllers\ClientErrorController;

Route::post('/auditlog/client-error', [ClientErrorController::class, 'store'])
    ->middleware('throttle:20,1')
    ->name('auditlog.client-error');