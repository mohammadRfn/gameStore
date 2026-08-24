<?php

namespace App\Exports\Concerns;

use App\Models\ArchivedRecord;
use Illuminate\Support\Carbon;
use Morilog\Jalali\Jalalian;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Color;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use Maatwebsite\Excel\Events\AfterSheet;

/**
 * منطق مشترک بین همه‌ی کلاس‌های اکسپورت بایگانی:
 * فرمت تاریخ جلالی، فرمت مبلغ، برچسب فارسی وضعیت‌ها،
 * دسترسی امن به snapshot_json، و استایل‌های لاکچری اکسل.
 */
trait FormatsArchiveExports
{
    /* ------------------------------------------------------------------ */
    /* رنگ‌های تم (گیم‌استور — تیره/طلایی)                                 */
    /* ------------------------------------------------------------------ */
    protected static string $colorHeaderBg      = '1A1A2E';   // سرمه‌ای تیره (فقط سرستون)
    protected static string $colorHeaderFont     = 'FFD700';   // طلایی
    protected static string $colorSubHeaderBg    = '16213E';   // سرمه‌ای متوسط
    protected static string $colorSubHeaderFont  = 'E8D5B7';   // کرم
    protected static string $colorRowEven        = 'F5EAD1';   // کرم عمیق‌تر
    protected static string $colorRowOdd          = 'FBF6E9';   // کرم روشن
    protected static string $colorRowFont        = '3B2F1E';   // قهوه‌ای تیره (خوانا روی کرم)
    protected static string $colorBorder         = 'D9C289';   // طلایی کدر
    protected static string $colorAccentGold     = 'C9A227';   // طلایی تیره‌تر برای بوردر/جداکننده
    protected static string $colorTotalBg        = 'B8860B';   // طلایی تیره
    protected static string $colorTotalFont      = 'FFFFFF';   // سفید
    protected static string $fontFamily          = 'Vazirmatn'; // باید روی سیستمی که فایل رو باز می‌کنه نصب باشه

    /* ------------------------------------------------------------------ */
    /* تبدیل‌ها و لیبل‌ها                                                  */
    /* ------------------------------------------------------------------ */

    protected function jalali(mixed $date): string
    {
        if (!$date) {
            return '-';
        }

        $carbon = $date instanceof Carbon ? $date : Carbon::parse($date);

        return class_exists(Jalalian::class)
            ? Jalalian::fromCarbon($carbon)->format('Y/m/d H:i')
            : $carbon->format('Y-m-d H:i');
    }

    protected function amount(mixed $value): string
    {
        if ($value === null || $value === '') {
            return '-';
        }

        return number_format((float) $value, 0);
    }

    protected function archiveStatusLabel(ArchivedRecord $record): string
    {
        return match ($record->archive_status) {
            ArchivedRecord::STATUS_TRANSFERRED => 'منتقل‌شده (حذف از بخش اصلی)',
            ArchivedRecord::STATUS_COPIED       => 'کپی‌شده (هنوز در بخش اصلی فعال است)',
            default                              => (string) $record->archive_status,
        };
    }

    protected function paymentStatusLabel(?string $status): string
    {
        return match ($status) {
            'paid'     => 'پرداخت‌شده ✓',
            'unpaid'   => 'پرداخت‌نشده ✗',
            'returned' => 'مرجوع‌شده ↩',
            default    => (string) $status,
        };
    }

    protected function paymentMethodLabel(?string $method): string
    {
        return match ($method) {
            'cash'         => 'نقدی 💵',
            'card_to_card' => 'کارت به کارت 💳',
            'pos_terminal' => 'دستگاه POS 🏧',
            default        => $method ?: '-',
        };
    }

    protected function snapshot(ArchivedRecord $record): array
    {
        return is_array($record->snapshot_json) ? $record->snapshot_json : [];
    }

    /* ------------------------------------------------------------------ */
    /* استخراج آیتم‌های سفارش از snapshot                                  */
    /* ------------------------------------------------------------------ */

    protected function orderItemsText(array $snapshot): string
    {
        $items = data_get($snapshot, 'paid_invoice.order_items', []);
        if (empty($items)) {
            return '-';
        }

        $lines = [];
        foreach ($items as $i => $item) {
            $name  = data_get($item, 'product_name', data_get($item, 'item.name', '—'));
            $qty   = data_get($item, 'quantity', 1);
            $price = number_format((float) data_get($item, 'price', 0), 0);
            $total = number_format((float) data_get($item, 'total_price', 0), 0);
            $lines[] = ($i + 1) . ") {$name} × {$qty} — في: {$price} — جمع: {$total}";
        }
        return implode("\n", $lines);
    }

