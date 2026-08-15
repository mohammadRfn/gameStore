<?php

namespace App\Services\Backup;

use App\Models\BackupFile;
use App\Models\BackupRun;
use Illuminate\Support\Facades\Storage;
use Throwable;

/**
 * کپی فایل‌های تصویری برنامه در بسته‌ی بکاپ، با طبقه‌بندی بر اساس موجودیت.
 *
 * ساختار خروجی:
 *   media/items/{item_id}/{file}
 *   media/order-items/{order_item_id}/{file}
 *   media/invoices/receipts/{invoice_id}/{file}
 *   media/_orphans/...              فایل‌هایی که در دیتابیس ارجاعی ندارند
 *
 * ویژگی‌ها:
 *  - کپی استریمی (بدون بارگذاری کامل فایل در حافظه)
 *  - dedupe بر اساس sha256
 *  - ثبت مانیفست کامل در جدول backup_files برای بازیابی دقیق و relink
 */
class MediaExportService
{
    public function __construct(
        private readonly BackupPathResolver $paths,
        private readonly BackupManifest $manifest,
        private readonly BackupRunRecorder $recorder,
    ) {
    }

    /**
     * @param  array<int, array<string, mixed>>  $references  خروجی DatabaseExportService
     * @return array<string, mixed>
     */
    public function export(BackupRun $run, string $runPath, array $references, array $options = []): array
    {
        $disk        = Storage::disk(config('backup.media.disk', 'public'));
        $deduplicate = (bool) ($options['deduplicate'] ?? config('backup.media.deduplicate', true));

        $stats = ['copied' => 0, 'skipped' => 0, 'missing' => 0, 'failed' => 0, 'bytes' => 0, 'orphans' => 0];
        $seen  = [];   // sha256 => relative_path
        $known = [];   // storage_path هایی که در DB ارجاع دارند

        foreach ($references as $reference) {
            $storagePath = ltrim((string) $reference['storage_path'], '/');
            $known[$storagePath] = true;

            $targetDir = $this->manifest->relativeMediaDir($reference['target'])
                . ($reference['model_id'] ? '/' . $reference['model_id'] : '');

            $result = $this->copyOne($run, $runPath, $disk, $storagePath, $targetDir, $reference, $deduplicate, $seen);

            $stats[$result['status']] = ($stats[$result['status']] ?? 0) + 1;
            $stats['bytes'] += $result['bytes'];
        }

        // فایل‌های یتیم (روی دیسک هستند ولی در دیتابیس ارجاع ندارند)
        if (! empty($options['include_orphan_media'])) {
            $stats['orphans'] = $this->exportOrphans($run, $runPath, $disk, $known, $seen, $deduplicate, $stats);
        }

        $this->recorder->event($run, 'info', 'media.exported', 'کپی فایل‌های تصویری پایان یافت.', $stats);

        return $stats;
    }

