<?php

namespace App\Http\Controllers;

use App\Models\BackupRun;
use App\Services\Backup\BackupManifest;
use App\Services\Backup\BackupPathResolver;
use App\Services\Backup\BackupService;
use App\Services\Backup\BackupSettingsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

/**
 * کنترلر ماژول پشتیبان‌گیری و بازیابی.
 *
 * دو ناحیه‌ی مجزا مطابق درخواست:
 *   خروجی (Export) : ساخت بسته‌ی CSV + تصاویر در دایرکتوری انتخابی کاربر
 *   ورودی  (Import): تزریق CSV و/یا تصاویر از یک دایرکتوری مشخص
 *
 * تمام پاسخ‌ها JSON هستند تا هم برای Inertia/axios و هم برای پل IPC
 * الکترون قابل استفاده باشند.
 */
class BackupController extends Controller
{
    public function __construct(
        protected BackupService $backupService,
        protected BackupManifest $manifest,
        protected BackupPathResolver $paths,
        protected BackupSettingsService $settings,
    ) {}

    /* ------------------------------------------------------------------ */
    /* داشبورد و متادیتا                                                  */
    /* ------------------------------------------------------------------ */

    /** خلاصه‌ی وضعیت + مسیرهای پیش‌فرض + لیست موجودیت‌های قابل انتخاب. */
    public function overview(): JsonResponse
    {
        return response()->json([
            'data' => [
                'statistics' => $this->backupService->statistics(),
                'entities'   => $this->entityCatalog(),
                'groups'     => config('backup.groups'),
                'settings'   => $this->settings->all(),
                'defaults'   => [
                    'export_root'   => $this->paths->defaultExportRoot(),
                    'import_root'   => $this->paths->defaultImportRoot(),
                    'documents'     => $this->paths->userDocumentsPath(),
                    'home'          => $this->paths->userHomePath(),
                    'directory_sep' => DIRECTORY_SEPARATOR,
                    'os'            => PHP_OS_FAMILY,
                ],
                'options' => [
                    'modes'      => [BackupRun::MODE_FULL, BackupRun::MODE_DATABASE, BackupRun::MODE_MEDIA],
                    'strategies' => [
                        BackupRun::STRATEGY_MERGE,
                        BackupRun::STRATEGY_REPLACE,
                        BackupRun::STRATEGY_SKIP_EXISTING,
                        BackupRun::STRATEGY_FAIL,
                        BackupRun::STRATEGY_REINDEX,
                    ],
                    'format_version' => config('backup.format_version'),
                ],
            ],
        ]);
    }

    /** لیست موجودیت‌ها به همراه تعداد رکورد فعلی (برای انتخاب دستی کاربر). */
    public function entities(): JsonResponse
    {
        return response()->json(['data' => $this->entityCatalog()]);
    }

    /* ------------------------------------------------------------------ */
    /* بخش خروجی (Export)                                                  */
    /* ------------------------------------------------------------------ */

