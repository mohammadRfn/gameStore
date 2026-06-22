<?php

namespace App\Http\Controllers;

use App\Http\Requests\CategoryRequest;
use App\Services\CategoryService;
use Inertia\Inertia;

class CategoryController extends Controller
{
    public function __construct(
        protected CategoryService $categoryService
    ) {}

    public function index()
    {
        return Inertia::render('Categories/Index', [
            'categories' => $this->categoryService->getAllCategories(),
        ]);
    }

    public function store(CategoryRequest $request)
    {
        $this->categoryService->createCategory($request->name);
        return redirect()->back();
    }

    public function destroy(int $id)
    {
        $this->categoryService->deleteCategory($id);
        return redirect()->back();
    }
}