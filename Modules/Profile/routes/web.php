<?php

use Illuminate\Support\Facades\Route;
use Modules\Profile\Http\Controllers\StoreProfileController;

Route::middleware('auth')->group(function () {
    // ---------------- Store Profiles ----------------
    Route::get('store-profiles', [StoreProfileController::class, 'index'])->name('store-profiles.index');
    Route::get('store-profiles/search', [StoreProfileController::class, 'search'])->name('store-profiles.search');
    Route::get('store-profiles/{id}', [StoreProfileController::class, 'show'])->name('store-profiles.show');
    Route::post('store-profiles', [StoreProfileController::class, 'store'])->name('store-profiles.store');
    Route::put('store-profiles/{id}', [StoreProfileController::class, 'update'])->name('store-profiles.update');
    Route::delete('store-profiles/{id}', [StoreProfileController::class, 'destroy'])->name('store-profiles.destroy');
    Route::post('store-profiles/{id}/primary', [StoreProfileController::class, 'setPrimary'])->name('store-profiles.primary');

    /*
    |--------------------------------------------------------------------------
    | JSON API (Electron renderer / local API) — Sanctum or same-session auth
    |--------------------------------------------------------------------------
    */

});