    /** اعتبارسنجی مسیر مقصد قبل از شروع (بررسی وجود، دسترسی نوشتن، فضای آزاد). */
    public function validateDestination(Request $request): JsonResponse
    {
        $data = $request->validate([
            'path' => ['nullable', 'string', 'max:1000'],
        ]);

        try {
            $path = $this->paths->prepareExportRoot($data['path'] ?? null);

            return response()->json([
                'data' => [
                    'path'         => $path,
                    'writable'     => true,
                    'free_space_mb' => round((float) (@disk_free_space($path) ?: 0) / 1048576, 1),
                ],
                'message' => 'مسیر معتبر است.',
            ]);
        } catch (Throwable $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }

    /** اجرای خروجی کامل/دیتابیس/تصاویر. */
    public function export(Request $request): JsonResponse
    {
        $this->castBooleans($request, [
            'include_media', 'include_soft_deleted', 'include_orphan_media',
            'redact_sensitive', 'remember_path',
        ]);

        $data = $request->validate([
            'destination_path'     => ['nullable', 'string', 'max:1000'],
            'mode'                 => ['nullable', Rule::in([BackupRun::MODE_FULL, BackupRun::MODE_DATABASE, BackupRun::MODE_MEDIA])],
            'entities'             => ['nullable', 'array'],
            'entities.*'           => ['string', Rule::in($this->manifest->keys())],
            'label'                => ['nullable', 'string', 'max:100'],
            'include_media'        => ['nullable', 'boolean'],
            'include_soft_deleted' => ['nullable', 'boolean'],
            'include_orphan_media' => ['nullable', 'boolean'],
            'redact_sensitive'     => ['nullable', 'boolean'],
            'shop_id'              => ['nullable', 'integer', 'exists:shops,id'],
            'from_date'            => ['nullable', 'date'],
            'to_date'              => ['nullable', 'date', 'after_or_equal:from_date'],
            'remember_path'        => ['nullable', 'boolean'],
        ]);

        if (! empty($data['remember_path']) && ! empty($data['destination_path'])) {
            $this->settings->set('export_root_path', $data['destination_path'], null, $request->user()?->id);
        }

        try {
            $run = $this->backupService->export($data, $request->user()?->id);
        } catch (Throwable $e) {
            return $this->error($e, 'خروجی گرفتن با خطا مواجه شد.');
        }

        return response()->json([
            'message' => 'پشتیبان‌گیری با موفقیت انجام شد.',
            'data'    => $this->backupService->presentRun($run),
        ]);
    }

    /** خروجی فقط دیتابیس (CSV) — میان‌بر. */
    public function exportDatabase(Request $request): JsonResponse
    {
        $request->merge(['mode' => BackupRun::MODE_DATABASE, 'include_media' => false]);

        return $this->export($request);
    }

    /** خروجی فقط تصاویر — میان‌بر. */
    public function exportMedia(Request $request): JsonResponse
    {
        $request->merge(['mode' => BackupRun::MODE_MEDIA, 'include_media' => true]);

        return $this->export($request);
    }

    /**
     * دانلود مستقیم CSV یک موجودیت (بدون ساخت بسته).
     * برای زمانی که کاربر فقط یک جدول را می‌خواهد.
     */
    public function downloadEntityCsv(Request $request, string $entityKey): StreamedResponse
    {
        $entity  = $this->manifest->get($entityKey);
        $columns = $this->manifest->columns($entity['table']);
        $csv     = config('backup.csv');

        $fileName = $entity['key'] . '_' . now()->format('Ymd_His') . '.csv';

        return response()->streamDownload(function () use ($entity, $columns, $csv) {
            $out = fopen('php://output', 'wb');

            if ($csv['bom'] ?? true) {
                fwrite($out, "\xEF\xBB\xBF");
            }

            fputcsv($out, $columns, $csv['delimiter'], $csv['enclosure']);

            \Illuminate\Support\Facades\DB::table($entity['table'])
                ->select($columns)
                ->orderBy('id')
                ->chunk(1000, function ($rows) use ($out, $columns, $csv) {
                    foreach ($rows as $row) {
                        $line = [];
                        foreach ($columns as $column) {
                            $value  = ((array) $row)[$column] ?? null;
                            $line[] = $value === null ? ($csv['null_marker'] ?? '\N') : $value;
                        }
                        fputcsv($out, $line, $csv['delimiter'], $csv['enclosure']);
                    }
                });

            fclose($out);
        }, $fileName, [
            'Content-Type'        => 'text/csv; charset=UTF-8',
            'Content-Disposition' => "attachment; filename=\"{$fileName}\"",
        ]);
    }

    /* ------------------------------------------------------------------ */
    /* بخش ورودی (Import)                                                  */
    /* ------------------------------------------------------------------ */

    /** بررسی بسته‌ی ورودی: چه فایل‌هایی هست، چند رکورد، سازگار هست یا نه. */
    public function inspect(Request $request): JsonResponse
    {
        $data = $request->validate([
            'source_path' => ['nullable', 'string', 'max:1000'],
        ]);

        try {
            return response()->json(['data' => $this->backupService->inspect($data['source_path'] ?? null)]);
        } catch (Throwable $e) {
            return $this->error($e, 'بررسی بسته‌ی ورودی ناموفق بود.');
        }
    }

    /** اجرای آزمایشی: همه‌چیز اجرا می‌شود ولی هیچ تغییری ذخیره نمی‌شود. */
    public function dryRun(Request $request): JsonResponse
    {
        $request->merge(['dry_run' => true]);

        return $this->import($request);
    }

    /** تزریق داده‌ها از بسته‌ی ورودی. */
    public function import(Request $request): JsonResponse
    {
        $this->castBooleans($request, [
            'dry_run', 'safety_backup', 'verify_checksums',
            'stop_on_error', 'relink', 'ignore_fk_violations', 'remember_path',
        ]);

        $data = $request->validate([
            'source_path'          => ['nullable', 'string', 'max:1000'],
            'mode'                 => ['nullable', Rule::in([BackupRun::MODE_FULL, BackupRun::MODE_DATABASE, BackupRun::MODE_MEDIA])],
            'strategy'             => ['nullable', Rule::in([
                BackupRun::STRATEGY_MERGE,
                BackupRun::STRATEGY_REPLACE,
                BackupRun::STRATEGY_SKIP_EXISTING,
                BackupRun::STRATEGY_FAIL,
                BackupRun::STRATEGY_REINDEX,
            ])],
            'entities'             => ['nullable', 'array'],
            'entities.*'           => ['string', Rule::in($this->manifest->keys())],
            'dry_run'              => ['nullable', 'boolean'],
            'safety_backup'        => ['nullable', 'boolean'],
            'verify_checksums'     => ['nullable', 'boolean'],
            'stop_on_error'        => ['nullable', 'boolean'],
            'relink'               => ['nullable', 'boolean'],
            'ignore_fk_violations' => ['nullable', 'boolean'],
            'confirmation'         => ['nullable', 'string'],
            'remember_path'        => ['nullable', 'boolean'],
        ]);

        // حالت replace داده‌های فعلی را پاک می‌کند؛ تاییدیه‌ی صریح لازم است.
        if (($data['strategy'] ?? null) === BackupRun::STRATEGY_REPLACE
            && empty($data['dry_run'])
            && ($data['confirmation'] ?? null) !== 'REPLACE'
        ) {
            throw ValidationException::withMessages([
                'confirmation' => 'برای جایگزینی کامل داده‌ها باید عبارت REPLACE را در فیلد تایید وارد کنید.',
            ]);
        }

        if (! empty($data['remember_path']) && ! empty($data['source_path'])) {
            $this->settings->set('import_root_path', $data['source_path'], null, $request->user()?->id);
        }

        try {
            $run = $this->backupService->import($data, $request->user()?->id);
        } catch (Throwable $e) {
            return $this->error($e, 'بازیابی اطلاعات با خطا مواجه شد.');
        }

        return response()->json([
            'message' => $run->is_dry_run
                ? 'اجرای آزمایشی انجام شد؛ هیچ تغییری ذخیره نشد.'
                : 'بازیابی اطلاعات با موفقیت انجام شد.',
            'data'    => $this->backupService->presentRun($run) + ['summary' => $run->summary_json],
        ]);
    }

    /** آپلود یک فایل CSV/تصویر از فرانت و قرار دادن آن در پوشه‌ی ورودی. */
    public function upload(Request $request): JsonResponse
    {
        $data = $request->validate([
            'file'        => ['required', 'file', 'max:51200'],
            'target'      => ['nullable', 'string', 'max:120'],
            'source_path' => ['nullable', 'string', 'max:1000'],
        ]);

        try {
            $root   = $this->paths->prepareExportRoot($data['source_path'] ?? $this->paths->defaultImportRoot());
            $target = $this->paths->ensureDirectory($root . DIRECTORY_SEPARATOR . trim($data['target'] ?? 'inbox', '/\\'));
            $name   = $this->paths->sanitizeFileName($request->file('file')->getClientOriginalName());

            $request->file('file')->move($target, $name);

            return response()->json([
                'message' => 'فایل در پوشه‌ی ورودی ذخیره شد.',
                'data'    => ['path' => $target . DIRECTORY_SEPARATOR . $name],
            ]);
        } catch (Throwable $e) {
            return $this->error($e, 'ذخیره‌ی فایل ناموفق بود.');
        }
    }

    /* ------------------------------------------------------------------ */
    /* تاریخچه‌ی اجراها                                                    */
    /* ------------------------------------------------------------------ */

    public function index(Request $request): JsonResponse
    {
        $this->castBooleans($request, ['include_auto']);

        $filters = $request->validate([
            'direction'    => ['nullable', Rule::in([BackupRun::DIRECTION_EXPORT, BackupRun::DIRECTION_IMPORT])],
            'status'       => ['nullable', 'string', 'max:20'],
            'mode'         => ['nullable', 'string', 'max:20'],
            'include_auto' => ['nullable', 'boolean'],
            'per_page'     => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        return response()->json(['data' => $this->backupService->paginateRuns($filters)]);
    }

    public function show(int $runId): JsonResponse
    {
        $run = $this->backupService->findRun($runId);

        return response()->json([
            'data' => [
                'run'      => $this->backupService->presentRun($run),
                'summary'  => $run->summary_json,
                'options'  => $run->options_json,
                'entities' => $run->entities,
                'events'   => $run->events,
            ],
        ]);
    }

    /** فایل‌های ثبت‌شده‌ی یک اجرا (برای گزارش تصاویر گم‌شده و ...). */
    public function files(Request $request, int $runId): JsonResponse
    {
        $run     = $this->backupService->findRun($runId);
        $status  = $request->query('status');

        $files = $run->files()
            ->when(is_string($status) && $status !== '', fn($q) => $q->where('status', $status))
            ->paginate(min(200, (int) $request->query('per_page', 50)));

        return response()->json(['data' => $files]);
    }

    /** دانلود فایل لاگ متنی یک اجرا. */
    public function downloadLog(int $runId)
    {
        $run  = $this->backupService->findRun($runId);
        $path = $run->run_path . DIRECTORY_SEPARATOR . config('backup.paths.logs_dir', 'logs') . DIRECTORY_SEPARATOR . 'run.log';

        if (! is_file($path)) {
            return response()->json(['message' => 'فایل لاگ یافت نشد.'], 404);
        }

        return response()->download($path, "backup-run-{$run->id}.log");
    }

    public function destroy(Request $request, int $runId): JsonResponse
    {
        $data = $request->validate(['delete_files' => ['nullable', 'boolean']]);

        try {
            $this->backupService->deleteRun($runId, (bool) ($data['delete_files'] ?? false));
        } catch (Throwable $e) {
            return $this->error($e, 'حذف رکورد ناموفق بود.');
        }

        return response()->json(['message' => 'رکورد بکاپ حذف شد.']);
    }

    /* ------------------------------------------------------------------ */
    /* تنظیمات                                                             */
    /* ------------------------------------------------------------------ */

    public function settings(): JsonResponse
    {
        return response()->json(['data' => $this->settings->all()]);
    }

    public function updateSettings(Request $request): JsonResponse
    {
        $this->castBooleans($request, [
            'include_media', 'include_soft_deleted', 'csv_bom',
            'auto_safety_backup', 'verify_checksums',
        ]);

        $data = $request->validate([
            'export_root_path'       => ['nullable', 'string', 'max:1000'],
            'import_root_path'       => ['nullable', 'string', 'max:1000'],
            'include_media'          => ['nullable', 'boolean'],
            'include_soft_deleted'   => ['nullable', 'boolean'],
            'csv_delimiter'          => ['nullable', 'string', 'max:2'],
            'csv_bom'                => ['nullable', 'boolean'],
            'csv_null_marker'        => ['nullable', 'string', 'max:5'],
            'chunk_size'             => ['nullable', 'integer', 'min:100', 'max:20000'],
            'retention_copies'       => ['nullable', 'integer', 'min:0', 'max:200'],
            'auto_safety_backup'     => ['nullable', 'boolean'],
            'verify_checksums'       => ['nullable', 'boolean'],
            'default_import_strategy' => ['nullable', Rule::in([
                BackupRun::STRATEGY_MERGE,
                BackupRun::STRATEGY_REPLACE,
                BackupRun::STRATEGY_SKIP_EXISTING,
                BackupRun::STRATEGY_FAIL,
                BackupRun::STRATEGY_REINDEX,
            ])],
        ]);

        foreach (['export_root_path', 'import_root_path'] as $pathKey) {
            if (! empty($data[$pathKey])) {
                $this->paths->assertSafePath($data[$pathKey]);
            }
        }

        $saved = $this->settings->setMany(
            array_filter($data, fn($value) => $value !== null),
            null,
            $request->user()?->id,
        );

        return response()->json([
            'message' => 'تنظیمات پشتیبان‌گیری ذخیره شد.',
            'data'    => $saved,
        ]);
    }

    /* ------------------------------------------------------------------ */
    /* Helpers                                                             */
    /* ------------------------------------------------------------------ */

    /** @return array<int, array<string, mixed>> */
    private function entityCatalog(): array
    {
        return array_values(array_map(function (array $entity) {
            $exists = \Illuminate\Support\Facades\Schema::hasTable($entity['table']);

            return [
                'key'           => $entity['key'],
                'label'         => $entity['label'],
                'table'         => $entity['table'],
                'group'         => $entity['group'],
                'group_label'   => config("backup.groups.{$entity['group']}"),
                'has_media'     => ! empty($entity['media']),
                'media_columns' => array_keys($entity['media'] ?? []),
                'soft_deletes'  => $entity['soft_deletes'],
                'rows'          => $exists ? (int) \Illuminate\Support\Facades\DB::table($entity['table'])->count() : 0,
                'available'     => $exists,
            ];
        }, $this->manifest->all()));
    }
    private function castBooleans(Request $request, array $fields): void
    {
        $request->merge(
            collect($fields)
                ->filter(fn($field) => $request->has($field))
                ->mapWithKeys(fn($field) => [
                    $field => filter_var($request->input($field), FILTER_VALIDATE_BOOLEAN),
                ])
                ->all()
        );
    }
    private function error(Throwable $e, string $fallback): JsonResponse
    {
        report($e);

        return response()->json([
            'message' => $e->getMessage() ?: $fallback,
            'error'   => class_basename($e),
        ], 422);
    }
}
