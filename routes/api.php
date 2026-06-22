<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\CustomerController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\RequestController;
use App\Http\Controllers\InvoiceController;
use App\Http\Controllers\CategoryController;
use App\Http\Controllers\ItemController;
use App\Http\Controllers\OrderItemController;
use App\Http\Controllers\ServiceJobController;
use App\Http\Controllers\ServiceTypeController;
use App\Http\Controllers\ShopController;
use App\Http\Controllers\StatsController;

Route::post('login', [AuthController::class, 'login']);
Route::post('logout', [AuthController::class, 'logout'])->middleware('auth:sanctum');
Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

Route::middleware(['auth:sanctum'])->group(function () {
    Route::prefix('requests')->middleware('auth:sanctum')->group(function () {
        Route::post('/', [RequestController::class, 'store']);
        Route::get('/', [RequestController::class, 'index']);
        Route::get('{id}', [RequestController::class, 'show']);
        Route::put('{id}', [RequestController::class, 'update']);
        Route::delete('{id}', [RequestController::class, 'destroy']);
    });

    Route::prefix('invoices')->middleware('auth:sanctum')->group(function () {
        Route::post('create', [InvoiceController::class, 'store']);
        Route::get('/', [InvoiceController::class, 'index']);
        Route::get('{invoiceId}', [InvoiceController::class, 'show']);
        Route::put('{invoiceId}', [InvoiceController::class, 'update']);
        Route::delete('{invoiceId}', [InvoiceController::class, 'destroy']);
        Route::post('{invoiceId}/confirmed', [InvoiceController::class, 'confirmInvoice']);
        Route::put('{invoiceId}/not-confirmed', [InvoiceController::class, 'setNotConfirmed']);
    });

    Route::prefix('order-items')->middleware('auth:sanctum')->group(function () {
        Route::get('/', [OrderItemController::class, 'index']);
        Route::get('{id}', [OrderItemController::class, 'show']);
        Route::post('/', [OrderItemController::class, 'store']);
        Route::put('{id}', [OrderItemController::class, 'update']);
        Route::delete('{id}', [OrderItemController::class, 'destroy']);
    });

    Route::prefix('customers')->group(function () {
        Route::get('{id}', [CustomerController::class, 'show']);
        Route::post('/', [CustomerController::class, 'store']);
        Route::put('{id}', [CustomerController::class, 'update']);
        Route::delete('{id}', [CustomerController::class, 'destroy']);
        Route::get('/', [CustomerController::class, 'index']);
    });

    Route::prefix('items')->group(function () {
        Route::get('/', [ItemController::class, 'index']);
        Route::post('/', [ItemController::class, 'store']);
        Route::get('{id}', [ItemController::class, 'show']);
        Route::put('{id}', [ItemController::class, 'update']);
        Route::delete('{id}', [ItemController::class, 'destroy']);
    });

    Route::prefix('categories')->group(function () {
        Route::get('/', [CategoryController::class, 'index']);
        Route::post('/', [CategoryController::class, 'store']);
    });



    Route::prefix('shops/{shop_id}')->group(function () {
        Route::apiResource('service-jobs', ServiceJobController::class);
        Route::apiResource('service-types', ServiceTypeController::class);

        Route::prefix('stats')->group(function () {
            Route::get('daily', [StatsController::class, 'dailyStats']);
            Route::get('monthly', [StatsController::class, 'monthlyStats']);
            Route::get('daily-items', [StatsController::class, 'dailyItemStats']);
        });
    });

    Route::apiResource('shops', ShopController::class);
});

Route::get('/health', function () {
    return response()->json(['status' => 'OK', 'timestamp' => now()]);
});
