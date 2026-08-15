<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\CategoryController;
use App\Http\Controllers\CustomerController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\InvoiceAdjustmentController;
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
use App\Http\Controllers\ArchiveController;
use Inertia\Inertia;

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

    // Customers
    Route::resource('customers', CustomerController::class);

    // Requests
    Route::resource('requests', RequestController::class);

    // Invoices
    Route::resource('invoices', InvoiceController::class);
    // Route::post('invoices/{invoiceId}/confirm', [InvoiceController::class, 'confirmInvoice'])->name('invoices.confirm');
    // Route::post('invoices/{invoiceId}/not-confirmed', [InvoiceController::class, 'setNotConfirmed'])->name('invoices.not-confirmed');
    Route::post('invoices/{invoice}/return', [InvoiceController::class, 'markReturned'])->name('invoices.return');
    Route::post('invoices/{invoice}/restock-items', [InvoiceController::class, 'restockItems'])->name('invoices.restock-items');
    // Order Items
    Route::resource('order-items', OrderItemController::class);
    Route::post('invoices/{invoice}/unreturn', [InvoiceController::class, 'unmarkReturned'])->name('invoices.unreturn');
    // Items (Inventory)
    Route::resource('items', ItemController::class);
    Route::get('invoices/{invoice}/pdf', [InvoiceController::class, 'exportPdf'])->name('invoices.pdf');
    Route::post('invoices/{invoice}/receipt', [InvoiceController::class, 'attachReceipt'])->name('invoices.receipt.store');
    Route::delete('invoices/{invoice}/receipt', [InvoiceController::class, 'removeReceipt'])->name('invoices.receipt.destroy');
    // Categories
    Route::resource('categories', CategoryController::class)->only(['index', 'store', 'destroy']);

    // Service Jobs
    Route::resource('service-jobs', ServiceJobController::class);
    Route::get('stats', [StatsController::class, 'index'])->name('stats.index');
    Route::get('stats/products', [StatsController::class, 'products'])->name('stats.products');
    Route::get('stats/services', [StatsController::class, 'services'])->name('stats.services');
    Route::get('stats/overview', [StatsController::class, 'overview'])->name('stats.overview');
    Route::get('stats/ranking', [StatsController::class, 'ranking'])
        ->name('stats.ranking');
    // Service Job Items
    Route::resource('service-jobs.items', ServiceJobItemController::class)
        ->shallow()
        ->names('service-job-items') // <--- این بخش اضافه شود
        ->only(['index', 'store', 'update', 'destroy']);

    Route::post('service-types/quick', [ServiceTypeController::class, 'quickStore'])
        ->name('service-types.quick-store');

    Route::resource('service-types', ServiceTypeController::class);
    // Items (Inventory)
    Route::resource('items', ItemController::class);
    Route::post('invoices/{invoice}/service-jobs', [OrderItemController::class, 'attachServiceJobs'])->name('invoices.service-jobs.attach');
    Route::delete('invoices/{invoice}/service-jobs/{serviceJob}', [OrderItemController::class, 'detachServiceJob'])->name('invoices.service-jobs.detach');
    // Service Types
    Route::resource('service-types', ServiceTypeController::class);
    Route::post('order-items/{id}/return', [OrderItemController::class, 'markReturned'])->name('order-items.return');
    Route::post('order-items/{id}/unreturn', [OrderItemController::class, 'unmarkReturned'])->name('order-items.unreturn');
    // Stock Movements
    Route::get('stock-movements', [StockMovementController::class, 'index'])->name('stock-movements.index');
    Route::post('stock-movements', [StockMovementController::class, 'storeManualMovement'])->name('stock-movements.store');
    Route::get('stock-movements/item/{itemId}', [StockMovementController::class, 'getItemStock'])->name('stock-movements.item-stock');

    // Stats
    Route::prefix('stats')->name('stats.')->group(function () {
        Route::get('daily', [StatsController::class, 'dailyStats'])->name('daily');
        Route::get('monthly', [StatsController::class, 'monthlyStats'])->name('monthly');
    });

    Route::get('/', [DashboardController::class, 'index'])->name('dashboard');
    Route::post('invoices/{invoiceId}/adjustments', [InvoiceAdjustmentController::class, 'store'])->name('invoice-adjustments.store');
    Route::delete('invoices/{invoiceId}/adjustments/{adjustmentId}', [InvoiceAdjustmentController::class, 'destroy'])->name('invoice-adjustments.destroy');


    Route::prefix('archives')->name('archives.')->group(function () {
        Route::get('/', [ArchiveController::class, 'index'])->name('index');
        Route::get('panel', fn() => Inertia::render('Archive/Index'))
            ->name('panel');

        Route::get('/', [ArchiveController::class, 'index'])->name('index');
        Route::get('export/invoices', [ArchiveController::class, 'exportInvoices'])->name('export.invoices');
        Route::get('export/requests', [ArchiveController::class, 'exportRequests'])->name('export.requests');
        Route::get('export/service-jobs', [ArchiveController::class, 'exportServiceJobs'])->name('export.service-jobs');
        Route::get('export/all', [ArchiveController::class, 'exportAll'])->name('export.all');

        Route::get('{archivedRecordId}', [ArchiveController::class, 'show'])
            ->whereNumber('archivedRecordId')->name('show');

        Route::post('sync-paid', [ArchiveController::class, 'syncPaidCopies'])->name('sync-paid');

        Route::post('{sourceType}/{sourceId}/copy', [ArchiveController::class, 'copy'])
            ->whereIn('sourceType', ['invoice', 'request', 'service-job'])
            ->whereNumber('sourceId')
            ->name('copy');

        Route::post('{sourceType}/{sourceId}/transfer', [ArchiveController::class, 'transfer'])
            ->whereIn('sourceType', ['invoice', 'request', 'service-job'])
            ->whereNumber('sourceId')
            ->name('transfer');

        Route::post('{archivedRecordId}/transfer', [ArchiveController::class, 'transferArchiveRecord'])
            ->whereNumber('archivedRecordId')
            ->name('records.transfer');

        Route::post('{archivedRecordId}/restore', [ArchiveController::class, 'restore'])
            ->whereNumber('archivedRecordId')
            ->name('restore');

        Route::delete('{archivedRecordId}', [ArchiveController::class, 'destroy'])
            ->whereNumber('archivedRecordId')
            ->name('destroy');
    });

    // Monthly Sales
    Route::resource('monthly-sales', MonthlySaleController::class)->only(['index', 'store']);

    Route::post('invoices/{invoiceId}/mark-paid', [InvoiceController::class, 'markAsPaid'])->name('invoices.mark-paid');
    Route::post('invoices/{invoiceId}/mark-unpaid', [InvoiceController::class, 'markAsUnpaid'])->name('invoices.mark-unpaid');

    // اسکلت خودپرداز خودکار — فعلاً بدون پیاده‌سازی کامل امنیتی
    Route::post('invoices/{invoiceId}/terminal-webhook', [InvoiceController::class, 'automaticTerminalWebhook'])->name('invoices.terminal-webhook');
});
