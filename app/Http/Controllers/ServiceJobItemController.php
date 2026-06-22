<?php

namespace App\Http\Controllers;

use App\Http\Requests\ServiceJobItemRequest;
use App\Services\ServiceJobItemService;
use Inertia\Inertia;

class ServiceJobItemController extends Controller
{
    public function __construct(
        protected ServiceJobItemService $serviceJobItemService
    ) {}

    public function index(int $serviceJobId)
    {
        return Inertia::render('ServiceJobItems/Index', [
            'items'        => $this->serviceJobItemService->getItemsByServiceJob($serviceJobId),
            'serviceJobId' => $serviceJobId,
        ]);
    }

    public function show(int $id)
    {
        return Inertia::render('ServiceJobItems/Show', [
            'item' => $this->serviceJobItemService->getItemById($id),
        ]);
    }

    public function store(ServiceJobItemRequest $request, int $serviceJobId)
    {
        $this->serviceJobItemService->createItemForServiceJob($request->validated(), $serviceJobId);
        return redirect()->route('service-jobs.show', $serviceJobId);
    }

    public function update(ServiceJobItemRequest $request, int $id)
    {
        $item = $this->serviceJobItemService->updateItemForServiceJob($id, $request->validated());
        return redirect()->route('service-jobs.show', $item->service_job_id);
    }

    public function destroy(int $id)
    {
        $item = $this->serviceJobItemService->getItemById($id);
        $serviceJobId = $item->service_job_id;
        $this->serviceJobItemService->deleteItem($id);
        return redirect()->route('service-jobs.show', $serviceJobId);
    }
}