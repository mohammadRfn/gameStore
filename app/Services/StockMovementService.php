<?php

namespace App\Services;

use App\Models\Invoice;
use App\Models\OrderItem;
use App\Models\ServiceJob;
use App\Models\ServiceJobItem;
use App\Models\StockMovement;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class StockMovementService
{
    public function getCurrentStock(int $itemId): int
    {
        $in = StockMovement::where('item_id', $itemId)
            ->whereIn('movement_type', [
                StockMovement::TYPE_IN,
                StockMovement::TYPE_ADJUST_IN,
            ])
            ->sum('quantity');

        $out = StockMovement::where('item_id', $itemId)
            ->whereIn('movement_type', [
                StockMovement::TYPE_OUT,
                StockMovement::TYPE_ADJUST_OUT,
            ])
            ->sum('quantity');

        return $in - $out;
    }

    public function createManualMovement(array $data): StockMovement
    {
        $this->validateMovementType($data['movement_type'] ?? '');

        if (($data['quantity'] ?? 0) <= 0) {
            throw new RuntimeException('Quantity must be positive.');
        }

        return StockMovement::create([
            'item_id'       => $data['item_id'],
            'movement_type' => $data['movement_type'],
            'quantity'      => $data['quantity'],
            'unit_cost'     => $data['unit_cost'] ?? null,
            'reason'        => $data['reason'] ?? 'manual_adjustment',
            'note'          => $data['note'] ?? null,
        ]);
    }

    /**
     * ثبت حرکت خروجی انبار برای یک قلم مشخص از فاکتور (نه کل فاکتور).
     * این متد از OrderItemService::createOrderItem صدا زده می‌شود، دقیقاً همان لحظه‌ای
     * که قلم به فاکتور اضافه می‌شود — نه با تأخیر و نه به‌صورت دستی.
     *
     * اگر موجودی کافی نباشد RuntimeException پرتاب می‌کند تا تراکنش بالادستی rollback شود.
     */
    public function recordSaleMovementForOrderItem(OrderItem $orderItem): void
    {
        if (!$orderItem->item_id || !$orderItem->quantity) {
            return;
        }

        $currentStock = $this->getCurrentStock($orderItem->item_id);
        if ($currentStock < $orderItem->quantity) {
            throw new RuntimeException(
                "موجودی انبار برای «{$orderItem->product_name}» کافی نیست (موجودی فعلی: {$currentStock})."
            );
        }

        StockMovement::create([
            'item_id'       => $orderItem->item_id,
            'order_item_id' => $orderItem->id,
            'invoice_id'    => $orderItem->invoice_id,
            'movement_type' => StockMovement::TYPE_OUT,
            'quantity'      => $orderItem->quantity,
            'unit_cost'     => null,
            'reason'        => 'sale',
            'note'          => "Invoice item #{$orderItem->id}",
        ]);
    }

    /**
     * حذف حرکت خروجی مربوط به یک قلم فاکتور (وقتی قلم حذف می‌شود، موجودی برمی‌گردد).
     * نیازمند ستون order_item_id روی جدول stock_movements است (به مایگریشن پیوست‌شده نگاه کن).
     */
    public function reverseSaleMovementForOrderItem(OrderItem $orderItem): void
    {
        StockMovement::where('order_item_id', $orderItem->id)
            ->where('movement_type', StockMovement::TYPE_OUT)
            ->delete();
    }
    /**
     * وقتی یک قلم فاکتور «مرجوع» می‌شود و کاربر گفته به انبار برگردد،
     * یک حرکت ورودی (TYPE_IN) با reason=return ثبت می‌کند.
     */
    public function recordReturnMovementForOrderItem(OrderItem $orderItem): void
    {
        if (!$orderItem->item_id || !$orderItem->quantity) {
            return;
        }

        StockMovement::create([
            'item_id'       => $orderItem->item_id,
            'order_item_id' => $orderItem->id,
            'invoice_id'    => $orderItem->invoice_id,
            'movement_type' => StockMovement::TYPE_IN,
            'quantity'      => $orderItem->quantity,
            'unit_cost'     => null,
            'reason'        => 'return',
            'note'          => "Return of invoice item #{$orderItem->id}",
        ]);
    }
    /**
     * وقتی تعداد یک قلم فاکتور ویرایش می‌شود، حرکت انبار قبلی را حذف و حرکت جدید را با تعداد
     * به‌روزشده ثبت می‌کند (و در صورت کمبود موجودی دوباره خطا می‌دهد).
     */
    public function adjustSaleMovementForOrderItem(OrderItem $orderItem): void
    {
        $this->reverseSaleMovementForOrderItem($orderItem);
        $this->recordSaleMovementForOrderItem($orderItem);
    }

    /**
     * @deprecated از این پس هر قلم به‌صورت جداگانه در لحظه‌ی افزودن، حرکت انبار خودش را می‌سازد
     * (recordSaleMovementForOrderItem). این متد فقط برای همگام‌سازی دستی/عقب‌مانده نگه داشته شده.
     */
    public function recordSaleMovementsForInvoice(Invoice $invoice): void
    {
        $invoice->loadMissing('orderItems');

        if ($invoice->orderItems->isEmpty()) {
            return;
        }

        DB::transaction(function () use ($invoice) {
            foreach ($invoice->orderItems as $orderItem) {
                $alreadyRecorded = StockMovement::where('order_item_id', $orderItem->id)->exists();
                if ($alreadyRecorded) {
                    continue;
                }
                $this->recordSaleMovementForOrderItem($orderItem);
            }
        });
    }

    public function recordConsumptionForServiceJob(ServiceJob $serviceJob): void
    {
        $serviceJob->loadMissing('items', 'invoice');

        if ($serviceJob->items->isEmpty()) {
            return;
        }

        DB::transaction(function () use ($serviceJob) {
            foreach ($serviceJob->items as $jobItem) {
                if (!$jobItem->item_id || !$jobItem->quantity) {
                    continue;
                }

                $currentStock = $this->getCurrentStock($jobItem->item_id);
                if ($currentStock < $jobItem->quantity) {
                    throw new RuntimeException("Not enough stock for item ID {$jobItem->item_id}.");
                }

                StockMovement::create([
                    'item_id'        => $jobItem->item_id,
                    'service_job_id' => $serviceJob->id,
                    'invoice_id'     => $serviceJob->invoice_id,
                    'movement_type'  => StockMovement::TYPE_OUT,
                    'quantity'       => $jobItem->quantity,
                    'unit_cost'      => null,
                    'reason'         => 'service_consumption',
                    'note'           => "Service job #{$serviceJob->id}",
                ]);
            }
        });
    }

    protected function validateMovementType(string $movementType): void
    {
        $allowed = [
            StockMovement::TYPE_IN,
            StockMovement::TYPE_OUT,
            StockMovement::TYPE_ADJUST_IN,
            StockMovement::TYPE_ADJUST_OUT,
        ];

        if (!in_array($movementType, $allowed, true)) {
            throw new RuntimeException("Invalid stock movement type: {$movementType}");
        }
    }
}
