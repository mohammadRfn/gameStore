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
    ) {
    }

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
                        implode(', ', array_unique(array_map(fn ($v) => $v->table ?? '?', $violations))),
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
            'relative_path' => $this->paths->relative(dirname($file, 3), $file),
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
            $this->recorder->event($run, 'warning', 'entity.columns_ignored',
                "ستون‌های ناشناخته نادیده گرفته شدند: " . implode(', ', $ignored), ['entity' => $entity['key']]);
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
