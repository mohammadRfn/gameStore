<?php

namespace App\Http\Controllers;

use App\Http\Requests\ItemRequest;
use App\Services\ItemService;
use Inertia\Inertia;

class ItemController extends Controller
{
    public function __construct(
        protected ItemService $itemService
    ) {}

    public function index()
    {
        return Inertia::render('Inventory/Index', [
            'items' => $this->itemService->getAllItems(),
        ]);
    }

    public function show(int $id)
    {
        return Inertia::render('Inventory/Show', [
            'item' => $this->itemService->findItem($id),
        ]);
    }

    public function create()
    {
        return Inertia::render('Inventory/Create');
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
        return Inertia::render('Inventory/Edit', [
            'item' => $this->itemService->findItem($id),
        ]);
    }

    public function update(ItemRequest $request, int $id)
    {
        $validated = $request->validated();

        if ($request->hasFile('image')) {
            $validated['image'] = $request->file('image');
        }

        $this->itemService->updateItem($id, $validated);
        return redirect()->route('items.show', $id);
    }

    public function destroy(int $id)
    {
        $this->itemService->deleteItem($id);
        return redirect()->route('items.index');
    }
}