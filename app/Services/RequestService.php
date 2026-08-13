<?php

namespace App\Services;

use App\Models\Request;
use Illuminate\Support\Collection;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class RequestService
{
    public function createRequest(array $data): Request
    {
        $request = Request::create([
            'customer_name' => $data['customer_name'],
            'description'   => $data['description'],
            'status'        => \App\Models\Request::STATUS_PENDING,
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
