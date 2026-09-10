<?php

namespace App\Services;

use App\Models\Invoice;
use App\Models\ItemSerialNumber;
use App\Models\OrderItem;
use App\Models\Warranty;
use App\Models\WarrantyProvider;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class WarrantyService
{
    public function providers(): Collection
    {
        return WarrantyProvider::orderBy('name')->get();
    }

    public function createProvider(array $data): WarrantyProvider
    {
        return WarrantyProvider::create([
            'name'        => $data['name'],
            'phone'       => $data['phone'] ?? null,
            'email'       => $data['email'] ?? null,
            'website'     => $data['website'] ?? null,
            'instagram'   => $data['instagram'] ?? null,
            'address'     => $data['address'] ?? null,
            'description' => $data['description'] ?? null,
        ]);
    }

    // ---------- کالای بدون شماره سریال (لنگر روی order_item) ----------

    public function canEdit(OrderItem $orderItem): bool
    {
        $orderItem->loadMissing('invoice');
        return $this->canEditOrderItemLevel($orderItem);
    }

    protected function canEditOrderItemLevel(OrderItem $orderItem): bool
    {
        $invoice = $orderItem->invoice;
        if (!$invoice) {
            return true;
        }
        if ($orderItem->is_returned || $invoice->is_returned) {
            return true;
        }
        if ($invoice->payment_status === Invoice::PAYMENT_PAID) {
            return false;
        }
        return true;
    }

    public function upsertForOrderItem(OrderItem $orderItem, array $data): Warranty
    {
        if (!$this->canEdit($orderItem)) {
            throw new RuntimeException('چون فاکتور این قلم پرداخت‌شده و هنوز مرجوع نشده، گارانتی آن قابل ویرایش نیست.');
        }

        return DB::transaction(function () use ($orderItem, $data) {
            $warranty = Warranty::where('order_item_id', $orderItem->id)->first()
                ?? new Warranty(['order_item_id' => $orderItem->id]);

            $this->fillWarranty($warranty, $data);
            $warranty->save();

            return $warranty;
        });
    }

    public function resetWarrantyForOrderItem(OrderItem $orderItem): void
    {
        // کالای بدون سریال: گارانتی مستقیم روی خود order_item است
        Warranty::where('order_item_id', $orderItem->id)->delete();

        // کالای سریال‌دار: گارانتی روی سریال(های) هنوز متصل به این order_item است
        // مهم: این متد باید قبل از آزادسازی سریال‌ها (نال‌شدن order_item_id) صدا زده شود
        $serialIds = ItemSerialNumber::where('order_item_id', $orderItem->id)->pluck('id');
        if ($serialIds->isNotEmpty()) {
            Warranty::whereIn('item_serial_number_id', $serialIds)->delete();
        }
    }

    // ---------- کالای دارای شماره سریال (لنگر روی item_serial_number) ----------

    public function canEditSerial(ItemSerialNumber $serial): bool
    {
        if (!$serial->order_item_id) {
            return true; // هنوز در انبار است، فروخته نشده
        }

        $orderItem = OrderItem::with('invoice')->find($serial->order_item_id);
        if (!$orderItem) {
            return true;
        }

        return $this->canEditOrderItemLevel($orderItem);
    }

    public function upsertForSerial(ItemSerialNumber $serial, array $data): Warranty
    {
        if (!$this->canEditSerial($serial)) {
            throw new RuntimeException('چون این واحد فروخته‌شده و فاکتورش پرداخت‌شده و هنوز مرجوع نشده، گارانتی آن قابل ویرایش نیست.');
        }

        return DB::transaction(function () use ($serial, $data) {
            $warranty = Warranty::where('item_serial_number_id', $serial->id)->first()
                ?? new Warranty(['item_serial_number_id' => $serial->id]);

            $this->fillWarranty($warranty, $data);
            $warranty->save();

            return $warranty;
        });
    }

    protected function fillWarranty(Warranty $warranty, array $data): void
    {
        $warranty->duration_value       = $data['duration_value'];
        $warranty->duration_unit        = $data['duration_unit'];
        $warranty->warranty_provider_id = $data['warranty_provider_id'] ?? null;
        $warranty->notes                = $data['notes'] ?? null;
        // هر بار ویرایش می‌شود یعنی هنوز فروخته/پرداخت نشده یا تازه مرجوع شده
        $warranty->starts_at  = null;
        $warranty->expires_at = null;
    }

    // ---------- فعال‌سازی هنگام پرداخت فاکتور ----------

    public function activateWarrantiesForInvoice(Invoice $invoice): void
    {
        $invoice->loadMissing('orderItems.item', 'orderItems.serialNumbers');

        foreach ($invoice->orderItems as $orderItem) {
            if ($orderItem->item && $orderItem->item->has_serial_number) {
                $serialIds = $orderItem->serialNumbers->pluck('id');
                if ($serialIds->isNotEmpty()) {
                    Warranty::whereIn('item_serial_number_id', $serialIds)
                        ->whereNull('starts_at')
                        ->get()
                        ->each(fn (Warranty $w) => $w->startCounting());
                }
            } else {
                Warranty::where('order_item_id', $orderItem->id)
                    ->whereNull('starts_at')
                    ->first()
                    ?->startCounting();
            }
        }
    }
}