    /** جدول آیتم‌ها به‌صورت فقط نام‌ها */
    protected function orderItemsNames(array $snapshot): string
    {
        $items = data_get($snapshot, 'paid_invoice.order_items', []);
        if (empty($items)) {
            return '-';
        }

        return collect($items)
            ->map(fn($item) => data_get($item, 'product_name', data_get($item, 'item.name', '—')))
            ->filter()
            ->implode('، ');
    }

    /* ------------------------------------------------------------------ */
    /* استخراج تعدیلات (تخفیف/اضافه) از snapshot                          */
    /* ------------------------------------------------------------------ */

    protected function adjustmentsText(array $snapshot): string
    {
        $adjustments = data_get($snapshot, 'paid_invoice.adjustments', []);
        if (empty($adjustments)) {
            return '-';
        }

        $lines = [];
        foreach ($adjustments as $adj) {
            $title     = data_get($adj, 'title', '-');
            $direction = data_get($adj, 'direction') === 'increase' ? '➕ افزایش' : '➖ کاهش';
            $type      = data_get($adj, 'type') === 'percentage' ? '%' : 'تومان';
            $value     = number_format((float) data_get($adj, 'value', 0), 0);
            $lines[]   = "{$title}: {$direction} {$value} {$type}";
        }
        return implode("\n", $lines);
    }

    /* ------------------------------------------------------------------ */
    /* استخراج قطعات مصرفی سرویس از snapshot                             */
    /* ------------------------------------------------------------------ */

    protected function serviceItemsText(array $snapshot): string
    {
        $items = data_get($snapshot, 'source.items', []);
        if (empty($items)) {
            return '-';
        }

        $lines = [];
        foreach ($items as $i => $item) {
            $name  = data_get($item, 'item.name', data_get($item, 'product_name', '—'));
            $qty   = data_get($item, 'quantity', 1);
            $price = number_format((float) data_get($item, 'unit_price', 0), 0);
            $lines[] = ($i + 1) . ") {$name} × {$qty} — {$price} تومان";
        }
        return implode("\n", $lines);
    }

    /* ------------------------------------------------------------------ */
    /* استخراج نوع سرویس‌ها از snapshot                                   */
    /* ------------------------------------------------------------------ */

    protected function serviceTypesText(array $snapshot): string
    {
        $types = data_get($snapshot, 'source.service_types', []);
        if (empty($types)) {
            return '-';
        }

        return collect($types)
            ->map(fn($st) => data_get($st, 'service_type.name', '-'))
            ->filter()
            ->implode('، ');
    }

    /* ------------------------------------------------------------------ */
    /* استایل لاکچری مشترک برای همه شیت‌ها                                */
    /* ------------------------------------------------------------------ */

