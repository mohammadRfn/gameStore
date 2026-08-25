<?php

namespace App\Services\Backup;

use App\Models\BackupRun;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use RuntimeException;
use Throwable;

/**
 * تزریق داده‌های CSV به دیتابیس SQLite.
 *
 * استراتژی‌ها:
 *   merge          : upsert بر اساس id یا natural key (پیش‌فرض، امن‌ترین)
 *   replace        : خالی کردن جدول و درج مجدد با حفظ id ها
 *   skip_existing  : فقط رکوردهای جدید درج می‌شوند
 *   fail_on_conflict : در صورت وجود رکورد تکراری، کل عملیات rollback می‌شود
 *
 * نکات فنی مهم برای SQLite:
 *   - کل عملیات داخل یک تراکنش انجام می‌شود؛ در صورت خطا rollback کامل.
 *   - PRAGMA foreign_keys موقتاً خاموش می‌شود تا ترتیب درج مشکل‌ساز نشود و
 *     در انتها با PRAGMA foreign_key_check صحت ارجاع‌ها بررسی می‌گردد.
 *   - پس از درج با id صریح، sqlite_sequence اصلاح می‌شود تا id بعدی درست باشد.
 */
class DatabaseImportService
{
    public function __construct(
        private readonly BackupManifest $manifest,
        private readonly BackupPathResolver $paths,
        private readonly BackupRunRecorder $recorder,
    ) {}

    /**
     * @param  array<string, array<string, mixed>>  $entities
     * @return array<string, array<string, mixed>>
     */
    public function import(BackupRun $run, string $sourcePath, array $entities, array $options = []): array
    {
        $strategy = $options['strategy'] ?? BackupRun::STRATEGY_MERGE;
        $dryRun   = (bool) ($options['dry_run'] ?? false);
        $report   = [];

        $connection = DB::connection();
        $isSqlite   = $connection->getDriverName() === 'sqlite';

        if ($isSqlite) {
            $connection->statement('PRAGMA foreign_keys = OFF');
        }

        // ── استراتژی reindex: بازسازی کلی با شماره‌گذاری مجدد بر اساس تاریخ ──
        if ($strategy === BackupRun::STRATEGY_REINDEX) {
            try {
                $report = $this->reindexAll($run, $sourcePath, $entities, $options, $dryRun);
            } catch (Throwable $e) {
                if ($connection->transactionLevel() > 0) {
                    $connection->rollBack();
                }
                throw $e;
            } finally {
                if ($isSqlite) {
                    $connection->statement('PRAGMA foreign_keys = ON');
                }
            }

            return $report;
        }

        $connection->beginTransaction();

        try {
            foreach ($entities as $key => $entity) {
                $file = $this->resolveCsvPath($sourcePath, $entity);

                if ($file === null) {
                    $report[$key] = ['status' => 'skipped', 'reason' => 'file_missing'];
                    $this->recorder->entity($run, $key, [
                        'table_name'    => $entity['table'],
                        'group_name'    => $entity['group'],
                        'display_name'  => $entity['label'],
                        'status'        => 'skipped',
                        'error_message' => 'فایل CSV در بسته‌ی ورودی یافت نشد.',
                    ]);

                    continue;
                }

                if (! Schema::hasTable($entity['table'])) {
                    $report[$key] = ['status' => 'skipped', 'reason' => 'table_missing'];

                    continue;
                }

                $report[$key] = $this->importEntity($run, $entity, $file, $strategy, $options);
            }

            if ($dryRun) {
                $connection->rollBack();
                $this->recorder->event($run, 'info', 'import.dry_run', 'اجرای آزمایشی: هیچ تغییری ذخیره نشد.');
            } else {
                $violations = $isSqlite ? $connection->select('PRAGMA foreign_key_check') : [];

                if ($violations !== [] && empty($options['ignore_fk_violations'])) {
                    $connection->rollBack();

                    throw new RuntimeException(sprintf(
                        'نقض کلید خارجی در %d رکورد پس از ایمپورت؛ عملیات برگشت خورد. (جدول: %s)',
                        count($violations),
                        implode(', ', array_unique(array_map(fn($v) => $v->table ?? '?', $violations))),
                    ));
                }

                $connection->commit();
            }
        } catch (Throwable $e) {
            if ($connection->transactionLevel() > 0) {
                $connection->rollBack();
            }

            throw $e;
        } finally {
            if ($isSqlite) {
                $connection->statement('PRAGMA foreign_keys = ON');
            }
        }

        if (! $dryRun && $isSqlite) {
            $this->resetSequences(array_keys($entities), $entities);

            if (config('backup.runtime.vacuum_after_import', true)) {
                try {
                    DB::statement('VACUUM');
                    DB::statement('PRAGMA optimize');
                } catch (Throwable) {
                    // بی‌اهمیت؛ صرفاً بهینه‌سازی
                }
            }
        }

        return $report;
    }

