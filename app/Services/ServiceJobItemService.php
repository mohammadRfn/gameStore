<?php

namespace App\Services;

use App\Models\ServiceJobItem;
use Illuminate\Database\Eloquent\Collection;

class ServiceJobItemService
{
    public function getItemsByServiceJob(int $serviceJobId): Collection
    {
        return ServiceJobItem::where('service_job_id', $serviceJobId)->with('item')->get();
    }

    public function getItemById(int $id): ServiceJobItem
    {
        return ServiceJobItem::findOrFail($id);
    }

    public function createItemForServiceJob(array $data, int $serviceJobId): ServiceJobItem
    {
        return ServiceJobItem::create([
            'service_job_id' => $serviceJobId,
            'item_id'        => $data['item_id'],
            'quantity'       => $data['quantity'],
            'unit_price'     => $data['unit_price'],
            'total_price'    => $data['quantity'] * $data['unit_price'],
        ]);
    }

    public function updateItemForServiceJob(int $id, array $data): ServiceJobItem
    {
        $item = ServiceJobItem::findOrFail($id);
        $item->update([
            'quantity'    => $data['quantity'],
            'unit_price'  => $data['unit_price'],
            'total_price' => $data['quantity'] * $data['unit_price'],
        ]);
        return $item;
    }

    public function deleteItem(int $id): void
    {
        ServiceJobItem::findOrFail($id)->delete();
    }
}
