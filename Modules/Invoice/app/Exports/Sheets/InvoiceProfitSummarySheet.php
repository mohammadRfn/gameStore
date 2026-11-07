<?php

namespace Modules\Invoice\Exports\Sheets;

use Illuminate\Support\Carbon;
use Maatwebsite\Excel\Concerns\Export;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithColumnWidths;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Events\AfterSheet;
use Modules\Invoice\Exports\Concerns\FormatsProfitExport;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;

class InvoiceProfitSummarySheet implements Export, FromArray, WithTitle, WithColumnWidths, WithEvents
{
    use FormatsProfitExport;

    /** @var array<int, array{0:string,1:mixed,2:string}> [برچسب، مقدار، نوع ردیف] */
    private array $lines = [];

    public function __construct(
        private array $summary,
        private Carbon $from,
        private Carbon $to
    ) {
        $this->buildLines();
    }

    public function title(): string
    {
        return 'خلاصه سود و زیان';
    }

    public function array(): array
    {
        return array_map(fn ($line) => [$line[0], $line[1]], $this->lines);
    }

    public function columnWidths(): array
    {
        return ['A' => 52, 'B' => 30];
    }

    /* ------------------------------------------------------------------ */

    private function add(string $label, mixed $value = '', string $type = 'money'): void
    {
        $this->lines[] = [$label, $value, $type];
    }

    private function buildLines(): void
    {
        $s = $this->summary;

        $this->add('گزارش سود و زیان فاکتورها', '', 'title');
        $this->add('بازه زمانی', 'از ' . $this->jalaliDate($this->from) . ' تا ' . $this->jalaliDate($this->to), 'text');
        $this->add('تاریخ تهیه گزارش', $this->jalaliDate(now(), 'Y/m/d H:i'), 'text');
        $this->add('تعداد کل فاکتورهای بازه (همهٔ وضعیت‌ها)', $s['invoice_count'], 'int');

        $this->block('✅ فاکتورهای پرداخت‌شده — سود محقق‌شده', $s['realized']);
        $this->block('⏳ فاکتورهای پرداخت‌نشده — سود احتمالی', $s['pending']);

        $all = $s['all_active'];
        $this->add('📊 جمع کل (پرداخت‌شده + پرداخت‌نشده)', '', 'section');
        $this->add('تعداد فاکتور', $all['count'], 'int');
        $this->add('جمع درآمد', $all['revenue']);
        $this->add('جمع هزینه', $all['cost']);
        $this->add('سود کل', $all['profit'], 'profit');
        $this->add('حاشیه سود', $all['margin'], 'percent');

        $r = $s['returned'];
        $this->add('↩ مرجوعی‌ها — بدون اثر در درآمد و سود', '', 'section');
        $this->add('فاکتورهای کاملاً مرجوع‌شده (تعداد)', $r['invoices_count'], 'int');
        $this->add('مبلغ فاکتورهای مرجوع‌شده که از محاسبه حذف شد', $r['invoices_value']);
        $this->add('اقلام مرجوع‌شده داخل فاکتورهای فعال (تعداد قلم)', $r['lines_count'], 'int');
        $this->add('مبلغ اقلام مرجوع‌شده که از محاسبه حذف شد', $r['lines_value']);

        $this->add('ℹ️ تعدیلات غیردرآمدی (در سود لحاظ نشده)', '', 'section');
        $this->add('خالص تعدیلات غیردرآمدی (ارسال، مالیات، کارمزد و…)', $all['non_revenue_adjustments']);

        $this->add('روش محاسبه', '', 'section');
        foreach ([
            'سود = (درآمد کالا − بهای خرید کالا) + (درآمد خدمات − قطعات مصرفی سرویس) + تعدیلات درآمدی',
            'بهای خرید هر قلم: قیمت خریدِ ثبت‌شده روی همان فاکتور؛ در نبودش قیمت خرید فعلی کالا',
            'تعدیل‌هایی که «درآمدی» نیستند (ارسال، مالیات، کارمزد درگاه و…) وارد سود نمی‌شوند',
            'تاریخ فاکتور پرداخت‌شده = تاریخ پرداخت؛ برای بقیه = تاریخ صدور',
        ] as $text) {
            $this->add($text, '', 'note');
        }
    }

