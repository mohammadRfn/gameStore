<?php

namespace Modules\Stock\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Modules\Stock\Http\Requests\StockMovementRequest;
use Modules\Stock\Models\Item;
use Modules\Stock\Models\StockMovement;
use Modules\Stock\Services\StockMovementService;

class StockMovementController extends Controller
{
    public function __construct(
        protected StockMovementService $stockMovementService
    ) {}

    public function index(Request $request)
    {
        $query = StockMovement::with('item')->orderByDesc('created_at');

        if ($itemId = $request->input('item_id')) {
            $query->where('item_id', $itemId);
        }

        if ($search = trim((string) $request->input('search'))) {
            $query->where(function ($q) use ($search) {
                $q->where('reason', 'like', "%{$search}%")
                    ->orWhere('note', 'like', "%{$search}%")
                    ->orWhereHas('item', fn ($i) => $i->where('name', 'like', "%{$search}%"));
            });
        }

        // فقط اقلامی که «موجودی انبار دارد» تیک خورده باشد (tracks_stock)
        // در انتخابگر گردش انبار و کارت‌های خلاصه ظاهر می‌شوند.
        $items = Item::where('tracks_stock', true)
            ->select('id', 'name', 'price', 'has_serial_number', 'has_warranty')
            ->orderBy('name')
            ->get();

        $stockSummary = $items->map(function ($item) {
            return [
                'id'            => $item->id,
                'name'          => $item->name,
                'price'         => $item->price,
                'current_stock' => $this->stockMovementService->getCurrentStock($item->id),
                'has_serial_number' => $item->has_serial_number,
                'has_warranty'      => $item->has_warranty,
            ];
        })->values();

        return Inertia::render('StockMovements/Index', [
            'movements'    => $query->paginate(30)->withQueryString(),
            'items'        => $items,
            'stockSummary' => $stockSummary,
            'filters'      => $request->only(['item_id', 'search']),
        ]);
    }

    public function getItemStock(int $itemId)
    {
        $item  = Item::findOrFail($itemId);
        $stock = $this->stockMovementService->getCurrentStock($item->id);

        return response()->json([
            'item_id'       => $item->id,
            'item_name'     => $item->name,
            'current_stock' => $stock,
        ]);
    }

    /**
     * لیست شماره سریال‌های موجود در انبار برای یک کالا؛ برای انتخاب هنگام افزودن قلم به
     * فاکتور یا هنگام ثبت حرکت انبار دستی خروجی استفاده می‌شود.
     */
    public function getAvailableSerialNumbers(int $itemId)
    {
        $item = Item::findOrFail($itemId);

        if (!$item->has_serial_number) {
            return response()->json(['serial_numbers' => []]);
        }

        return response()->json([
            'serial_numbers' => $this->stockMovementService->getAvailableSerialNumbers($item->id),
        ]);
    }

    public function storeManualMovement(StockMovementRequest $request)
    {
        try {
            $this->stockMovementService->createManualMovement($request->validated());
        } catch (\RuntimeException $e) {
            return redirect()->back()->withErrors(['quantity' => $e->getMessage()])->withInput();
        }

        return redirect()->route('stock-movements.index');
    }

    /**
     * لیست جایگاه‌های «بدون شماره سریال» یک کالا؛ برای مودال تکمیل سریال روی صفحه‌ی محصولات.
     */
    public function getMissingSerialSlots(int $itemId)
    {
        $item = Item::findOrFail($itemId);

        return response()->json([
            'slots' => $this->stockMovementService->getUnassignedSerialSlots($item->id),
        ]);
    }

    /**
     * ثبت شماره سریال برای یک جایگاه خالی.
     */
    public function assignSerialNumber(Request $request, int $itemSerialNumberId)
    {
        $data = $request->validate([
            'serial_number' => 'required|string|max:255',
        ]);

        try {
            $this->stockMovementService->assignSerialNumber($itemSerialNumberId, $data['serial_number']);
        } catch (\RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json(['status' => 'ok']);
    }
    public function updateSerialNumber(Request $request, int $itemSerialNumberId)
    {
        $data = $request->validate([
            'serial_number' => 'required|string|max:255',
        ]);

        try {
            $serial = $this->stockMovementService->updateSerialNumber($itemSerialNumberId, $data['serial_number']);
        } catch (\RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json(['status' => 'ok', 'serial' => $serial]);
    }

    public function deleteSerialSlot(int $itemSerialNumberId)
    {
        try {
            $this->stockMovementService->deleteSerialSlot($itemSerialNumberId);
        } catch (\RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json(['status' => 'ok']);
    }
}
