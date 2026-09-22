<?php

declare(strict_types=1);

namespace Modules\AuditLog\Services;

use Illuminate\Support\Facades\DB;
use Modules\AuditLog\Support\HashChain;

/**
 * بررسی سلامت زنجیره‌ی هش لاگ‌ها؛ هر گونه ویرایش یا حذف رکورد
 * باعث شکستن زنجیره و گزارش آن می‌شود.
 */
class IntegrityVerifier
{
    public function __construct(private readonly HashChain $chain)
    {
    }

    /**
     * @return array{checked:int, valid:bool, broken:list<array<string, mixed>>, first_id:int|null, last_id:int|null}
     */
    public function verify(?int $fromId = null, ?int $limit = null): array
    {
        $checked = 0;
        $broken = [];
        $previousHash = null;
        $previousSequence = null;
        $firstId = null;
        $lastId = null;

        if (! $this->chain->enabled()) {
            return ['checked' => 0, 'valid' => true, 'broken' => [], 'first_id' => null, 'last_id' => null];
        }

        $query = DB::connection(config('auditlog.storage.connection') ?: null)
            ->table('audit_logs')
            ->when($fromId !== null, fn ($q) => $q->where('id', '>=', $fromId))
            ->orderBy('id');

        if ($limit !== null) {
            $query->limit($limit);
        }

        foreach ($query->cursor() as $record) {
            $row = (array) $record;

            foreach (['old_values', 'new_values', 'changed_keys', 'context', 'tags'] as $json) {
                if (isset($row[$json]) && is_string($row[$json])) {
                    $row[$json] = json_decode($row[$json], true);
                }
            }

            $firstId ??= (int) $row['id'];
            $lastId = (int) $row['id'];
            $checked++;

            // رکورد اول زنجیره (یا شروع از وسط) با previous_hash خودش سنجیده می‌شود
            $expectedPrevious = $previousHash ?? ($row['previous_hash'] ?? null);

            if (! $this->chain->matches($row, $expectedPrevious)) {
                $broken[] = [
                    'id'       => (int) $row['id'],
                    'uuid'     => (string) $row['uuid'],
                    'sequence' => (int) $row['sequence'],
                    'reason'   => 'hash_mismatch',
                ];
            }

            if ($previousSequence !== null && (int) $row['sequence'] !== $previousSequence + 1) {
                $broken[] = [
                    'id'       => (int) $row['id'],
                    'uuid'     => (string) $row['uuid'],
                    'sequence' => (int) $row['sequence'],
                    'reason'   => 'sequence_gap',
                    'expected' => $previousSequence + 1,
                ];
            }

            $previousHash = $row['hash'] ?? null;
            $previousSequence = (int) $row['sequence'];
        }

        return [
            'checked'  => $checked,
            'valid'    => $broken === [],
            'broken'   => $broken,
            'first_id' => $firstId,
            'last_id'  => $lastId,
        ];
    }
}
