<?php

namespace Modules\Request\Services;

use Modules\Request\Models\Request;
use Illuminate\Support\Facades\DB;
use Modules\Invoice\Models\Invoice;
use Modules\Invoice\Services\InvoiceService;
use Illuminate\Support\Collection;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class RequestService
{
    public function createRequest(array $data): Request
    {
        $request = Request::create([
            'customer_name' => $data['customer_name'],
            'description'   => $data['description'],
            'status'        => Request::STATUS_PENDING,
            'customer_id'   => $data['customer_id'] ?? null,
        ]);

        if (!empty($data['category_ids'])) {
            $categoryIds = is_array($data['category_ids'])
                ? $data['category_ids']
                : [$data['category_ids']];

            $request->categories()->sync($categoryIds);
        }

        return $request->load('categories');
    }



    /**
     * ثبت درخواست + فاکتور منتسب به آن (مشتری و اقلام انتخاب‌شده) در یک تراکنش.
     * اگر افزودن یک قلم شکست بخورد، نه درخواست می‌ماند نه فاکتور.
     */
    public function createRequestWithInvoice(array $data): Invoice
    {
        return DB::transaction(function () use ($data) {
            $request = $this->createRequest($data);

            $invoiceService = app(InvoiceService::class);
            $invoice = $invoiceService->createInvoice($request->id, ['customer_id' => $request->customer_id]);

            $items = collect($data['items'] ?? [])
                ->map(fn ($i) => ['item_id' => (int) $i['item_id'], 'quantity' => (int) $i['quantity']])
                ->values()
                ->all();

            if ($items) {
                $invoiceService->addOrderItemsToInvoice($invoice->id, $items);
            }

            return $invoice;
        });
    }

    public function getAllRequests(array $filters = []): LengthAwarePaginator
    {
        $query = Request::with('categories', 'customer');

        if (!empty($filters['search'])) {
            $query->where(function ($q) use ($filters) {
                $q->where('customer_name', 'like', '%' . $filters['search'] . '%')
                    ->orWhere('description', 'like', '%' . $filters['search'] . '%');
            });
        }

        if (!empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        return $query->latest()->paginate(10);
    }
    public function updateRequest(int $requestId, array $data): Request
    {
        $request = Request::findOrFail($requestId);

        $request->update([
            'customer_name' => $data['customer_name'] ?? $request->customer_name,
            'description'   => $data['description']   ?? $request->description,
            'customer_id'   => $data['customer_id']   ?? $request->customer_id,
        ]);

        if (isset($data['category_ids'])) {
            $categoryIds = is_array($data['category_ids'])
                ? $data['category_ids']
                : [$data['category_ids']];

            $request->categories()->sync($categoryIds);
        }

        return $request->load('categories');
    }

    public function deleteRequest(int $requestId): void
    {
        $request = Request::findOrFail($requestId);
        $request->categories()->detach();
        $request->delete();
    }

    public function showRequest(int $requestId): Request
    {
        return Request::with('categories', 'customer', 'invoice')->findOrFail($requestId);
    }
}
