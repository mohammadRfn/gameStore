<?php

namespace Modules\Service\Http\Controllers;

use App\Http\Controllers\Controller;
use Modules\Category\Models\Category;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Modules\Customer\Models\Customer;
use Modules\Request\Models\Request as ServiceRequest;
use Modules\Service\Http\Requests\ServiceJobRequest;
use Modules\Service\Models\ServiceJob;
use Modules\Service\Models\ServiceType;
use Modules\Service\Services\ServiceJobService;
use Modules\Stock\Models\Item;

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

        if ($search = trim((string) $request->input('search'))) {
            $query->where(function ($q) use ($search) {
                $q->where('device_type', 'like', "%{$search}%")
                    ->orWhere('device_serial', 'like', "%{$search}%")
                    ->orWhereHas('customer', fn ($c) => $c
                        ->where('name', 'like', "%{$search}%")
                        ->orWhere('phone', 'like', "%{$search}%"))
                    ->orWhereHas('serviceTypes.serviceType', fn ($s) => $s
                        ->where('name', 'like', "%{$search}%"));

                if (ctype_digit($search)) {
                    $q->orWhere('id', (int) $search);
                }
            });
        }

        return Inertia::render('ServiceJobs/Index', [
            'serviceJobs' => $query->paginate(20)->withQueryString(),
            'filters'     => $request->only(['status', 'search']),
        ]);
    }
    public function show(int $id)
    {
        $job = $this->serviceJobService->findServiceJob($id);

        return Inertia::render('ServiceJobs/Show', [
            'job'          => $job,
            'attachTarget' => $this->serviceJobService->invoiceAttachPreview($job),
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
        $job = $this->serviceJobService->findServiceJob($id);

        return Inertia::render('ServiceJobs/Edit', array_merge(
            [
                'job'          => $job,
                'attachTarget' => $this->serviceJobService->invoiceAttachPreview($job),
            ],
            $this->sharedFormData()
        ));
    }

    public function update(int $id, ServiceJobRequest $request)
    {
        $this->serviceJobService->updateServiceJob($id, $request->validated());
        return redirect()->route('service-jobs.show', $id);
    }

    public function attachToInvoice(int $id)
    {
        try {
            $invoice = $this->serviceJobService->attachToRequestInvoice($id);
        } catch (\RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', 'سرویس به فاکتور «' . $invoice->invoice_number . '» اضافه شد.');
    }

    public function exportPdf(int $id)
    {
        $job = $this->serviceJobService->findServiceJob($id);
        $pdf = app(\Modules\Service\Services\ServiceJobPdfService::class)->generate($job);

        return response($pdf, 200, [
            'Content-Type'        => 'application/pdf',
            'Content-Disposition' => 'inline; filename="service-' . $job->id . '.pdf"',
        ]);
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
