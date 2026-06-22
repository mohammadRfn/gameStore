<?php

namespace App\Services;

use App\Models\Item;
use Illuminate\Database\Eloquent\Collection;

class ItemService
{
    public function getAllItems(): Collection
    {
        return Item::all();
    }

    public function findItem(int $id): Item
    {
        return Item::findOrFail($id);
    }

    public function createItem(array $data): Item
    {
        if (isset($data['image'])) {
            $data['image_path'] = $this->storeImage($data['image']);
        }

        return Item::create([
            'name'        => $data['name'],
            'price'       => $data['price'],
            'description' => $data['description'] ?? null,
            'image_path'  => $data['image_path'] ?? null,
        ]);
    }

    public function updateItem(int $id, array $data): Item
    {
        $item = Item::findOrFail($id);

        if (isset($data['image'])) {
            $data['image_path'] = $this->storeImage($data['image']);
        }

        $item->update([
            'name'        => $data['name']        ?? $item->name,
            'price'       => $data['price']       ?? $item->price,
            'description' => $data['description'] ?? $item->description,
            'image_path'  => $data['image_path']  ?? $item->image_path,
        ]);

        return $item;
    }

    public function deleteItem(int $id): void
    {
        Item::findOrFail($id)->delete();
    }

    private function storeImage($image): string
    {
        return $image->store('images/items', 'public');
    }
}
