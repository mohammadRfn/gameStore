<?php

use Illuminate\Support\Facades\Route;
use Modules\Authentication\Http\Controllers\AuthController;
use Modules\Authentication\Http\Controllers\AccountController;
use Modules\Authentication\Http\Controllers\PasswordRecoveryController;

Route::middleware('guest')->group(function () {
    Route::get('login', [AuthController::class, 'showLogin'])->name('login');
    Route::post('login', [AuthController::class, 'login'])->name('login.store');

    Route::get('forgot-password', [PasswordRecoveryController::class, 'show'])->name('password.forgot');
    Route::post('forgot-password', [PasswordRecoveryController::class, 'reset'])
        ->middleware('throttle:5,1') // جلوگیری از حدس زدن کد
        ->name('password.forgot.store');
});

Route::post('logout', [AuthController::class, 'logout'])
    ->middleware('auth')
    ->name('logout');

Route::middleware('auth')->prefix('account')->name('account.')->group(function () {
    Route::get('credentials', [AccountController::class, 'show'])->name('credentials.show');
    Route::put('credentials', [AccountController::class, 'update'])
        ->middleware('throttle:5,1') // جلوگیری از حدس زدن رمز فعلی
        ->name('credentials.update');
});