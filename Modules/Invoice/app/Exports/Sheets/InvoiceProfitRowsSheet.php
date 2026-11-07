<?php

namespace Modules\Invoice\Exports\Sheets;

use Maatwebsite\Excel\Concerns\Export;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithColumnWidths;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Events\AfterSheet;
use Modules\Invoice\Exports\Concerns\FormatsProfitExport;
use Modules\Invoice\Services\InvoiceProfitReportService;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;

class InvoiceProfitRowsSheet implements Export, FromArray, WithHeadings, WithTitle, WithColumnWidths, WithEvents
{
    use FormatsProfitExport;

    private const LAST_COL = 'R';

    /** @param array<int, array<string, mixed>> $rows */
    public function __construct(private array $rows)
    {
    }

    public function title(): string
    {
        return 'فاکتورها';
    }

    public function headings(): array
    {
        return [
            '#',
            'شماره فاکتور',
            'تاریخ',
            'مشتری',
            'وضعیت',
            'روش پرداخت',
            'درآمد کالا',
            'بهای خرید کالا',
            'درآمد خدمات',
            'قطعات مصرفی خدمات',
            'تعدیلات درآمدی',
            'جمع درآمد',
            'جمع هزینه',
            'سود',
            'حاشیه سود',
            'تعدیل غیردرآمدی (نادیده)',
            'اقلام مرجوع‌شده',
            'توضیحات',
        ];
    }

    public function array(): array
    {
        $out = [];

        foreach (array_values($this->rows) as $i => $r) {
            $out[] = [
                $i + 1,
                $r['number'],
                $this->jalaliDate($r['date']),
                $r['customer'],
                $this->statusLabel($r['status']),
                $this->methodLabel($r['method']),
                $r['items_revenue'],
                $r['items_cost'],
                $r['services_revenue'],
                $r['services_cost'],
                $r['revenue_adjustments'],
                $r['revenue'],
                $r['cost'],
                $r['profit'],
                $r['margin'] ?? '—',
                $r['non_revenue_adjustments'],
                $r['returned_lines'],
                $r['note'] ?: '-',
            ];
        }

        return $out;
    }

    public function columnWidths(): array
    {
        return [
            'A' => 5,  'B' => 20, 'C' => 12, 'D' => 20, 'E' => 13, 'F' => 14,
            'G' => 15, 'H' => 15, 'I' => 15, 'J' => 16, 'K' => 15, 'L' => 15,
            'M' => 15, 'N' => 15, 'O' => 11, 'P' => 18, 'Q' => 11, 'R' => 46,
        ];
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event): void {
                $sheet = $event->sheet->getDelegate();
                $last  = self::LAST_COL;
                $count = count($this->rows);
                $end   = $count + 1;

                $sheet->setRightToLeft(true);
                $sheet->freezePane('C2');

                // سرستون
                $sheet->getStyle("A1:{$last}1")->applyFromArray([
                    'font'      => ['bold' => true, 'size' => 11, 'color' => ['argb' => 'FF' . static::$colorHeaderFont]],
                    'fill'      => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => 'FF' . static::$colorHeaderBg]],
                    'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER, 'wrapText' => true],
                    'borders'   => ['bottom' => ['borderStyle' => Border::BORDER_MEDIUM, 'color' => ['argb' => 'FF' . static::$colorGold]]],
                ]);
                $sheet->getRowDimension(1)->setRowHeight(36);

                if ($count === 0) {
                    return;
                }

                foreach (array_values($this->rows) as $i => $r) {
                    $row = $i + 2;
                    $bg  = ($row % 2 === 0) ? static::$colorRowEven : static::$colorRowOdd;

                    $sheet->getStyle("A{$row}:{$last}{$row}")->applyFromArray([
                        'font'      => ['size' => 10.5, 'color' => ['argb' => 'FF' . static::$colorRowFont]],
                        'fill'      => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => 'FF' . $bg]],
                        'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER, 'wrapText' => true],
                        'borders'   => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['argb' => 'FF' . static::$colorBorder]]],
                    ]);

                    $sheet->getStyle("R{$row}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);

                    // وضعیت
                    [$statusBg, $statusFont] = match ($r['bucket']) {
                        InvoiceProfitReportService::BUCKET_REALIZED => ['D9EAD3', '1E6B2E'],
                        InvoiceProfitReportService::BUCKET_PENDING  => ['FCE8B2', '8A5A00'],
                        default                                     => ['F4CCCC', '9C1C1C'],
                    };
                    $sheet->getStyle("E{$row}")->applyFromArray([
                        'font' => ['bold' => true, 'color' => ['argb' => 'FF' . $statusFont]],
                        'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => 'FF' . $statusBg]],
                    ]);

                    // سود: سبز/قرمز؛ مرجوعی‌ها خاکستری
                    if ($r['bucket'] === InvoiceProfitReportService::BUCKET_RETURNED) {
                        $sheet->getStyle("A{$row}:{$last}{$row}")->getFont()->setItalic(true)->getColor()->setARGB('FF' . static::$colorMuted);
                        $sheet->getStyle("E{$row}")->getFont()->getColor()->setARGB('FF9C1C1C');
                    } else {
                        $sheet->getStyle("N{$row}")->getFont()->setBold(true)->getColor()
                            ->setARGB('FF' . (((float) $r['profit']) >= 0 ? static::$colorProfit : static::$colorLoss));
                    }
                }

                // قالب اعداد
                $sheet->getStyle("G2:N{$end}")->getNumberFormat()->setFormatCode('#,##0;-#,##0');
                $sheet->getStyle("P2:P{$end}")->getNumberFormat()->setFormatCode('#,##0;-#,##0');
                $sheet->getStyle("O2:O{$end}")->getNumberFormat()->setFormatCode('0.0"%"');
                $sheet->getStyle("Q2:Q{$end}")->getNumberFormat()->setFormatCode('#,##0');

                // کادر بیرونی
                $sheet->getStyle("A1:{$last}{$end}")->applyFromArray([
                    'borders' => ['outline' => ['borderStyle' => Border::BORDER_THICK, 'color' => ['argb' => 'FF' . static::$colorGold]]],
                ]);

                $sheet->setAutoFilter("A1:{$last}{$end}");
            },
        ];
    }
}
