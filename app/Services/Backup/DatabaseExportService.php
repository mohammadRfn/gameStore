<?php

namespace App\Services\Backup;

use App\Models\BackupRun;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Throwable;

/**
 * خروجی گرفتن از جداول دیتابیس به‌صورت فایل‌های CSV طبقه‌بندی‌شده.
 *
 * ویژگی‌ها:
 *  - پیمایش chunk-based با keyset pagination (مصرف حافظه ثابت حتی روی جداول بزرگ)
 *  - ستون‌ها مستقیماً از اسکیما خوانده می‌شوند (سازگار با مایگریشن‌های آینده)
 *  - فیلتر بازه‌ی تاریخ / فروشگاه / رکوردهای حذف‌شده‌ی نرم
 *  - محاسبه‌ی sha256 هر فایل برای اعتبارسنجی هنگام ایمپورت
 */
class DatabaseExportService
{
    public function __construct(
        private readonly BackupManifest $manifest,
        private readonly BackupPathResolver $paths,
        private readonly BackupRunRecorder $recorder,
    ) {
    }

    /**
     * @param  array<string, array<string, mixed>>  $entities
     * @return array<string, array<string, mixed>>  گزارش هر موجودیت
     */
    public function export(BackupRun $run, string $runPath, array $entities, array $options = []): array
    {
        $csv     = $this->csvOptions($options);
        $chunk   = max(100, (int) ($options['chunk_size'] ?? config('backup.runtime.chunk_size', 1000)));
        $report  = [];

        foreach ($entities as $key => $entity) {
            $startedAt = microtime(true);

            if (! empty($entity['missing']) || ! Schema::hasTable($entity['table'])) {
                $report[$key] = ['status' => 'skipped', 'reason' => 'table_missing', 'rows' => 0];
                $this->recorder->entity($run, $key, [
                    'table_name'   => $entity['table'],
                    'group_name'   => $entity['group'],
                    'display_name' => $entity['label'],
                    'status'       => 'skipped',
                    'error_message'=> 'جدول در دیتابیس وجود ندارد.',
                ]);

                continue;
            }

            try {
                $report[$key] = $this->exportEntity($run, $runPath, $entity, $csv, $chunk, $options, $startedAt);
            } catch (Throwable $e) {
                $report[$key] = ['status' => 'failed', 'error' => $e->getMessage(), 'rows' => 0];

                $this->recorder->entity($run, $key, [
                    'table_name'    => $entity['table'],
                    'group_name'    => $entity['group'],
                    'display_name'  => $entity['label'],
                    'status'        => 'failed',
                    'error_message' => mb_substr($e->getMessage(), 0, 1000),
                    'finished_at'   => now(),
                ]);

                $this->recorder->event($run, 'error', 'entity.failed', "خطا در خروجی «{$entity['label']}»: {$e->getMessage()}", ['entity' => $key]);
            }
        }

        return $report;
    }

    private function exportEntity(
        BackupRun $run,
        string $runPath,
        array $entity,
        array $csv,
        int $chunk,
        array $options,
        float $startedAt,
    ): array {
        $relative = $this->manifest->relativeCsvPath($entity);
        $absolute = $this->paths->normalize($runPath . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relative));

        $this->paths->ensureDirectory(dirname($absolute));

        $redact  = ! empty($options['redact_sensitive']) ? ($entity['redact'] ?? []) : [];
        $columns = $this->manifest->columns($entity['table'], $redact);

        $this->recorder->entity($run, $entity['key'], [
            'table_name'    => $entity['table'],
            'group_name'    => $entity['group'],
            'display_name'  => $entity['label'],
            'relative_path' => $relative,
            'absolute_path' => $absolute,
            'columns_json'  => $columns,
            'status'        => 'running',
            'started_at'    => now(),
        ]);

