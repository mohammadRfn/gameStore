<?php

namespace App\Http\Controllers;

use App\Http\Requests\InvoiceRequest;
use App\Services\InvoiceService;
use Illuminate\Http\Request;
use Inertia\Inertia;

class InvoiceController extends Controller
{
    public function __construct(
        protected InvoiceService $invoiceService
    ) {}

    public function index()
    {
        return Inertia::render('Invoices/Index', [
            'invoices' => $this->invoiceService->getAllInvoices(),
        ]);
    }

    public function show(int $invoiceId)
    {
        return Inertia::render('Invoices/Show', [
            'invoice' => $this->invoiceService->getInvoice($invoiceId),
        ]);
    }

    public function store(InvoiceRequest $request)
    {
        $invoice = $this->invoiceService->createInvoice(
            $request->input('request_id'),
            $request->validated()
        );

        return redirect()->route('invoices.show', $invoice->id);
    }

    public function update(InvoiceRequest $request, int $invoiceId)
    {
        $this->invoiceService->updateInvoice($invoiceId, $request->validated());
        return redirect()->route('invoices.show', $invoiceId);
    }

    public function destroy(int $invoiceId)
    {
        $this->invoiceService->deleteInvoice($invoiceId);
        return redirect()->route('invoices.index');
    }

    public function confirmInvoice(int $invoiceId)
    {
        $this->invoiceService->confirmInvoice($invoiceId);
        return redirect()->back();
    }

    public function setNotConfirmed(int $invoiceId)
    {
        $this->invoiceService->setNotConfirmed($invoiceId);
        return redirect()->back();
    }
}