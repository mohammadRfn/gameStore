<?php

namespace App\Services;

use App\Models\Item;
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
        $costPrice = $data['cost_price'] ?? Item::find($data['item_id'])?->purchase_price ?? 0;

        return ServiceJobItem::create([
            'service_job_id' => $serviceJobId,
            'item_id'        => $data['item_id'],
            'quantity'       => $data['quantity'],
            'unit_price'     => $data['unit_price'],
            'total_price'    => $data['quantity'] * $data['unit_price'],
            'cost_price'     => $costPrice,
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
