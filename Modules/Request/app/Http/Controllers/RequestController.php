<?php

namespace Modules\Request\Http\Controllers;

use App\Http\Controllers\Controller;
use Modules\Category\Services\CategoryService;
use Illuminate\Http\Request as HttpRequest;
use Inertia\Inertia;
use Modules\Request\Http\Requests\RequestRequest;
use Modules\Request\Services\RequestService;

class RequestController extends Controller
{
    public function __construct(
        protected RequestService $requestService,
        protected CategoryService $categoryService,
    ) {}

    public function index(HttpRequest $request)
    {
        return Inertia::render('Requests/Index', [
            'requests' => $this->requestService->getAllRequests($request->only(['search', 'status'])),
            'filters'  => $request->only(['search', 'status']),
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
            'customers'  => \App\Models\Customer::select('id', 'name')->get(),
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
            'customers'  => \App\Models\Customer::select('id', 'name')->get(),
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
