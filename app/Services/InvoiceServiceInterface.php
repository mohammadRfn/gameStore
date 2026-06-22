<?php

namespace App\Services;
use App\Models\Invoice;

interface InvoiceServiceInterface
{
    public function createInvoice($requestId, array $data): Invoice;
    public function getAllInvoices();
    public function calculateTotalAmount(int $invoiceId): float;
    public function addOrderItemsToInvoice(int $invoiceId, array $orderItemsData);
    public function updateInvoice(int $invoiceId, array $data): Invoice;
    public function deleteInvoice(int $invoiceId): bool;
    public function confirmInvoice(int $invoiceId): Invoice;
    public function setNotConfirmed(int $invoiceId): Invoice;
    public function getInvoice(int $invoiceId);
}
