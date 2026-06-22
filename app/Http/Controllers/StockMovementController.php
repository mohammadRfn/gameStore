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

        return Inertia::render('StockMovements/Index', [
            'movements' => $query->paginate(30),
            'filters'   => $request->only(['item_id']),
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