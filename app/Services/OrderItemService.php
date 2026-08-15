<?php

namespace App\Services;

use App\Models\Invoice;
use App\Models\Item;
use App\Models\OrderItem;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

class OrderItemService
{
    protected StockMovementService $stockMovementService;

    public function __construct(StockMovementService $stockMovementService)
    {
        $this->stockMovementService = $stockMovementService;
    }

    public function getAllOrderItems(): Collection
    {
        return OrderItem::with('item', 'category')->get();
    }

    public function findOrderItem(int $id): OrderItem
    {
        return OrderItem::with('item', 'category')->findOrFail($id);
    }

    /**
     * ساخت یک قلم فاکتور.
     * از این پس، ساخت قلم هیچ تأثیری روی موجودی انبار ندارد.
     * کسر انبار فقط زمانی اتفاق می‌افتد که فاکتور هم‌زمان «تأیید شده» و «پرداخت‌شده» باشد
     * (به InvoiceService::maybeDeductStock نگاه کن).
     */
    public function createOrderItem(array $data, int $invoiceId): OrderItem
    {
        if (empty($data['item_id']) || empty($data['quantity'])) {
            throw new \Exception('اطلاعات قلم ناقص است (محصول و تعداد الزامی‌اند).');
        }

        return DB::transaction(function () use ($data, $invoiceId) {
            $invoiceCheck = Invoice::findOrFail($invoiceId);
            if ($invoiceCheck->isLocked()) {
                throw new \RuntimeException('فاکتور پرداخت‌شده یا مرجوع‌شده را نمی‌توان ویرایش کرد.');
            }

            $item = Item::findOrFail($data['item_id']);
            $totalPrice = $data['quantity'] * $item->sale_price;

            $orderItem = OrderItem::create([
                'invoice_id'        => $invoiceId,
                'item_id'           => $item->id,
                'category_id'       => $item->category_id,
                'product_name'      => $item->name,
                'quantity'          => $data['quantity'],
                'price'             => $item->sale_price,
                'total_price'       => $totalPrice,
                'cost_price'        => $item->purchase_price,
                'deduct_from_stock' => $item->tracks_stock
                    ? (array_key_exists('deduct_from_stock', $data) ? (bool) $data['deduct_from_stock'] : true)
                    : false,
            ]);

            if (isset($data['image'])) {
                $orderItem->image_path = $data['image']->store('images/order_items', 'public');
                $orderItem->save();
            }

       
            $invoice = Invoice::find($invoiceId);
            if ($invoice && $invoice->stock_deducted && $orderItem->deduct_from_stock) {
                $this->stockMovementService->recordSaleMovementForOrderItem($orderItem);
            }

            $this->updateInvoiceTotalAmount($invoiceId);

            return $orderItem;
        });
    }

    public function updateOrderItem(int $id, array $data): OrderItem
    {
        return DB::transaction(function () use ($id, $data) {
            $orderItem = OrderItem::with('invoice')->findOrFail($id);
            if ($orderItem->invoice && $orderItem->invoice->isLocked()) {
                throw new \RuntimeException('فاکتور پرداخت‌شده یا مرجوع‌شده را نمی‌توان ویرایش کرد.');
            }
            $oldQuantity = $orderItem->quantity;
            $wasStockDeducted = $orderItem->invoice && $orderItem->invoice->stock_deducted;

            $orderItem->update($data);
            $orderItem->total_price = $orderItem->quantity * $orderItem->price;
            $orderItem->save();

            if (isset($data['image'])) {
                $orderItem->image_path = $data['image']->store('images/order_items', 'public');
                $orderItem->save();
            }

            // فقط اگر واقعاً قبلاً از انبار کسر شده بود، حرکت انبار را تنظیم کن
            if ($wasStockDeducted && (int) $orderItem->quantity !== (int) $oldQuantity) {
                $this->stockMovementService->adjustSaleMovementForOrderItem($orderItem);
            }

            $this->updateInvoiceTotalAmount($orderItem->invoice_id);

            return $orderItem;
        });
    }

