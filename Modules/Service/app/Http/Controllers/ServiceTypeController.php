<?php

namespace Modules\Service\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Modules\Service\Http\Requests\ServiceTypeRequest;
use Modules\Service\Services\ServiceTypeService;

class ServiceTypeController extends Controller
{
    public function __construct(
        protected ServiceTypeService $serviceTypeService
    ) {}

    public function index()
    {
        return Inertia::render('ServiceTypes/Index', [
            'serviceTypes' => $this->serviceTypeService->listWithUsage(),
        ]);
    }

    public function show(int $id)
    {
        return redirect()->route('service-types.index');
    }

    public function create()
    {
        return redirect()->route('service-types.index');
    }

    public function store(ServiceTypeRequest $request)
    {
        $this->serviceTypeService->createServiceType($request->validated());
        return redirect()->route('service-types.index')->with('success', 'نوع سرویس اضافه شد.');
    }

    public function edit(int $id)
    {
        return redirect()->route('service-types.index');
    }

    public function update(ServiceTypeRequest $request, int $id)
    {
        $this->serviceTypeService->updateServiceType($id, $request->validated());
        return redirect()->route('service-types.index')->with('success', 'نوع سرویس به‌روزرسانی شد.');
    }

    public function destroy(int $id)
    {
        try {
            $this->serviceTypeService->deleteServiceType($id);
        } catch (\RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        return redirect()->route('service-types.index')->with('success', 'نوع سرویس حذف شد.');
    }
    public function quickStore(Request $request)
    {
        $data = $request->validate([
            'name'       => 'required|string|max:255',
            'base_price' => 'nullable|numeric|min:0',
        ]);

        $serviceType = $this->serviceTypeService->createServiceType([
            'name'       => $data['name'],
            'base_price' => $data['base_price'] ?? null,
            'is_active'  => true,
        ]);

        return response()->json($serviceType, 201);
    }
}