    private function block(string $title, array $t): void
    {
        $this->add($title, '', 'section');
        $this->add('تعداد فاکتور', $t['count'], 'int');
        $this->add('درآمد کالا', $t['items_revenue']);
        $this->add('بهای خرید کالا', $t['items_cost']);
        $this->add('سود کالا', $t['items_profit'], 'profit');
        $this->add('درآمد خدمات', $t['services_revenue']);
        $this->add('قطعات مصرفی خدمات', $t['services_cost']);
        $this->add('سود خدمات', $t['services_profit'], 'profit');
        $this->add('تعدیلات درآمدی (خالص تخفیف/افزایش)', $t['revenue_adjustments']);
        $this->add('جمع درآمد', $t['revenue']);
        $this->add('جمع هزینه', $t['cost']);
        $this->add('سود خالص', $t['profit'], 'profit');
        $this->add('حاشیه سود', $t['margin'], 'percent');
    }

    /* ------------------------------------------------------------------ */

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event): void {
                $sheet = $event->sheet->getDelegate();
                $sheet->setRightToLeft(true);

                $zebra = 0;

                foreach ($this->lines as $index => [$label, $value, $type]) {
                    $row   = $index + 1;
                    $range = "A{$row}:B{$row}";

                    if ($type === 'title' || $type === 'section' || $type === 'note') {
                        $sheet->mergeCells($range);
                    }

                    if ($type === 'title') {
                        $sheet->getStyle($range)->applyFromArray([
                            'font'      => ['bold' => true, 'size' => 15, 'color' => ['argb' => 'FF' . static::$colorHeaderFont]],
                            'fill'      => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => 'FF' . static::$colorHeaderBg]],
                            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
                        ]);
                        $sheet->getRowDimension($row)->setRowHeight(34);
                        $zebra = 0;
                        continue;
                    }

                    if ($type === 'section') {
                        $sheet->getStyle($range)->applyFromArray([
                            'font'      => ['bold' => true, 'size' => 12, 'color' => ['argb' => 'FF' . static::$colorSectionFont]],
                            'fill'      => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => 'FF' . static::$colorSectionBg]],
                            'alignment' => ['horizontal' => Alignment::HORIZONTAL_RIGHT, 'vertical' => Alignment::VERTICAL_CENTER, 'indent' => 1],
                            'borders'   => ['bottom' => ['borderStyle' => Border::BORDER_MEDIUM, 'color' => ['argb' => 'FF' . static::$colorGold]]],
                        ]);
                        $sheet->getRowDimension($row)->setRowHeight(26);
                        $zebra = 0;
                        continue;
                    }

                    if ($type === 'note') {
                        $sheet->getStyle($range)->applyFromArray([
                            'font'      => ['size' => 10, 'italic' => true, 'color' => ['argb' => 'FF' . static::$colorMuted]],
                            'alignment' => ['horizontal' => Alignment::HORIZONTAL_RIGHT, 'wrapText' => true, 'indent' => 1],
                        ]);
                        continue;
                    }

                    // ردیف‌های داده
                    $bg = ($zebra++ % 2 === 0) ? static::$colorRowEven : static::$colorRowOdd;

                    $sheet->getStyle($range)->applyFromArray([
                        'font'      => ['size' => 11, 'color' => ['argb' => 'FF' . static::$colorRowFont]],
                        'fill'      => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => 'FF' . $bg]],
                        'borders'   => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['argb' => 'FF' . static::$colorBorder]]],
                        'alignment' => ['vertical' => Alignment::VERTICAL_CENTER],
                    ]);
                    $sheet->getStyle("A{$row}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT)->setIndent(1);
                    $sheet->getStyle("B{$row}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

                    if ($type === 'money' || $type === 'profit') {
                        $sheet->getStyle("B{$row}")->getNumberFormat()->setFormatCode('#,##0;-#,##0');
                    } elseif ($type === 'int') {
                        $sheet->getStyle("B{$row}")->getNumberFormat()->setFormatCode('#,##0');
                    } elseif ($type === 'percent') {
                        if ($value === null) {
                            $sheet->setCellValue("B{$row}", '—');
                        } else {
                            $sheet->getStyle("B{$row}")->getNumberFormat()->setFormatCode('0.0"%"');
                        }
                    }

                    if ($type === 'profit') {
                        $sheet->getStyle($range)->getFont()->setBold(true);
                        $sheet->getStyle("B{$row}")->getFont()
                            ->getColor()->setARGB('FF' . (((float) $value) >= 0 ? static::$colorProfit : static::$colorLoss));
                    }
                }
            },
        ];
    }
}
