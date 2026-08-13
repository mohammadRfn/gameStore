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
        return ServiceJob::with('items.item', 'serviceTypes.serviceType', 'customer')->findOrFail($id);
    }

    public function createServiceJob(array $data): ServiceJob
    {
        return DB::transaction(function () use ($data) {
            $serviceJob = ServiceJob::create([
                'customer_id'                  => $data['customer_id']                  ?? null,
                'request_id'                   => $data['request_id']                   ?? null,
                'invoice_id'                   => $data['invoice_id']                   ?? null,
                'device_type'                  => $data['device_type']                  ?? null,
                'device_serial'                => $data['device_serial']                ?? null,
                'customer_problem_description' => $data['customer_problem_description'] ?? null,
                'diagnosis_description'        => $data['diagnosis_description']        ?? null,
                'status'                       => $data['status']                       ?? ServiceJob::STATUS_RECEIVED,
                'received_at'                  => $data['received_at']                  ?? now(),
                'started_at'                   => $data['started_at']                   ?? null,
                'completed_at'                 => $data['completed_at']                 ?? null,
                'delivered_at'                 => $data['delivered_at']                 ?? null,
            ]);

            if (!empty($data['items']) && is_array($data['items'])) {
                $this->syncServiceJobItems($serviceJob, $data['items']);
            }

            if (!empty($data['service_types']) && is_array($data['service_types'])) {
                $this->syncServiceTypes($serviceJob, $data['service_types']);
            }

            $this->recalcTotals($serviceJob);

            if ($serviceJob->request_id) {
                $serviceJob->request?->markInProgress();
            }

            return $serviceJob->load('items.item', 'serviceTypes', 'customer');
        });
    }

    public function updateServiceJob(int $id, array $data): ServiceJob
    {
        return DB::transaction(function () use ($id, $data) {
            $serviceJob = ServiceJob::findOrFail($id);

            $updatable = [
                'customer_id',
                'request_id',
                'invoice_id',
                'device_type',
                'device_serial',
                'customer_problem_description',
                'diagnosis_description',
                'status',
                'received_at',
                'started_at',
                'completed_at',
                'delivered_at',
            ];

            $updateData = [];
            foreach ($updatable as $field) {
                $updateData[$field] = array_key_exists($field, $data)
                    ? $data[$field]
                    : $serviceJob->{$field};
            }

            $serviceJob->update($updateData);

            if (array_key_exists('items', $data) && is_array($data['items'])) {
                $this->syncServiceJobItems($serviceJob, $data['items']);
            }

            if (array_key_exists('service_types', $data) && is_array($data['service_types'])) {
                $this->syncServiceTypes($serviceJob, $data['service_types']);
            }

            $this->recalcTotals($serviceJob);
            if ($serviceJob->request_id) {
                $serviceJob->request?->markInProgress();
            }
            return $serviceJob->load('items.item', 'serviceTypes', 'customer');
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

    /**
     * Sync a job's consumable-item rows. Each row is an independent
     * hasMany record (service_job_items.id), so the SAME item_id may
     * appear multiple times with different quantity/price. Rows carrying
     * an existing `id` are updated in place; rows without one are
     * created; any existing row not present in the incoming payload is
     * deleted.
     */
    protected function syncServiceJobItems(ServiceJob $serviceJob, array $items): void
    {
        DB::transaction(function () use ($serviceJob, $items) {
            $existingIds = $serviceJob->items()->pluck('id')->all();
            $keepIds = [];

            foreach ($items as $row) {
                if (empty($row['item_id']) || empty($row['quantity']) || empty($row['unit_price'])) {
                    continue;
                }

                $quantity  = (int) $row['quantity'];
                $unitPrice = (float) $row['unit_price'];
                $totalPrice = $quantity * $unitPrice;

                if (!empty($row['id']) && in_array($row['id'], $existingIds)) {
                    ServiceJobItem::where('id', $row['id'])->update([
                        'item_id'     => $row['item_id'],
                        'quantity'    => $quantity,
                        'unit_price'  => $unitPrice,
                        'total_price' => $totalPrice,
                    ]);
                    $keepIds[] = $row['id'];
                } else {
                    $created = ServiceJobItem::create([
                        'service_job_id' => $serviceJob->id,
                        'item_id'        => $row['item_id'],
                        'quantity'       => $quantity,
                        'unit_price'     => $unitPrice,
                        'total_price'    => $totalPrice,
                    ]);
                    $keepIds[] = $created->id;
                }
            }

            $toDelete = array_diff($existingIds, $keepIds);
            if (!empty($toDelete)) {
                ServiceJobItem::whereIn('id', $toDelete)->delete();
            }
        });
    }

    /**
     * Sync the service types attached to a job. Each row is now a real
     * hasMany record (service_job_service_types.id), so the SAME
     * service_type_id can appear multiple times with different prices.
     * Rows carrying an existing `id` are updated in place; rows without
     * an `id` are treated as new. Any existing row whose `id` is not
     * present in the incoming payload is deleted.
     */
    protected function syncServiceTypes(ServiceJob $serviceJob, array $serviceTypes): void
    {
        $existingIds = $serviceJob->serviceTypes()->pluck('id')->all();
        $keepIds = [];

        foreach ($serviceTypes as $row) {
            if (empty($row['service_type_id'])) {
                continue;
            }

            $price = $row['price'] ?? 0;

            if (!empty($row['id']) && in_array($row['id'], $existingIds)) {
                $serviceJob->serviceTypes()->where('id', $row['id'])->update([
                    'service_type_id' => $row['service_type_id'],
                    'price'           => $price,
                ]);
                $keepIds[] = $row['id'];
            } else {
                $created = $serviceJob->serviceTypes()->create([
                    'service_type_id' => $row['service_type_id'],
                    'price'           => $price,
                ]);
                $keepIds[] = $created->id;
            }
        }

        $toDelete = array_diff($existingIds, $keepIds);
        if (!empty($toDelete)) {
            $serviceJob->serviceTypes()->whereIn('id', $toDelete)->delete();
        }
    }

    /**
     * Recalculate estimated_price (sum of selected service types' prices)
     * and final_price (services total + consumable items total), and
     * persist them on the service job.
     */
    protected function recalcTotals(ServiceJob $serviceJob): void
    {
        $servicesTotal = (float) $serviceJob->serviceTypes()->sum('price');
        $itemsTotal    = (float) $serviceJob->items()->sum('total_price');

        $serviceJob->update([
            'estimated_price' => $servicesTotal,
            'final_price'     => $servicesTotal + $itemsTotal,
        ]);
    }
}
