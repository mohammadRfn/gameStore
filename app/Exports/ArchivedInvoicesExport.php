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
            'شناسه مشتری',
            'اقلام سفارش',
            'تعداد اقلام',
            'مبلغ پایه (تومان)',
            'تعدیلات (تخفیف/افزایش)',
            'مبلغ نهایی (تومان)',
            'وضعیت پرداخت',
            'روش پرداخت',
            'تاریخ پرداخت',
            'تاریخ ثبت فاکتور',
            'وضعیت بایگانی',
            'تاریخ بایگانی',
            'تاریخ انتقال به بایگانی',
            'بایگانی‌کننده',
            'دلیل',
        ];
    }

    /** @param ArchivedRecord $record */
    public function map($record): array
    {
        $snapshot      = $this->snapshot($record);
        $paymentMethod = data_get($snapshot, 'paid_invoice.payment_method');
        $orderItems    = data_get($snapshot, 'paid_invoice.order_items', []);
        $baseAmount    = data_get($snapshot, 'paid_invoice.total_amount', $record->total_amount);
        $finalAmount   = data_get($snapshot, 'paid_invoice.final_amount', $record->total_amount);
        $archivedBy    = data_get($snapshot, 'paid_invoice.archived_by_name', $record->archivedBy?->name ?? '-');

        return [
            $record->id,
            $record->invoice_number ?? '-',
            $record->customer_name ?? '-',
            $record->customer_id ?? '-',
            $this->orderItemsText($snapshot),
            count($orderItems),
            $this->amount($baseAmount),
            $this->adjustmentsText($snapshot),
            $this->amount($finalAmount),
            $this->paymentStatusLabel($record->payment_status),
            $this->paymentMethodLabel($paymentMethod),
            $this->jalali($record->paid_at),
            $this->jalali($record->source_created_at),
            $this->archiveStatusLabel($record),
            $this->jalali($record->archived_at),
            $this->jalali($record->removed_from_source_at),
            is_string($archivedBy) ? $archivedBy : '-',
            $record->reason ?: '-',
        ];
    }

    public function styles(Worksheet $sheet): array
    {
        // استایل‌های اصلی در registerEvents اعمال می‌شوند
        return [];
    }

    public function title(): string
    {
        return '🧾 فاکتورهای بایگانی‌شده';
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => $this->luxuryAfterSheetEvent('R'), // 18 ستون = A تا R
        ];
    }
}
