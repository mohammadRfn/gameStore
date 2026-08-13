<?php

namespace App\Http\Controllers;

use App\Http\Requests\ItemRequest;
use App\Services\ItemService;
use Inertia\Inertia;

class ItemController extends Controller
{
    public function __construct(
        protected ItemService $itemService,
        protected \App\Services\StockMovementService $stockMovementService
    ) {}

    public function index()
    {
        $items = $this->itemService->getAllItems()->map(function ($item) {
            $item->current_stock = $item->tracks_stock
                ? $this->stockMovementService->getCurrentStock($item->id)
                : null;
            return $item;
        });

        return Inertia::render('Items/Index', [
            'items' => $items,
        ]);
    }

    public function show(int $id)
    {
        $item = $this->itemService->findItem($id);
        $item->current_stock = $item->tracks_stock
            ? $this->stockMovementService->getCurrentStock($item->id)
            : null;

        return Inertia::render('Items/Show', [
            'item' => $item,
        ]);
    }

    public function create()
    {
        return Inertia::render('Items/Create', [
            'categories' => $this->itemService->getAllCategories(),
        ]);
    }
    public function store(ItemRequest $request)
    {
        $validated = $request->validated();

        if ($request->hasFile('image')) {
            $validated['image'] = $request->file('image');
        }

        $this->itemService->createItem($validated);
        return redirect()->route('items.index');
    }

    public function edit(int $id)
    {
        return Inertia::render('Items/Edit', [
            'item'       => $this->itemService->findItem($id),
            'categories' => $this->itemService->getAllCategories(),
        ]);
    }

    public function update(ItemRequest $request, int $id)
    {
        \Log::info('=== UPDATE CALLED ===', ['id' => $id, 'input' => $request->all()]);

        $validated = $request->validated();
        \Log::info('=== VALIDATED ===', $validated);

        if ($request->hasFile('image')) {
            $validated['image'] = $request->file('image');
        }

        $item = $this->itemService->updateItem($id, $validated);
        \Log::info('=== AFTER UPDATE ===', $item->fresh()->toArray());

        return redirect()->route('items.index');
    }
    public function destroy(int $id)
    {
        $this->itemService->deleteItem($id);
        return redirect()->route('items.index');
    }
}