    /** @return array{status:string, bytes:int} */
    private function copyOne(
        BackupRun $run,
        string $runPath,
        \Illuminate\Contracts\Filesystem\Filesystem $disk,
        string $storagePath,
        string $targetDir,
        array $reference,
        bool $deduplicate,
        array &$seen,
    ): array {
        $attributes = [
            'backup_run_id' => $run->id,
            'entity_key'    => $reference['entity_key'] ?? null,
            'model_type'    => $reference['model_type'] ?? null,
            'model_id'      => $reference['model_id'] ?? null,
            'column_name'   => $reference['column_name'] ?? null,
            'disk'          => config('backup.media.disk', 'public'),
            'storage_path'  => $storagePath,
            'direction'     => BackupRun::DIRECTION_EXPORT,
        ];

        try {
            if (! $disk->exists($storagePath)) {
                BackupFile::query()->create($attributes + [
                    'relative_path' => $targetDir . '/' . basename($storagePath),
                    'status'        => BackupFile::STATUS_MISSING,
                    'error_message' => 'فایل روی دیسک یافت نشد.',
                ]);

                $this->recorder->event($run, 'warning', 'file.missing', "فایل یافت نشد: {$storagePath}", $attributes);

                return ['status' => 'missing', 'bytes' => 0];
            }

            $source = $disk->path($storagePath);
            $hash   = hash_file(config('backup.media.hash_algorithm', 'sha256'), $source) ?: null;

            // فایل تکراری: فقط در مانیفست ثبت می‌شود
            if ($deduplicate && $hash && isset($seen[$hash])) {
                BackupFile::query()->create($attributes + [
                    'relative_path' => $seen[$hash],
                    'sha256'        => $hash,
                    'size_bytes'    => (int) @filesize($source),
                    'extension'     => pathinfo($storagePath, PATHINFO_EXTENSION),
                    'original_name' => basename($storagePath),
                    'status'        => BackupFile::STATUS_DUPLICATED,
                ]);

                return ['status' => 'skipped', 'bytes' => 0];
            }

            $fileName = $this->paths->sanitizeFileName(basename($storagePath));
            $relative = $targetDir . '/' . $fileName;
            $absolute = $this->paths->normalize($runPath . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relative));

            $this->paths->ensureDirectory(dirname($absolute));
            $absolute = $this->uniquePath($absolute);
            $relative = $this->paths->relative($runPath, $absolute);

            $this->streamCopy($source, $absolute);

            $size = (int) @filesize($absolute);

            BackupFile::query()->create($attributes + [
                'relative_path' => $relative,
                'absolute_path' => $absolute,
                'original_name' => basename($storagePath),
                'extension'     => pathinfo($storagePath, PATHINFO_EXTENSION),
                'mime_type'     => $this->guessMime($source),
                'size_bytes'    => $size,
                'sha256'        => $hash,
                'status'        => BackupFile::STATUS_COPIED,
            ]);

            if ($hash) {
                $seen[$hash] = $relative;
            }

            return ['status' => 'copied', 'bytes' => $size];
        } catch (Throwable $e) {
            BackupFile::query()->create($attributes + [
                'relative_path' => $targetDir . '/' . basename($storagePath),
                'status'        => BackupFile::STATUS_FAILED,
                'error_message' => mb_substr($e->getMessage(), 0, 500),
            ]);

            $this->recorder->event($run, 'error', 'file.failed', $e->getMessage(), $attributes);

            return ['status' => 'failed', 'bytes' => 0];
        }
    }

    /** فایل‌های موجود روی دیسک که در هیچ رکوردی ارجاع ندارند. */
    private function exportOrphans(
        BackupRun $run,
        string $runPath,
        \Illuminate\Contracts\Filesystem\Filesystem $disk,
        array $known,
        array &$seen,
        bool $deduplicate,
        array &$stats,
    ): int {
        $count = 0;

        foreach ($disk->allFiles('images') as $path) {
            if (isset($known[$path])) {
                continue;
            }

            $result = $this->copyOne($run, $runPath, $disk, $path, $this->manifest->relativeMediaDir('_orphans/' . dirname(str_replace('images/', '', $path))), [
                'entity_key' => null,
                'model_type' => null,
                'model_id'   => null,
                'column_name'=> null,
            ], $deduplicate, $seen);

            $stats['bytes'] += $result['bytes'];
            $count++;
        }

        return $count;
    }

    private function streamCopy(string $source, string $destination): void
    {
        $in  = @fopen($source, 'rb');
        $out = @fopen($destination, 'wb');

        if ($in === false || $out === false) {
            if (is_resource($in)) {
                fclose($in);
            }
            if (is_resource($out)) {
                fclose($out);
            }

            throw new \RuntimeException("کپی فایل ناموفق بود: {$source}");
        }

        stream_copy_to_stream($in, $out);

        fclose($in);
        fclose($out);

        @touch($destination, (int) @filemtime($source));
    }

    private function uniquePath(string $path): string
    {
        if (! file_exists($path)) {
            return $path;
        }

        $dir  = dirname($path);
        $name = pathinfo($path, PATHINFO_FILENAME);
        $ext  = pathinfo($path, PATHINFO_EXTENSION);
        $i    = 1;

        do {
            $candidate = $dir . DIRECTORY_SEPARATOR . $name . '_' . $i++ . ($ext ? '.' . $ext : '');
        } while (file_exists($candidate));

        return $candidate;
    }

    private function guessMime(string $path): ?string
    {
        if (! function_exists('mime_content_type')) {
            return null;
        }

        $mime = @mime_content_type($path);

        return $mime === false ? null : $mime;
    }
}
