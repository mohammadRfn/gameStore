<?php

namespace App\Services;

use App\Models\Category;
use Illuminate\Database\Eloquent\Collection;
use RuntimeException;

class CategoryService
{

    public function getAllCategories(): Collection
    {
        return Category::all();
    }


    public function deleteCategory(int $id): void
    {
        $category = Category::findOrFail($id);

        if ($category->items()->exists() || $category->orderItems()->exists()) {
            throw new RuntimeException('این دسته‌بندی قبلاً برای یک محصول یا فاکتور استفاده شده و قابل حذف نیست.');
        }

        $category->delete();
    }
    public function createCategory(string $name, bool $defaultTracksStock = true): Category
    {
        return Category::create([
            'name'                  => $name,
            'default_tracks_stock'  => $defaultTracksStock,
        ]);
    }
}