    /**
     * اعمال استایل حرفه‌ای روی شیت
     *
     * @param Worksheet $sheet
     * @param string    $lastCol   آخرین ستون (مثلاً 'O' یا 'P')
     * @param int       $dataRows  تعداد سطرهای داده
     */
    protected function applyLuxuryStyles(Worksheet $sheet, string $lastCol, int $dataRows): void
    {
        $lastDataRow = $dataRows + 1; // +1 بخاطر هدر

        /* ---------- راست‌به‌چپ و فریز هدر ---------- */
        $sheet->setRightToLeft(true);
        $sheet->freezePane('A2');

        /* ---------- استایل ردیف هدر ---------- */
        $headerRange = "A1:{$lastCol}1";
        $sheet->getStyle($headerRange)->applyFromArray([
            'font' => [
                'bold'  => true,
                'size'  => 12,
                'color' => ['argb' => 'FF' . static::$colorHeaderFont],
                'name'  => static::$fontFamily,
            ],
            'fill' => [
                'fillType'   => Fill::FILL_SOLID,
                'startColor' => ['argb' => 'FF' . static::$colorHeaderBg],
            ],
            'alignment' => [
                'horizontal' => Alignment::HORIZONTAL_CENTER,
                'vertical'   => Alignment::VERTICAL_CENTER,
                'wrapText'   => true,
            ],
            'borders' => [
                'bottom' => [
                    'borderStyle' => Border::BORDER_MEDIUM,
                    'color'       => ['argb' => 'FF' . static::$colorAccentGold],
                ],
            ],
        ]);
        $sheet->getRowDimension(1)->setRowHeight(
            $this->calculateHeaderRowHeight($sheet, $lastCol)
        );

        /* ---------- استایل ردیف‌های داده ---------- */
        for ($row = 2; $row <= $lastDataRow; $row++) {
            $isEven  = ($row % 2 === 0);
            $bgColor = $isEven ? static::$colorRowEven : static::$colorRowOdd;

            $sheet->getStyle("A{$row}:{$lastCol}{$row}")->applyFromArray([
                'font' => [
                    'size'  => 10.5,
                    'color' => ['argb' => 'FF' . static::$colorRowFont],
                    'name'  => static::$fontFamily,
                ],
                'fill' => [
                    'fillType'   => Fill::FILL_SOLID,
                    'startColor' => ['argb' => 'FF' . $bgColor],
                ],
                'alignment' => [
                    'horizontal' => Alignment::HORIZONTAL_CENTER,
                    'vertical'   => Alignment::VERTICAL_CENTER,
                    'wrapText'   => true,
                ],
                'borders' => [
                    'allBorders' => [
                        'borderStyle' => Border::BORDER_THIN,
                        'color'       => ['argb' => 'FF' . static::$colorBorder],
                    ],
                ],
            ]);
            $sheet->getRowDimension($row)->setRowHeight(
                $this->calculateRowHeight($sheet, $row, $lastCol)
            );
        }

        /* ---------- بوردر بیرونی کل جدول ---------- */
        if ($lastDataRow >= 2) {
            $sheet->getStyle("A1:{$lastCol}{$lastDataRow}")->applyFromArray([
                'borders' => [
                    'outline' => [
                        'borderStyle' => Border::BORDER_THICK,
                        'color'       => ['argb' => 'FF' . static::$colorAccentGold],
                    ],
                ],
            ]);
        }
    }

    protected function estimateWrappedLineCount(string $text, float $columnWidthChars): int
    {
        $columnWidthChars = max($columnWidthChars, 4);
        $lines = 0;

        foreach (explode("\n", $text) as $segment) {
            $len = mb_strlen($segment);
            $lines += (int) max(1, ceil($len / $columnWidthChars));
        }

        return max($lines, 1);
    }

    /**
     * ارتفاع ردیف هدر را بر اساس طول عنوان هر ستون نسبت به عرض همان
     * ستون محاسبه می‌کند.
     */
    protected function calculateHeaderRowHeight(Worksheet $sheet, string $lastCol): int
    {
        $maxLines   = 1;
        $highestCol = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::columnIndexFromString($lastCol);

        for ($colIndex = 1; $colIndex <= $highestCol; $colIndex++) {
            $col   = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($colIndex);
            $value = (string) $sheet->getCell("{$col}1")->getValue();
            $width = $sheet->getColumnDimension($col)->getWidth();
            $maxLines = max($maxLines, $this->estimateWrappedLineCount($value, $width));
        }

        return min(max($maxLines * 16 + 14, 30), 90);
    }

    /**
     * ارتفاع یک ردیف داده را بر اساس بیشترین تعداد خط لازم در بین همه‌ی
     * ستون‌های آن ردیف (با احتساب عرض واقعی هر ستون) محاسبه می‌کند.
     */
    protected function calculateRowHeight(Worksheet $sheet, int $row, string $lastCol): int
    {
        $maxLines   = 1;
        $highestCol = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::columnIndexFromString($lastCol);

        for ($colIndex = 1; $colIndex <= $highestCol; $colIndex++) {
            $col   = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($colIndex);
            $value = (string) $sheet->getCell("{$col}{$row}")->getValue();
            $width = $sheet->getColumnDimension($col)->getWidth();
            $maxLines = max($maxLines, $this->estimateWrappedLineCount($value, $width));
        }

        return min(max($maxLines * 15 + 8, 26), 260);
    }

    /**
     * رجیسترهای مشترک AfterSheet برای اعمال استایل.
     */
    protected function luxuryAfterSheetEvent(string $lastCol): \Closure
    {
        return function (AfterSheet $event) use ($lastCol): void {
            $sheet    = $event->sheet->getDelegate();
            $dataRows = max($sheet->getHighestRow() - 1, 0);
            $this->applyLuxuryStyles($sheet, $lastCol, $dataRows);
        };
    }
}
