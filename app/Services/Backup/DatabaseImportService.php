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
     * ─────────────────────────────────────────────────────────────────────────
     *  استراتژی reindex — نسخه‌ی اصلاح‌شده (v2)
     * ─────────────────────────────────────────────────────────────────────────
     * سناریوی هدف:
     *   رکوردها پاک شده‌اند، AUTOINCREMENT ریست شده و کاربر دوباره داده ساخته
     *   (مثلاً ID های ۱ و ۲ حالا مال داده‌ی جدید است). حالا بکاپ قدیمی ایمپورت
     *   می‌شود؛ انتظار: هر دو مجموعه بمانند و ID ها از ۱ و بر اساس تاریخ ایجاد
     *   بازتوزیع شوند (قدیمی‌ها ۱..۱۰، جدیدها ۱۱..۲۰) و همه‌ی کلیدهای خارجی
     *   درست بمانند.
     *
     * اشکالات نسخه‌ی قبل که اینجا برطرف شد:
     *   ۱) رکورد بکاپی که با یک رکورد موجود «یکی» تشخیص داده می‌شد با continue
     *      حذف می‌شد و هیچ نگشتی از ID قدیمی‌اش ثبت نمی‌شد؛ در نتیجه همه‌ی
     *      فرزندان آن رکورد (که در فضای ID بکاپ بودند) به رکورد اشتباه یا به
     *      هیچ وصل می‌شدند → در UI «رکوردهای جدید حذف شدند» به نظر می‌رسید.
     *   ۲) تطبیق natural key روی مقادیر خامِ کلید خارجی انجام می‌شد
     *      (items.category_id): عدد ۳ در فضای بکاپ با عدد ۳ در فضای فعلی دو
     *      والد کاملاً متفاوت است → تطبیق‌های غلط و حذف‌های غلط.
     *   ۳) جدول وسط حلقه DROP می‌شد؛ اگر جدول بعدی خطا می‌داد، حالت نیمه‌ساخته
     *      باقی می‌ماند (حالا کل برنامه‌ریزی قبل از هر نوشتن انجام می‌شود).
     *   ۴) remap فقط روی جدول‌هایی اعمال می‌شد که خودشان rebuild شده بودند
     *      (`$scopedIds === []` → continue)؛ جدول‌های فرزندِ بیرون از مانیفست یا
     *      skipped، با کلیدهای بی‌مرجع می‌ماندند.
     *   ۵) remap با ترفند «+ offset و بعد اصلاح» انجام می‌شد که با برخورد
     *      ID های مقصد/مبدأ جابه‌جایی زنجیره‌ای تولید می‌کند؛ جای آن یک
     *      UPDATE با CASE (ارزش‌گذاری اتمیک روی مقدار قبلی) و در نهایت
     *      ترجمه‌ی FK «همزمان با» شماره‌گذاری هر جدول نشسته است.
     *   ۶) preparePayload برای رکوردهای بکاپ، created_at خالی را «همین حالا»
     *      می‌کرد و ترتیب زمانی را به هم می‌ریخت؛ مسیر reindex حالا خواننده‌ی
     *      مستقل دارد.
     *
     * @param  array<string, array<string, mixed>>  $entities
     * @return array<string, array<string, mixed>>
     */
    private function reindexAll(BackupRun $run, string $sourcePath, array $entities, array $options, bool $dryRun): array
    {
        $connection = DB::connection();

        if ($connection->getDriverName() !== 'sqlite') {
            throw new RuntimeException('استراتژی «بازسازی با شماره‌گذاری مجدد» فقط روی SQLite پشتیبانی می‌شود (به‌دلیل بازسازی DDL جدول‌ها).');
        }

        $policy = $this->reindexPolicy($options);

        /** @var array<string, array<string, mixed>> $plans  table => plan (به ترتیب مانیفست: والد قبل از فرزند) */
        $plans  = [];
        $report = [];

        // ── مرحله‌ی ۱: برنامه‌ریزی (فقط خواندن؛ هیچ نوشتن انجام نمی‌شود) ──────
        foreach ($entities as $key => $definition) {
            $entity = $this->normalizeReindexEntity((string) $key, $definition);
            $table  = $entity['table'];

            if (! Schema::hasTable($table)) {
                $report[$entity['key']] = ['status' => 'skipped', 'reason' => 'table_missing', 'label' => $entity['label']];

                continue;
            }

            $file     = $this->resolveCsvPath($sourcePath, $entity);
            $existing = $this->readLiveRows($table);
            $backup   = [];

            if ($file === null) {
                $this->recorder->event($run, 'warning', 'reindex.file_missing', "فایل CSV «{$entity['label']}» در بسته یافت نشد؛ جدول فقط با داده‌های فعلی بازسازی می‌شود.", ['entity' => $entity['key']]);
            } else {
                $backup = $this->readBackupRows($file, $entity, $options);
            }

            $plans[$table] = $this->buildReindexPlan($entity, $existing, $backup, $plans, $policy);

            $report[$entity['key']] = [
                'status'     => 'planned',
                'label'      => $entity['label'],
                'existing'   => count($existing),
                'backup'     => count($backup),
                'rows'       => count($plans[$table]['rows']),
                'merged'     => count($plans[$table]['aliases']),
                'orphans'    => count($plans[$table]['orphans']),
                'inserted'   => 0,
                'updated'    => 0,
                'skipped'    => 0,
                'failed'     => 0,
            ];
        }

        // ── مرحله‌ی ۲: اعمال ──────────────────────────────────────────────────
        $connection->beginTransaction();

        try {
            foreach ($plans as $table => $plan) {
                $this->rebuildTable($table, $plan, $policy);
            }

            foreach ($plans as $parentTable => $plan) {
                $this->remapExternalChildren($parentTable, $plan, $plans, $policy);
            }

            $violations = DB::select('PRAGMA foreign_key_check');

            if ($violations !== []) {
                $nullified = $policy['null_orphans'] ? $this->nullifyOrphans($violations) : 0;

                if (empty($options['ignore_fk_violations'])) {
                    throw new RuntimeException(sprintf(
                        'پس از بازسازی %d کلید خارجی بی‌مرجع باقی است%s؛ عملیات برگشت خورد.',
                        count($violations),
                        $nullified > 0 ? " ({$nullified} مورد NULL شد)" : ''
                    ));
                }

                $this->recorder->event($run, 'warning', 'reindex.fk_violations', 'کلیدهای خارجی بی‌مرجع پس از بازسازی: ' . count($violations));
            }

            if ($dryRun) {
                $connection->rollBack();
                $this->recorder->event($run, 'info', 'import.dry_run', 'اجرای آزمایشی reindex: هیچ تغییری ذخیره نشد.');
            } else {
                $connection->commit();
            }
        } catch (Throwable $e) {
            if ($connection->transactionLevel() > 0) {
                $connection->rollBack();
            }

            throw $e;
        }

        // ── مرحله‌ی ۳: گزارش ───────────────────────────────────────────────────
        foreach ($plans as $table => $plan) {
            $entity   = $plan['entity'];
            $inserted = count($plan['rows']);
            $merged   = count($plan['aliases']);

            foreach ($plan['orphans'] as $orphan) {
                $this->recorder->event($run, 'warning', 'reindex.orphan', sprintf(
                    'در جدول «%s» کلید خارجی %s=%s به والدی اشاره می‌کند که نه در بکاپ و نه در دیتابیس نیست.',
                    $table,
                    $orphan['column'],
                    var_export($orphan['old_value'], true)
                ), ['entity' => $entity['key'], 'origin' => $orphan['origin']]);
            }

            $report[$entity['key']] = [
                'status'    => 'completed',
                'label'     => $entity['label'],
                'processed' => $inserted + $merged,
                'inserted'  => $inserted,
                'updated'   => 0,
                'skipped'   => $merged,
                'failed'    => 0,
                'rows'      => $inserted,
                'merged'    => $merged,
                'orphans'   => count($plan['orphans']),
                // برای MediaImportService: مسیرهای ذخیره‌شده بر پایه‌ی ID قدیمی
                // (media/items/{id}/…) باید با همین نگاشت بازتطبیق شوند.
                'idmap'     => $plan['idMap'],
            ];

            $this->recorder->entity($run, $entity['key'], [
                'table_name'     => $table,
                'group_name'     => $entity['group'],
                'display_name'   => $entity['label'],
                'status'         => 'completed',
                'row_count'      => $inserted,
                'processed_rows' => $inserted + $merged,
                'inserted_rows'  => $inserted,
                'updated_rows'   => 0,
                'skipped_rows'   => $merged,
                'failed_rows'    => 0,
                'finished_at'    => now(),
            ]);
        }

        if (! $dryRun) {
            $this->resetSequencesFor(array_keys($plans));

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

    /** تعریف موجودیت را برای مسیر reindex آماده می‌کند. */
    private function normalizeReindexEntity(string $key, array $definition): array
    {
        return [
            'key'         => (string) ($definition['key'] ?? $key),
            'table'       => (string) ($definition['table'] ?? $key),
            'group'       => (string) ($definition['group'] ?? '00_core'),
            'label'       => (string) ($definition['label'] ?? $key),
            'natural_key' => array_values((array) ($definition['natural_key'] ?? [])),
        ];
    }

    /** سیاست رفتاری reindex. */
    private function reindexPolicy(array $options): array
    {
        $policy      = (array) ($options['reindex'] ?? []);
        $onDuplicate = (string) ($policy['on_duplicate'] ?? 'keep_existing');

        if (! in_array($onDuplicate, ['keep_existing', 'keep_backup', 'keep_both', 'fill_nulls'], true)) {
            $onDuplicate = 'keep_existing';
        }

        return [
            'on_duplicate' => $onDuplicate,
            'null_orphans' => (bool) ($policy['null_orphans'] ?? false),
            'orphan_action' => (string) ($policy['on_missing_parent'] ?? 'null'),  // null | keep | fail
            'strict_indexes' => (bool) ($policy['strict_indexes'] ?? true),
            'insert_chunk' => max(50, (int) ($options['chunk_size'] ?? config('backup.runtime.chunk_size', 500))),
            'case_chunk'   => 200,
        ];
    }

    /**
     * خواندن رکوردهای زنده‌ی یک جدول (به ترتیب تاریخ، تا سورت پایدار بماند).
     *
     * @return array<int, array<string, mixed>>
     */
    private function readLiveRows(string $table): array
    {
        $query = DB::table($table);

        foreach ($this->orderColumns($table) as $column) {
            $query->orderBy($column);
        }

        return $query->get()->map(fn ($row) => (array) $row)->all();
    }

    /**
     * خواندن CSV برای reindex.
     *
     * برخلاف preparePayload، مقدار خالی created_at به «همین حالا» تبدیل نمی‌شود؛
     * چون ترتیب زمانی، مبنای کل شماره‌گذاری مجدد است.
     *
     * @return array<int, array<string, mixed>>
     */
    private function readBackupRows(string $file, array $entity, array $options): array
    {
        $reader = new CsvStreamReader(
            path: $file,
            delimiter: $options['csv_delimiter'] ?? config('backup.csv.delimiter', ','),
            enclosure: config('backup.csv.enclosure', '"'),
            escape: config('backup.csv.escape', '\\'),
            nullMarker: $options['csv_null_marker'] ?? config('backup.csv.null_marker', '\N'),
        );

        $columns = $this->manifest->columns($entity['table']);
        $usable  = array_values(array_intersect($reader->header(), $columns));

        if ($usable === []) {
            $reader->close();

            throw new RuntimeException("هیچ ستون سازگاری بین فایل «{$entity['key']}.csv» و جدول «{$entity['table']}» یافت نشد.");
        }

        $rows = [];

        foreach ($reader->rows() as $row) {
            unset($row['__line']);

            $payload = [];

            foreach ($usable as $column) {
                $value = $row[$column] ?? null;

                if (is_string($value)) {
                    $value = trim($value);

                    if ($value === '' && str_ends_with($column, '_id')) {
                        $value = null;
                    }
                }

                $payload[$column] = $value === '' ? null : $value;
            }

            $rows[] = $payload;
        }

        $reader->close();

        return $rows;
    }

    /**
     * ادغام دو مجموعه، ادغام رکوردهای «همان رکورد»، شماره‌گذاری زمانی و
     * ترجمه‌ی کلیدهای خارجی به ID های جدید.
     *
     * @param  array<int, array<string, mixed>>  $existingRows
     * @param  array<int, array<string, mixed>>  $backupRows
     * @param  array<string, array<string, mixed>>  $plans  برنامه‌ی جدول‌های والدِ قبلی
     * @return array<string, mixed>
     */
    private function buildReindexPlan(array $entity, array $existingRows, array $backupRows, array $plans, array $policy): array
    {
        $table       = $entity['table'];
        $columns     = $this->manifest->columns($table);
        $natural     = array_values(array_intersect($entity['natural_key'], $columns));
        $onDuplicate = $policy['on_duplicate'];
        $foreign     = $this->foreignKeyColumns($table);

        /** @var array<int, array<string, mixed>> $rows */
        $rows = [];
        /** @var array<string, int> $bySignature */
        $bySignature = [];
        /** @var array<int, array{origin: string, old_id: int, into: int, action: string}> $aliases */
        $aliases = [];

        // ۱) رکوردهای موجود؛ این‌ها هرگز حذف نمی‌شوند.
        foreach ($existingRows as $row) {
            $index  = count($rows);
            $rows[] = $row + ['_origin' => 'existing', '_old_id' => (int) ($row['id'] ?? 0), '_alias' => []];

            if ($natural !== []) {
                $bySignature[$this->rowSignature($rows[$index], $natural, 'existing', $plans, $foreign)] ??= $index;
            }
        }

        // ۲) رکوردهای بکاپ.
        foreach ($backupRows as $row) {
            $backupId  = (int) ($row['id'] ?? 0);
            $signature = null;
            $hit       = null;

            if ($natural !== []) {
                $signature = $this->rowSignature($row, $natural, 'backup', $plans, $foreign);

                if (isset($bySignature[$signature])) {
                    $hit = $bySignature[$signature];
                }
            }

            if ($hit !== null && $onDuplicate !== 'keep_both') {
                // «همان رکورد» است: داده‌ی بکاپ کنار گذاشته (یا ادغام) می‌شود،
                // ولی ID قدیمیِ بکاپ به رکورد بازمانده نگاشته می‌شود تا
                // هیچ فرزندی بی‌مرجع نماند. این حلقه‌ی گم‌شده‌ی نسخه‌ی قبل بود.
                $rows[$hit]['_alias'][] = ['origin' => 'backup', 'old_id' => $backupId];

                $aliases[] = ['origin' => 'backup', 'old_id' => $backupId, 'into' => $hit, 'action' => $onDuplicate, 'signature' => $signature];

                if ($onDuplicate === 'fill_nulls') {
                    foreach ($columns as $column) {
                        if ($column === 'id' || ! array_key_exists($column, $row)) {
                            continue;
                        }

                        if (($rows[$hit][$column] ?? null) === null && $row[$column] !== null) {
                            $rows[$hit][$column] = $row[$column];
                        }
                    }
                } elseif ($onDuplicate === 'keep_backup') {
                    $rows[$hit] = $row + [
                        '_origin' => $rows[$hit]['_origin'],
                        '_old_id' => $rows[$hit]['_old_id'],
                        '_alias'  => $rows[$hit]['_alias'],
                    ];
                }

                continue;
            }

            unset($row['id']);

            $index  = count($rows);
            $rows[] = $row + ['_origin' => 'backup', '_old_id' => $backupId, '_alias' => []];

            if ($natural !== []) {
                $bySignature[$signature ?? ''] ??= $index;
            }
        }

        // ۳) سورت: قدیمی‌تر → ID کوچک‌تر. در تساوی، بکاپ مقدم است.
        usort($rows, fn ($a, $b) => $this->compareForReindex($a, $b));

        // ۴) شماره‌گذاری از ۱ + نگاشت old→new (شامل رکوردهای ادغام‌شده).
        $idMap   = ['existing' => [], 'backup' => []];
        $origins = [];
        $newId   = 1;

        foreach ($rows as $index => $row) {
            $rows[$index]['id'] = $newId;
            $origins[$newId]    = $row['_origin'];

            if ((int) $row['_old_id'] > 0) {
                $idMap[$row['_origin']][(int) $row['_old_id']] = $newId;
            }

            foreach ($row['_alias'] as $alias) {
                if ((int) $alias['old_id'] > 0) {
                    $idMap[$alias['origin']][(int) $alias['old_id']] = $newId;
                }
            }

            $newId++;
        }

        // ۵) ترجمه‌ی کلیدهای خارجی به فضای جدید (خودِ جدول هم شامل می‌شود).
        $orphans = [];

        // ارجاع‌های خودارجاع (مثل categories.parent_id) با نگاشت خودِ جدول حل می‌شوند.
        $plans[$table] = ['idMap' => $idMap];

        foreach ($rows as $index => $row) {
            foreach ($foreign as $column => $parentTable) {
                $value = $row[$column] ?? null;

                if ($value === null || $value === '' || ! is_numeric($value)) {
                    continue;
                }

                $map = $plans[$parentTable]['idMap'][$row['_origin']] ?? null;

                if ($map === null) {
                    continue; // والد در این اجرا بازسازی نمی‌شود؛ دست نخورده می‌ماند
                }

                if (isset($map[(int) $value])) {
                    $rows[$index][$column] = $map[(int) $value];

                    continue;
                }

                $orphans[] = ['column' => $column, 'old_value' => $value, 'origin' => $row['_origin']];

                if ($policy['orphan_action'] === 'fail') {
                    throw new RuntimeException("کلید خارجی بی‌مرجع در «{$table}».{$column}={$value} (رکورد بکاپ به والدی اشاره دارد که ایمپورت نشده است).");
                }

                if ($policy['orphan_action'] === 'null' && $this->columnIsNullable($table, $column)) {
                    $rows[$index][$column] = null;
                }
            }
        }

        // ۶) گارد ضد‌حذف: یک رکورد هم نباید بی‌دلیل کم شود.
        $expected = count($existingRows) + count($backupRows) - count($aliases);

        if (count($rows) !== $expected) {
            throw new RuntimeException("خطای داخلی در «{$table}»: ".count($rows).' رکورد در برابر '.count($existingRows).'+' .count($backupRows).' ورودی.');
        }

        return [
            'entity'  => $entity,
            'table'   => $table,
            'columns' => $columns,
            'natural' => $natural,
            'rows'    => $rows,
            'aliases' => $aliases,
            'idMap'   => $idMap,
            'origins' => $origins,
            'orphans' => $orphans,
        ];
    }

    /**
     * امضای هویتی رکورد برای تشخیص «همان رکورد» بین بکاپ و دیتابیس.
     *
     * اگر ستونی از natural key خودش کلید خارجی باشد، مقدارش با مقادیر خام
     * مقایسه نمی‌شود؛ چون فضای ID بکاپ و فضای فعلی با هم فرق دارند و همان عدد
     * به دو والد متفاوت می‌رسد. مقدار به ID جدید والد ترجمه می‌شود.
     */
    private function rowSignature(array $row, array $natural, string $origin, array $plans, array $foreign): string
    {
        $parts = [];

        foreach ($natural as $column) {
            $value = $row[$column] ?? null;

            // کلید خارجی در natural key: عدد خام در دو فضای ID معنای متفاوت دارد،
            // پس اول به ID جدید (فضای خنثی) والدِ *واقعاً* ارجاع‌شده ترجمه می‌شود.
            if (is_numeric($value) && isset($foreign[$column])) {
                $parentTable = $foreign[$column];

                if (isset($plans[$parentTable]['idMap'][$origin])) {
                    $value = (string) ($plans[$parentTable]['idMap'][$origin][(int) $value] ?? '~miss:' . $origin . ':' . (int) $value);
                } else {
                    $value = (string) (int) $value;
                }
            }

            if (is_string($value)) {
                $value = mb_strtolower(trim(preg_replace('/\s+/u', ' ', $value) ?? $value));
            }

            $parts[] = ($value === null || $value === '') ? '' : (string) $value;
        }

        return implode("\0", $parts);
    }

    /** ترتیب‌دهی رکوردها برای شماره‌گذاری مجدد. */
    private function compareForReindex(array $a, array $b): int
    {
        $ta = $this->timestampOf($a);
        $tb = $this->timestampOf($b);

        if ($ta !== $tb) {
            return $ta <=> $tb;
        }

        $rankA = ($a['_origin'] ?? 'existing') === 'backup' ? 0 : 1;
        $rankB = ($b['_origin'] ?? 'existing') === 'backup' ? 0 : 1;

        if ($rankA !== $rankB) {
            return $rankA <=> $rankB;
        }

        return ((int) ($a['_old_id'] ?? 0)) <=> ((int) ($b['_old_id'] ?? 0));
    }

    /**
     * مبنای زمانی. رکورد بدون timestamp: بکاپ = قدیمی‌ترین، رکورد فعلی =
     * جدیدترین (چون بعد از ریست ساخته شده است).
     */
    private function timestampOf(array $row): int
    {
        foreach (['created_at', 'updated_at'] as $column) {
            $value = $row[$column] ?? null;

            if (is_string($value) && trim($value) !== '') {
                $time = strtotime(trim($value));

                if ($time !== false) {
                    return $time;
                }
            }
        }

        return ($row['_origin'] ?? 'existing') === 'existing' ? PHP_INT_MAX : 0;
    }

    /** @return array<int, string> */
    private function orderColumns(string $table): array
    {
        $order = [];

        foreach (['created_at', 'id'] as $column) {
            if (Schema::hasColumn($table, $column)) {
                $order[] = $column;
            }
        }

        return $order === [] ? ['id'] : $order;
    }

    /**
     * ساخت مجدد جدول با ID های تازه با حفظ کامل DDL و ایندکس‌ها.
     *
     * @param  array<string, mixed>  $plan
     */
    private function rebuildTable(string $table, array $plan, array $policy): void
    {
        $columns = $plan['columns'];

        $originalDdl = optional(DB::selectOne("SELECT sql FROM sqlite_master WHERE type = 'table' AND name = ?", [$table]))->sql;

        if ($originalDdl === null) {
            throw new RuntimeException("ساختار جدول «{$table}» در sqlite_master یافت نشد.");
        }

        $originalIndexes = DB::select("SELECT name, sql FROM sqlite_master WHERE type = 'index' AND tbl_name = ? AND sql IS NOT NULL", [$table]);
        $tempTable       = "_reindex_{$table}";

        DB::statement("DROP TABLE IF EXISTS [{$tempTable}]");

        $tempDdl = preg_replace(
            '/\bCREATE\s+TABLE\s+(?:IF\s+NOT\s+EXISTS\s+)?["\[\`]?' . preg_quote($table, '/') . '["\]\`]?/i',
            "CREATE TABLE [{$tempTable}]",
            $originalDdl,
            1
        );

        // قیدهای UNIQUE روی جدول موقت، ادغام را می‌شکنند؛ در انتها بازسازی می‌شوند.
        $tempDdl = preg_replace('/\s+(?:CONSTRAINT\s+["\w]+\s+)?UNIQUE\s*\([^)]*\)/i', '', (string) $tempDdl);

        DB::statement($tempDdl);

        $values = [];

        foreach ($plan['rows'] as $row) {
            $payload = array_fill_keys($columns, null);

            foreach ($columns as $column) {
                if (array_key_exists($column, $row)) {
                    $payload[$column] = $row[$column];
                }
            }

            $values[] = array_values($payload);

            if (count($values) >= $policy['insert_chunk']) {
                $this->insertChunk($tempTable, $columns, $values);
                $values = [];
            }
        }

        if ($values !== []) {
            $this->insertChunk($tempTable, $columns, $values);
        }

        $inserted = (int) DB::table($tempTable)->count();

        if ($inserted !== count($plan['rows'])) {
            throw new RuntimeException("ساخت مجدد «{$table}» کامل نشد ({$inserted} از " . count($plan['rows']) . ' رکورد)؛ عملیات برگشت خورد.');
        }

        DB::statement("DROP TABLE [{$table}]");
        DB::statement("ALTER TABLE [{$tempTable}] RENAME TO [{$table}]");

        foreach ($originalIndexes as $index) {
            if (empty($index->sql)) {
                continue;
            }

            try {
                DB::statement($index->sql);
            } catch (Throwable $e) {
                if ($policy['strict_indexes']) {
                    throw new RuntimeException("بازسازی ایندکس «{$index->name}» روی «{$table}» ممکن نشد (داده‌ی تکراری با قید یکتا؟): {$e->getMessage()}", 0, $e);
                }

                break;
            }
        }
    }

    /**
     * درج دسته‌ای با ID صریح (بدون اتکاء به query builder برای سرعت).
     *
     * @param  array<int, string>  $columns
     * @param  array<int, array<int, mixed>>  $values
     */
    private function insertChunk(string $table, array $columns, array $values): void
    {
        $quoted = implode(', ', array_map(fn ($column) => "[{$column}]", $columns));
        $oneRow = '(' . implode(', ', array_fill(0, count($columns), '?')) . ')';
        $sql    = "INSERT INTO [{$table}] ({$quoted}) VALUES " . implode(', ', array_fill(0, count($values), $oneRow));

        DB::statement($sql, array_merge(...array_map(fn (array $row) => array_values($row), $values)));
    }

    /**
     * جدول‌هایی که در این اجرا rebuild نمی‌شوند ولی به یک جدولِ بازسازی‌شده
     * اشاره می‌کنند: کلیدهایشان باید مستقیماً بازنویسی شوند (در نسخه‌ی قبل
     * دقیقاً همین‌جا رکوردها بی‌مرجع رها می‌شدند).
     *
     * @param  array<string, array<string, mixed>>  $plans
     */
    private function remapExternalChildren(string $parentTable, array $plan, array $plans, array $policy): void
    {
        foreach ($this->childReferences($parentTable) as [$childTable, $childColumn]) {
            if (isset($plans[$childTable]) || $childTable === $parentTable) {
                continue; // داخل plan خودش ترجمه شده است
            }

            $changes = [];

            foreach ($plan['idMap']['existing'] as $old => $new) {
                if ((int) $old !== (int) $new) {
                    $changes[(int) $old] = (int) $new;
                }
            }

            // رکوردهای بکاپی که «ادغام» شدند و فرزند در جدول بیرونی دارند:
            foreach ($plan['aliases'] as $alias) {
                $survivorId = $plan['rows'][$alias['into']]['id'] ?? null;

                if ($survivorId !== null && (int) $alias['old_id'] !== (int) $survivorId) {
                    $changes[(int) $alias['old_id']] = (int) $survivorId;
                }
            }

            if ($changes === []) {
                continue;
            }

            $this->applyRemap($childTable, $childColumn, $changes, $policy['case_chunk']);
        }
    }

    /**
     * بازنویسی اتمیک با CASE: مقدار قبلی ستون در همان جمله خوانده می‌شود،
     * بنابراین برخورد «ID مقصد با ID مبدأ» (علت جابه‌جایی زنجیره‌ای) ممکن نیست.
     *
     * @param  array<int, int>  $changes
     */
    private function applyRemap(string $childTable, string $childColumn, array $changes, int $caseChunk): void
    {
        if (! Schema::hasTable($childTable) || ! Schema::hasColumn($childTable, $childColumn)) {
            return;
        }

        foreach (array_chunk($changes, max(20, $caseChunk), true) as $batch) {
            $cases = '';

            foreach ($batch as $old => $new) {
                $cases .= " WHEN [{$childColumn}] = ? THEN ?";
            }

            $sql = "UPDATE [{$childTable}] SET [{$childColumn}] = CASE{$cases} ELSE [{$childColumn}] END"
                . ' WHERE [' . $childColumn . '] IN (' . implode(', ', array_fill(0, count($batch), '?')) . ')';

            DB::statement($sql, array_merge($this->pairs($batch), array_map('intval', array_keys($batch))));
        }
    }

    /** @param  array<int, int>  $batch  @return array<int, int> */
    private function pairs(array $batch): array
    {
        $flat = [];

        foreach ($batch as $old => $new) {
            $flat[] = (int) $old;
            $flat[] = (int) $new;
        }

        return $flat;
    }

    /**
     * رابطه‌ی ستون → جدول والد برای یک جدول (بر پایه‌ی PRAGMA foreign_key_list).
     *
     * @return array<string, string>
     */
    private function foreignKeyColumns(string $table): array
    {
        $map = [];

        foreach (DB::select("PRAGMA foreign_key_list([{$table}])") as $fk) {
            $fk     = (array) $fk;
            $from   = (string) ($fk['from'] ?? '');
            $parent = (string) ($fk['table'] ?? '');

            if ($from !== '' && $parent !== '') {
                $map[$from] = $parent;
            }
        }

        foreach ($this->fkRegistry() as $parent => $children) {
            foreach ($children as [$childTable, $childColumn]) {
                if ($childTable === $table && Schema::hasColumn($table, $childColumn)) {
                    $map[$childColumn] ??= $parent;
                }
            }
        }

        return $map;
    }

    /**
     * فرزندان یک والد در سراسر دیتابیس (رجیستری دستی هم پوشش داده می‌شود).
     *
     * @return array<int, array{0: string, 1: string}>
     */
    private function childReferences(string $parentTable): array
    {
        $children = [];

        foreach ($this->allTables() as $table) {
            if ($table === $parentTable || str_starts_with($table, '_reindex_')) {
                continue;
            }

            foreach ($this->foreignKeyColumns($table) as $column => $parent) {
                if ($parent === $parentTable && Schema::hasColumn($table, $column)) {
                    $children[$table . '.' . $column] = [$table, $column];
                }
            }
        }

        foreach ($this->fkRegistry()[$parentTable] ?? [] as [$table, $column]) {
            if (Schema::hasTable($table) && Schema::hasColumn($table, $column)) {
                $children[$table . '.' . $column] = [$table, $column];
            }
        }

        return array_values($children);
    }

    /** @return array<int, string> */
    private function allTables(): array
    {
        return array_map(
            fn ($row) => (string) ((array) $row)['name'],
            DB::select("SELECT name FROM sqlite_master WHERE type = 'table' AND name NOT LIKE 'sqlite_%' ORDER BY name")
        );
    }

    private function columnIsNullable(string $table, string $column): bool
    {
        foreach (DB::select("PRAGMA table_info([{$table}])") as $info) {
            $info = (array) $info;

            if (($info['name'] ?? null) === $column) {
                return (int) ($info['notnull'] ?? 0) === 0;
            }
        }

        return true;
    }

    /** بی‌مرجع‌ها فقط با اجازه‌ی کاربر NULL می‌شوند. */
    private function nullifyOrphans(array $violations): int
    {
        $count = 0;

        foreach ($violations as $violation) {
            $violation = (array) $violation;
            $table     = (string) ($violation['table'] ?? '');
            $rowid     = (int) ($violation['rowid'] ?? 0);
            $fkId      = (int) ($violation['fkid'] ?? -1);

            if ($table === '' || $rowid <= 0 || ! Schema::hasColumn($table, 'id')) {
                continue;
            }

            foreach (DB::select("PRAGMA foreign_key_list([{$table}])") as $fk) {
                $fk     = (array) $fk;
                $column = (string) ($fk['from'] ?? '');

                if ((int) ($fk['id'] ?? -1) === $fkId && $column !== '' && $this->columnIsNullable($table, $column)) {
                    DB::table($table)->where('id', $rowid)->update([$column => null]);
                    $count++;
                }
            }
        }

        return $count;
    }

    /** اصلاح AUTOINCREMENT پس از درج ID های صریح. */
    private function resetSequencesFor(array $tables): void
    {
        foreach ($tables as $table) {
            if (! Schema::hasTable($table) || ! Schema::hasColumn($table, 'id')) {
                continue;
            }

            try {
                $max    = (int) DB::table($table)->max('id');
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
     * رجیستری دستیِ وابستگی‌ها؛ پشتیبانِ PRAGMA foreign_key_list برای
     * دیتابیس‌هایی که FK اعلام‌شده ندارند (یا برای نسخه‌ی قدیمی SQLite).
     *
     * @return array<string, array<int, array{0: string, 1: string}>>
     */
    private function fkRegistry(): array
    {
        return [
            'customers'          => [['invoices', 'customer_id'], ['requests', 'customer_id'], ['service_jobs', 'customer_id'], ['archived_records', 'customer_id']],
            'categories'         => [['items', 'category_id'], ['order_items', 'category_id']],
            'items'              => [['order_items', 'item_id'], ['service_job_items', 'item_id'], ['stock_movements', 'item_id'], ['daily_item_stats', 'item_id']],
            'invoices'           => [['order_items', 'invoice_id'], ['service_jobs', 'invoice_id'], ['stock_movements', 'invoice_id'], ['invoice_adjustments', 'invoice_id'], ['archived_records', 'invoice_id']],
            'requests'           => [['invoices', 'request_id'], ['service_jobs', 'request_id'], ['request_categories', 'request_id']],
            'service_types'      => [['service_job_service_types', 'service_type_id']],
            'service_jobs'       => [['service_job_items', 'service_job_id'], ['service_job_service_types', 'service_job_id'], ['stock_movements', 'service_job_id']],
            'archived_records'   => [['archive_actions', 'archived_record_id']],
            'setting_groups'     => [['app_settings', 'group_id']],
            'users'              => [['archive_actions', 'actor_id'], ['cache_maintenance_runs', 'user_id'], ['requests', 'user_id'], ['invoices', 'user_id']],
            'order_items'        => [['stock_movements', 'order_item_id']],
        ];
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