    /** @return array<string, mixed> */
    private function importEntity(BackupRun $run, array $entity, string $file, string $strategy, array $options): array
    {
        $startedAt = microtime(true);
        $table     = $entity['table'];

        $this->recorder->entity($run, $entity['key'], [
            'table_name'    => $table,
            'group_name'    => $entity['group'],
            'display_name'  => $entity['label'],
            'relative_path' => basename($file),
            'absolute_path' => $file,
            'status'        => 'running',
            'started_at'    => now(),
        ]);

        $reader = new CsvStreamReader(
            path: $file,
            delimiter: $options['csv_delimiter'] ?? config('backup.csv.delimiter', ','),
            enclosure: config('backup.csv.enclosure', '"'),
            escape: config('backup.csv.escape', '\\'),
            nullMarker: $options['csv_null_marker'] ?? config('backup.csv.null_marker', '\N'),
        );

        $tableColumns = $this->manifest->columns($table);
        $header       = $reader->header();
        $usable       = array_values(array_intersect($header, $tableColumns));
        $ignored      = array_values(array_diff($header, $tableColumns, ['__line']));

        if ($usable === []) {
            $reader->close();

            throw new RuntimeException("هیچ ستون سازگاری بین فایل «{$entity['key']}.csv» و جدول «{$table}» یافت نشد.");
        }

        if ($ignored !== []) {
            $this->recorder->event(
                $run,
                'warning',
                'entity.columns_ignored',
                "ستون‌های ناشناخته نادیده گرفته شدند: " . implode(', ', $ignored),
                ['entity' => $entity['key']]
            );
        }

        if ($strategy === BackupRun::STRATEGY_REPLACE) {
            DB::table($table)->delete();
            $this->recorder->event($run, 'warning', 'entity.truncated', "محتوای قبلی «{$entity['label']}» پاک شد.", ['entity' => $entity['key']]);
        }

        $stats  = ['inserted' => 0, 'updated' => 0, 'skipped' => 0, 'failed' => 0, 'processed' => 0];
        $buffer = [];
        $chunk  = max(50, (int) ($options['chunk_size'] ?? config('backup.runtime.chunk_size', 1000)));

        foreach ($reader->rows() as $row) {
            $line = $row['__line'] ?? null;
            unset($row['__line']);

            $payload = $this->preparePayload($row, $usable, $table);
            $stats['processed']++;

            try {
                match ($strategy) {
                    BackupRun::STRATEGY_REPLACE => $buffer[] = $payload,
                    default                     => $this->upsertRow($entity, $payload, $strategy, $stats),
                };

                if (count($buffer) >= $chunk) {
                    DB::table($table)->insert($buffer);
                    $stats['inserted'] += count($buffer);
                    $buffer = [];
                }
            } catch (Throwable $e) {
                $stats['failed']++;

                if (! empty($options['stop_on_error'])) {
                    throw new RuntimeException("خطا در سطر {$line} فایل «{$entity['key']}.csv»: {$e->getMessage()}", 0, $e);
                }

                $this->recorder->event($run, 'error', 'row.failed', "سطر {$line}: {$e->getMessage()}", [
                    'entity' => $entity['key'],
                    'line'   => $line,
                ]);
            }
        }

        if ($buffer !== []) {
            DB::table($table)->insert($buffer);
            $stats['inserted'] += count($buffer);
        }

        $reader->close();

        $status = $stats['failed'] === 0 ? 'completed' : 'partial';

        $this->recorder->entity($run, $entity['key'], [
            'status'         => $status,
            'row_count'      => $stats['processed'],
            'processed_rows' => $stats['processed'],
            'inserted_rows'  => $stats['inserted'],
            'updated_rows'   => $stats['updated'],
            'skipped_rows'   => $stats['skipped'],
            'failed_rows'    => $stats['failed'],
            'bytes'          => (int) @filesize($file),
            'checksum'       => hash_file('sha256', $file) ?: null,
            'columns_json'   => $usable,
            'finished_at'    => now(),
            'duration_ms'    => (int) ((microtime(true) - $startedAt) * 1000),
        ]);

        return ['status' => $status, 'label' => $entity['label']] + $stats;
    }

