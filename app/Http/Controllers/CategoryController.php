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
        $category = $this->categoryService->createCategory(
            $request->name,
            $request->boolean('default_tracks_stock', true)
        );

        if ($request->wantsJson()) {
            return response()->json($category, 201);
        }

        return redirect()->back();
    }

    public function destroy(int $id)
    {
        try {
            $this->categoryService->deleteCategory($id);
        } catch (\RuntimeException $e) {
            if (request()->wantsJson()) {
                return response()->json(['message' => $e->getMessage()], 422);
            }
            return redirect()->back()->withErrors(['category' => $e->getMessage()]);
        }

        if (request()->wantsJson()) {
            return response()->json(['success' => true]);
        }

        return redirect()->back();
    }
}
