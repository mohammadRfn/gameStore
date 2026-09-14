<?php

namespace Modules\Invoice\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Modules\Invoice\Services\InvoiceService;

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