        $writer = new CsvStreamWriter(
            path: $absolute,
            delimiter: $csv['delimiter'],
            enclosure: $csv['enclosure'],
            lineEnding: $csv['line_ending'],
            bom: $csv['bom'],
            nullMarker: $csv['null_marker'],
        );

        $writer->writeHeader($columns);

        $lastId  = 0;
        $hasId   = in_array('id', $columns, true);
        $total   = 0;
        $mediaRefs = [];

        do {
            $query = $this->baseQuery($entity, $options)->select($columns);

            if ($hasId) {
                $query->where('id', '>', $lastId)->orderBy('id')->limit($chunk);
            } else {
                $query->offset($total)->limit($chunk);
            }

            $rows = $query->get();

            foreach ($rows as $row) {
                $data = (array) $row;
                $writer->writeRow($data);

                if ($hasId) {
                    $lastId = (int) ($data['id'] ?? $lastId);
                }

                // جمع‌آوری ارجاعات تصویری برای مرحله‌ی مدیا (بدون کوئری دوباره)
                foreach ($entity['media'] as $column => $target) {
                    if (! empty($data[$column])) {
                        $mediaRefs[] = [
                            'entity_key'  => $entity['key'],
                            'model_type'  => $entity['model'],
                            'model_id'    => $data['id'] ?? null,
                            'column_name' => $column,
                            'target'      => $target,
                            'storage_path'=> (string) $data[$column],
                        ];
                    }
                }

                $total++;
            }
        } while ($rows->count() === $chunk);

        $result = $writer->close();

        $this->recorder->entity($run, $entity['key'], [
            'status'         => 'completed',
            'row_count'      => $total,
            'processed_rows' => $total,
            'bytes'          => $result['bytes'],
            'checksum'       => $result['checksum'],
            'finished_at'    => now(),
            'duration_ms'    => (int) ((microtime(true) - $startedAt) * 1000),
        ]);

        $this->recorder->event($run, 'info', 'entity.exported', "«{$entity['label']}» با {$total} رکورد ذخیره شد.", [
            'entity' => $entity['key'],
            'file'   => $relative,
        ]);

        return [
            'status'        => 'completed',
            'label'         => $entity['label'],
            'table'         => $entity['table'],
            'rows'          => $total,
            'bytes'         => $result['bytes'],
            'checksum'      => $result['checksum'],
            'relative_path' => $relative,
            'columns'       => $columns,
            'media_refs'    => $mediaRefs,
        ];
    }

    /** ساخت کوئری پایه با اعمال فیلترها. */
    private function baseQuery(array $entity, array $options): Builder
    {
        $query = DB::table($entity['table']);

        // رکوردهای soft-delete شده
        if ($entity['soft_deletes'] && Schema::hasColumn($entity['table'], 'deleted_at') && empty($options['include_soft_deleted'])) {
            $query->whereNull('deleted_at');
        }

        // فیلتر فروشگاه
        if (! empty($options['shop_id']) && Schema::hasColumn($entity['table'], 'shop_id')) {
            $query->where('shop_id', $options['shop_id']);
        }

        // بازه‌ی تاریخ بر اساس created_at
        if (Schema::hasColumn($entity['table'], 'created_at')) {
            if (! empty($options['from_date'])) {
                $query->where('created_at', '>=', $options['from_date']);
            }
            if (! empty($options['to_date'])) {
                $query->where('created_at', '<=', $options['to_date']);
            }
        }

        return $query;
    }

    /** @return array<string, mixed> */
    private function csvOptions(array $options): array
    {
        return [
            'delimiter'   => $options['csv_delimiter'] ?? config('backup.csv.delimiter', ','),
            'enclosure'   => $options['csv_enclosure'] ?? config('backup.csv.enclosure', '"'),
            'line_ending' => config('backup.csv.line_ending', "\r\n"),
            'bom'         => (bool) ($options['csv_bom'] ?? config('backup.csv.bom', true)),
            'null_marker' => $options['csv_null_marker'] ?? config('backup.csv.null_marker', '\N'),
        ];
    }
}
