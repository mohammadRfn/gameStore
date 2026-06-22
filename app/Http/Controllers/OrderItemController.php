<?php

namespace App\Http\Controllers;

use App\Http\Requests\OrderItemRequest;
use App\Services\OrderItemService;
use Inertia\Inertia;

class OrderItemController extends Controller
{
    public function __construct(
        protected OrderItemService $orderItemService
    ) {}

    public function index()
    {
        return Inertia::render('OrderItems/Index', [
            'orderItems' => $this->orderItemService->getAllOrderItems(),
        ]);
    }

    public function show(int $id)
    {
        return Inertia::render('OrderItems/Show', [
            'orderItem' => $this->orderItemService->findOrderItem($id),
        ]);
    }

    public function store(OrderItemRequest $request)
    {
        $validated = $request->validated();

        if ($request->hasFile('image')) {
            $validated['image'] = $request->file('image');
        }

        $orderItem = $this->orderItemService->createOrderItem($validated, $request->input('invoice_id'));
        return redirect()->route('invoices.show', $orderItem->invoice_id);
    }

    public function update(OrderItemRequest $request, int $id)
    {
        $validated = $request->validated();

        if ($request->hasFile('image')) {
            $validated['image'] = $request->file('image');
        }

        $orderItem = $this->orderItemService->updateOrderItem($id, $validated);
        return redirect()->route('invoices.show', $orderItem->invoice_id);
    }

    public function destroy(int $id)
    {
        $orderItem = $this->orderItemService->findOrderItem($id);
        $invoiceId = $orderItem->invoice_id;

        $this->orderItemService->deleteOrderItem($id);
        $this->orderItemService->updateInvoiceTotalAmount($invoiceId);

        return redirect()->route('invoices.show', $invoiceId);
    }
}