<?php

namespace App\Services;

use App\Models\OrderItem;

interface OrderItemInterface
{
    public function getAllOrderItems();
    public function createOrderItem(array $data, $invoiceId): OrderItem;
    public function updateOrderItem(int $id, array $data): OrderItem;
    public function deleteOrderItem(int $id): void;
    public function findOrderItem(int $id): OrderItem;
    public function updateInvoiceTotalAmount(int $orderItemId);
}
