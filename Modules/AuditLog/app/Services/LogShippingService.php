<?php

declare(strict_types=1);

namespace Modules\AuditLog\Services;

use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Modules\AuditLog\Enums\AuditAction;
use Modules\AuditLog\Enums\LogLevel;
use Modules\AuditLog\Enums\SyncStatus;
use Modules\AuditLog\Exceptions\LogShippingException;
use Modules\AuditLog\Models\AuditLog;
use Modules\AuditLog\Models\AuditLogBatch;
use Throwable;

/**
 * موتور ارسال لاگ‌ها به StoreServer (الگوی Outbox + Backoff).
 *
 * جریان کار:
 *   1) رکوردهای pending/failed که زمان تلاش‌شان رسیده انتخاب می‌شوند.
 *   2) یک batch ساخته و رکوردها به آن قفل می‌شوند (sync_status = sending).
 *   3) payload ساخته، امضا و ارسال می‌شود.
 *   4) در صورت موفقیت synced و در غیر این صورت failed با next_attempt_at.
 *
 * تضمین‌ها: at-least-once + Idempotency-Key تا سرور بتواند تکراری‌ها را حذف کند.
 */
class LogShippingService
{
    private const LOCK_KEY      = 'auditlog:shipping:lock';
    private const LAST_RUN_KEY  = 'auditlog:shipping:last-run';

    public function __construct(
        private readonly LogPayloadBuilder $builder,
        private readonly StoreServerClient $client,
        private readonly AuditLogger $audit,
    ) {
    }

    /**
     * ارسال همه‌ی دسته‌های آماده.
     *
     * @return array{batches:int, sent:int, failed:int, skipped:bool, errors:list<string>}
     */
    public function shipPending(?int $maxBatches = null): array
    {
        $summary = ['batches' => 0, 'sent' => 0, 'failed' => 0, 'skipped' => false, 'errors' => []];

        if (! $this->enabled()) {
            $summary['skipped'] = true;

            return $summary;
        }

        $lock = Cache::lock(self::LOCK_KEY, (int) config('auditlog.shipping.auto.lock_seconds', 120));

        if (! $lock->get()) {
            $summary['skipped'] = true;
            $summary['errors'][] = 'یک فرآیند ارسال دیگر در حال اجراست.';

            return $summary;
        }

        try {
            $maxBatches ??= (int) config('auditlog.shipping.max_batches_per_run', 5);
            $batchSize = max(1, (int) config('auditlog.shipping.batch_size', 200));

            for ($i = 0; $i < $maxBatches; $i++) {
                $logs = $this->nextChunk($batchSize);

                if ($logs->isEmpty()) {
                    break;
                }

                $summary['batches']++;

                try {
                    $sent = $this->shipChunk($logs);
                    $summary['sent'] += $sent;
                } catch (LogShippingException $e) {
                    $summary['failed'] += $logs->count();
                    $summary['errors'][] = $e->getMessage();

                    if (! $e->retryable) {
                        break; // پیکربندی یا داده اشکال دارد؛ ادامه بی‌فایده است
                    }
                }
            }

            Cache::put(self::LAST_RUN_KEY, now()->toIso8601String(), now()->addDay());
        } finally {
            $lock->release();
        }

        return $summary;
    }

