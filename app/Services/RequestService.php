<?php

namespace App\Services;

use App\Models\Request;
use Illuminate\Support\Collection;

class RequestService
{
    public function createRequest(array $data): Request
    {
        $request = Request::create([
            'customer_name' => $data['customer_name'],
            'description'   => $data['description'],
            'status'        => $data['status'],
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

    public function getAllRequests(): Collection
    {
        return Request::with('categories', 'customer')->get();
    }

    public function updateRequest(int $requestId, array $data): Request
    {
        $request = Request::findOrFail($requestId);

        $request->update([
            'customer_name' => $data['customer_name'] ?? $request->customer_name,
            'description'   => $data['description']   ?? $request->description,
            'status'        => $data['status']         ?? $request->status,
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
