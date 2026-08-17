<?php

namespace App\Exports;

use App\Exports\Concerns\FormatsArchiveExports;
use App\Models\ArchivedRecord;
use App\Services\ArchiveService;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\Export;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class ArchivedInvoicesExport implements
    Export,
    FromCollection,
    WithHeadings,
    WithMapping,
    WithStyles,
    WithEvents,
    ShouldAutoSize,
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
        return app(ArchiveService::class)->exportQuery(ArchiveService::TYPE_INVOICE, $this->filters, $this->actorId);
    }

    public function headings(): array
    {
        return [
            '#',
            'شماره فاکتور',
            'نام مشتری',
            'مبلغ نهایی (تومان)',
            'وضعیت پرداخت',
            'روش پرداخت',
            'تاریخ پرداخت',
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
        $paymentMethod = data_get($snapshot, 'paid_invoice.payment_method');

        return [
            $record->id,
            $record->invoice_number ?? '-',
            $record->customer_name ?? '-',
            $this->amount($record->total_amount),
            $this->paymentStatusLabel($record->payment_status),
            $paymentMethod ?: '-',
            $this->jalali($record->paid_at),
            $this->archiveStatusLabel($record),
            $this->jalali($record->archived_at),
            $this->jalali($record->removed_from_source_at),
            $record->reason ?: '-',
        ];
    }

    public function styles(Worksheet $sheet): array
    {
        return [1 => ['font' => ['bold' => true]]];
    }

    public function title(): string
    {
        return 'فاکتورهای بایگانی‌شده';
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event): void {
                $event->sheet->getDelegate()->setRightToLeft(true);
                $event->sheet->getDelegate()->freezePane('A2');
                $event->sheet->getStyle('A1:K1')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
            },
        ];
    }
}
