<?php

namespace App\Http\Controllers;

use App\Http\Requests\RequestRequest;
use App\Services\CategoryService;
use App\Services\RequestService;
use Illuminate\Http\Request as HttpRequest;
use Inertia\Inertia;

class RequestController extends Controller
{
    public function __construct(
        protected RequestService $requestService,
        protected CategoryService $categoryService,
    ) {}

    public function index()
    {
        return Inertia::render('Requests/Index', [
            'requests' => $this->requestService->getAllRequests(),
        ]);
    }

    public function show(int $id)
    {
        return Inertia::render('Requests/Show', [
            'request' => $this->requestService->showRequest($id),
        ]);
    }

    public function create()
    {
        return Inertia::render('Requests/Create', [
            'categories' => $this->categoryService->getAllCategories(),
        ]);
    }

    public function store(RequestRequest $request)
    {
        $this->requestService->createRequest($request->validated());
        return redirect()->route('requests.index');
    }

    public function edit(int $id)
    {
        return Inertia::render('Requests/Edit', [
            'request'    => $this->requestService->showRequest($id),
            'categories' => $this->categoryService->getAllCategories(),
        ]);
    }

    public function update(int $id, RequestRequest $request)
    {
        $this->requestService->updateRequest($id, $request->validated());
        return redirect()->route('requests.show', $id);
    }

    public function destroy(int $id)
    {
        $this->requestService->deleteRequest($id);
        return redirect()->route('requests.index');
    }
}