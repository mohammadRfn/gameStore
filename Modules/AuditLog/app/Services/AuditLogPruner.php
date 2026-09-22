<?php

declare(strict_types=1);

namespace Modules\AuditLog\Services;

use Illuminate\Support\Facades\DB;
use Modules\AuditLog\Enums\AuditAction;
use Modules\AuditLog\Enums\LogLevel;
use Modules\AuditLog\Enums\SyncStatus;
use Modules\AuditLog\Models\AuditLog;
use Modules\AuditLog\Models\AuditLogBatch;

/**
 * پاک‌سازی لاگ‌های قدیمی با رعایت سیاست نگه‌داری.
 * رکورد ارسال‌نشده (اگر keep_unsynced فعال باشد) هرگز حذف نمی‌شود.
 */
class AuditLogPruner
{
    public function __construct(private readonly AuditLogger $audit)
    {
    }

    /**
     * @return array{deleted:int, batches:int, dry_run:bool, cutoffs:array<string, string>}
     */
    public function prune(?int $days = null, bool $dryRun = false): array
    {
        $days ??= (int) config('auditlog.retention.days', 120);
        /** @var array<string, int> $overrides */
        $overrides = (array) config('auditlog.retention.level_overrides', []);
        $keepUnsynced = (bool) config('auditlog.retention.keep_unsynced', true);
        $chunk = max(100, (int) config('auditlog.retention.chunk', 1000));

        $cutoffs = [];
        $deleted = 0;

        // اجازه‌ی حذف فقط در همین فرآیند (نگهبان مدل AuditLog)
        app()->instance('auditlog.pruning', true);

        try {
            foreach (LogLevel::cases() as $level) {
                $levelDays = (int) ($overrides[$level->value] ?? $days);
                $cutoff = now()->subDays($levelDays);
                $cutoffs[$level->value] = $cutoff->toDateTimeString();

                $query = AuditLog::query()
                    ->where('level', $level->value)
                    ->where('occurred_at', '<', $cutoff);

                if ($keepUnsynced) {
                    $query->whereIn('sync_status', [
                        SyncStatus::Synced->value,
                        SyncStatus::Skipped->value,
                    ]);
                }

                if ($dryRun) {
                    $deleted += $query->count();

                    continue;
                }

                do {
                    $ids = (clone $query)->limit($chunk)->pluck('id');

                    if ($ids->isEmpty()) {
                        break;
                    }

                    $deleted += DB::connection(config('auditlog.storage.connection') ?: null)
                        ->table('audit_logs')
                        ->whereIn('id', $ids->all())
                        ->delete();
                } while ($ids->count() === $chunk);
            }

            $batchCutoff = now()->subDays((int) config('auditlog.retention.batches_days', 30));

            $batches = $dryRun
                ? AuditLogBatch::query()->where('created_at', '<', $batchCutoff)->count()
                : AuditLogBatch::query()->where('created_at', '<', $batchCutoff)->delete();
        } finally {
            app()->forgetInstance('auditlog.pruning');
        }

        if (! $dryRun && $deleted > 0) {
            $this->audit->sync(AuditAction::LogsPruned, sprintf('%d رکورد لاگ قدیمی حذف شد', $deleted), [
                'deleted' => $deleted,
                'batches' => $batches,
                'days'    => $days,
            ]);
        }

        return [
            'deleted' => $deleted,
            'batches' => (int) $batches,
            'dry_run' => $dryRun,
            'cutoffs' => $cutoffs,
        ];
    }
}
