<?php

namespace App\Services;

use App\Models\Invoice;
use App\Models\Item;
use App\Models\ItemSerialNumber;
use App\Models\OrderItem;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

class OrderItemService
{
    protected StockMovementService $stockMovementService;
    protected WarrantyService $warrantyService;

    public function __construct(StockMovementService $stockMovementService, WarrantyService $warrantyService)
    {
        $this->stockMovementService = $stockMovementService;
        $this->warrantyService = $warrantyService;
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

            $deductFromStock = $item->tracks_stock
                ? (array_key_exists('deduct_from_stock', $data) ? (bool) $data['deduct_from_stock'] : true)
                : false;

            $reservedSerials = new Collection();
            if ($item->has_serial_number && $deductFromStock) {
                $quantityNeeded = (int) $data['quantity'];
                $serialIds = $data['serial_number_ids'] ?? [];

                if (count($serialIds) > $quantityNeeded) {
                    throw new \RuntimeException(
                        "تعداد شماره سریال‌های انتخاب‌شده برای «{$item->name}» نمی‌تواند از تعداد قلم بیشتر باشد."
                    );
                }

                $chosenSerials = new Collection();
                if (!empty($serialIds)) {
                    $chosenSerials = ItemSerialNumber::where('item_id', $item->id)
                        ->where('status', ItemSerialNumber::STATUS_IN_STOCK)
                        ->whereIn('id', $serialIds)
                        ->lockForUpdate()
                        ->get();

                    if ($chosenSerials->count() !== count($serialIds)) {
                        throw new \RuntimeException(
                            "برخی از شماره سریال‌های انتخاب‌شده برای «{$item->name}» دیگر در انبار موجود نیستند."
                        );
                    }
                }

                // باقی تعداد (بدون انتخاب صریح) خودکار از انبار همین کالا تأمین می‌شود؛
                // اولویت با جایگاه‌های بدون شماره سریال است تا سریال‌های نام‌دار برای انتخاب دستی باقی بمانند.
                $remaining = $quantityNeeded - $chosenSerials->count();
                $autoFilled = new Collection();
                if ($remaining > 0) {
                    $autoFilled = ItemSerialNumber::where('item_id', $item->id)
                        ->where('status', ItemSerialNumber::STATUS_IN_STOCK)
                        ->whereNotIn('id', $chosenSerials->pluck('id'))
                        ->orderByRaw('serial_number IS NOT NULL')
                        ->lockForUpdate()
                        ->limit($remaining)
                        ->get();

                    if ($autoFilled->count() !== $remaining) {
                        throw new \RuntimeException("موجودی انبار برای «{$item->name}» کافی نیست.");
                    }
                }

                $reservedSerials = $chosenSerials->concat($autoFilled);
            }

            $orderItem = OrderItem::create([
                'invoice_id'        => $invoiceId,
                'item_id'           => $item->id,
                'category_id'       => $item->category_id,
                'product_name'      => $item->name,
                'quantity'          => $data['quantity'],
                'price'             => $item->sale_price,
                'total_price'       => $totalPrice,
                'cost_price'        => $item->purchase_price,
                'deduct_from_stock' => $deductFromStock,
            ]);

            if ($reservedSerials->isNotEmpty()) {
                ItemSerialNumber::whereIn('id', $reservedSerials->pluck('id'))->update([
                    'status'        => ItemSerialNumber::STATUS_RESERVED,
                    'order_item_id' => $orderItem->id,
                ]);
            }

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
            $orderItem = OrderItem::with('invoice', 'item')->findOrFail($id);
            if ($orderItem->invoice && $orderItem->invoice->isLocked()) {
                throw new \RuntimeException('فاکتور پرداخت‌شده یا مرجوع‌شده را نمی‌توان ویرایش کرد.');
            }
            $oldQuantity = $orderItem->quantity;
            $wasStockDeducted = $orderItem->invoice && $orderItem->invoice->stock_deducted;

            // برای کالاهای دارای شماره سریال، تغییر تعداد بدون انتخاب مجدد سریال معنا ندارد
            if ($orderItem->item && $orderItem->item->has_serial_number
                && array_key_exists('quantity', $data)
                && (int) $data['quantity'] !== (int) $oldQuantity) {
                throw new \RuntimeException(
                    'برای کالاهای دارای شماره سریال، تعداد قابل ویرایش نیست؛ این قلم را حذف و دوباره با شماره سریال‌های موردنظر اضافه کنید.'
                );
            }

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

            // اگر هنوز کسر نشده بود ولی سریالی برای این قلم رزرو شده بود (فاکتور پرداخت‌نشده)، آزادش کن
            ItemSerialNumber::where('order_item_id', $orderItem->id)
                ->where('status', ItemSerialNumber::STATUS_RESERVED)
                ->update([
                    'status'        => ItemSerialNumber::STATUS_IN_STOCK,
                    'order_item_id' => null,
                ]);

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

            // مهم: قبل از آزادسازی سریال‌ها صدا بزن، وگرنه ارتباط سریال↔order_item از دست می‌رود
            $this->warrantyService->resetWarrantyForOrderItem($orderItem);

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

                // سریال‌هایی که با مرجوعی به انبار برگشته بودند، دوباره «فروخته‌شده» علامت بخورند
                ItemSerialNumber::where('order_item_id', $orderItem->id)
                    ->where('status', ItemSerialNumber::STATUS_IN_STOCK)
                    ->update(['status' => ItemSerialNumber::STATUS_SOLD]);
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