    /** درج یا به‌روزرسانی یک رکورد بر اساس id / natural key. */
    private function upsertRow(array $entity, array $payload, string $strategy, array &$stats): void
    {
        $table = $entity['table'];
        $query = DB::table($table);

        $match = [];

        if (! empty($payload['id'])) {
            $match = ['id' => $payload['id']];
        } elseif ($entity['natural_key'] !== []) {
            foreach ($entity['natural_key'] as $column) {
                if (array_key_exists($column, $payload) && $payload[$column] !== null) {
                    $match[$column] = $payload[$column];
                }
            }
        }

        $existing = $match !== [] ? (clone $query)->where($match)->first() : null;

        if ($existing === null) {
            $query->insert($payload);
            $stats['inserted']++;

            return;
        }

        if ($strategy === BackupRun::STRATEGY_SKIP_EXISTING) {
            $stats['skipped']++;

            return;
        }

        if ($strategy === BackupRun::STRATEGY_FAIL) {
            throw new RuntimeException("رکورد تکراری در جدول {$table}: " . json_encode($match, JSON_UNESCAPED_UNICODE));
        }

        $update = $payload;
        unset($update['id'], $update['created_at']);

        DB::table($table)->where($match)->update($update);
        $stats['updated']++;
    }

    /** پاک‌سازی و تبدیل تایپ مقادیر یک سطر. */
    private function preparePayload(array $row, array $columns, string $table): array
    {
        $payload = [];

        foreach ($columns as $column) {
            $value = $row[$column] ?? null;

            if (is_string($value)) {
                $value = trim($value);

                if ($value === '' && str_ends_with($column, '_id')) {
                    $value = null;
                }
            }

            $payload[$column] = $value;
        }

        // اطمینان از وجود timestamps
        if (in_array('created_at', $columns, true) && empty($payload['created_at'])) {
            $payload['created_at'] = now()->toDateTimeString();
        }
        if (in_array('updated_at', $columns, true) && empty($payload['updated_at'])) {
            $payload['updated_at'] = now()->toDateTimeString();
        }

        return $payload;
    }

    /** اصلاح AUTOINCREMENT بعد از درج id های صریح. */
    private function resetSequences(array $keys, array $entities): void
    {
        foreach ($keys as $key) {
            $table = $entities[$key]['table'] ?? null;

            if (! $table || ! Schema::hasTable($table) || ! Schema::hasColumn($table, 'id')) {
                continue;
            }

            try {
                $max = (int) DB::table($table)->max('id');

                $exists = DB::table('sqlite_sequence')->where('name', $table)->exists();

                if ($exists) {
                    DB::table('sqlite_sequence')->where('name', $table)->update(['seq' => $max]);
                } elseif ($max > 0) {
                    DB::table('sqlite_sequence')->insert(['name' => $table, 'seq' => $max]);
                }
            } catch (Throwable) {
                // جدول بدون AUTOINCREMENT: sqlite_sequence ندارد
            }
        }
    }

