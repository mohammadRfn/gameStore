<?php

namespace App\Services\Backup;

use App\Models\BackupFile;
use App\Models\BackupRun;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Morilog\Jalali\Jalalian;
use RuntimeException;
use Throwable;

/**
 * ارکستراتور ماژول بکاپ: نقطه‌ی ورود واحد برای اکسپورت و ایمپورت.
 *
 * مسئولیت‌ها:
 *  - قفل هم‌زمانی (فقط یک عملیات در لحظه روی سیستم تک‌کاربره)
 *  - ساخت ساختار دایرکتوری استاندارد و مانیفست JSON + فایل checksums
 *  - هماهنگی سرویس‌های دیتابیس/مدیا و جمع‌بندی متریک‌ها
 *  - بکاپ ایمنی خودکار پیش از هر ایمپورت (rollback-friendly)
 *  - سیاست نگهداری (retention) و پاک‌سازی نسخه‌های قدیمی
 *
 * ساختار بسته‌ی خروجی:
 *   GameStore_export_2026-01-20_14-30-05_1404-10-30/
 *     ├── manifest.json
 *     ├── checksums.sha256
 *     ├── README.txt
 *     ├── database/00_core/users.csv ...
 *     ├── media/items/12/ps5-controller.jpg ...
 *     └── logs/run.log
 */
class BackupService
{
    private const LOCK_KEY = 'backup:running';

    public function __construct(
        private readonly BackupManifest $manifest,
        private readonly BackupPathResolver $paths,
        private readonly BackupSettingsService $settings,
        private readonly BackupRunRecorder $recorder,
        private readonly DatabaseExportService $databaseExporter,
        private readonly MediaExportService $mediaExporter,
        private readonly DatabaseImportService $databaseImporter,
        private readonly MediaImportService $mediaImporter,
    ) {}

    /* ================================================================== */
    /*  EXPORT                                                            */
    /* ================================================================== */

    /**
     * گرفتن خروجی کامل (CSV + تصاویر) در دایرکتوری انتخابی کاربر.
     *
     * @param  array{
     *     destination_path?: string|null, mode?: string, entities?: array<int,string>,
     *     label?: string|null, include_media?: bool, include_soft_deleted?: bool,
     *     include_orphan_media?: bool, redact_sensitive?: bool, shop_id?: int|null,
     *     from_date?: string|null, to_date?: string|null, dry_run?: bool, is_auto?: bool,
     *     is_safety_copy?: bool, csv_delimiter?: string, chunk_size?: int
     * }  $options
     */
    public function export(array $options = [], ?int $actorId = null): BackupRun
    {
        return $this->withLock(fn() => $this->performExport($options, $actorId));
    }

