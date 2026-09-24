<?php

use Illuminate\Support\Facades\Route;
use Modules\Invoice\Http\Controllers\InvoiceController;

use Modules\Invoice\Http\Controllers\OrderItemController;
use Modules\Invoice\Http\Controllers\InvoiceAdjustmentController;

Route::middleware(['auth', 'verified', 'license.module:Invoice'])->group(function () {
    Route::resource('invoices', InvoiceController::class);
    Route::resource('order-items', OrderItemController::class);

    Route::post('invoices/{invoice}/return', [InvoiceController::class, 'markReturned'])->name('invoices.return');
    Route::post('invoices/{invoice}/restock-items', [InvoiceController::class, 'restockItems'])->name('invoices.restock-items');
    Route::post('invoices/{invoice}/unreturn', [InvoiceController::class, 'unmarkReturned'])->name('invoices.unreturn');
    Route::get('invoices/{invoice}/pdf', [InvoiceController::class, 'exportPdf'])->name('invoices.pdf');
    Route::post('invoices/{invoice}/receipt', [InvoiceController::class, 'attachReceipt'])->name('invoices.receipt.store');
    Route::delete('invoices/{invoice}/receipt', [InvoiceController::class, 'removeReceipt'])->name('invoices.receipt.destroy');
    Route::post('invoices/{invoice}/service-jobs', [OrderItemController::class, 'attachServiceJobs'])->name('invoices.service-jobs.attach');
    Route::delete('invoices/{invoice}/service-jobs/{serviceJob}', [OrderItemController::class, 'detachServiceJob'])->name('invoices.service-jobs.detach');
    Route::post('order-items/{id}/return', [OrderItemController::class, 'markReturned'])->name('order-items.return');
    Route::post('order-items/{id}/unreturn', [OrderItemController::class, 'unmarkReturned'])->name('order-items.unreturn');
    Route::post('invoices/{invoiceId}/adjustments', [InvoiceAdjustmentController::class, 'store'])->name('invoice-adjustments.store');
    Route::delete('invoices/{invoiceId}/adjustments/{adjustmentId}', [InvoiceAdjustmentController::class, 'destroy'])->name('invoice-adjustments.destroy');
    Route::post('invoices/{invoiceId}/mark-paid', [InvoiceController::class, 'markAsPaid'])->name('invoices.mark-paid');
    Route::post('invoices/{invoiceId}/mark-unpaid', [InvoiceController::class, 'markAsUnpaid'])->name('invoices.mark-unpaid');
    Route::post('invoices/{invoiceId}/terminal-webhook', [InvoiceController::class, 'automaticTerminalWebhook'])->name('invoices.terminal-webhook');
});
