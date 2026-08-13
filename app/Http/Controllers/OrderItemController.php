<?php

namespace App\Http\Controllers;

use App\Http\Requests\OrderItemRequest;
use App\Models\Category;
use App\Models\Item;
use App\Services\OrderItemService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use RuntimeException;

class OrderItemController extends Controller
{
    public function __construct(
        protected OrderItemService $orderItemService
    ) {}

    public function index()
    {
        return Inertia::render('OrderItems/Index', [
            'orderItems' => $this->orderItemService->getAllOrderItems(),
        ]);
    }

    /**
     * صفحه‌ی افزودن قلم به فاکتور.
     * قبلاً این متد اصلاً وجود نداشت، برای همین route('order-items.create') بالا نمی‌آمد.
     */
    public function create(Request $request)
    {
        $invoiceId = $request->input('invoice_id');
        $invoice = $invoiceId ? \App\Models\Invoice::find($invoiceId) : null;

        $eligibleServiceJobs = [];
        if ($invoice && $invoice->customer_id) {
            $eligibleServiceJobs = \App\Models\ServiceJob::where('status', 'delivered')
                ->whereNull('invoice_id')
                ->where('customer_id', $invoice->customer_id)
                ->with('serviceTypes.serviceType')
                ->get();
        }

        return Inertia::render('OrderItems/Create', [
            'invoiceId'   => $invoiceId,
            'items'       => Item::select('id', 'name', 'sale_price', 'tracks_stock', 'category_id')->orderBy('name')->get(),
            'serviceJobs' => $eligibleServiceJobs,
        ]);
    }

    public function show(int $id)
    {
        return Inertia::render('OrderItems/Show', [
            'orderItem' => $this->orderItemService->findOrderItem($id),
        ]);
    }

    public function store(OrderItemRequest $request)
    {
        $validated = $request->validated();

        if ($request->hasFile('image')) {
            $validated['image'] = $request->file('image');
        }

        $invoiceId = $request->input('invoice_id');

        try {
            $orderItem = $this->orderItemService->createOrderItem($validated, $invoiceId);
        } catch (RuntimeException $e) {
            // معمولاً یعنی موجودی انبار کافی نیست
            return redirect()->back()
                ->withErrors(['quantity' => $e->getMessage()])
                ->withInput();
        }

        return redirect()->route('invoices.show', $orderItem->invoice_id);
    }

    public function update(OrderItemRequest $request, int $id)
    {
        $validated = $request->validated();

        if ($request->hasFile('image')) {
            $validated['image'] = $request->file('image');
        }

        try {
            $orderItem = $this->orderItemService->updateOrderItem($id, $validated);
        } catch (RuntimeException $e) {
            return redirect()->back()
                ->withErrors(['quantity' => $e->getMessage()])
                ->withInput();
        }

        return redirect()->route('invoices.show', $orderItem->invoice_id);
    }

    public function destroy(int $id)
    {
        $orderItem = $this->orderItemService->findOrderItem($id);
        $invoiceId = $orderItem->invoice_id;

        $this->orderItemService->deleteOrderItem($id);
        $this->orderItemService->updateInvoiceTotalAmount($invoiceId);

        return redirect()->route('invoices.show', $invoiceId);
    }
    public function markReturned(Request $request, int $id)
    {
        $data = $request->validate([
            'restock' => 'boolean',
        ]);

        $orderItem = $this->orderItemService->returnOrderItem($id, (bool) ($data['restock'] ?? false));

        return redirect()->route('invoices.show', $orderItem->invoice_id);
    }

    public function unmarkReturned(int $id)
    {
        $orderItem = $this->orderItemService->unreturnOrderItem($id);
        return redirect()->route('invoices.show', $orderItem->invoice_id);
    }
    public function attachServiceJobs(Request $request, int $invoiceId)
    {
        $data = $request->validate([
            'service_job_ids'   => 'required|array|min:1',
            'service_job_ids.*' => 'integer|exists:service_jobs,id',
        ]);

        app(\App\Services\OrderItemService::class)
            ->attachServiceJobsToInvoice($invoiceId, $data['service_job_ids']);

        return redirect()->route('invoices.show', $invoiceId);
    }

    public function detachServiceJob(int $invoiceId, int $serviceJobId)
    {
        app(\App\Services\OrderItemService::class)
            ->detachServiceJobFromInvoice($invoiceId, $serviceJobId);

        return redirect()->route('invoices.show', $invoiceId);
    }
}
