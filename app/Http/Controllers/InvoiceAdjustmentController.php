<?php

namespace App\Http\Controllers;

use App\Services\InvoiceService;
use Illuminate\Http\Request;

class InvoiceAdjustmentController extends Controller
{
    public function __construct(
        protected InvoiceService $invoiceService
    ) {}

    public function store(Request $request, int $invoiceId)
    {
        $data = $request->validate([
            'title'             => 'required|string|max:255',
            'type'              => 'required|in:percentage,fixed',
            'direction'         => 'required|in:increase,decrease',
            'value'             => 'required|numeric|min:0.01',
            'category_key'      => 'nullable|string|exists:adjustment_categories,key',
            'counts_as_revenue' => 'nullable|boolean',
        ]);

        $this->invoiceService->addAdjustment($invoiceId, $data);

        return redirect()->route('invoices.show', $invoiceId);
    }

    public function destroy(int $invoiceId, int $adjustmentId)
    {
        $this->invoiceService->removeAdjustment($invoiceId, $adjustmentId);

        return redirect()->route('invoices.show', $invoiceId);
    }
}
