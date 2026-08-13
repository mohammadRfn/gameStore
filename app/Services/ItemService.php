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
            'name'            => $data['name'],
            'purchase_price'  => $data['purchase_price'],
            'sale_price'      => $data['sale_price'],
            'description'     => $data['description'] ?? null,
            'image_path'      => $data['image_path'] ?? null,
            'category_id'     => $data['category_id'] ?? null,
            'tracks_stock'    => $data['tracks_stock'] ?? true,
        ]);
    }

    public function updateItem(int $id, array $data): Item
    {
        $item = Item::findOrFail($id);

        if (isset($data['image'])) {
            $data['image_path'] = $this->storeImage($data['image']);
        }

        $item->update([
            'name'            => $data['name']            ?? $item->name,
            'purchase_price'  => $data['purchase_price']  ?? $item->purchase_price,
            'sale_price'      => $data['sale_price']      ?? $item->sale_price,
            'description'     => $data['description']     ?? $item->description,
            'image_path'      => $data['image_path']      ?? $item->image_path,
            'category_id'     => $data['category_id']     ?? $item->category_id,
            'tracks_stock'    => array_key_exists('tracks_stock', $data) ? $data['tracks_stock'] : $item->tracks_stock,
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