    /**
     * ارسال یک دسته‌ی مشخص از رکوردها.
     *
     * @param  Collection<int, AuditLog>  $logs
     * @return int تعداد رکوردهای تأییدشده
     *
     * @throws LogShippingException
     */
    public function shipChunk(Collection $logs): int
    {
        $batchUuid = (string) Str::uuid();
        $payload   = $this->builder->build($batchUuid, $logs);

        $batch = AuditLogBatch::query()->create([
            'uuid'          => $batchUuid,
            'status'        => AuditLogBatch::STATUS_SENDING,
            'log_count'     => $logs->count(),
            'checksum'      => $payload['batch']['checksum'],
            'payload_bytes' => strlen((string) json_encode($payload, JSON_UNESCAPED_UNICODE)),
            'endpoint'      => $this->safeEndpoint(),
            'attempts'      => 1,
            'dispatched_at' => now(),
        ]);

        $this->markSending($logs, $batchUuid);

        try {
            $result = $this->client->sendBatch($batchUuid, $payload);
        } catch (LogShippingException $e) {
            $batch->markFailed($e->httpStatus, $e->errorCode, $e->getMessage(), 0, $e->response);
            $this->markFailed($logs, $e->getMessage(), $e->retryable);

            $this->audit->sync(
                AuditAction::LogsShipFailed,
                'ارسال دسته لاگ به StoreServer ناموفق بود',
                ['batch' => $batchUuid, 'count' => $logs->count(), 'error' => $e->errorCode, 'message' => $e->getMessage()],
                LogLevel::Error,
            );

            throw $e;
        }

        $accepted = $this->acceptedUuids($result['body'], $logs);
        $rejected = $logs->pluck('uuid')->diff($accepted)->values();

        $batch->markSent($result['status'], $result['body'], $result['duration_ms']);
        $this->markSynced($accepted, $batchUuid, $result['body']);

        if ($rejected->isNotEmpty()) {
            $this->markRejected($rejected, (string) (data_get($result['body'], 'data.message') ?? 'رکورد توسط سرور پذیرفته نشد'));
        }

        $this->audit->sync(
            AuditAction::LogsShipped,
            sprintf('%d رکورد لاگ با موفقیت به StoreServer ارسال شد', $accepted->count()),
            [
                'batch'       => $batchUuid,
                'accepted'    => $accepted->count(),
                'rejected'    => $rejected->count(),
                'duration_ms' => $result['duration_ms'],
                'http_status' => $result['status'],
            ],
        );

        return $accepted->count();
    }

    /**
     * اجرای ارسال در صورت رسیدن موعد (برای اپ دسکتاپ که cron ندارد).
     */
    public function shipDueIfNeeded(): void
    {
        if (! $this->enabled() || ! (bool) config('auditlog.shipping.auto.enabled', true)) {
            return;
        }

        $interval = (int) config('auditlog.shipping.auto.interval_seconds', 300);
        $lastRun  = Cache::get(self::LAST_RUN_KEY);

        if (is_string($lastRun) && Carbon::parse($lastRun)->addSeconds($interval)->isFuture()) {
            return;
        }

        try {
            if (! $this->hasPending()) {
                Cache::put(self::LAST_RUN_KEY, now()->toIso8601String(), now()->addDay());

                return;
            }

            $connection = config('auditlog.shipping.auto.queue');

            if ($connection !== null && $connection !== '') {
                \Modules\AuditLog\Jobs\ShipAuditLogsJob::dispatch()->onConnection((string) $connection);

                return;
            }

            $this->shipPending();
        } catch (Throwable $e) {
            Log::warning('[AuditLog] ارسال خودکار لاگ‌ها ناموفق بود: ' . $e->getMessage());
        }
    }

    /**
     * وضعیت فعلی صف ارسال.
     *
     * @return array<string, mixed>
     */
    public function status(): array
    {
        $counts = AuditLog::query()
            ->select('sync_status', DB::raw('count(*) as aggregate'))
            ->groupBy('sync_status')
            ->pluck('aggregate', 'sync_status')
            ->all();

        return [
            'enabled'       => $this->enabled(),
            'endpoint'      => $this->safeEndpoint(),
            'counts'        => $counts,
            'pending'       => AuditLog::query()->shippable()->count(),
            'dead'          => (int) ($counts[SyncStatus::Dead->value] ?? 0),
            'last_run_at'   => Cache::get(self::LAST_RUN_KEY),
            'last_batches'  => AuditLogBatch::query()->latest('id')->limit(5)->get([
                'uuid', 'status', 'log_count', 'http_status', 'error_code', 'duration_ms', 'created_at',
            ]),
            'oldest_pending' => AuditLog::query()->shippable()->value('occurred_at'),
        ];
    }

    public function hasPending(): bool
    {
        return AuditLog::query()->shippable()->exists();
    }

    /** بازگرداندن رکوردهای dead/failed به صف ارسال. */
    public function retryFailed(bool $includeDead = true): int
    {
        $statuses = [SyncStatus::Failed->value];

        if ($includeDead) {
            $statuses[] = SyncStatus::Dead->value;
        }

        return AuditLog::query()
            ->whereIn('sync_status', $statuses)
            ->update([
                'sync_status'     => SyncStatus::Pending->value,
                'next_attempt_at' => null,
                'last_error'      => null,
                'updated_at'      => now(),
            ]);
    }

    /**
     * آزادسازی رکوردهایی که در وضعیت sending گیر کرده‌اند (کرش وسط ارسال).
     */
    public function releaseStuck(int $olderThanMinutes = 15): int
    {
        return AuditLog::query()
            ->where('sync_status', SyncStatus::Sending->value)
            ->where('updated_at', '<=', now()->subMinutes($olderThanMinutes))
            ->update([
                'sync_status'     => SyncStatus::Pending->value,
                'next_attempt_at' => null,
                'updated_at'      => now(),
            ]);
    }

