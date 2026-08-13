<?php

namespace App\Http\Controllers;

use App\Http\Requests\StockMovementRequest;
use App\Models\Item;
use App\Models\StockMovement;
use App\Services\StockMovementService;
use Illuminate\Http\Request;
use Inertia\Inertia;

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

        // فقط اقلامی که «موجودی انبار دارد» تیک خورده باشد (tracks_stock)
        // در انتخابگر گردش انبار و کارت‌های خلاصه ظاهر می‌شوند.
        $items = Item::where('tracks_stock', true)
            ->select('id', 'name', 'price')
            ->orderBy('name')
            ->get();

        $stockSummary = $items->map(function ($item) {
            return [
                'id'            => $item->id,
                'name'          => $item->name,
                'price'         => $item->price,
                'current_stock' => $this->stockMovementService->getCurrentStock($item->id),
            ];
        })->values();

        return Inertia::render('StockMovements/Index', [
            'movements'    => $query->paginate(30),
            'items'        => $items,
            'stockSummary' => $stockSummary,
            'filters'      => $request->only(['item_id']),
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

    public function storeManualMovement(StockMovementRequest $request)
    {
        $this->stockMovementService->createManualMovement($request->validated());
        return redirect()->route('stock-movements.index');
    }
}