    /**
     * بازسازی کلی دیتابیس با شماره‌گذاری مجدد بر اساس تاریخ ثبت.
     *
     * رکوردهای بکاپ قدیم و دیتابیس فعلی بر اساس created_at ادغام شده
     * و ID های جدید به ترتیب زمانی اختصاص می‌یابند:
     *   - رکوردهای قدیمی‌تر → ID های پایین‌تر
     *   - رکوردهای جدیدتر  → ID های بالاتر
     *
     * @param  array<string, array<string, mixed>>  $entities
     * @return array<string, array<string, mixed>>
     */
    private function reindexAll(BackupRun $run, string $sourcePath, array $entities, array $options, bool $dryRun): array
    {
        $connection = DB::connection();
        $isSqlite   = $connection->getDriverName() === 'sqlite';
        $report     = [];
        $allIdMaps  = [];
        $rowOrigins = []; // table => [newId => 'existing'|'backup']

        $connection->beginTransaction();
        try {
            foreach ($entities as $key => $entity) {
                $table = $entity['table'];
                if (! Schema::hasTable($table)) {
                    $report[$key] = ['status' => 'skipped', 'reason' => 'table_missing'];
                    continue;
                }
                $file = $this->resolveCsvPath($sourcePath, $entity);
                if ($file === null) {
                    $report[$key] = ['status' => 'skipped', 'reason' => 'file_missing'];
                    $this->recorder->entity($run, $key, [
                        'table_name' => $table,
                        'group_name' => $entity['group'],
                        'display_name' => $entity['label'],
                        'status' => 'skipped',
                        'error_message' => 'فایل CSV یافت نشد.',
                    ]);
                    continue;
                }

                $tableColumns = $this->manifest->columns($table);
                $existingRows = DB::table($table)->orderBy('created_at')->orderBy('id')->get()->map(fn($r) => (array)$r)->toArray();

                $reader = new CsvStreamReader(
                    path: $file,
                    delimiter: $options['csv_delimiter'] ?? config('backup.csv.delimiter', ','),
                    enclosure: config('backup.csv.enclosure', '"'),
                    escape: config('backup.csv.escape', '\\'),
                    nullMarker: $options['csv_null_marker'] ?? config('backup.csv.null_marker', '\N'),
                );
                $header = $reader->header();
                $usable = array_values(array_intersect($header, $tableColumns));
                $backupRows = [];
                foreach ($reader->rows() as $row) {
                    unset($row['__line']);
                    $backupRows[] = $this->preparePayload($row, $usable, $table);
                }
                $reader->close();

                // ── 1. ادغام ──
                $naturalKeys = $entity['natural_key'] ?? [];
                $merged = [];
                foreach ($existingRows as $er) {
                    $er['_origin'] = 'existing';
                    $merged[] = $er;
                }
                $existingKeySet = [];
                if ($naturalKeys !== []) {
                    foreach ($existingRows as $er) {
                        $k = implode("\0", array_map(fn($nk) => (string)($er[$nk] ?? ''), $naturalKeys));
                        $existingKeySet[$k] = true;
                    }
                }
                foreach ($backupRows as $bRow) {
                    if ($naturalKeys !== []) {
                        $k = implode("\0", array_map(fn($nk) => (string)($bRow[$nk] ?? ''), $naturalKeys));
                        if (isset($existingKeySet[$k])) continue;
                        $existingKeySet[$k] = true;
                    }
                    $backupId = $bRow['id'] ?? null;
                    unset($bRow['id']);
                    $bRow['_backup_old_id'] = $backupId;
                    $bRow['_origin'] = 'backup';
                    $merged[] = $bRow;
                }

                // ── 2. سورت ──
                usort($merged, function ($a, $b) {
                    $ta = strtotime($a['created_at'] ?? '') ?: 0;
                    $tb = strtotime($b['created_at'] ?? '') ?: 0;
                    if ($ta !== $tb) return $ta <=> $tb;
                    return strcmp($a['created_at'] ?? '', $b['created_at'] ?? '') ?: (($a['id'] ?? PHP_INT_MAX) <=> ($b['id'] ?? PHP_INT_MAX));
                });

                // ── 3. بازسازی جدول با DDL اصلی ──
                $originalDdl = optional(DB::selectOne("SELECT sql FROM sqlite_master WHERE type='table' AND name=?", [$table]))->sql;
                if ($originalDdl === null) throw new RuntimeException("DDL جدول {$table} یافت نشد");
                $originalIndexes = DB::select("SELECT sql FROM sqlite_master WHERE type='index' AND tbl_name=? AND sql IS NOT NULL", [$table]);

                $tempTable = "_reindex_{$table}";
                DB::statement("DROP TABLE IF EXISTS [{$tempTable}]");
                $tempDdl = preg_replace('/\bCREATE\s+TABLE\s+(?:IF\s+NOT\s+EXISTS\s+)?["\[`]?' . preg_quote($table, '/') . '["\]`]?/i', "CREATE TABLE [{$tempTable}]", $originalDdl, 1);
                DB::statement($tempDdl);

                // ── 4. درج با id جدید ──
                $mapping = [];
                $idMap   = ['existing' => [], 'backup' => []];
                $newId = 1;
                foreach ($merged as $row) {
                    $origin = $row['_origin'] ?? 'existing';
                    $oldId  = $origin === 'existing' ? ($row['id'] ?? null) : ($row['_backup_old_id'] ?? null);
                    unset($row['_backup_old_id'], $row['_origin']);
                    $row['id'] = $newId;
                    $insertData = array_merge(array_fill_keys($tableColumns, null), array_intersect_key($row, array_flip($tableColumns)));
                    DB::table($tempTable)->insert($insertData);
                    if ($oldId !== null) {
                        $idMap[$origin][(int)$oldId] = $newId;
                        if ((int)$oldId !== $newId) $mapping[] = ['old' => (int)$oldId, 'new' => $newId, 'origin' => $origin];
                    }
                    $rowOrigins[$table][$newId] = $origin;
                    $newId++;
                }

                DB::statement("DROP TABLE [{$table}]");
                DB::statement("ALTER TABLE [{$tempTable}] RENAME TO [{$table}]");
                foreach ($originalIndexes as $idx) {
                    if (!empty($idx->sql)) try {
                        DB::statement($idx->sql);
                    } catch (Throwable) {
                    }
                }

                $allIdMaps[$table] = $idMap;

                $report[$key] = ['status' => 'completed', 'label' => $entity['label'], 'rows' => count($merged), 'inserted' => count($merged), 'updated' => 0, 'skipped' => 0, 'failed' => 0, 'remapped' => count($mapping)];
                $this->recorder->entity($run, $key, [
                    'table_name' => $table,
                    'group_name' => $entity['group'],
                    'display_name' => $entity['label'],
                    'status' => 'completed',
                    'row_count' => count($merged),
                    'processed_rows' => count($merged),
                    'inserted_rows' => count($merged),
                    'updated_rows' => 0,
                    'skipped_rows' => 0,
                    'failed_rows' => 0,
                    'finished_at' => now(),
                ]);
            }

            $this->applyFkRemaps($allIdMaps, $rowOrigins);

            if (! $dryRun && $isSqlite) {
                $violations = DB::select('PRAGMA foreign_key_check');
                if ($violations !== [] && empty($options['ignore_fk_violations'])) {
                    throw new RuntimeException('نقض FK پس از reindex: ' . count($violations) . ' رکورد.');
                }
            }

            if ($dryRun) {
                $connection->rollBack();
                $this->recorder->event($run, 'info', 'import.dry_run', 'اجرای آزمایشی reindex: هیچ تغییری ذخیره نشد.');
            } else {
                $connection->commit();
            }
        } catch (Throwable $e) {
            if ($connection->transactionLevel() > 0) $connection->rollBack();
            throw $e;
        }

        if (! $dryRun && $isSqlite) {
            $this->resetSequences(array_keys($entities), $entities);
            if (config('backup.runtime.vacuum_after_import', true)) {
                try {
                    DB::statement('VACUUM');
                    DB::statement('PRAGMA optimize');
                } catch (Throwable) {
                }
            }
        }
        return $report;
    }

