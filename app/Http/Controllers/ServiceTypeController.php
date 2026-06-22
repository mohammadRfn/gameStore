<?php

namespace App\Http\Controllers;

use App\Http\Requests\ServiceTypeRequest;
use App\Services\ServiceTypeService;
use Inertia\Inertia;

class ServiceTypeController extends Controller
{
    public function __construct(
        protected ServiceTypeService $serviceTypeService
    ) {}

    public function index()
    {
        return Inertia::render('ServiceTypes/Index', [
            'serviceTypes' => $this->serviceTypeService->getAllServiceTypes(),
        ]);
    }

    public function show(int $id)
    {
        return Inertia::render('ServiceTypes/Show', [
            'serviceType' => $this->serviceTypeService->getServiceTypeById($id),
        ]);
    }

    public function create()
    {
        return Inertia::render('ServiceTypes/Create');
    }

    public function store(ServiceTypeRequest $request)
    {
        $this->serviceTypeService->createServiceType($request->validated());
        return redirect()->route('service-types.index');
    }

    public function edit(int $id)
    {
        return Inertia::render('ServiceTypes/Edit', [
            'serviceType' => $this->serviceTypeService->getServiceTypeById($id),
        ]);
    }

    public function update(ServiceTypeRequest $request, int $id)
    {
        $this->serviceTypeService->updateServiceType($id, $request->validated());
        return redirect()->route('service-types.show', $id);
    }

    public function destroy(int $id)
    {
        $this->serviceTypeService->deleteServiceType($id);
        return redirect()->route('service-types.index');
    }
}