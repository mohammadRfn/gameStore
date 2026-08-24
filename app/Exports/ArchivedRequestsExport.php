<?php

namespace App\Exports;

use App\Exports\Concerns\FormatsArchiveExports;
use App\Models\ArchivedRecord;
use App\Services\ArchiveService;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\Export;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithColumnWidths;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class ArchivedRequestsExport implements
    Export,
    FromCollection,
    WithHeadings,
    WithMapping,
    WithStyles,
    WithEvents,
    WithColumnWidths,
    WithTitle
{
    use FormatsArchiveExports;

    public function __construct(
        protected array $filters = [],
        protected ?int $actorId = null
    ) {
    }

    public function collection(): Collection
    {
        return app(ArchiveService::class)->exportQuery(ArchiveService::TYPE_REQUEST, $this->filters, $this->actorId);
    }

    public function headings(): array
    {
        return [
            '#',
            'شناسه درخواست',
            'نام مشتری',
            'شناسه مشتری',
            'دسته‌بندی‌ها',
            'شرح درخواست',
            'شماره فاکتور مرتبط',
            'اقلام فاکتور',
            'تعدیلات فاکتور',
            'مبلغ پایه فاکتور (تومان)',
            'مبلغ نهایی فاکتور (تومان)',
            'وضعیت پرداخت',
            'روش پرداخت',
            'تاریخ پرداخت',
            'تاریخ ثبت درخواست',
            'وضعیت بایگانی',
            'تاریخ بایگانی',
            'تاریخ انتقال به بایگانی',
            'دلیل',
        ];
    }

    /** @param ArchivedRecord $record */
    public function map($record): array
    {
        $snapshot = $this->snapshot($record);

        $categories = collect(data_get($snapshot, 'source.categories', []))
            ->pluck('name')
            ->filter()
            ->implode('، ');

        $paymentMethod = data_get($snapshot, 'paid_invoice.payment_method');
        $baseAmount    = data_get($snapshot, 'paid_invoice.total_amount', $record->total_amount);
        $finalAmount   = data_get($snapshot, 'paid_invoice.final_amount', $record->total_amount);

        return [
            $record->id,
            $record->source_id,
            $record->customer_name ?? '-',
            $record->customer_id ?? '-',
            $categories ?: '-',
            data_get($snapshot, 'source.description', '-'),
            $record->invoice_number ?? '-',
            $this->orderItemsText($snapshot),
            $this->adjustmentsText($snapshot),
            $this->amount($baseAmount),
            $this->amount($finalAmount),
            $this->paymentStatusLabel($record->payment_status),
            $this->paymentMethodLabel($paymentMethod),
            $this->jalali($record->paid_at),
            $this->jalali($record->source_created_at),
            $this->archiveStatusLabel($record),
            $this->jalali($record->archived_at),
            $this->jalali($record->removed_from_source_at),
            $record->reason ?: '-',
        ];
    }

    public function columnWidths(): array
    {
        return [
            'A' => 6,
            'B' => 14,
            'C' => 18,
            'D' => 10,
            'E' => 18,
            'F' => 28,
            'G' => 16,
            'H' => 30,
            'I' => 22,
            'J' => 16,
            'K' => 16,
            'L' => 16,
            'M' => 16,
            'N' => 16,
            'O' => 16,
            'P' => 24,
            'Q' => 16,
            'R' => 16,
            'S' => 22,
        ];
    }

    public function styles(Worksheet $sheet): array
    {
        return [];
    }

    public function title(): string
    {
        return '📋 درخواست‌های بایگانی‌شده';
    }

    public function registerEvents(): array
    {
        return [
            // ستون‌های چندخطی: F (شرح درخواست)، H (اقلام فاکتور)، I (تعدیلات فاکتور)
            AfterSheet::class => $this->luxuryAfterSheetEvent('S', ['F', 'H', 'I']),
        ];
    }
}