    /**
     * منطق واقعی خروجی گرفتن، بدون گرفتن قفل.
     * توسط export() عمومی (که خودش قفل می‌گیرد) و همچنین توسط
     * import() برای ساخت بکاپ ایمنی (که از قبل داخل قفل است) فراخوانی می‌شود.
     */
    private function performExport(array $options = [], ?int $actorId = null): BackupRun
    {
        $this->tuneRuntime();

        $mode     = $options['mode'] ?? BackupRun::MODE_FULL;
        $entities = $this->manifest->only($options['entities'] ?? []);
        $root     = $this->paths->prepareExportRoot($options['destination_path'] ?? null);
        $runPath  = $this->paths->makeRunDirectory($root, 'export', $options['label'] ?? null);

        $options = $this->normalizeExportOptions($options);

        $run = $this->createRun([
            'direction'      => BackupRun::DIRECTION_EXPORT,
            'mode'           => $mode,
            'label'          => $options['label'] ?? null,
            'root_path'      => $root,
            'run_path'       => $runPath,
            'is_dry_run'     => false,
            'is_auto'        => (bool) ($options['is_auto'] ?? false),
            'is_safety_copy' => (bool) ($options['is_safety_copy'] ?? false),
            'shop_id'        => $options['shop_id'] ?? null,
            'options_json'   => $options,
            'filters_json'   => array_filter([
                'shop_id'   => $options['shop_id'] ?? null,
                'from_date' => $options['from_date'] ?? null,
                'to_date'   => $options['to_date'] ?? null,
            ], fn($v) => $v !== null),
            'entities_json'  => array_keys($entities),
            'created_by'     => $actorId,
        ]);

        $this->prepareRunSkeleton($runPath);
        $this->recorder->attachLogFile($runPath . DIRECTORY_SEPARATOR . config('backup.paths.logs_dir', 'logs') . DIRECTORY_SEPARATOR . 'run.log');
        $this->recorder->start($run);

        try {
            $report     = [];
            $mediaStats = ['copied' => 0, 'skipped' => 0, 'missing' => 0, 'failed' => 0, 'bytes' => 0, 'orphans' => 0];

            if ($mode !== BackupRun::MODE_MEDIA) {
                $report = $this->databaseExporter->export($run, $runPath, $entities, $options);
            }

            if ($mode !== BackupRun::MODE_DATABASE && ! empty($options['include_media'])) {
                $references = [];

                foreach ($report as $entityReport) {
                    foreach ($entityReport['media_refs'] ?? [] as $reference) {
                        $references[] = $reference;
                    }
                }

                if ($mode === BackupRun::MODE_MEDIA) {
                    $references = $this->collectMediaReferences($options);
                }

                $mediaStats = $this->mediaExporter->export($run, $runPath, $references, $options);
            }

            $totals = $this->summarizeExport($report, $mediaStats);

            $manifestPath = $this->writeManifest($run, $runPath, $entities, $report, $mediaStats, $totals);
            $checksumPath = $this->writeChecksums($runPath);
            $this->writeReadme($run, $runPath, $totals);

            $run->forceFill([
                'manifest_path'  => $manifestPath,
                'total_entities' => $totals['entities'],
                'total_rows'     => $totals['rows'],
                'total_files'    => $totals['files'],
                'missing_files'  => $mediaStats['missing'] ?? 0,
                'total_bytes'    => $totals['bytes'],
                'checksum'       => is_file($checksumPath) ? hash_file('sha256', $checksumPath) : null,
            ])->save();

            $status = $totals['failed_entities'] > 0 ? BackupRun::STATUS_PARTIAL : BackupRun::STATUS_COMPLETED;

            $this->recorder->finish($run, $status, $totals + ['media' => $mediaStats]);

            $this->applyRetention($root, $run);

            return $run->refresh();
        } catch (Throwable $e) {
            $this->recorder->fail($run, $e);

            throw $e;
        }
    }

    /* ================================================================== */
    /*  IMPORT                                                            */
    /* ================================================================== */

