<?php

namespace App\Services;

use App\Models\Invoice;
use App\Models\Item;
use App\Models\OrderItem;
use Illuminate\Database\Eloquent\Collection;

class OrderItemService
{
    public function getAllOrderItems(): Collection
    {
        return OrderItem::with('item', 'category')->get();
    }

    public function findOrderItem(int $id): OrderItem
    {
        return OrderItem::with('item', 'category')->findOrFail($id);
    }

    public function createOrderItem(array $data, int $invoiceId): OrderItem
    {
        if (empty($data['item_id']) || empty($data['quantity']) || empty($data['category_id'])) {
            throw new \Exception("Missing required fields.");
        }

        $item = Item::findOrFail($data['item_id']);
        $totalPrice = $data['quantity'] * $item->price;

        $orderItem = OrderItem::create([
            'invoice_id'   => $invoiceId,
            'item_id'      => $item->id,
            'category_id'  => $data['category_id'],
            'product_name' => $item->name,
            'quantity'     => $data['quantity'],
            'price'        => $item->price,
            'total_price'  => $totalPrice,
        ]);

        if (isset($data['image'])) {
            $orderItem->image_path = $data['image']->store('images/order_items', 'public');
            $orderItem->save();
        }

        return $orderItem;
    }

    public function updateOrderItem(int $id, array $data): OrderItem
    {
        $orderItem = OrderItem::findOrFail($id);
        $orderItem->update($data);
        $orderItem->total_price = $orderItem->quantity * $orderItem->price;
        $orderItem->save();

        if (isset($data['image'])) {
            $orderItem->image_path = $data['image']->store('images/order_items', 'public');
            $orderItem->save();
        }

        return $orderItem;
    }

    public function deleteOrderItem(int $id): void
    {
        OrderItem::findOrFail($id)->delete();
    }

    public function updateInvoiceTotalAmount(int $invoiceId): void
    {
        $invoice = Invoice::findOrFail($invoiceId);
        $invoice->total_amount = OrderItem::where('invoice_id', $invoiceId)->sum('total_price');
        $invoice->save();
    }
}
