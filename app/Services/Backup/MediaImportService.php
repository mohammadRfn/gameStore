<?php

namespace App\Services\Backup;

use App\Models\BackupFile;
use App\Models\BackupRun;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Throwable;

/**
 * تزریق تصاویر از بسته‌ی ورودی به دیسک برنامه و اصلاح مسیر آن‌ها در دیتابیس.
 *
 * دو حالت پشتیبانی می‌شود:
 *   1) بسته‌ی استاندارد خودِ برنامه (media/items/{id}/file.jpg) → relink دقیق
 *      بر اساس شناسه‌ی رکورد و ستون مربوطه.
 *   2) پوشه‌ی دستیِ کاربر (هر ساختاری) → فایل‌ها بر اساس نام فایل با مقدار
 *      فعلیِ ستون‌های تصویری تطبیق داده می‌شوند.
 */
class MediaImportService
{
    /** نگاشت پوشه‌ی مدیا به موجودیت و ستون مربوطه. */
    private const FOLDER_MAP = [
        'items'              => ['entity' => 'items',       'table' => 'items',       'column' => 'image_path',         'storage_dir' => 'images/items'],
        'order-items'        => ['entity' => 'order_items', 'table' => 'order_items', 'column' => 'image_path',         'storage_dir' => 'images/order_items'],
        'order_items'        => ['entity' => 'order_items', 'table' => 'order_items', 'column' => 'image_path',         'storage_dir' => 'images/order_items'],
        'invoices/receipts'  => ['entity' => 'invoices',    'table' => 'invoices',    'column' => 'receipt_image_path', 'storage_dir' => 'images/receipts'],
        'invoices'           => ['entity' => 'invoices',    'table' => 'invoices',    'column' => 'receipt_image_path', 'storage_dir' => 'images/receipts'],
    ];

    public function __construct(
        private readonly BackupPathResolver $paths,
        private readonly BackupRunRecorder $recorder,
    ) {
    }

    /** @return array<string, int> */
    public function import(BackupRun $run, string $sourcePath, array $options = []): array
    {
        $mediaRoot = $this->locateMediaRoot($sourcePath);

        if ($mediaRoot === null) {
            $this->recorder->event($run, 'warning', 'media.not_found', 'پوشه‌ی تصاویر در بسته‌ی ورودی یافت نشد.');

            return ['imported' => 0, 'skipped' => 0, 'failed' => 0, 'relinked' => 0, 'bytes' => 0];
        }

        $disk    = Storage::disk(config('backup.media.disk', 'public'));
        $dryRun  = (bool) ($options['dry_run'] ?? false);
        $relink  = (bool) ($options['relink'] ?? config('backup.media.relink_on_import', true));
        $maxSize = (int) config('backup.media.max_file_mb', 25) * 1048576;
        $allowed = (array) config('backup.media.allowed_mimes', []);

        $stats = ['imported' => 0, 'skipped' => 0, 'failed' => 0, 'relinked' => 0, 'bytes' => 0];

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($mediaRoot, \FilesystemIterator::SKIP_DOTS)
        );

        foreach ($iterator as $file) {
            /** @var \SplFileInfo $file */
            if (! $file->isFile()) {
                continue;
            }

            $relative = str_replace('\\', '/', $this->paths->relative($mediaRoot, $file->getPathname()));
            $mapping  = $this->resolveMapping($relative);

            try {
                if ($file->getSize() > $maxSize) {
                    $stats['skipped']++;
                    $this->log($run, $file, $mapping, BackupFile::STATUS_SKIPPED, 'حجم فایل بیش از حد مجاز است.');

                    continue;
                }

                $mime = function_exists('mime_content_type') ? (@mime_content_type($file->getPathname()) ?: null) : null;

                if ($allowed !== [] && $mime !== null && ! in_array($mime, $allowed, true)) {
                    $stats['skipped']++;
                    $this->log($run, $file, $mapping, BackupFile::STATUS_SKIPPED, "نوع فایل مجاز نیست: {$mime}");

                    continue;
                }

                $storageDir  = $mapping['storage_dir'] ?? 'images/imported';
                $fileName    = $this->paths->sanitizeFileName($file->getFilename());
                $storagePath = $storageDir . '/' . $fileName;

                $hash = hash_file('sha256', $file->getPathname()) ?: null;

                // اگر فایل با همان محتوا از قبل هست، دوباره کپی نکن
                if ($disk->exists($storagePath) && $hash && hash_file('sha256', $disk->path($storagePath)) === $hash) {
                    $stats['skipped']++;
                } elseif (! $dryRun) {
                    if ($disk->exists($storagePath)) {
                        $storagePath = $storageDir . '/' . pathinfo($fileName, PATHINFO_FILENAME)
                            . '_' . substr((string) $hash, 0, 8)
                            . ($file->getExtension() ? '.' . $file->getExtension() : '');
                    }

                    $stream = @fopen($file->getPathname(), 'rb');

                    if ($stream === false) {
                        throw new \RuntimeException('امکان خواندن فایل وجود ندارد.');
                    }

                    $disk->put($storagePath, $stream);

                    if (is_resource($stream)) {
                        fclose($stream);
                    }

                    $stats['imported']++;
                    $stats['bytes'] += $file->getSize();
                } else {
                    $stats['imported']++;
                }

                $modelId  = $mapping['model_id'] ?? null;
                $relinked = false;

                if ($relink && ! $dryRun && isset($mapping['table'], $mapping['column'])) {
                    $relinked = $this->relink($mapping, $modelId, $storagePath, $file->getFilename());

                    if ($relinked) {
                        $stats['relinked']++;
                    }
                }

                $this->log($run, $file, $mapping, $relinked ? BackupFile::STATUS_RELINKED : BackupFile::STATUS_COPIED, null, $storagePath, $hash, $mime);
            } catch (Throwable $e) {
                $stats['failed']++;
                $this->log($run, $file, $mapping, BackupFile::STATUS_FAILED, mb_substr($e->getMessage(), 0, 500));
                $this->recorder->event($run, 'error', 'file.import_failed', $e->getMessage(), ['file' => $relative]);
            }
        }