    /**
     * تزریق داده از یک بسته‌ی بکاپ (CSV و/یا تصاویر).
     *
     * @param  array{
     *     source_path?: string|null, mode?: string, strategy?: string,
     *     entities?: array<int,string>, dry_run?: bool, verify_checksums?: bool,
     *     safety_backup?: bool, stop_on_error?: bool, relink?: bool,
     *     ignore_fk_violations?: bool, chunk_size?: int
     * }  $options
     */
    public function import(array $options = [], ?int $actorId = null): BackupRun
    {
        return $this->withLock(function () use ($options, $actorId) {
            $this->tuneRuntime();

            $mode     = $options['mode'] ?? BackupRun::MODE_FULL;
            $strategy = $options['strategy'] ?? $this->settings->get('default_import_strategy', BackupRun::STRATEGY_MERGE);
            $dryRun   = (bool) ($options['dry_run'] ?? false);
            $source   = $this->paths->prepareImportRoot($options['source_path'] ?? null);
            $source   = $this->resolvePackageRoot($source);

            $entities = $this->manifest->only($options['entities'] ?? []);

            $run = $this->createRun([
                'direction'     => BackupRun::DIRECTION_IMPORT,
                'mode'          => $mode,
                'strategy'      => $strategy,
                'label'         => $options['label'] ?? null,
                'root_path'     => $source,
                'run_path'      => $source,
                'is_dry_run'    => $dryRun,
                'options_json'  => $options,
                'entities_json' => array_keys($entities),
                'created_by'    => $actorId,
            ]);

            $this->recorder->start($run);

            try {
                $manifest = $this->readManifest($source);

                $this->assertCompatible($manifest, $run);

                if (($options['verify_checksums'] ?? $this->settings->bool('verify_checksums', true)) && ! $dryRun) {
                    $this->verifyChecksums($run, $source);
                }

                // بکاپ ایمنی قبل از تغییر داده‌ها (قابل بازگشت بودن عملیات)
                $safety = null;
                if (! $dryRun && ($options['safety_backup'] ?? $this->settings->bool('auto_safety_backup', true))) {
                    $safety = $this->performExport([
                        'label'          => 'pre-import-safety',
                        'is_auto'        => true,
                        'is_safety_copy' => true,
                        'include_media'  => false,
                    ], $actorId);

                    $this->recorder->event(
                        $run,
                        'info',
                        'import.safety_backup',
                        'بکاپ ایمنی قبل از ایمپورت گرفته شد.',
                        ['run_id' => $safety->id, 'path' => $safety->run_path]
                    );
                }

                $report     = [];
                $mediaStats = ['imported' => 0, 'skipped' => 0, 'failed' => 0, 'relinked' => 0, 'bytes' => 0];

                if ($mode !== BackupRun::MODE_MEDIA) {
                    $report = $this->databaseImporter->import($run, $source, $entities, $options + ['strategy' => $strategy, 'dry_run' => $dryRun]);
                }

                if ($mode !== BackupRun::MODE_DATABASE) {
                    $mediaStats = $this->mediaImporter->import($run, $source, $options + ['dry_run' => $dryRun]);
                }

                $totals = $this->summarizeImport($report, $mediaStats);

                $run->forceFill([
                    'total_entities' => $totals['entities'],
                    'total_rows'     => $totals['rows'],
                    'inserted_rows'  => $totals['inserted'],
                    'updated_rows'   => $totals['updated'],
                    'skipped_rows'   => $totals['skipped'],
                    'failed_rows'    => $totals['failed'],
                    'total_files'    => $mediaStats['imported'] ?? 0,
                    'total_bytes'    => $mediaStats['bytes'] ?? 0,
                ])->save();

                $status = $totals['failed'] > 0 ? BackupRun::STATUS_PARTIAL : BackupRun::STATUS_COMPLETED;

                $this->recorder->finish($run, $status, $totals + [
                    'media'           => $mediaStats,
                    'safety_backup_id' => $safety?->id,
                ]);

                return $run->refresh();
            } catch (Throwable $e) {
                $this->recorder->fail($run, $e);

                throw $e;
            }
        });
    }

    /* ================================================================== */
    /*  PREVIEW / VALIDATION                                              */
    /* ================================================================== */

    /** بررسی بسته‌ی ورودی بدون هیچ تغییری در داده‌ها. */
    public function inspect(?string $sourcePath = null): array
    {
        $source   = $this->resolvePackageRoot($this->paths->prepareImportRoot($sourcePath));
        $manifest = $this->readManifest($source);
        $entities = [];

        foreach ($this->manifest->all() as $key => $entity) {
            $file = $source . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $this->manifest->relativeCsvPath($entity));

            $entities[$key] = [
                'label'      => $entity['label'],
                'table'      => $entity['table'],
                'file_found' => is_file($file),
                'rows'       => is_file($file) ? CsvStreamReader::countRows($file) : 0,
                'bytes'      => is_file($file) ? (int) filesize($file) : 0,
                'current_rows' => \Illuminate\Support\Facades\Schema::hasTable($entity['table'])
                    ? (int) DB::table($entity['table'])->count()
                    : null,
            ];
        }

        $mediaDir = $source . DIRECTORY_SEPARATOR . config('backup.paths.media_dir', 'media');