    /** @return Collection<int, AuditLog> */
    private function nextChunk(int $size): Collection
    {
        return AuditLog::query()->shippable()->limit($size)->get();
    }

    /** @param Collection<int, AuditLog> $logs */
    private function markSending(Collection $logs, string $batchUuid): void
    {
        AuditLog::query()->whereIn('id', $logs->modelKeys())->update([
            'sync_status' => SyncStatus::Sending->value,
            'batch_uuid'  => $batchUuid,
            'updated_at'  => now(),
        ]);
    }

    /** @param Collection<int, string> $uuids */
    private function markSynced(Collection $uuids, string $batchUuid, array $response): void
    {
        if ($uuids->isEmpty()) {
            return;
        }

        $remoteId = (string) (data_get($response, 'data.batch_id') ?? data_get($response, 'data.id') ?? '');

        $uuids->chunk(300)->each(function (Collection $chunk) use ($batchUuid, $remoteId): void {
            AuditLog::query()->whereIn('uuid', $chunk->all())->update([
                'sync_status'     => SyncStatus::Synced->value,
                'synced_at'       => now(),
                'batch_uuid'      => $batchUuid,
                'next_attempt_at' => null,
                'last_error'      => null,
                'remote_id'       => $remoteId !== '' ? mb_substr($remoteId, 0, 64) : null,
                'updated_at'      => now(),
            ]);
        });
    }

    /** @param Collection<int, AuditLog> $logs */
    private function markFailed(Collection $logs, string $message, bool $retryable): void
    {
        $maxAttempts = (int) config('auditlog.shipping.max_attempts', 8);
        /** @var list<int> $backoff */
        $backoff = (array) config('auditlog.shipping.backoff', [60]);

        foreach ($logs as $log) {
            $attempts = (int) $log->sync_attempts + 1;
            $dead = ! $retryable || $attempts >= $maxAttempts;
            $delay = $backoff[min($attempts - 1, count($backoff) - 1)] ?? 3600;

            AuditLog::query()->whereKey($log->getKey())->update([
                'sync_status'     => $dead ? SyncStatus::Dead->value : SyncStatus::Failed->value,
                'sync_attempts'   => $attempts,
                'next_attempt_at' => $dead ? null : now()->addSeconds((int) $delay),
                'last_error'      => mb_substr($message, 0, 512),
                'updated_at'      => now(),
            ]);
        }
    }

    /** @param Collection<int, string> $uuids */
    private function markRejected(Collection $uuids, string $message): void
    {
        AuditLog::query()->whereIn('uuid', $uuids->all())->update([
            'sync_status'     => SyncStatus::Failed->value,
            'next_attempt_at' => now()->addSeconds((int) (config('auditlog.shipping.backoff.0') ?? 60)),
            'last_error'      => mb_substr($message, 0, 512),
            'updated_at'      => now(),
        ]);
    }

    /**
     * سرور می‌تواند لیست uuidهای پذیرفته‌شده را برگرداند؛
     * اگر برنگرداند، کل دسته پذیرفته‌شده فرض می‌شود (2xx).
     *
     * @param  array<string, mixed>  $body
     * @param  Collection<int, AuditLog>  $logs
     * @return Collection<int, string>
     */
    private function acceptedUuids(array $body, Collection $logs): Collection
    {
        $accepted = data_get($body, 'data.accepted');

        if (is_array($accepted) && $accepted !== [] && is_string($accepted[array_key_first($accepted)] ?? null)) {
            return collect($accepted)->values();
        }

        $rejected = data_get($body, 'data.rejected');

        if (is_array($rejected) && $rejected !== []) {
            $rejectedUuids = collect($rejected)->map(
                static fn ($item): string => is_array($item) ? (string) ($item['uuid'] ?? '') : (string) $item,
            )->filter()->values();

            return $logs->pluck('uuid')->diff($rejectedUuids)->values();
        }

        return $logs->pluck('uuid')->values();
    }

    private function enabled(): bool
    {
        return (bool) config('auditlog.enabled', true) && (bool) config('auditlog.shipping.enabled', true);
    }

    private function safeEndpoint(): string
    {
        try {
            return $this->client->ingestUrl();
        } catch (Throwable) {
            return '';
        }
    }
}
