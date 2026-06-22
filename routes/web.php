<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\CategoryController;
use App\Http\Controllers\CustomerController;
use App\Http\Controllers\InvoiceController;
use App\Http\Controllers\ItemController;
use App\Http\Controllers\MonthlySaleController;
use App\Http\Controllers\OrderItemController;
use App\Http\Controllers\RequestController;
use App\Http\Controllers\ServiceJobController;
use App\Http\Controllers\ServiceJobItemController;
use App\Http\Controllers\ServiceTypeController;
use App\Http\Controllers\StatsController;
use App\Http\Controllers\StockMovementController;
use Illuminate\Support\Facades\Route;

// ============================================================
// Auth
// ============================================================
Route::middleware('guest')->group(function () {
    Route::get('login', [AuthController::class, 'showLogin'])->name('login');
    Route::post('login', [AuthController::class, 'login'])->name('login.store');
});

Route::post('logout', [AuthController::class, 'logout'])
    ->middleware('auth')
    ->name('logout');

// ============================================================
// Protected Routes
// ============================================================
Route::middleware('auth')->group(function () {

    // Dashboard
    Route::get('/', fn() => inertia('Dashboard'))->name('dashboard');

    // Customers
    Route::resource('customers', CustomerController::class);

    // Requests
    Route::resource('requests', RequestController::class);

    // Invoices
    Route::resource('invoices', InvoiceController::class);
    Route::post('invoices/{invoiceId}/confirm', [InvoiceController::class, 'confirmInvoice'])->name('invoices.confirm');
    Route::post('invoices/{invoiceId}/not-confirmed', [InvoiceController::class, 'setNotConfirmed'])->name('invoices.not-confirmed');

    // Order Items
    Route::resource('order-items', OrderItemController::class);

    // Items (Inventory)
    Route::resource('items', ItemController::class);

    // Categories
    Route::resource('categories', CategoryController::class)->only(['index', 'store', 'destroy']);

    // Service Jobs
    Route::resource('service-jobs', ServiceJobController::class);

    // Service Job Items
    Route::resource('service-jobs.items', ServiceJobItemController::class)
        ->shallow();

    // Service Types
    Route::resource('service-types', ServiceTypeController::class);

    // Stock Movements
    Route::get('stock-movements', [StockMovementController::class, 'index'])->name('stock-movements.index');
    Route::post('stock-movements', [StockMovementController::class, 'storeManualMovement'])->name('stock-movements.store');
    Route::get('stock-movements/item/{itemId}', [StockMovementController::class, 'getItemStock'])->name('stock-movements.item-stock');

    // Stats
    Route::prefix('stats')->name('stats.')->group(function () {
        Route::get('daily', [StatsController::class, 'dailyStats'])->name('daily');
        Route::get('monthly', [StatsController::class, 'monthlyStats'])->name('monthly');
    });

    // Monthly Sales
    Route::resource('monthly-sales', MonthlySaleController::class)->only(['index', 'store']);
});