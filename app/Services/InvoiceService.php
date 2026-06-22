<?php

namespace App\Services;

use App\Models\Invoice;
use App\Models\OrderItem;
use Illuminate\Database\Eloquent\Collection;

class InvoiceService
{
    protected OrderItemService $orderItemService;

    public function __construct(OrderItemService $orderItemService)
    {
        $this->orderItemService = $orderItemService;
    }

    public function getAllInvoices(): Collection
    {
        return Invoice::with('customer', 'orderItems')->get();
    }

    public function getInvoice(int $invoiceId): Invoice
    {
        $invoice = Invoice::with('orderItems')->findOrFail($invoiceId);

        if ($invoice->orderItems->count() > 0) {
            $invoice->total_amount = $this->calculateTotalAmount($invoiceId);
            $invoice->save();
        }

        return $invoice;
    }

    public function createInvoice(int $requestId, array $data): Invoice
    {
        return Invoice::create([
            'invoice_number' => 'INV-' . uniqid(),
            'request_id'     => $requestId,
            'customer_id'    => $data['customer_id'] ?? null,
            'total_amount'   => 0,
            'is_confirmed'   => null,
        ]);
    }

    public function addOrderItemsToInvoice(int $invoiceId, array $orderItemsData): Invoice
    {
        foreach ($orderItemsData as $itemData) {
            $this->orderItemService->createOrderItem($itemData, $invoiceId);
        }

        $invoice = Invoice::findOrFail($invoiceId);
        $invoice->total_amount = $this->calculateTotalAmount($invoiceId);
        $invoice->save();

        return $invoice;
    }

    public function updateInvoice(int $invoiceId, array $data): Invoice
    {
        $invoice = Invoice::findOrFail($invoiceId);
        $invoice->update([
            'invoice_number' => $data['invoice_number'] ?? $invoice->invoice_number,
            'customer_id'    => $data['customer_id']    ?? $invoice->customer_id,
            'total_amount'   => $data['total_amount']   ?? $invoice->total_amount,
            'is_confirmed'   => $data['is_confirmed']   ?? $invoice->is_confirmed,
        ]);

        return $invoice;
    }

    public function deleteInvoice(int $invoiceId): bool
    {
        $invoice = Invoice::findOrFail($invoiceId);
        return $invoice->delete();
    }

    public function confirmInvoice(int $invoiceId): Invoice
    {
        $invoice = Invoice::findOrFail($invoiceId);
        $invoice->is_confirmed = Invoice::STATUS_CONFIRMED;
        $invoice->save();
        $this->updateRequestStatus($invoice, 'completed');
        return $invoice;
    }

    public function setNotConfirmed(int $invoiceId): Invoice
    {
        $invoice = Invoice::findOrFail($invoiceId);
        $invoice->is_confirmed = Invoice::STATUS_NOT_CONFIRMED;
        $invoice->save();
        $this->updateRequestStatus($invoice, 'canceled');
        return $invoice;
    }

    public function calculateTotalAmount(int $invoiceId): float
    {
        return OrderItem::where('invoice_id', $invoiceId)->sum('total_price');
    }

    protected function updateRequestStatus(Invoice $invoice, string $status): void
    {
        $request = $invoice->request;
        if ($request) {
            $request->update(['status' => $status]);
        }
    }
}