    /**
     * ساخت نقشه به‌روزرسانی کلیدهای خارجی بر اساس نقشه‌های reindex.
     *
     * @param  array<string, array<int, array{old: int, new: int}>>  $allMappings
     * @return array<int, array{table: string, fk_column: string, old_id: int, new_id: int}>
     */
    private function fkRegistry(): array
    {
        return [
            'customers'    => [['invoices', 'customer_id'], ['requests', 'customer_id'], ['service_jobs', 'customer_id'], ['archived_records', 'customer_id']],
            'categories'   => [['items', 'category_id'], ['order_items', 'category_id']],
            'items'        => [['order_items', 'item_id'], ['service_job_items', 'item_id'], ['stock_movements', 'item_id'], ['daily_item_stats', 'item_id']],
            'invoices'     => [['order_items', 'invoice_id'], ['service_jobs', 'invoice_id'], ['stock_movements', 'invoice_id'], ['invoice_adjustments', 'invoice_id'], ['archived_records', 'invoice_id']],
            'requests'     => [['invoices', 'request_id'], ['service_jobs', 'request_id']],
            'service_types' => [['service_job_service_types', 'service_type_id']],
            'service_jobs' => [['service_job_items', 'service_job_id'], ['service_job_service_types', 'service_job_id'], ['stock_movements', 'service_job_id']],
            'archived_records' => [['archive_actions', 'archived_record_id']],
            'setting_groups'  => [['app_settings', 'group_id']],
            'users'        => [['archive_actions', 'actor_id'], ['cache_maintenance_runs', 'user_id']],
            'order_items'  => [['stock_movements', 'order_item_id']],
        ];
    }

