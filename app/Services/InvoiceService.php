<?php

namespace App\Services;

use App\Models\Invoice;
use App\Models\OrderItem;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

class InvoiceService
{
    protected OrderItemService $orderItemService;
    protected StockMovementService $stockMovementService;

    public function __construct(OrderItemService $orderItemService, StockMovementService $stockMovementService)
    {
        $this->orderItemService = $orderItemService;
        $this->stockMovementService = $stockMovementService;
    }

    public function getAllInvoices(array $filters = []): LengthAwarePaginator
    {
        $query = Invoice::with('customer', 'orderItems')->latest();

        if (!empty($filters['search'])) {
            $query->where('invoice_number', 'like', '%' . $filters['search'] . '%');
        }

        if (isset($filters['payment_status']) && $filters['payment_status'] !== '') {
            $query->where('payment_status', $filters['payment_status']);
        }

        return $query->paginate(20);
    }

    public function getInvoice(int $invoiceId): Invoice
    {
        $invoice = Invoice::with('orderItems', 'customer', 'request', 'adjustments', 'serviceJobs.serviceTypes.serviceType')
            ->findOrFail($invoiceId);

        if ($invoice->orderItems->count() > 0 || $invoice->adjustments->count() > 0 || $invoice->serviceJobs->count() > 0) {
            $invoice->recalculateAmounts();
        }

        return $invoice;
    }

    public function createInvoice(?int $requestId, array $data): Invoice
    {
        $invoice = Invoice::create([
            'invoice_number'  => 'INV-' . uniqid(),
            'request_id'      => $requestId,
            'customer_id'     => $data['customer_id'] ?? null,
            'total_amount'    => 0,
            'is_confirmed'    => null,
            'payment_status'  => Invoice::PAYMENT_UNPAID,
            'stock_deducted'  => false,
        ]);

        if ($requestId) {
            $invoice->request?->markInProgress();
        }

        return $invoice;
    }

    public function addOrderItemsToInvoice(int $invoiceId, array $orderItemsData): Invoice
    {
        foreach ($orderItemsData as $itemData) {
            $this->orderItemService->createOrderItem($itemData, $invoiceId);
        }

        $invoice = Invoice::with('orderItems', 'adjustments')->findOrFail($invoiceId);
        $invoice->recalculateAmounts();

        return $invoice;
    }

    public function updateInvoice(int $invoiceId, array $data): Invoice
    {
        $invoice = Invoice::findOrFail($invoiceId);
        $invoice->update([
            'invoice_number' => $data['invoice_number'] ?? $invoice->invoice_number,
            'request_id'     => array_key_exists('request_id', $data) ? $data['request_id'] : $invoice->request_id,
            'customer_id'    => array_key_exists('customer_id', $data) ? $data['customer_id'] : $invoice->customer_id,
            'total_amount'   => $data['total_amount'] ?? $invoice->total_amount,
            'is_confirmed'   => array_key_exists('is_confirmed', $data) ? $data['is_confirmed'] : $invoice->is_confirmed,
        ]);

        return $invoice;
    }

    public function deleteInvoice(int $invoiceId): bool
    {
        return DB::transaction(function () use ($invoiceId) {
            $invoice = Invoice::with('orderItems')->findOrFail($invoiceId);

            foreach ($invoice->orderItems as $orderItem) {
                // deleteOrderItem خودش چک می‌کند که آیا واقعاً باید انبار برگردد یا نه
                $this->orderItemService->deleteOrderItem($orderItem->id);
            }

            return $invoice->delete();
        });
    }

    /**
     * ثبت دستی پرداخت (نقدی، کارت‌به‌کارت، یا خودپرداز به‌صورت دستی).
     */
    public function markAsPaid(int $invoiceId, array $data): Invoice
    {
        $invoice = Invoice::findOrFail($invoiceId);

        $invoice->payment_status         = Invoice::PAYMENT_PAID;
        $invoice->payment_method         = $data['payment_method'] ?? $invoice->payment_method;
        $invoice->payment_terminal_mode  = $data['payment_terminal_mode'] ?? $invoice->payment_terminal_mode;
        $invoice->paid_at                = now();
        $invoice->save();

        $this->maybeDeductStock($invoice);

        $invoice->request?->markCompleted();

        return $invoice->fresh();
    }
    public function attachReceiptImage(int $invoiceId, $image): Invoice
    {
        $invoice = Invoice::findOrFail($invoiceId);

        if ($invoice->receipt_image_path) {
            \Illuminate\Support\Facades\Storage::disk('public')->delete($invoice->receipt_image_path);
        }

        $invoice->receipt_image_path = $image->store('images/receipts', 'public');
        $invoice->save();

        return $invoice;
    }

