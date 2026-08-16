<?php

namespace App\Services;

use App\Models\ArchiveAction;
use App\Models\ArchivedRecord;
use App\Models\Invoice;
use App\Models\Request as ServiceRequest;
use App\Models\ServiceJob;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ArchiveService
{
    public const TYPE_INVOICE     = ArchivedRecord::TYPE_INVOICE;
    public const TYPE_REQUEST     = ArchivedRecord::TYPE_REQUEST;
    public const TYPE_SERVICE_JOB = ArchivedRecord::TYPE_SERVICE_JOB;

    public const VALID_TYPES = [self::TYPE_INVOICE, self::TYPE_REQUEST, self::TYPE_SERVICE_JOB];

    private const SOURCE_TABLES = [
        self::TYPE_INVOICE     => 'invoices',
        self::TYPE_REQUEST     => 'requests',
        self::TYPE_SERVICE_JOB => 'service_jobs',
    ];

    /* ------------------------------------------------------------------ */
    /* Listing / retrieval                                                */
    /* ------------------------------------------------------------------ */

    public function paginate(array $filters = []): LengthAwarePaginator
    {
        $perPage = max(1, min((int) ($filters['per_page'] ?? 20), 100));

        return $this->baseQuery($filters)->paginate($perPage);
    }

    public function findOrFail(int $archivedRecordId): ArchivedRecord
    {
        return ArchivedRecord::findOrFail($archivedRecordId);
    }

    /**
     * لیست کامل (بدون صفحه‌بندی) برای استفاده در اکسپورت اکسل هر بخش.
     * چون همه‌ی داده‌ها از روی ستون‌های denormalize‌شده و snapshot_json
     * خوانده می‌شوند، حتی اگر رکورد مبدا حذف (soft/force) شده باشد،
     * اکسپورت همچنان کامل و صحیح باقی می‌ماند.
     */
    public function exportQuery(string $sourceType, array $filters = [], ?int $actorId = null): Collection
    {
        $this->assertValidType($sourceType);

        $filters['source_type'] = $sourceType;
        $records = $this->baseQuery($filters)->get();

        $this->logAction(
            archivedRecordId: null,
            sourceType: $sourceType,
            sourceId: null,
            action: 'exported',
            actorId: $actorId,
            reason: null,
            payload: ['filters' => $filters, 'rows' => $records->count()],
        );

        return $records;
    }

    private function baseQuery(array $filters): Builder
    {
        $query = ArchivedRecord::query()->orderByDesc('archived_at')->orderByDesc('id');

        if (!empty($filters['source_type'])) {
            $this->assertValidType($filters['source_type']);
            $query->where('source_type', $filters['source_type']);
        }

        if (!empty($filters['archive_status'])) {
            $query->where('archive_status', $filters['archive_status']);
        }

        if (!empty($filters['search'])) {
            $search = trim((string) $filters['search']);
            $query->where(function (Builder $q) use ($search) {
                $q->where('invoice_number', 'like', "%{$search}%")
                    ->orWhere('customer_name', 'like', "%{$search}%")
                    ->orWhere('title', 'like', "%{$search}%");
            });
        }

        if (!empty($filters['from'])) {
            $query->whereDate('archived_at', '>=', $filters['from']);
        }

        if (!empty($filters['to'])) {
            $query->whereDate('archived_at', '<=', $filters['to']);
        }

        return $query;
    }

    /* ------------------------------------------------------------------ */
    /* Copy paid records into the archive (idempotent)                    */
    /* ------------------------------------------------------------------ */

    /**
     * روی همه‌ی فاکتورهای paid و درخواست‌ها/سرویس‌هایی که فاکتور مرتبطشان
     * paid است اجرا می‌شود و یک کپی/بروزرسانی در بایگانی می‌سازد.
     * idempotent است: اجرای مکرر رکورد تکراری نمی‌سازد، فقط snapshot را تازه می‌کند.
     */
    public function syncAllPaidCopies(?int $actorId = null): array
    {
        $result = [self::TYPE_INVOICE => 0, self::TYPE_REQUEST => 0, self::TYPE_SERVICE_JOB => 0];

        Invoice::query()
            ->where('payment_status', Invoice::PAYMENT_PAID)
            ->pluck('id')
            ->each(function ($id) use (&$result, $actorId) {
                $this->copyPaidToArchive(self::TYPE_INVOICE, (int) $id, $actorId, 'همگام‌سازی خودکار بایگانی');
                $result[self::TYPE_INVOICE]++;
            });

        ServiceRequest::query()
            ->whereHas('invoice', fn(Builder $q) => $q->where('payment_status', Invoice::PAYMENT_PAID))
            ->pluck('id')
            ->each(function ($id) use (&$result, $actorId) {
                $this->copyPaidToArchive(self::TYPE_REQUEST, (int) $id, $actorId, 'همگام‌سازی خودکار بایگانی');
                $result[self::TYPE_REQUEST]++;
            });

        ServiceJob::query()
            ->whereHas('invoice', fn(Builder $q) => $q->where('payment_status', Invoice::PAYMENT_PAID))
            ->pluck('id')
            ->each(function ($id) use (&$result, $actorId) {
                $this->copyPaidToArchive(self::TYPE_SERVICE_JOB, (int) $id, $actorId, 'همگام‌سازی خودکار بایگانی');
                $result[self::TYPE_SERVICE_JOB]++;
            });

        return $result;
    }

    public function copyPaidToArchive(
        string $sourceType,
        int $sourceId,
        ?int $actorId = null,
        ?string $reason = null
    ): ArchivedRecord {
        $this->assertValidType($sourceType);

        return DB::transaction(function () use ($sourceType, $sourceId, $actorId, $reason) {
            [$source, $invoice] = $this->resolveSourceAndInvoice($sourceType, $sourceId);

            $this->assertInvoiceIsPaid($invoice);

            $snapshot = $this->buildSnapshot($sourceType, $source, $invoice);
            $snapshotJson = json_encode($snapshot, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            $customer = $this->resolveCustomer($source, $invoice);

            $attributes = [
                'source_table'      => self::SOURCE_TABLES[$sourceType],
                'invoice_id'        => $invoice->id,
                'invoice_number'    => $invoice->invoice_number,
                'customer_id'       => $customer['id'],
                'customer_name'     => $customer['name'],
                'title'             => $this->makeTitle($sourceType, $source, $invoice),
                'payment_status'    => $invoice->payment_status,
                'total_amount'      => $this->resolveArchiveAmount($sourceType, $source, $invoice),
                'paid_at'           => $invoice->paid_at,
                'source_created_at' => $source->created_at,
                'source_updated_at' => $source->updated_at,
                'snapshot_json'     => $snapshot,
                'snapshot_hash'     => hash('sha256', $snapshotJson),
                'archived_by'       => $actorId,
                'archived_at'       => now(),
                'reason'            => $reason,
                'metadata_json'     => ['schema_version' => 1, 'generated_by' => static::class],
            ];

            /** @var ArchivedRecord|null $record */
            $record = ArchivedRecord::where('source_type', $sourceType)
                ->where('source_id', $sourceId)
                ->first();

            if ($record) {
                $record->fill($attributes)->save();

                $this->logAction($record->id, $sourceType, $sourceId, 'copied', $actorId, $reason, [
                    'mode' => 'refresh_existing_copy',
                ]);

                return $record->fresh();
            }

            $record = ArchivedRecord::create(array_merge($attributes, [
                'source_type'    => $sourceType,
                'source_id'      => $sourceId,
                'archive_status' => ArchivedRecord::STATUS_COPIED,
            ]));

            $this->logAction($record->id, $sourceType, $sourceId, 'copied', $actorId, $reason, [
                'mode' => 'new_copy',
            ]);

            return $record;
        });
    }

    /* ------------------------------------------------------------------ */
    /* Transfer: copy (if needed) then soft-delete the source record      */
    /* ------------------------------------------------------------------ */

    public function transferToArchive(
        string $sourceType,
        int $sourceId,
        ?int $actorId = null,
        ?string $reason = null
    ): ArchivedRecord {
        $this->assertValidType($sourceType);

        return DB::transaction(function () use ($sourceType, $sourceId, $actorId, $reason) {
            $record = $this->copyPaidToArchive($sourceType, $sourceId, $actorId, $reason);

            [$source, $invoice] = $this->resolveSourceAndInvoice($sourceType, $sourceId);
            $this->assertInvoiceIsPaid($invoice);

            $alreadyTrashed = method_exists($source, 'trashed') && $source->trashed();

            if (!$alreadyTrashed) {
                $source->delete(); // soft delete (مدل‌ها از SoftDeletes استفاده می‌کنند)
            }

            $record->forceFill([
                'archive_status'         => ArchivedRecord::STATUS_TRANSFERRED,
                'removed_from_source_at' => $record->removed_from_source_at ?? now(),
                'deletion_mode'          => 'soft_delete',
            ])->save();

            $this->logAction($record->id, $sourceType, $sourceId, 'transferred', $actorId, $reason, [
                'already_deleted' => $alreadyTrashed,
            ]);

            return $record->fresh();
        });
    }

    public function transferArchiveRecord(
        int $archivedRecordId,
        ?int $actorId = null,
        ?string $reason = null
    ): ArchivedRecord {
        $record = $this->findOrFail($archivedRecordId);

        return $this->transferToArchive($record->source_type, (int) $record->source_id, $actorId, $reason);
    }

    /* ------------------------------------------------------------------ */
    /* Restore: undo a transfer (bring the source back from soft-delete)  */
    /* ------------------------------------------------------------------ */

    public function restoreFromArchive(int $archivedRecordId, ?int $actorId = null): ArchivedRecord
    {
        return DB::transaction(function () use ($archivedRecordId, $actorId) {
            $record = $this->findOrFail($archivedRecordId);

            $modelClass = match ($record->source_type) {
                self::TYPE_INVOICE     => Invoice::class,
                self::TYPE_REQUEST     => ServiceRequest::class,
                self::TYPE_SERVICE_JOB => ServiceJob::class,
            };

            /** @var Model|null $source */
            $source = $modelClass::withTrashed()->find($record->source_id);

            if ($source && method_exists($source, 'trashed') && $source->trashed()) {
                $source->restore();
            }

            $record->forceFill([
                'archive_status'         => ArchivedRecord::STATUS_COPIED,
                'removed_from_source_at' => null,
                'deletion_mode'          => null,
            ])->save();

            $this->logAction($record->id, $record->source_type, (int) $record->source_id, 'restored', $actorId, null, []);

            return $record->fresh();
        });
    }

    /* ------------------------------------------------------------------ */
    /* Remove only the archive entry itself (soft delete)                 */
    /* ------------------------------------------------------------------ */

    public function softDeleteArchiveRecord(int $archivedRecordId, ?int $actorId = null, ?string $reason = null): void
    {
        DB::transaction(function () use ($archivedRecordId, $actorId, $reason) {
            $record = $this->findOrFail($archivedRecordId);
            $record->delete();

            $this->logAction($record->id, $record->source_type, (int) $record->source_id, 'deleted', $actorId, $reason, []);
        });
    }

    /* ------------------------------------------------------------------ */
    /* Internal helpers                                                   */
    /* ------------------------------------------------------------------ */

    private function assertValidType(string $sourceType): void
    {
        if (!in_array($sourceType, self::VALID_TYPES, true)) {
            throw ValidationException::withMessages(['source_type' => 'نوع بایگانی نامعتبر است.']);
        }
    }

    private function assertInvoiceIsPaid(?Invoice $invoice): void
    {
        if (!$invoice || $invoice->payment_status !== Invoice::PAYMENT_PAID) {
            throw ValidationException::withMessages([
                'invoice' => 'فقط مواردی قابل بایگانی هستند که فاکتور مرتبط آن‌ها پرداخت‌شده (paid) باشد.',
            ]);
        }
    }

    private function resolveSourceAndInvoice(string $sourceType, int $sourceId): array
    {
        return match ($sourceType) {
            self::TYPE_INVOICE     => $this->resolveInvoiceSource($sourceId),
            self::TYPE_REQUEST     => $this->resolveRequestSource($sourceId),
            self::TYPE_SERVICE_JOB => $this->resolveServiceJobSource($sourceId),
        };
    }

    private function resolveInvoiceSource(int $sourceId): array
    {
        $invoice = Invoice::withTrashed()
            ->with([
                'customer',
                'request.categories',
                'orderItems.item',
                'serviceJobs.serviceTypes.serviceType',
                'serviceJobs.items.item',
                'adjustments',
            ])
            ->findOrFail($sourceId);

        return [$invoice, $invoice];
    }

    private function resolveRequestSource(int $sourceId): array
    {
        $request = ServiceRequest::withTrashed()
            ->with([
                'customer',
                'categories',
                'invoice.customer',
                'invoice.orderItems.item',
                'invoice.adjustments',
                'serviceJobs.items.item',
                'serviceJobs.serviceTypes.serviceType',
            ])
            ->findOrFail($sourceId);

        $invoice = $request->invoice
            ?: Invoice::withTrashed()->where('request_id', $request->id)->latest()->first();

        return [$request, $invoice];
    }

    private function resolveServiceJobSource(int $sourceId): array
    {
        $serviceJob = ServiceJob::withTrashed()
            ->with([
                'customer',
                'request.categories',
                'invoice.customer',
                'invoice.orderItems.item',
                'invoice.adjustments',
                'items.item',
                'serviceTypes.serviceType',
            ])
            ->findOrFail($sourceId);

        $invoice = $serviceJob->invoice_id
            ? Invoice::withTrashed()->with('customer')->find($serviceJob->invoice_id)
            : null;

        return [$serviceJob, $invoice];
    }

    private function buildSnapshot(string $sourceType, Model $source, Invoice $invoice): array
    {
        return [
            'schema_version' => 1,
            'source_type'    => $sourceType,
            'source_table'   => self::SOURCE_TABLES[$sourceType],
            'source_id'      => $source->getKey(),
            'archived_at'    => now()->toISOString(),
            'source'         => $source->toArray(),
            'paid_invoice'   => $invoice->toArray(),
        ];
    }

    private function resolveCustomer(Model $source, Invoice $invoice): array
    {
        $customer = $source->relationLoaded('customer') ? $source->getRelation('customer') : null;
        $customer ??= $invoice->relationLoaded('customer') ? $invoice->getRelation('customer') : null;

        return [
            'id'   => $customer?->id ?? $invoice->customer_id ?? $source->getAttribute('customer_id'),
            'name' => $customer?->name ?? $source->getAttribute('customer_name'),
        ];
    }

    private function makeTitle(string $sourceType, Model $source, Invoice $invoice): string
    {
        return match ($sourceType) {
            self::TYPE_INVOICE     => 'فاکتور ' . ($invoice->invoice_number ?: ('#' . $invoice->id)),
            self::TYPE_REQUEST     => 'درخواست #' . $source->getKey(),
            self::TYPE_SERVICE_JOB => 'سرویس ' . ($source->device_type ?: ('#' . $source->getKey())),
        };
    }

    private function logAction(
        ?int $archivedRecordId,
        ?string $sourceType,
        ?int $sourceId,
        string $action,
        ?int $actorId,
        ?string $reason,
        array $payload = []
    ): void {
        ArchiveAction::create([
            'archived_record_id' => $archivedRecordId,
            'source_type'        => $sourceType,
            'source_id'          => $sourceId,
            'action'             => $action,
            'actor_id'           => $actorId,
            'reason'             => $reason,
            'payload_json'       => $payload,
        ]);
    }
    private function resolveArchiveAmount(string $sourceType, Model $source, Invoice $invoice): float
    {
        return match ($sourceType) {
            self::TYPE_SERVICE_JOB => (float) (
                $source->final_price
                ?? $source->estimated_price
                ?? 0
            ),

            default => (float) ($invoice->final_amount ?? $invoice->total_amount ?? 0),
        };
    }
}
