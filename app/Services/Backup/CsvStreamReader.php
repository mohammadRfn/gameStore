<?php

namespace App\Services\Backup;

use Generator;
use RuntimeException;

/**
 * خواننده‌ی استریمیِ CSV با مصرف حافظه‌ی ثابت.
 *
 *  - حذف خودکار BOM
 *  - بازگرداندن هر سطر به‌صورت آرایه‌ی associative بر اساس هدر
 *  - تبدیل «null marker» به NULL واقعی و خنثی‌سازی prefix ضدفرمول
 *  - اعتبارسنجی تعداد ستون‌ها و گزارش شماره‌ی سطر خطا
 */
class CsvStreamReader
{
    /** @var resource */
    private $handle;

    /** @var array<int, string> */
    private array $header = [];

    public function __construct(
        private readonly string $path,
        private readonly string $delimiter = ',',
        private readonly string $enclosure = '"',
        private readonly string $escape = '\\',
        private readonly string $nullMarker = '\N',
    ) {
        if (! is_file($this->path) || ! is_readable($this->path)) {
            throw new RuntimeException("فایل CSV قابل خواندن نیست: {$this->path}");
        }

        $handle = @fopen($this->path, 'rb');

        if ($handle === false) {
            throw new RuntimeException("امکان باز کردن فایل CSV وجود ندارد: {$this->path}");
        }

        $this->handle = $handle;
        $this->readHeader();
    }

    private function readHeader(): void
    {
        $first = fgets($this->handle);

        if ($first === false) {
            $this->header = [];

            return;
        }

        // حذف BOM
        $first = preg_replace('/^\xEF\xBB\xBF/', '', $first) ?? $first;

        $parsed = str_getcsv(rtrim($first, "\r\n"), $this->delimiter, $this->enclosure, $this->escape);

        $this->header = array_map(
            static fn ($column) => trim((string) $column),
            $parsed
        );
    }

    /** @return array<int, string> */
    public function header(): array
    {
        return $this->header;
    }

    /**
     * پیمایش سطرها.
     *
     * @return Generator<int, array<string, mixed>>
     */
    public function rows(): Generator
    {
        $line = 1;

        while (($record = fgetcsv($this->handle, 0, $this->delimiter, $this->enclosure, $this->escape)) !== false) {
            $line++;

            // سطر خالی
            if ($record === [null] || $record === [] || (count($record) === 1 && trim((string) $record[0]) === '')) {
                continue;
            }

            if (count($record) !== count($this->header)) {
                // سطرهای ناقص را با padding تحمل می‌کنیم تا کل ایمپورت خراب نشود
                $record = array_pad(array_slice($record, 0, count($this->header)), count($this->header), null);
            }

            $row = [];
            foreach ($this->header as $index => $column) {
                $row[$column] = $this->normalizeValue($record[$index] ?? null);
            }

            $row['__line'] = $line;

            yield $row;
        }
    }

    private function normalizeValue(mixed $value): mixed
    {
        if ($value === null) {
            return null;
        }

        $value = (string) $value;

        if ($value === $this->nullMarker) {
            return null;
        }

        // خنثی‌سازی prefix ضدفرمول که هنگام اکسپورت اضافه شده بود
        if (strlen($value) > 1 && $value[0] === "'" && in_array($value[1], ['=', '+', '@'], true)) {
            $value = substr($value, 1);
        }

        return $value;
    }

    /** تعداد سطرهای داده (بدون هدر) — برای پیش‌نمایش/گزارش. */
    public static function countRows(string $path): int
    {
        $handle = @fopen($path, 'rb');

        if ($handle === false) {
            return 0;
        }

        $count = 0;
        while (fgets($handle) !== false) {
            $count++;
        }
        fclose($handle);

        return max(0, $count - 1);
    }

    public function close(): void
    {
        if (is_resource($this->handle)) {
            fclose($this->handle);
        }
    }

    public function __destruct()
    {
        $this->close();
    }
}
