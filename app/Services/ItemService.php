<?php

namespace App\Services;

use App\Models\Category;
use App\Models\Item;
use Illuminate\Database\Eloquent\Collection;

class ItemService
{
    public function getAllItems(): Collection
    {
        return Item::with('category')->get();
    }

    public function findItem(int $id): Item
    {
        return Item::with('category')->findOrFail($id);
    }

    public function getAllCategories(): Collection
    {
        return Category::orderBy('name')->get();
    }

    public function createItem(array $data): Item
    {
        if (isset($data['image'])) {
            $data['image_path'] = $this->storeImage($data['image']);
        }

        return Item::create([
            'name'               => $data['name'],
            'purchase_price'     => $data['purchase_price'],
            'sale_price'         => $data['sale_price'],
            'description'        => $data['description'] ?? null,
            'image_path'         => $data['image_path'] ?? null,
            'category_id'        => $data['category_id'] ?? null,
            'tracks_stock'       => $data['tracks_stock'] ?? true,
            'has_serial_number'  => $data['has_serial_number'] ?? false,
            'has_warranty'       => $data['has_warranty'] ?? false,
        ]);
    }

    public function updateItem(int $id, array $data): Item
    {
        $item = Item::findOrFail($id);

        if (isset($data['image'])) {
            $data['image_path'] = $this->storeImage($data['image']);
        }

        $item->update([
            'name'               => $data['name']            ?? $item->name,
            'purchase_price'     => $data['purchase_price']  ?? $item->purchase_price,
            'sale_price'         => $data['sale_price']      ?? $item->sale_price,
            'description'        => $data['description']     ?? $item->description,
            'image_path'         => $data['image_path']      ?? $item->image_path,
            'category_id'        => $data['category_id']     ?? $item->category_id,
            'tracks_stock'       => array_key_exists('tracks_stock', $data) ? $data['tracks_stock'] : $item->tracks_stock,
            'has_serial_number'  => array_key_exists('has_serial_number', $data) ? (bool) $data['has_serial_number'] : $item->has_serial_number,
            'has_warranty'       => array_key_exists('has_warranty', $data) ? (bool) $data['has_warranty'] : $item->has_warranty,
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
