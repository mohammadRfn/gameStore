<?php

namespace App\Http\Controllers;

use App\Http\Requests\WarrantyRequest;
use App\Models\Item;
use App\Models\ItemSerialNumber;
use App\Models\OrderItem;
use App\Services\WarrantyService;
use Illuminate\Http\Request;
use RuntimeException;

class WarrantyController extends Controller
{
    public function __construct(
        protected WarrantyService $warrantyService
    ) {}

    public function providers()
    {
        return response()->json([
            'providers' => $this->warrantyService->providers(),
        ]);
    }

    public function storeProvider(Request $request)
    {
        $data = $request->validate([
            'name'        => 'required|string|max:255',
            'phone'       => 'nullable|string|max:255',
            'email'       => 'nullable|email|max:255',
            'website'     => 'nullable|string|max:255',
            'instagram'   => 'nullable|string|max:255',
            'address'     => 'nullable|string',
            'description' => 'nullable|string',
        ]);

        return response()->json([
            'provider' => $this->warrantyService->createProvider($data),
        ]);
    }

    /**
     * لیست کامل ردیف‌های قابل‌مدیریت گارانتی برای یک کالا.
     * برای کالای سریال‌دار: همه‌ی شماره‌سریال‌ها (در انبار یا فروخته‌شده).
     * برای کالای بدون سریال: همه‌ی order_itemهای فروخته‌شده‌ی آن کالا.
     */
    public function candidatesForItem(int $itemId)
    {
        $item = Item::findOrFail($itemId);

        if ($item->has_serial_number) {
            $rows = ItemSerialNumber::query()
                ->where('item_id', $itemId)
                ->whereIn('status', [
                    ItemSerialNumber::STATUS_IN_STOCK,
                    ItemSerialNumber::STATUS_RESERVED,
                    ItemSerialNumber::STATUS_SOLD,
                ])
                ->with(['warranty.provider', 'orderItem.invoice.customer'])
                ->orderByDesc('id')
                ->get();

            $data = $rows->map(function (ItemSerialNumber $serial) {
                $orderItem = $serial->orderItem;
                $invoice   = $orderItem?->invoice;

                return [
                    'anchor'                => 'serial',
                    'item_serial_number_id' => $serial->id,
                    'order_item_id'         => $orderItem?->id,
                    'serial_number'         => $serial->serial_number,
                    'in_stock'              => $serial->status === ItemSerialNumber::STATUS_IN_STOCK,
                    'invoice_number'        => $invoice?->invoice_number,
                    'customer_name'         => $invoice?->customer?->name,
                    'payment_status'        => $invoice?->payment_status,
                    'is_returned'           => (bool) $orderItem?->is_returned,
                    'warranty'              => $serial->warranty,
                    'editable'              => $this->warrantyService->canEditSerial($serial),
                ];
            });
        } else {
            $rows = OrderItem::query()
                ->where('item_id', $itemId)
                ->with(['invoice.customer', 'warranty.provider'])
                ->orderByDesc('id')
                ->get();

            $data = $rows->map(function (OrderItem $oi) {
                return [
                    'anchor'         => 'order_item',
                    'order_item_id'  => $oi->id,
                    'quantity'       => $oi->quantity,
                    'invoice_number' => $oi->invoice?->invoice_number,
                    'customer_name'  => $oi->invoice?->customer?->name,
                    'payment_status' => $oi->invoice?->payment_status,
                    'is_returned'    => (bool) $oi->is_returned,
                    'warranty'       => $oi->warranty,
                    'editable'       => $this->warrantyService->canEdit($oi),
                ];
            });
        }

        return response()->json(['rows' => $data]);
    }

    public function store(WarrantyRequest $request, int $orderItemId)
    {
        $orderItem = OrderItem::with('invoice')->findOrFail($orderItemId);

        try {
            $warranty = $this->warrantyService->upsertForOrderItem($orderItem, $request->validated());
        } catch (RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json(['warranty' => $warranty->load('provider')]);
    }

    public function storeForSerial(WarrantyRequest $request, int $itemSerialNumberId)
    {
        $serial = ItemSerialNumber::findOrFail($itemSerialNumberId);

        try {
            $warranty = $this->warrantyService->upsertForSerial($serial, $request->validated());
        } catch (RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json(['warranty' => $warranty->load('provider')]);
    }
}