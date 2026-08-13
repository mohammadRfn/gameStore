<?php

namespace App\Http\Controllers;

use App\Http\Requests\ServiceJobRequest;
use App\Models\Category;
use App\Models\Customer;
use App\Models\Item;
use App\Models\Request as ServiceRequest;
use App\Models\ServiceJob;
use App\Models\ServiceType;
use App\Services\ServiceJobService;
use Illuminate\Http\Request;
use Inertia\Inertia;

class ServiceJobController extends Controller
{
    public function __construct(
        protected ServiceJobService $serviceJobService
    ) {}

    public function index(Request $request)
    {
        $query = ServiceJob::with('serviceTypes.serviceType', 'customer')
            ->orderByDesc('created_at');

        if ($status = $request->input('status')) {
            $query->where('status', $status);
        }

        return Inertia::render('ServiceJobs/Index', [
            'serviceJobs' => $query->paginate(20),
            'filters'     => $request->only(['status']),
        ]);
    }
    public function show(int $id)
    {
        return Inertia::render('ServiceJobs/Show', [
            'job' => $this->serviceJobService->findServiceJob($id),
        ]);
    }

    public function create()
    {
        return Inertia::render('ServiceJobs/Create', $this->sharedFormData());
    }

    public function store(ServiceJobRequest $request)
    {
        $job = $this->serviceJobService->createServiceJob($request->validated());
        return redirect()->route('service-jobs.show', $job->id);
    }

    public function edit(int $id)
    {
        return Inertia::render('ServiceJobs/Edit', array_merge(
            ['job' => $this->serviceJobService->findServiceJob($id)],
            $this->sharedFormData()
        ));
    }

    public function update(int $id, ServiceJobRequest $request)
    {
        $this->serviceJobService->updateServiceJob($id, $request->validated());
        return redirect()->route('service-jobs.show', $id);
    }

    public function destroy(int $id)
    {
        try {
            $this->serviceJobService->deleteServiceJob($id);
        } catch (\RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        return redirect()->route('service-jobs.index')->with('success', 'سرویس با موفقیت حذف شد.');
    }

    protected function sharedFormData(): array
    {
        return [
            'customers'    => Customer::select('id', 'name')->orderBy('name')->get(),
            'requests'     => ServiceRequest::select('id', 'customer_id', 'customer_name', 'description')
                ->with('categories:id,name')
                ->whereHas('categories', function ($q) {
                    $q->whereIn('name', ['تعمیرات', 'خدمات']);
                })
                ->latest()->get(),
            'serviceTypes' => ServiceType::where('is_active', true)
                ->select('id', 'name', 'base_price')->orderBy('name')->get(),
            'items'        => Item::select('id', 'name', 'sale_price', 'category_id')->orderBy('name')->get(),
            'categories'   => Category::select('id', 'name')->orderBy('name')->get(),
        ];
    }
}