        return [
            'source_path'    => $source,
            'manifest'       => $manifest,
            'is_compatible'  => $manifest === null || version_compare((string) ($manifest['format_version'] ?? '1.0'), (string) config('backup.format_version'), '<='),
            'entities'       => $entities,
            'media_files'    => is_dir($mediaDir) ? $this->countFiles($mediaDir) : 0,
            'total_rows'     => array_sum(array_column($entities, 'rows')),
        ];
    }

    /** آمار سریع برای داشبورد بکاپ. */
    public function statistics(): array
    {
        $lastExport = BackupRun::query()->exports()->successful()->latest('finished_at')->first();
        $lastImport = BackupRun::query()->imports()->successful()->latest('finished_at')->first();

        return [
            'export_root'     => $this->paths->defaultExportRoot(),
            'import_root'     => $this->paths->defaultImportRoot(),
            'entities_count'  => count($this->manifest->all()),
            'total_runs'      => BackupRun::query()->count(),
            'failed_runs'     => BackupRun::query()->where('status', BackupRun::STATUS_FAILED)->count(),
            'last_export'     => $lastExport ? $this->presentRun($lastExport) : null,
            'last_import'     => $lastImport ? $this->presentRun($lastImport) : null,
            'days_since_backup' => $lastExport?->finished_at
                ? (int) $lastExport->finished_at->diffInDays(now())
                : null,
            'disk_free_mb'    => round(((float) (@disk_free_space($this->paths->defaultExportRoot()) ?: 0)) / 1048576, 1),
        ];
    }

    public function paginateRuns(array $filters = []): LengthAwarePaginator
    {
        $perPage = max(1, min((int) ($filters['per_page'] ?? 20), 100));

        return BackupRun::query()
            ->when(! empty($filters['direction']), fn(Builder $q) => $q->where('direction', $filters['direction']))
            ->when(! empty($filters['status']), fn(Builder $q) => $q->where('status', $filters['status']))
            ->when(! empty($filters['mode']), fn(Builder $q) => $q->where('mode', $filters['mode']))
            ->when(isset($filters['include_auto']) && ! $filters['include_auto'], fn(Builder $q) => $q->where('is_auto', false))
            ->withCount(['entities', 'files'])
            ->orderByDesc('id')
            ->paginate($perPage);
    }

    public function findRun(int $id): BackupRun
    {
        return BackupRun::query()->with(['entities', 'events' => fn($q) => $q->latest('id')->limit(200)])->findOrFail($id);
    }

    /** حذف رکورد اجرا و در صورت تمایل، پوشه‌ی فیزیکی آن. */
    public function deleteRun(int $id, bool $deleteFiles = false): void
    {
        $run = BackupRun::query()->findOrFail($id);

        if ($deleteFiles && $run->run_path && is_dir($run->run_path) && $run->direction === BackupRun::DIRECTION_EXPORT) {
            $this->deleteDirectory($run->run_path);
        }

        $run->delete();
    }

    /* ================================================================== */
    /*  Internals                                                         */
    /* ================================================================== */

    private function createRun(array $attributes): BackupRun
    {
        return BackupRun::query()->create(array_merge([
            'uuid'           => (string) Str::uuid(),
            'status'         => BackupRun::STATUS_PENDING,
            'format'         => 'csv',
            'app_version'    => config('app.version', config('backup.format_version')),
            'schema_version' => $this->schemaVersion(),
            'db_driver'      => DB::connection()->getDriverName(),
            'hostname'       => gethostname() ?: null,
            'os_family'      => PHP_OS_FAMILY,
        ], $attributes));
    }

    private function normalizeExportOptions(array $options): array
    {
        return array_merge([
            'include_media'        => $this->settings->bool('include_media', true),
            'include_soft_deleted' => $this->settings->bool('include_soft_deleted', true),
            'include_orphan_media' => false,
            'redact_sensitive'     => false,
            'chunk_size'           => $this->settings->int('chunk_size', 1000),
            'csv_delimiter'        => $this->settings->get('csv_delimiter', config('backup.csv.delimiter')),
            'csv_null_marker'      => $this->settings->get('csv_null_marker', config('backup.csv.null_marker')),
            'csv_bom'              => $this->settings->bool('csv_bom', true),
        ], array_filter($options, fn($v) => $v !== null));
    }

    private function prepareRunSkeleton(string $runPath): void
    {
        foreach ([config('backup.paths.database_dir'), config('backup.paths.media_dir'), config('backup.paths.logs_dir')] as $dir) {
            $this->paths->ensureDirectory($runPath . DIRECTORY_SEPARATOR . $dir);
        }

        foreach (array_keys((array) config('backup.groups', [])) as $group) {
            $this->paths->ensureDirectory($runPath . DIRECTORY_SEPARATOR . config('backup.paths.database_dir') . DIRECTORY_SEPARATOR . $group);
        }
    }

    /** ارجاعات تصویری مستقیم از دیتابیس (حالت mode=media). */
    private function collectMediaReferences(array $options): array
    {
        $references = [];

        foreach ($this->manifest->withMedia() as $entity) {
            foreach ($entity['media'] as $column => $target) {
                DB::table($entity['table'])
                    ->select(['id', $column])
                    ->whereNotNull($column)
                    ->when(
                        ! empty($options['shop_id']) && \Illuminate\Support\Facades\Schema::hasColumn($entity['table'], 'shop_id'),
                        fn($q) => $q->where('shop_id', $options['shop_id'])
                    )
                    ->orderBy('id')
                    ->chunk(500, function ($rows) use (&$references, $entity, $column, $target) {
                        foreach ($rows as $row) {
                            $references[] = [
                                'entity_key'   => $entity['key'],
                                'model_type'   => $entity['model'],
                                'model_id'     => $row->id,
                                'column_name'  => $column,
                                'target'       => $target,
                                'storage_path' => (string) $row->{$column},
                            ];
                        }
                    });
            }
        }

        return $references;
    }

    private function summarizeExport(array $report, array $mediaStats): array
    {
        $rows  = 0;
        $bytes = 0;
        $failed = 0;

        foreach ($report as $entity) {
            $rows  += $entity['rows'] ?? 0;
            $bytes += $entity['bytes'] ?? 0;
            $failed += ($entity['status'] ?? '') === 'failed' ? 1 : 0;
        }

        return [
            'entities'         => count($report),
            'failed_entities'  => $failed,
            'rows'             => $rows,
            'files'            => ($mediaStats['copied'] ?? 0),
            'bytes'            => $bytes + ($mediaStats['bytes'] ?? 0),
        ];
    }

    private function summarizeImport(array $report, array $mediaStats): array
    {
        $totals = ['entities' => count($report), 'rows' => 0, 'inserted' => 0, 'updated' => 0, 'skipped' => 0, 'failed' => 0];

        foreach ($report as $entity) {
            $totals['rows']     += $entity['processed'] ?? 0;
            $totals['inserted'] += $entity['inserted'] ?? 0;
            $totals['updated']  += $entity['updated'] ?? 0;
            $totals['skipped']  += $entity['skipped'] ?? 0;
            $totals['failed']   += $entity['failed'] ?? 0;
        }

        $totals['failed'] += $mediaStats['failed'] ?? 0;

        return $totals;
    }

    /** نوشتن manifest.json (قرارداد رسمی بسته‌ی بکاپ). */
    private function writeManifest(BackupRun $run, string $runPath, array $entities, array $report, array $mediaStats, array $totals): string
    {
        $path = $runPath . DIRECTORY_SEPARATOR . config('backup.paths.manifest_file', 'manifest.json');

        $payload = [
            'format_version' => config('backup.format_version'),
            'application'    => [
                'name'        => config('backup.app_name'),
                'app_version' => $run->app_version,
                'schema'      => $run->schema_version,
                'db_driver'   => $run->db_driver,
                'os'          => $run->os_family,
                'hostname'    => $run->hostname,
            ],
            'run' => [
                'uuid'       => $run->uuid,
                'direction'  => $run->direction,
                'mode'       => $run->mode,
                'label'      => $run->label,
                'created_at' => now()->toIso8601String(),
                'created_at_jalali' => Jalalian::now()->format('Y/m/d H:i:s'),
                'created_by' => $run->created_by,
                'filters'    => $run->filters_json,
                'options'    => $run->options_json,
            ],
            'totals'   => $totals + ['media' => $mediaStats],
            'entities' => array_map(static function ($entity) use ($report) {
                $result = $report[$entity['key']] ?? [];

                return [
                    'key'           => $entity['key'],
                    'table'         => $entity['table'],
                    'label'         => $entity['label'],
                    'group'         => $entity['group'],
                    'file'          => $result['relative_path'] ?? null,
                    'rows'          => $result['rows'] ?? 0,
                    'bytes'         => $result['bytes'] ?? 0,
                    'checksum'      => $result['checksum'] ?? null,
                    'columns'       => $result['columns'] ?? [],
                    'natural_key'   => $entity['natural_key'],
                    'media_columns' => array_keys($entity['media'] ?? []),
                    'status'        => $result['status'] ?? 'skipped',
                ];
            }, $entities),
            'media' => BackupFile::query()
                ->where('backup_run_id', $run->id)
                ->where('direction', BackupRun::DIRECTION_EXPORT)
                ->get(['entity_key', 'model_id', 'column_name', 'storage_path', 'relative_path', 'sha256', 'size_bytes', 'status'])
                ->toArray(),
            'csv' => [
                'delimiter'   => $run->options_json['csv_delimiter'] ?? config('backup.csv.delimiter'),
                'enclosure'   => config('backup.csv.enclosure'),
                'null_marker' => $run->options_json['csv_null_marker'] ?? config('backup.csv.null_marker'),
                'encoding'    => 'UTF-8',
                'bom'         => (bool) ($run->options_json['csv_bom'] ?? true),
            ],
        ];

        file_put_contents($path, json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));

        return $path;
    }

    /** تولید فایل checksums.sha256 برای کل بسته. */
    private function writeChecksums(string $runPath): string
    {
        $path  = $runPath . DIRECTORY_SEPARATOR . config('backup.paths.checksum_file', 'checksums.sha256');
        $lines = [];

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($runPath, \FilesystemIterator::SKIP_DOTS)
        );

        foreach ($iterator as $file) {
            /** @var \SplFileInfo $file */
            if (! $file->isFile() || $file->getPathname() === $path) {
                continue;
            }

            $lines[] = hash_file('sha256', $file->getPathname()) . '  ' . str_replace('\\', '/', $this->paths->relative($runPath, $file->getPathname()));
        }

        sort($lines);
        file_put_contents($path, implode("\n", $lines) . "\n");

        return $path;
    }

    private function writeReadme(BackupRun $run, string $runPath, array $totals): void
    {
        $jalaliNow = Jalalian::now()->format('Y/m/d H:i');

        $content = <<<TXT
    {$run->app_version} | بسته‌ی پشتیبان فروشگاه
    =====================================================
    شناسه اجرا      : {$run->uuid}
    تاریخ (میلادی)  : {$run->created_at}
    تاریخ (شمسی)    : {$jalaliNow}
    نوع             : {$run->mode}
    تعداد جداول     : {$totals['entities']}
    تعداد رکوردها   : {$totals['rows']}
    تعداد تصاویر    : {$totals['files']}

    ساختار پوشه‌ها
    -----------------------------------------------------
    database/   فایل‌های CSV دیتابیس، طبقه‌بندی‌شده بر اساس بخش‌های برنامه
    media/      تصاویر کالاها، اقلام فاکتور و رسیدهای پرداخت
    logs/       گزارش کامل اجرای بکاپ
    manifest.json     شناسنامه‌ی بسته (ستون‌ها، تعداد رکوردها، هش فایل‌ها)
    checksums.sha256  هش تمام فایل‌ها برای بررسی سلامت بسته

    نکات
    -----------------------------------------------------
    * فایل‌های CSV با انکودینگ UTF-8 و BOM ذخیره شده‌اند (سازگار با Excel).
    * مقدار خالیِ واقعی با نشانه‌ی \\N مشخص شده تا با رشته‌ی خالی اشتباه نشود.
    * برای بازیابی، همین پوشه را در بخش «ورود اطلاعات» برنامه انتخاب کنید.
    * پیش از بازیابی، برنامه به‌صورت خودکار یک نسخه‌ی ایمنی از وضعیت فعلی می‌گیرد.
    TXT;

        file_put_contents(
            $runPath . DIRECTORY_SEPARATOR . config('backup.paths.readme_file', 'README.txt'),
            $content
        );
    }

    private function readManifest(string $sourcePath): ?array
    {
        $path = $sourcePath . DIRECTORY_SEPARATOR . config('backup.paths.manifest_file', 'manifest.json');

        if (! is_file($path)) {
            return null;
        }

        $decoded = json_decode((string) file_get_contents($path), true);

        return is_array($decoded) ? $decoded : null;
    }

    private function assertCompatible(?array $manifest, BackupRun $run): void
    {
        if ($manifest === null) {
            $this->recorder->event(
                $run,
                'warning',
                'import.no_manifest',
                'فایل manifest.json یافت نشد؛ ایمپورت در حالت سازگاری انجام می‌شود.'
            );

            return;
        }

        $packageVersion = (string) ($manifest['format_version'] ?? '1.0');

        if (version_compare($packageVersion, (string) config('backup.format_version'), '>')) {
            throw new RuntimeException(
                "نسخه‌ی بسته‌ی بکاپ ({$packageVersion}) جدیدتر از نسخه‌ی پشتیبانی‌شده‌ی برنامه است؛ ابتدا برنامه را به‌روزرسانی کنید."
            );
        }
    }

    /** اعتبارسنجی sha256 فایل‌ها بر اساس checksums.sha256 */
    private function verifyChecksums(BackupRun $run, string $sourcePath): void
    {
        $path = $sourcePath . DIRECTORY_SEPARATOR . config('backup.paths.checksum_file', 'checksums.sha256');

        if (! is_file($path)) {
            $this->recorder->event($run, 'warning', 'import.no_checksums', 'فایل checksums.sha256 یافت نشد؛ اعتبارسنجی انجام نشد.');

            return;
        }

        $mismatched = [];

        foreach (file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [] as $line) {
            [$hash, $relative] = array_pad(preg_split('/\s+/', trim($line), 2) ?: [], 2, null);

            if (! $hash || ! $relative) {
                continue;
            }

            $file = $sourcePath . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relative);

            if (! is_file($file) || hash_file('sha256', $file) !== $hash) {
                $mismatched[] = $relative;
            }
        }

        if ($mismatched !== []) {
            throw new RuntimeException(sprintf(
                'سلامت بسته تایید نشد؛ %d فایل تغییر کرده یا ناقص است: %s',
                count($mismatched),
                implode(', ', array_slice($mismatched, 0, 5)),
            ));
        }

        $this->recorder->event($run, 'info', 'import.checksums_ok', 'سلامت تمام فایل‌های بسته تایید شد.');
    }

    /** اگر کاربر پوشه‌ی والد را انتخاب کرده باشد، جدیدترین بسته‌ی معتبر داخل آن پیدا می‌شود. */
    private function resolvePackageRoot(string $path): string
    {
        $manifestFile = config('backup.paths.manifest_file', 'manifest.json');
        $databaseDir  = config('backup.paths.database_dir', 'database');
        $mediaDir     = config('backup.paths.media_dir', 'media');

        if (
            is_file($path . DIRECTORY_SEPARATOR . $manifestFile)
            || is_dir($path . DIRECTORY_SEPARATOR . $databaseDir)
            || is_dir($path . DIRECTORY_SEPARATOR . $mediaDir)
        ) {
            return $path;
        }

        $candidates = glob($path . DIRECTORY_SEPARATOR . '*', GLOB_ONLYDIR) ?: [];
        $valid      = array_values(array_filter($candidates, fn($dir) => is_file($dir . DIRECTORY_SEPARATOR . $manifestFile)));

        if ($valid !== []) {
            usort($valid, fn($a, $b) => filemtime($b) <=> filemtime($a));

            return $valid[0];
        }

        return $path;
    }

    /** نگهداری فقط N نسخه‌ی آخر در پوشه‌ی خروجی. */
    private function deleteDirectory(string $path): void
    {
        $this->paths->assertSafePath($path);

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($path, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST
        );

        foreach ($iterator as $file) {
            /** @var \SplFileInfo $file */
            $file->isDir() ? @rmdir($file->getPathname()) : @unlink($file->getPathname());
        }

        @rmdir($path);
    }

    private function countFiles(string $dir): int
    {
        $count    = 0;
        $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS));

        foreach ($iterator as $file) {
            /** @var \SplFileInfo $file */
            if ($file->isFile()) {
                $count++;
            }
        }

        return $count;
    }

    private function schemaVersion(): ?string
    {
        try {
            return (string) DB::table('migrations')->orderByDesc('id')->value('migration');
        } catch (Throwable) {
            return null;
        }
    }

    /** جلوگیری از اجرای هم‌زمان دو عملیات بکاپ. */
    private function withLock(callable $callback): BackupRun
    {
        $lock = Cache::lock(self::LOCK_KEY, (int) config('backup.runtime.lock_seconds', 1800));

        if (! $lock->get()) {
            throw new RuntimeException('یک عملیات بکاپ/بازیابی در حال اجراست؛ لطفاً تا پایان آن صبر کنید.');
        }

        try {
            return $callback();
        } finally {
            optional($lock)->release();
        }
    }

    private function tuneRuntime(): void
    {
        @ini_set('memory_limit', (string) config('backup.runtime.memory_limit', '512M'));
        @set_time_limit((int) config('backup.runtime.time_limit', 0));
    }

    public function presentRun(BackupRun $run): array
    {
        return [
            'id'          => $run->id,
            'uuid'        => $run->uuid,
            'direction'   => $run->direction,
            'mode'        => $run->mode,
            'status'      => $run->status,
            'label'       => $run->label,
            'path'        => $run->run_path,
            'rows'        => $run->total_rows,
            'files'       => $run->total_files,
            'size_mb'     => round($run->total_bytes / 1048576, 2),
            'duration_ms' => $run->duration_ms,
            'created_at'  => $run->created_at?->toIso8601String(),
            'created_at_jalali' => $run->created_at ? Jalalian::fromCarbon($run->created_at)->format('Y/m/d H:i') : null,
            'error'       => $run->error_message,
        ];
    }
    private function applyRetention(string $root, BackupRun $current): void
    {
        $keep = $this->settings->int('retention_copies', (int) config('backup.runtime.retention_copies', 10));

        if ($keep > 0) {
            $runs = BackupRun::query()
                ->exports()
                ->where('root_path', $root)
                ->where('is_safety_copy', false)
                ->whereNotNull('run_path')
                ->orderByDesc('id')
                ->get();

            foreach ($runs->slice($keep) as $old) {
                if ($old->id === $current->id) {
                    continue;
                }

                if ($old->run_path && is_dir($old->run_path)) {
                    $this->deleteDirectory($old->run_path);
                }

                $old->delete();
            }
        }

        $this->applySafetyRetention($root, $current);
    }

    /** نگهداری فقط N نسخه‌ی ایمنی آخر (safety copies جدا از بکاپ‌های دستی/معمولی مدیریت می‌شوند). */
    private function applySafetyRetention(string $root, BackupRun $current): void
    {
        $keep = (int) config('backup.runtime.safety_retention_copies', 3);

        if ($keep <= 0) {
            return;
        }

        $runs = BackupRun::query()
            ->exports()
            ->where('root_path', $root)
            ->where('is_safety_copy', true)
            ->whereNotNull('run_path')
            ->orderByDesc('id')
            ->get();

        foreach ($runs->slice($keep) as $old) {
            if ($old->id === $current->id) {
                continue;
            }

            if ($old->run_path && is_dir($old->run_path)) {
                $this->deleteDirectory($old->run_path);
            }

            $old->delete();
        }
    }
}
