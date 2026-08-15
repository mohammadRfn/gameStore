<?php

namespace App\Services\Backup;

use Illuminate\Support\Facades\Schema;
use InvalidArgumentException;

/**
 * رجیستری موجودیت‌های قابل بکاپ.
 *
 * ستون‌ها به‌صورت داینامیک از خودِ اسکیمای SQLite خوانده می‌شوند؛ بنابراین با
 * اضافه شدن هر مایگریشن جدید، خروجی CSV هم به‌صورت خودکار کامل می‌ماند و
 * نیازی به به‌روزرسانی دستی لیست ستون‌ها نیست.
 */
class BackupManifest
{
    /** @var array<string, array<int, string>> */
    private array $columnCache = [];

    /** @return array<string, array<string, mixed>> */
    public function all(): array
    {
        $entities = [];

        foreach ((array) config('backup.entities', []) as $key => $definition) {
            $definition = $this->normalize($key, $definition);

            if (! Schema::hasTable($definition['table'])) {
                if (! empty($definition['optional'])) {
                    continue;
                }

                // جدول اجباری ولی ناموجود: با فلگ missing برگردانده می‌شود تا گزارش شود.
                $definition['missing'] = true;
            }

            $entities[$key] = $definition;
        }

        return $entities;
    }

    /** @return array<int, string> */
    public function keys(): array
    {
        return array_keys($this->all());
    }

    /** @return array<string, array<string, mixed>> */
    public function only(array $keys): array
    {
        if ($keys === []) {
            return $this->all();
        }

        $all     = $this->all();
        $unknown = array_diff($keys, array_keys($all));

        if ($unknown !== []) {
            throw new InvalidArgumentException('موجودیت نامعتبر: ' . implode(', ', $unknown));
        }

        // ترتیب مانیفست (وابستگی FK) حفظ می‌شود، نه ترتیب ورودی کاربر.
        return array_filter($all, fn ($key) => in_array($key, $keys, true), ARRAY_FILTER_USE_KEY);
    }

    public function get(string $key): array
    {
        $all = $this->all();

        if (! isset($all[$key])) {
            throw new InvalidArgumentException("موجودیت «{$key}» تعریف نشده است.");
        }

        return $all[$key];
    }

    /** موجودیت‌هایی که ستون تصویری دارند. */
    public function withMedia(): array
    {
        return array_filter($this->all(), fn ($e) => ! empty($e['media']));
    }

    /**
     * ستون‌های واقعی جدول (بدون ستون‌های حساس در حالت redact).
     *
     * @return array<int, string>
     */
    public function columns(string $table, array $exclude = []): array
    {
        $columns = $this->columnCache[$table] ??= (Schema::hasTable($table) ? Schema::getColumnListing($table) : []);

        return array_values(array_diff($columns, $exclude));
    }

    /** ترتیب معکوس برای عملیات حذف/replace (فرزندان قبل از والدین). */
    public function reverseOrder(array $entities): array
    {
        return array_reverse($entities, true);
    }

    /** نام فایل CSV و مسیر نسبی داخل بسته. */
    public function relativeCsvPath(array $entity): string
    {
        $dir = trim((string) config('backup.paths.database_dir', 'database'), '/');
        $ext = config('backup.csv.extension', 'csv');

        return "{$dir}/{$entity['group']}/{$entity['key']}.{$ext}";
    }

    /** مسیر نسبی پوشه‌ی مدیای یک ستون تصویری. */
    public function relativeMediaDir(string $target): string
    {
        $dir = trim((string) config('backup.paths.media_dir', 'media'), '/');

        return "{$dir}/" . trim($target, '/');
    }

    private function normalize(string $key, array $definition): array
    {
        return [
            'key'           => $key,
            'table'         => $definition['table'] ?? $key,
            'model'         => $definition['model'] ?? null,
            'group'         => $definition['group'] ?? '00_core',
            'label'         => $definition['label'] ?? $key,
            'natural_key'   => $definition['natural_key'] ?? [],
            'soft_deletes'  => (bool) ($definition['soft_deletes'] ?? false),
            'media'         => $definition['media'] ?? [],
            'redact'        => $definition['redact'] ?? [],
            'optional'      => (bool) ($definition['optional'] ?? false),
            'missing'       => false,
        ];
    }
}
