<?php

namespace App\Http\Controllers;

use App\Http\Requests\InvoiceRequest;
use App\Services\InvoiceService;
use Inertia\Inertia;
use Illuminate\Http\Request as HttpRequest;
use App\Models\Request as ServiceRequest;

class InvoiceController extends Controller
{
    public function __construct(
        protected InvoiceService $invoiceService,
        protected \App\Services\InvoicePdfService $invoicePdfService
    ) {}

    public function exportPdf(int $invoiceId)
    {
        $invoice = $this->invoiceService->getInvoice($invoiceId);
        $pdf = $this->invoicePdfService->generate($invoice);

        return response($pdf, 200, [
            'Content-Type'        => 'application/pdf',
            'Content-Disposition' => 'inline; filename="invoice-' . $invoice->invoice_number . '.pdf"',
        ]);
    }
    public function index(HttpRequest $request)
    {
        $filters = $request->only(['search', 'status']);
        $filters['payment_status'] = $filters['status'] ?? null;

        return Inertia::render('Invoices/Index', [
            'invoices' => $this->invoiceService->getAllInvoices($filters),
            'filters'  => $request->only(['search', 'status']),
        ]);
    }

    public function show(int $invoiceId)
    {
        return Inertia::render('Invoices/Show', [
            'invoice'               => $this->invoiceService->getInvoice($invoiceId),
            'adjustment_categories' => \App\Models\AdjustmentCategory::where('is_active', true)
                ->orderBy('sort_order')
                ->get(['key', 'label', 'default_counts_as_revenue']),
        ]);
    }
    public function create(HttpRequest $httpRequest)
    {
        $requestId = $httpRequest->query('request_id');
        $customerId = null;

        if ($requestId) {
            $customerId = ServiceRequest::where('id', $requestId)->value('customer_id');
        }

        return Inertia::render('Invoices/Create', [
            'customers'           => \App\Models\Customer::select('id', 'name')->orderBy('name')->get(),
            'default_customer_id' => $customerId,
        ]);
    }
    public function edit(int $invoiceId)
    {
        return Inertia::render('Invoices/Edit', [
            'invoice'   => $this->invoiceService->getInvoice($invoiceId),
            'customers' => \App\Models\Customer::select('id', 'name')->orderBy('name')->get(),
            'requests'  => ServiceRequest::select('id', 'customer_id')
                ->with('customer:id,name')->latest()->get(),
        ]);
    }

    public function store(InvoiceRequest $request)
    {
        $invoice = $this->invoiceService->createInvoice(
            $request->input('request_id') ?: null,
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

    public function markAsPaid(HttpRequest $request, int $invoiceId)
    {
        $data = $request->validate([
            'payment_method'        => 'required|in:cash,card_to_card,pos_terminal',
            'payment_terminal_mode' => 'nullable|in:manual,automatic',
        ]);

        $this->invoiceService->markAsPaid($invoiceId, $data);
        return redirect()->back();
    }
    public function attachReceipt(HttpRequest $request, int $invoiceId)
    {
        $data = $request->validate([
            'receipt_image' => 'required|image|max:5120',
        ]);

        $this->invoiceService->attachReceiptImage($invoiceId, $data['receipt_image']);

        return redirect()->back();
    }

    public function removeReceipt(int $invoiceId)
    {
        $this->invoiceService->removeReceiptImage($invoiceId);
        return redirect()->back();
    }

    public function markReturned(int $invoiceId)
    {
        $this->invoiceService->markReturned($invoiceId);
        return redirect()->back();
    }
    public function unmarkReturned(int $invoiceId)
    {
        $this->invoiceService->unmarkReturned($invoiceId);
        return redirect()->back();
    }
    public function restockItems(HttpRequest $request, int $invoiceId)
    {
        $data = $request->validate([
            'order_item_ids'   => 'required|array|min:1',
            'order_item_ids.*' => 'integer|exists:order_items,id',
        ]);

        $this->invoiceService->restockOrderItems($invoiceId, $data['order_item_ids']);

        return redirect()->back();
    }
    /**
     * اسکلت خالی برای اتصال آینده به دستگاه خودپرداز.
     * فعلاً بدون احراز هویت/اعتبارسنجی — نباید در محیط واقعی بدون تکمیل امنیتی فعال شود.
     */
    public function automaticTerminalWebhook(HttpRequest $request, int $invoiceId)
    {
        // TODO: تکمیل اعتبارسنجی درخواست دستگاه خودپرداز
        $this->invoiceService->handleAutomaticTerminalCallback($invoiceId, $request->all());
        return response()->json(['status' => 'ok']);
    }
}