        $this->recorder->event($run, 'info', 'media.imported', 'تزریق تصاویر پایان یافت.', $stats);

        return $stats;
    }

    /**
     * اتصال مجدد مسیر فایل به رکورد دیتابیس.
     *  - اگر شناسه در ساختار پوشه بود: بر اساس id
     *  - در غیر این صورت: تطبیق بر اساس نام فایل قدیمی
     */
    private function relink(array $mapping, ?int $modelId, string $storagePath, string $originalName): bool
    {
        $table  = $mapping['table'];
        $column = $mapping['column'];

        if (! Schema::hasTable($table) || ! Schema::hasColumn($table, $column)) {
            return false;
        }

        if ($modelId !== null) {
            return DB::table($table)->where('id', $modelId)->update([$column => $storagePath]) > 0;
        }

        return DB::table($table)
            ->where($column, 'like', '%' . $originalName)
            ->update([$column => $storagePath]) > 0;
    }

    /** تشخیص موجودیت/ستون/شناسه از روی مسیر نسبی فایل. */
    private function resolveMapping(string $relativePath): array
    {
        $segments = explode('/', trim($relativePath, '/'));
        array_pop($segments); // حذف نام فایل

        $modelId = null;
        if ($segments !== [] && ctype_digit((string) end($segments))) {
            $modelId = (int) array_pop($segments);
        }

        $folder = implode('/', $segments);

        foreach (self::FOLDER_MAP as $prefix => $mapping) {
            if ($folder === $prefix || Str::startsWith($folder, $prefix . '/')) {
                return $mapping + ['model_id' => $modelId, 'folder' => $folder];
            }
        }

        return ['entity' => null, 'table' => null, 'column' => null, 'storage_dir' => 'images/imported/' . ($folder ?: 'misc'), 'model_id' => $modelId, 'folder' => $folder];
    }

    private function log(
        BackupRun $run,
        \SplFileInfo $file,
        array $mapping,
        string $status,
        ?string $error = null,
        ?string $storagePath = null,
        ?string $hash = null,
        ?string $mime = null,
    ): void {
        BackupFile::query()->create([
            'backup_run_id' => $run->id,
            'entity_key'    => $mapping['entity'] ?? null,
            'model_id'      => $mapping['model_id'] ?? null,
            'column_name'   => $mapping['column'] ?? null,
            'disk'          => config('backup.media.disk', 'public'),
            'storage_path'  => $storagePath,
            'relative_path' => $file->getFilename(),
            'absolute_path' => $file->getPathname(),
            'original_name' => $file->getFilename(),
            'extension'     => $file->getExtension(),
            'mime_type'     => $mime,
            'size_bytes'    => (int) $file->getSize(),
            'sha256'        => $hash,
            'direction'     => BackupRun::DIRECTION_IMPORT,
            'status'        => $status,
            'error_message' => $error,
        ]);
    }

    /** پیدا کردن پوشه‌ی media داخل بسته (یا خودِ مسیر اگر مستقیماً تصاویر باشد). */
    private function locateMediaRoot(string $sourcePath): ?string
    {
        $candidate = $sourcePath . DIRECTORY_SEPARATOR . config('backup.paths.media_dir', 'media');

        if (is_dir($candidate)) {
            return $candidate;
        }

        return is_dir($sourcePath) ? $sourcePath : null;
    }
}
