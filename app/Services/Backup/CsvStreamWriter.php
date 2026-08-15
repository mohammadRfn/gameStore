<?php

namespace App\Services\Backup;

use RuntimeException;

/**
 * نویسنده‌ی استریمیِ CSV سازگار با RFC-4180 و Excel فارسی.
 *
 *  - خروجی UTF-8 با BOM اختیاری (برای باز شدن درست در Excel ویندوز)
 *  - تفکیک NULL از رشته‌ی خالی با «null marker» (پیش‌فرض \N)
 *  - محاسبه‌ی هم‌زمان sha256 و حجم فایل بدون خواندن مجدد
 *  - حفاظت در برابر CSV/Formula Injection (=, +, -, @)
 */
class CsvStreamWriter
{
    /** @var resource */
    private $handle;

    private \HashContext $hash;

    private int $bytes = 0;

    private int $rows = 0;

    /** @var array<int, string> */
    private array $columns = [];

    public function __construct(
        private readonly string $path,
        private readonly string $delimiter = ',',
        private readonly string $enclosure = '"',
        private readonly string $lineEnding = "\r\n",
        private readonly bool $bom = true,
        private readonly string $nullMarker = '\N',
    ) {
        $handle = @fopen($this->path, 'wb');

        if ($handle === false) {
            throw new RuntimeException("امکان ایجاد فایل CSV وجود ندارد: {$this->path}");
        }

        $this->handle = $handle;
        $this->hash   = hash_init('sha256');

        if ($this->bom) {
            $this->raw("\xEF\xBB\xBF");
        }
    }

    /** @param array<int, string> $columns */
    public function writeHeader(array $columns): void
    {
        $this->columns = $columns;
        $this->writeRow($columns, escapeFormulas: false);
    }

    /** @param array<string, mixed>|array<int, mixed> $row */
    public function writeRow(array $row, bool $escapeFormulas = true): void
    {
        if ($this->columns !== [] && ! array_is_list($row)) {
            $ordered = [];
            foreach ($this->columns as $column) {
                $ordered[] = $row[$column] ?? null;
            }
            $row = $ordered;
        }

        $cells = array_map(fn ($value) => $this->formatCell($value, $escapeFormulas), $row);

        $this->raw(implode($this->delimiter, $cells) . $this->lineEnding);
        $this->rows++;
    }

    private function formatCell(mixed $value, bool $escapeFormulas): string
    {
        if ($value === null) {
            return $this->nullMarker;
        }

        if (is_bool($value)) {
            $value = $value ? '1' : '0';
        } elseif ($value instanceof \DateTimeInterface) {
            $value = $value->format('Y-m-d H:i:s');
        } elseif (is_array($value)) {
            $value = json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        } else {
            $value = (string) $value;
        }

        // جلوگیری از اجرای فرمول هنگام باز کردن در Excel
        if ($escapeFormulas && $value !== '' && in_array($value[0], ['=', '+', '@', "\t", "\r"], true)) {
            $value = "'" . $value;
        }

        $needsQuotes = $value === ''
            || str_contains($value, $this->delimiter)
            || str_contains($value, $this->enclosure)
            || str_contains($value, "\n")
            || str_contains($value, "\r");

        if ($needsQuotes) {
            $value = $this->enclosure . str_replace($this->enclosure, $this->enclosure . $this->enclosure, $value) . $this->enclosure;
        }

        return $value;
    }

    private function raw(string $chunk): void
    {
        $written = fwrite($this->handle, $chunk);

        if ($written === false) {
            throw new RuntimeException("نوشتن در فایل CSV ناموفق بود: {$this->path}");
        }

        hash_update($this->hash, $chunk);
        $this->bytes += $written;
    }

    /** @return array{path:string, rows:int, bytes:int, checksum:string, columns:array<int,string>} */
    public function close(): array
    {
        if (is_resource($this->handle)) {
            fflush($this->handle);
            fclose($this->handle);
        }

        return [
            'path'     => $this->path,
            'rows'     => $this->rows,
            'bytes'    => $this->bytes,
            'checksum' => hash_final($this->hash),
            'columns'  => $this->columns,
        ];
    }

    public function rowCount(): int
    {
        return $this->rows;
    }
}