    public function removeReceiptImage(int $invoiceId): Invoice
    {
        $invoice = Invoice::findOrFail($invoiceId);

        if ($invoice->receipt_image_path) {
            \Illuminate\Support\Facades\Storage::disk('public')->delete($invoice->receipt_image_path);
            $invoice->receipt_image_path = null;
            $invoice->save();
        }

        return $invoice;
    }
    public function markReturned(int $invoiceId): Invoice
    {
        $invoice = Invoice::findOrFail($invoiceId);

        if (!$invoice->is_returned) {
            $invoice->is_returned     = true;
            $invoice->returned_at     = now();
            $invoice->payment_status  = Invoice::PAYMENT_RETURNED;
            $invoice->save();

            $invoice->request?->markCanceled();
        }

        return $invoice->fresh();
    }
    public function unmarkReturned(int $invoiceId): Invoice
    {
        $invoice = Invoice::with('orderItems')->findOrFail($invoiceId);

        if ($invoice->orderItems->whereNotNull('restocked_at')->isNotEmpty()) {
            throw new \RuntimeException('چون قلم‌ها به انبار برگشته‌اند، لغو مرجوعیت امکان‌پذیر نیست.');
        }

        $invoice->is_returned    = false;
        $invoice->returned_at    = null;
        $invoice->payment_status = Invoice::PAYMENT_PAID;
        $invoice->save();

        return $invoice;
    }

    /**
     * فقط برای قلم‌هایی که تیک خورده‌اند، اگر واقعاً از انبار کسر شده بودند
     * و قبلاً برنگشته باشند، حرکت برگشت به انبار ثبت می‌کند.
     */
    public function restockOrderItems(int $invoiceId, array $orderItemIds): Invoice
    {
        $invoice = Invoice::with('orderItems')->findOrFail($invoiceId);

        if (!$invoice->is_returned) {
            throw new \RuntimeException('ابتدا باید فاکتور مرجوع شود.');
        }

        foreach ($invoice->orderItems as $orderItem) {
            if (!in_array($orderItem->id, $orderItemIds, true)) {
                continue;
            }
            if ($orderItem->restocked_at) {
                continue;
            }
            if (!$orderItem->deduct_from_stock || !$invoice->stock_deducted) {
                continue;
            }

            $this->stockMovementService->recordReturnMovementForOrderItem($orderItem);

            $orderItem->restocked_at = now();
            $orderItem->save();
        }

        return $invoice->fresh(['orderItems']);
    }

    /**
     * جایگاه آینده: وقتی دستگاه خودپرداز به‌صورت خودکار (webhook) اعلام کند
     * که تراکنش موفق بوده است. فعلاً پیاده‌سازی نشده — فقط اسکلت.
     */
    public function handleAutomaticTerminalCallback(int $invoiceId, array $payload): Invoice
    {
        // TODO: اعتبارسنجی امضا/توکن دستگاه خودپرداز قبل از اعتماد به این callback
        return $this->markAsPaid($invoiceId, [
            'payment_method'        => Invoice::PAYMENT_METHOD_POS_TERMINAL,
            'payment_terminal_mode' => Invoice::TERMINAL_MODE_AUTOMATIC,
        ]);
    }

    /**
     * فقط وقتی فاکتور هم‌زمان «تأیید شده» و «پرداخت‌شده» باشد، و قبلاً کسر نشده باشد،
     * موجودی انبار قلم‌هایی که deduct_from_stock دارند کم می‌شود.
     * این متد idempotent است — دوباره صدا زدنش بعد از کسر، هیچ کاری نمی‌کند.
     */
    protected function maybeDeductStock(Invoice $invoice): void
    {
        if ($invoice->stock_deducted) {
            return;
        }

        if (!$invoice->isPaid()) {
            return;
        }

        DB::transaction(function () use ($invoice) {
            $invoice->loadMissing('orderItems');

            foreach ($invoice->orderItems as $orderItem) {
                if ($orderItem->deduct_from_stock) {
                    $this->stockMovementService->recordSaleMovementForOrderItem($orderItem);
                }
            }

            $invoice->stock_deducted = true;
            $invoice->save();
        });
    }

    public function calculateTotalAmount(int $invoiceId): float
    {
        return OrderItem::where('invoice_id', $invoiceId)->sum('total_price');
    }


    public function addAdjustment(int $invoiceId, array $data): Invoice
    {
        $invoice = Invoice::with('orderItems', 'adjustments')->findOrFail($invoiceId);
        if ($invoice->isLocked()) {
            throw new \RuntimeException('فاکتور پرداخت‌شده یا مرجوع‌شده را نمی‌توان ویرایش کرد.');
        }

        $categoryKey = $data['category_key'] ?? 'other';

        $countsAsRevenue = $data['counts_as_revenue']
            ?? \App\Models\AdjustmentCategory::where('key', $categoryKey)->value('default_counts_as_revenue')
            ?? true;

        $invoice->adjustments()->create([
            'title'             => $data['title'],
            'type'              => $data['type'],
            'direction'         => $data['direction'],
            'value'             => $data['value'],
            'category_key'      => $categoryKey,
            'counts_as_revenue' => $countsAsRevenue,
        ]);

        $invoice->recalculateAmounts();

        return $invoice->fresh(['orderItems', 'adjustments']);
    }

    public function removeAdjustment(int $invoiceId, int $adjustmentId): Invoice
    {
        $invoice = Invoice::with('orderItems', 'adjustments')->findOrFail($invoiceId);
        if ($invoice->isLocked()) {
            throw new \RuntimeException('فاکتور پرداخت‌شده یا مرجوع‌شده را نمی‌توان ویرایش کرد.');
        }

        $invoice->adjustments()->where('id', $adjustmentId)->delete();

        $invoice->recalculateAmounts();

        return $invoice->fresh(['orderItems', 'adjustments']);
    }
}