    private function applyFkRemaps(array $allIdMaps, array $rowOrigins): void
    {
        $offset = 1000000000;

        foreach ($this->fkRegistry() as $parent => $children) {
            foreach (['existing', 'backup'] as $origin) {
                $changes = [];
                foreach ($allIdMaps[$parent][$origin] ?? [] as $old => $new) {
                    if ((int) $old !== (int) $new) {
                        $changes[(int) $old] = (int) $new;
                    }
                }

                if ($changes === []) {
                    continue;
                }

                foreach ($children as [$childTable, $childCol]) {
                    if (! Schema::hasTable($childTable) || ! Schema::hasColumn($childTable, $childCol)) {
                        continue;
                    }

                    // فقط ردیف‌هایی از جدول فرزند که خودشان از همین منبع (existing/backup)
                    // هستند با این نگاشت remap می‌شوند. چون FK یک ردیفِ بکاپ به فضای idِ
                    // بکاپ اشاره دارد و FK یک ردیفِ موجود به فضای idِ قبل از reindex؛
                    // این دو فضا می‌توانند id های خامِ یکسان (مثلاً بعد از ریست sequence)
                    // ولی معنای کاملاً متفاوت داشته باشند.
                    $scopedIds = array_keys(array_filter(
                        $rowOrigins[$childTable] ?? [],
                        fn($o) => $o === $origin
                    ));

                    if ($scopedIds === []) {
                        continue;
                    }

                    DB::table($childTable)
                        ->whereIn('id', $scopedIds)
                        ->whereIn($childCol, array_keys($changes))
                        ->update([$childCol => DB::raw("{$childCol} + {$offset}")]);

                    foreach ($changes as $old => $new) {
                        DB::table($childTable)
                            ->whereIn('id', $scopedIds)
                            ->where($childCol, $old + $offset)
                            ->update([$childCol => $new]);
                    }
                }
            }
        }
    }

    /** یافتن فایل CSV یک موجودیت در بسته‌ی ورودی (با مسیر استاندارد یا جست‌وجوی بازگشتی). */
    private function resolveCsvPath(string $sourcePath, array $entity): ?string
    {
        $candidates = [
            $sourcePath . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $this->manifest->relativeCsvPath($entity)),
            $sourcePath . DIRECTORY_SEPARATOR . $entity['key'] . '.csv',
            $sourcePath . DIRECTORY_SEPARATOR . $entity['table'] . '.csv',
        ];

        foreach ($candidates as $candidate) {
            if (is_file($candidate)) {
                return $candidate;
            }
        }

        // جست‌وجوی بازگشتی برای پشتیبانی از ساختارهای دستیِ کاربر
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($sourcePath, \FilesystemIterator::SKIP_DOTS)
        );

        foreach ($iterator as $file) {
            /** @var \SplFileInfo $file */
            if (! $file->isFile() || strtolower($file->getExtension()) !== 'csv') {
                continue;
            }

            $name = strtolower($file->getBasename('.' . $file->getExtension()));

            if ($name === $entity['key'] || $name === $entity['table']) {
                return $file->getPathname();
            }
        }

        return null;
    }
}