    public function deleteOrderItem(int $id): void
    {
        DB::transaction(function () use ($id) {
            $orderItem = OrderItem::with('invoice')->findOrFail($id);
            if ($orderItem->invoice && $orderItem->invoice->isLocked()) {
                throw new \RuntimeException('فاکتور پرداخت‌شده یا مرجوع‌شده را نمی‌توان ویرایش کرد.');
            }
            $wasStockDeducted = $orderItem->invoice && $orderItem->invoice->stock_deducted;

            // فقط اگر واقعاً قبلاً از انبار کسر شده بود، موجودی را برگردان
            if ($wasStockDeducted) {
                $this->stockMovementService->reverseSaleMovementForOrderItem($orderItem);
            }

            $orderItem->delete();
        });
    }
    /**
     * یک قلم فاکتور را «مرجوع‌شده» علامت می‌زند.
     * اگر $restock=true و قبلاً واقعاً از انبار کسر شده بود، موجودی برمی‌گردد.
     */
    public function returnOrderItem(int $id, bool $restock): OrderItem
    {
        return DB::transaction(function () use ($id, $restock) {
            $orderItem = OrderItem::with('invoice')->findOrFail($id);

            if ($orderItem->is_returned) {
                return $orderItem;
            }

            $wasStockDeducted = $orderItem->invoice
                && $orderItem->invoice->stock_deducted
                && $orderItem->deduct_from_stock;

            $orderItem->is_returned       = true;
            $orderItem->restock_on_return = $restock;
            $orderItem->returned_at       = now();
            $orderItem->save();

            if ($restock && $wasStockDeducted) {
                $this->stockMovementService->recordReturnMovementForOrderItem($orderItem);
            }

            $this->updateInvoiceTotalAmount($orderItem->invoice_id);

            return $orderItem;
        });
    }

    /**
     * برگرداندن اشتباهی — مرجوعی را لغو می‌کند و اگر حرکت انبار ثبت شده بود حذفش می‌کند.
     */
    public function unreturnOrderItem(int $id): OrderItem
    {
        return DB::transaction(function () use ($id) {
            $orderItem = OrderItem::findOrFail($id);

            if (!$orderItem->is_returned) {
                return $orderItem;
            }

            if ($orderItem->restock_on_return) {
                \App\Models\StockMovement::where('order_item_id', $orderItem->id)
                    ->where('reason', 'return')
                    ->delete();
            }

            $orderItem->is_returned       = false;
            $orderItem->restock_on_return = false;
            $orderItem->returned_at       = null;
            $orderItem->save();

            $this->updateInvoiceTotalAmount($orderItem->invoice_id);

            return $orderItem;
        });
    }
    public function updateInvoiceTotalAmount(int $invoiceId): void
    {
        $invoice = Invoice::with('orderItems', 'adjustments')->findOrFail($invoiceId);
        $invoice->recalculateAmounts();
    }
    public function attachServiceJobsToInvoice(int $invoiceId, array $serviceJobIds): Invoice
    {
        $invoice = Invoice::findOrFail($invoiceId);
        if ($invoice->isLocked()) {
            throw new \RuntimeException('فاکتور پرداخت‌شده یا مرجوع‌شده را نمی‌توان ویرایش کرد.');
        }

        \App\Models\ServiceJob::where('customer_id', $invoice->customer_id)
            ->whereNull('invoice_id')
            ->where('status', \App\Models\ServiceJob::STATUS_DELIVERED)
            ->whereIn('id', $serviceJobIds)
            ->update(['invoice_id' => $invoice->id]);

        $invoice->recalculateAmounts();

        return $invoice->fresh(['orderItems', 'adjustments', 'serviceJobs']);
    }

    public function detachServiceJobFromInvoice(int $invoiceId, int $serviceJobId): Invoice
    {
        $invoice = Invoice::findOrFail($invoiceId);
        if ($invoice->isLocked()) {
            throw new \RuntimeException('فاکتور پرداخت‌شده یا مرجوع‌شده را نمی‌توان ویرایش کرد.');
        }

        \App\Models\ServiceJob::where('id', $serviceJobId)
            ->where('invoice_id', $invoice->id)
            ->update(['invoice_id' => null]);

        $invoice->recalculateAmounts();

        return $invoice->fresh(['orderItems', 'adjustments', 'serviceJobs']);
    }
}
