<?php

namespace App\Services;

use App\Models\ServiceJob;
use App\Models\ServiceJobItem;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class ServiceJobService
{
    public function findServiceJob(int $id): ServiceJob
    {
        return ServiceJob::with('items.item', 'serviceType', 'customer')->findOrFail($id);
    }

    public function createServiceJob(array $data): ServiceJob
    {
        return DB::transaction(function () use ($data) {
            $serviceJob = ServiceJob::create([
                'customer_id'                  => $data['customer_id']                  ?? null,
                'request_id'                   => $data['request_id']                   ?? null,
                'service_type_id'              => $data['service_type_id']              ?? null,
                'invoice_id'                   => $data['invoice_id']                   ?? null,
                'device_type'                  => $data['device_type']                  ?? null,
                'device_serial'                => $data['device_serial']                ?? null,
                'customer_problem_description' => $data['customer_problem_description'] ?? null,
                'diagnosis_description'        => $data['diagnosis_description']        ?? null,
                'status'                       => $data['status']                       ?? ServiceJob::STATUS_RECEIVED,
                'estimated_price'              => $data['estimated_price']              ?? null,
                'final_price'                  => $data['final_price']                  ?? null,
                'received_at'                  => $data['received_at']                  ?? now(),
                'started_at'                   => $data['started_at']                   ?? null,
                'completed_at'                 => $data['completed_at']                 ?? null,
                'delivered_at'                 => $data['delivered_at']                 ?? null,
            ]);

            if (!empty($data['items']) && is_array($data['items'])) {
                $this->syncServiceJobItems($serviceJob, $data['items']);
            }

            return $serviceJob->load('items.item', 'serviceType', 'customer');
        });
    }

    public function updateServiceJob(int $id, array $data): ServiceJob
    {
        return DB::transaction(function () use ($id, $data) {
            $serviceJob = ServiceJob::findOrFail($id);

            $serviceJob->update([
                'customer_id'                  => $data['customer_id']                  ?? $serviceJob->customer_id,
                'request_id'                   => $data['request_id']                   ?? $serviceJob->request_id,
                'service_type_id'              => $data['service_type_id']              ?? $serviceJob->service_type_id,
                'invoice_id'                   => $data['invoice_id']                   ?? $serviceJob->invoice_id,
                'device_type'                  => $data['device_type']                  ?? $serviceJob->device_type,
                'device_serial'                => $data['device_serial']                ?? $serviceJob->device_serial,
                'customer_problem_description' => $data['customer_problem_description'] ?? $serviceJob->customer_problem_description,
                'diagnosis_description'        => $data['diagnosis_description']        ?? $serviceJob->diagnosis_description,
                'status'                       => $data['status']                       ?? $serviceJob->status,
                'estimated_price'              => $data['estimated_price']              ?? $serviceJob->estimated_price,
                'final_price'                  => $data['final_price']                  ?? $serviceJob->final_price,
                'received_at'                  => $data['received_at']                  ?? $serviceJob->received_at,
                'started_at'                   => $data['started_at']                   ?? $serviceJob->started_at,
                'completed_at'                 => $data['completed_at']                 ?? $serviceJob->completed_at,
                'delivered_at'                 => $data['delivered_at']                 ?? $serviceJob->delivered_at,
            ]);

            if (array_key_exists('items', $data) && is_array($data['items'])) {
                $this->syncServiceJobItems($serviceJob, $data['items']);
            }

            return $serviceJob->load('items.item', 'serviceType', 'customer');
        });
    }

    public function deleteServiceJob(int $id): void
    {
        $serviceJob = ServiceJob::withTrashed()->findOrFail($id);

        if (in_array($serviceJob->status, [ServiceJob::STATUS_COMPLETED, ServiceJob::STATUS_DELIVERED])) {
            throw new RuntimeException('Cannot delete completed jobs.');
        }

        $serviceJob->trashed() ? $serviceJob->forceDelete() : $serviceJob->delete();
    }

    protected function syncServiceJobItems(ServiceJob $serviceJob, array $items): void
    {
        DB::transaction(function () use ($serviceJob, $items) {
            $newItemIds = collect($items)->pluck('item_id')->filter()->unique()->values()->all();
            $existingItemIds = $serviceJob->items()->pluck('item_id')->filter()->unique()->values()->all();

            foreach ($items as $row) {
                if (empty($row['item_id']) || empty($row['quantity']) || empty($row['unit_price'])) {
                    continue;
                }

                $quantity   = (int) $row['quantity'];
                $unitPrice  = (float) $row['unit_price'];

                ServiceJobItem::updateOrCreate(
                    ['service_job_id' => $serviceJob->id, 'item_id' => $row['item_id']],
                    ['quantity' => $quantity, 'unit_price' => $unitPrice, 'total_price' => $quantity * $unitPrice]
                );
            }

            $toDelete = array_diff($existingItemIds, $newItemIds);
            if (!empty($toDelete)) {
                ServiceJobItem::where('service_job_id', $serviceJob->id)
                    ->whereIn('item_id', $toDelete)
                    ->delete();
            }
        });
    }
}
