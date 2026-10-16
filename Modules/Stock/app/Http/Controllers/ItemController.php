<?php

namespace Modules\Stock\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request as HttpRequest;
use Inertia\Inertia;
use Modules\Stock\Http\Requests\ItemRequest;
use Modules\Stock\Services\ItemService;
use Modules\Stock\Services\StockMovementService;

class ItemController extends Controller
{
    public function __construct(
        protected ItemService $itemService,
        protected StockMovementService $stockMovementService
    ) {}

    public function index(HttpRequest $request)
    {
        $filters = [
            'search'      => trim((string) $request->query('search', '')),
            'category_id' => $request->query('category_id') ? (int) $request->query('category_id') : null,
        ];

        $items = $this->itemService->paginateItems($filters, 25);

        // موجودی و سریال‌های ناقص فقط برای ۲۵ قلمِ همین صفحه و با یک کوئری گروهی محاسبه می‌شود
        $ids        = $items->getCollection()->pluck('id')->all();
        $stockMap   = $this->stockMovementService->getCurrentStockMap($ids);
        $missingMap = $this->stockMovementService->getMissingSerialCountMap($ids);

        $items->getCollection()->transform(function ($item) use ($stockMap, $missingMap) {
            $item->current_stock = $item->tracks_stock ? ($stockMap[$item->id] ?? 0) : null;
            $item->missing_serial_count = $item->has_serial_number ? ($missingMap[$item->id] ?? 0) : 0;
            return $item;
        });

        return Inertia::render('Items/Index', [
            'items'      => $items,
            'filters'    => $filters,
            'categories' => $this->itemService->getCategoriesWithItems(),
            'stats'      => [
                'total'    => $this->itemService->countItems(),
                'in_stock' => $this->stockMovementService->countItemsInStock(),
            ],
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