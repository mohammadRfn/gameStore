<?php

namespace App\Services;

use App\Models\Category;
use Illuminate\Database\Eloquent\Collection;

class CategoryService
{
    public function createCategory(string $name): Category
    {
        return Category::create(['name' => $name]);
    }

    public function getAllCategories(): Collection
    {
        return Category::all();
    }

    public function deleteCategory(int $id): void
    {
        $category = Category::findOrFail($id);
        $category->delete();
    }
}