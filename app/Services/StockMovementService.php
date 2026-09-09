<?php

namespace App\Services;

use App\Models\Invoice;
use App\Models\Item;
use App\Models\ItemSerialNumber;
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

    /**
     * لیست شماره سریال‌های «موجود در انبار» (رزرو/فروخته نشده) برای یک کالا.
     * برای پر کردن سلکت انتخاب سریال هنگام افزودن قلم به فاکتور یا حرکت انبار دستی استفاده می‌شود.
     */
    public function getAvailableSerialNumbers(int $itemId): \Illuminate\Support\Collection
    {
        return ItemSerialNumber::where('item_id', $itemId)
            ->where('status', ItemSerialNumber::STATUS_IN_STOCK)
            ->whereNotNull('serial_number')
            ->orderBy('serial_number')
            ->get(['id', 'serial_number']);
    }

    /**
     * تعداد واحدهایی از این کالا که در انبار موجودند ولی هنوز شماره سریال ندارند.
     * برای نمایش نوتیف روی کارت محصول استفاده می‌شود.
     */
    public function getMissingSerialCount(int $itemId): int
    {
        return ItemSerialNumber::where('item_id', $itemId)
            ->where('status', ItemSerialNumber::STATUS_IN_STOCK)
            ->whereNull('serial_number')
            ->count();
    }

    /**
     * لیست جایگاه‌های «بدون شماره سریال» یک کالا، برای نمایش در فرم تکمیل سریال.
     */
    public function getUnassignedSerialSlots(int $itemId): \Illuminate\Support\Collection
    {
        return ItemSerialNumber::where('item_id', $itemId)
            ->where('status', ItemSerialNumber::STATUS_IN_STOCK)
            ->whereNull('serial_number')
            ->orderBy('id')
            ->get(['id']);
    }

    /**
     * ثبت شماره سریال برای یک جایگاه که قبلاً بدون سریال وارد شده بود.
     */
    public function assignSerialNumber(int $itemSerialNumberId, string $serialNumber): ItemSerialNumber
    {
        return DB::transaction(function () use ($itemSerialNumberId, $serialNumber) {
            $serial = ItemSerialNumber::where('id', $itemSerialNumberId)
                ->whereNull('serial_number')
                ->lockForUpdate()
                ->first();

            if (!$serial) {
                throw new RuntimeException('این ردیف قابل ویرایش نیست (شماره سریال قبلاً ثبت شده یا وجود ندارد).');
            }

            $duplicate = ItemSerialNumber::where('item_id', $serial->item_id)
                ->where('serial_number', $serialNumber)
                ->exists();
            if ($duplicate) {
                throw new RuntimeException('این شماره سریال قبلاً برای همین کالا ثبت شده است.');
            }

            $serial->serial_number = $serialNumber;
            $serial->save();

            return $serial;
        });
    }

    public function createManualMovement(array $data): StockMovement
    {
        $this->validateMovementType($data['movement_type'] ?? '');

        if (($data['quantity'] ?? 0) <= 0) {
            throw new RuntimeException('Quantity must be positive.');
        }

        $item = Item::findOrFail($data['item_id']);

        if ($item->has_serial_number) {
            return $this->createManualMovementWithSerials($item, $data);
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
     * برای اقلامی که شماره سریال دارند: در حرکت ورودی، به تعداد quantity باید دقیقاً
     * همان تعداد شماره سریال (serial_numbers) ارسال شود و برای هرکدام یک رکورد
     * ItemSerialNumber با وضعیت in_stock ساخته می‌شود. در حرکت خروجی/تعدیل خروجی،
     * باید شناسه‌ی سریال‌های موجود (serial_number_ids) به همان تعداد quantity ارسال شود.
     */
    protected function createManualMovementWithSerials(Item $item, array $data): StockMovement
    {
        $quantity  = (int) $data['quantity'];
        $isInbound = in_array($data['movement_type'], [
            StockMovement::TYPE_IN,
            StockMovement::TYPE_ADJUST_IN,
        ], true);

        return DB::transaction(function () use ($item, $data, $quantity, $isInbound) {
            $movement = StockMovement::create([
                'item_id'       => $item->id,
                'movement_type' => $data['movement_type'],
                'quantity'      => $quantity,
                'unit_cost'     => $data['unit_cost'] ?? null,
                'reason'        => $data['reason'] ?? 'manual_adjustment',
                'note'          => $data['note'] ?? null,
            ]);

            if ($isInbound) {
                // شماره سریال اختیاری است؛ هر تعدادی کمتر یا مساوی quantity قابل قبول است
                $serialNumbers = array_values(array_filter(
                    $data['serial_numbers'] ?? [],
                    fn ($s) => trim((string) $s) !== ''
                ));

                if (count($serialNumbers) > $quantity) {
                    throw new RuntimeException('تعداد شماره سریال‌های واردشده نمی‌تواند از تعداد کل بیشتر باشد.');
                }

                $duplicates = ItemSerialNumber::where('item_id', $item->id)
                    ->whereIn('serial_number', $serialNumbers)
                    ->pluck('serial_number');
                if ($duplicates->isNotEmpty()) {
                    throw new RuntimeException('این شماره سریال(ها) قبلاً برای همین کالا ثبت شده: ' . $duplicates->implode('، '));
                }

                foreach ($serialNumbers as $serial) {
                    ItemSerialNumber::create([
                        'item_id'           => $item->id,
                        'serial_number'     => $serial,
                        'status'            => ItemSerialNumber::STATUS_IN_STOCK,
                        'stock_movement_id' => $movement->id,
                    ]);
                }

                // برای باقیِ تعداد که شماره سریال واردش نشده، یک جایگاه «بدون شماره سریال»
                // در انبار ثبت می‌شود که بعداً از صفحه‌ی محصولات قابل تکمیل است.
                $missing = $quantity - count($serialNumbers);
                for ($i = 0; $i < $missing; $i++) {
                    ItemSerialNumber::create([
                        'item_id'           => $item->id,
                        'serial_number'     => null,
                        'status'            => ItemSerialNumber::STATUS_IN_STOCK,
                        'stock_movement_id' => $movement->id,
                    ]);
                }
            } else {
                // انتخاب شماره سریال برای خروج هم اختیاری است؛ باقی به‌صورت خودکار
                // (با اولویت روی جایگاه‌های بدون شماره سریال) از انبار کسر می‌شود.
                $serialIds = $data['serial_number_ids'] ?? [];
                if (count($serialIds) > $quantity) {
                    throw new RuntimeException('تعداد شماره سریال‌های انتخاب‌شده نمی‌تواند از تعداد بیشتر باشد.');
                }

                $chosen = collect();
                if (!empty($serialIds)) {
                    $chosen = ItemSerialNumber::where('item_id', $item->id)
                        ->where('status', ItemSerialNumber::STATUS_IN_STOCK)
                        ->whereIn('id', $serialIds)
                        ->lockForUpdate()
                        ->get();

                    if ($chosen->count() !== count($serialIds)) {
                        throw new RuntimeException('برخی از شماره سریال‌های انتخاب‌شده در انبار موجود نیستند.');
                    }
                }

                $remaining = $quantity - $chosen->count();
                $autoFilled = collect();
                if ($remaining > 0) {
                    $autoFilled = ItemSerialNumber::where('item_id', $item->id)
                        ->where('status', ItemSerialNumber::STATUS_IN_STOCK)
                        ->whereNotIn('id', $chosen->pluck('id'))
                        ->orderByRaw('serial_number IS NOT NULL') // اول جایگاه‌های بدون سریال مصرف شوند
                        ->lockForUpdate()
                        ->limit($remaining)
                        ->get();

                    if ($autoFilled->count() !== $remaining) {
                        throw new RuntimeException('موجودی این کالا برای این حرکت کافی نیست.');
                    }
                }

                $allSerials = $chosen->concat($autoFilled);
                ItemSerialNumber::whereIn('id', $allSerials->pluck('id'))->update([
                    'status'            => ItemSerialNumber::STATUS_REMOVED,
                    'stock_movement_id' => $movement->id,
                ]);
            }

            return $movement;
        });
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

        DB::transaction(function () use ($orderItem) {
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

            // اگر این قلم مربوط به کالای دارای شماره سریال است، سریال‌های رزروشده‌اش را «فروخته‌شده» کن
            $reservedSerials = ItemSerialNumber::where('order_item_id', $orderItem->id)
                ->where('status', ItemSerialNumber::STATUS_RESERVED)
                ->get();

            if ($reservedSerials->isNotEmpty()) {
                if ($reservedSerials->count() !== (int) $orderItem->quantity) {
                    throw new RuntimeException(
                        "تعداد شماره سریال‌های رزروشده با تعداد قلم «{$orderItem->product_name}» یکسان نیست."
                    );
                }

                ItemSerialNumber::whereIn('id', $reservedSerials->pluck('id'))
                    ->update(['status' => ItemSerialNumber::STATUS_SOLD]);
            }
        });
    }

    /**
     * حذف حرکت خروجی مربوط به یک قلم فاکتور (وقتی قلم حذف می‌شود، موجودی برمی‌گردد).
     * نیازمند ستون order_item_id روی جدول stock_movements است (به مایگریشن پیوست‌شده نگاه کن).
     */
    public function reverseSaleMovementForOrderItem(OrderItem $orderItem): void
    {
        DB::transaction(function () use ($orderItem) {
            StockMovement::where('order_item_id', $orderItem->id)
                ->where('movement_type', StockMovement::TYPE_OUT)
                ->delete();

            // سریال‌های رزرو/فروخته‌شده این قلم آزاد و به انبار برمی‌گردند
            ItemSerialNumber::where('order_item_id', $orderItem->id)
                ->whereIn('status', [ItemSerialNumber::STATUS_RESERVED, ItemSerialNumber::STATUS_SOLD])
                ->update([
                    'status'        => ItemSerialNumber::STATUS_IN_STOCK,
                    'order_item_id' => null,
                ]);
        });
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

        DB::transaction(function () use ($orderItem) {
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

            // سریال‌های فروخته‌شده‌ی این قلم دوباره به انبار برمی‌گردند
            ItemSerialNumber::where('order_item_id', $orderItem->id)
                ->where('status', ItemSerialNumber::STATUS_SOLD)
                ->update([
                    'status'        => ItemSerialNumber::STATUS_IN_STOCK,
                    'order_item_id' => null,
                ]);
        });
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
