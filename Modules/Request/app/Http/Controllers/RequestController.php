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
        $serviceRequest = $this->requestService->showRequest($id);

        return Inertia::render('Requests/Show', [
            'request'               => $serviceRequest,
            // سرویس‌های تحویل‌شدهٔ این درخواست که می‌توانند به فاکتورش اضافه شوند
            'attachableServiceJobs' => app(\Modules\Service\Services\ServiceJobService::class)
                ->attachableJobsForRequest($serviceRequest),
        ]);
    }

    public function create()
    {
        return Inertia::render('Requests/Create', [
            'categories' => $this->categoryService->getAllCategories(),
            'customers'  => \Modules\Customer\Models\Customer::select('id', 'name')->get(),
        ]);
    }

    public function store(RequestRequest $request)
    {
        $data = $request->validated();

        if (!empty($data['create_invoice'])) {
            try {
                $invoice = $this->requestService->createRequestWithInvoice($data);
            } catch (\Exception $e) {
                // مثلاً کمبود موجودی/سریال؛ تراکنش برگشت خورده و فرم با همان محتوا باز می‌ماند
                return back()->withErrors(['items' => $e->getMessage()]);
            }

            return redirect()->route('invoices.show', $invoice->id);
        }

        $this->requestService->createRequest($data);
        return redirect()->route('requests.index');
    }

    public function edit(int $id)
    {
        return Inertia::render('Requests/Edit', [
            'request'    => $this->requestService->showRequest($id),
            'categories' => $this->categoryService->getAllCategories(),
            'customers'  => \Modules\Customer\Models\Customer::select('id', 'name')->get(),
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