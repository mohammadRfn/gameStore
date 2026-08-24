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

class ArchivedServiceJobsExport implements
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
        return app(ArchiveService::class)->exportQuery(ArchiveService::TYPE_SERVICE_JOB, $this->filters, $this->actorId);
    }

    public function headings(): array
    {
        return [
            '#',
            'شناسه سرویس',
            'نوع دستگاه',
            'سریال دستگاه',
            'نام مشتری',
            'شناسه مشتری',
            'نوع سرویس‌ها',
            'شرح ایراد مشتری',
            'شرح تشخیص تکنسین',
            'قطعات مصرفی',
            'وضعیت سرویس',
            'قیمت تخمینی (تومان)',
            'قیمت نهایی سرویس (تومان)',
            'شماره فاکتور مرتبط',
            'اقلام فاکتور',
            'تعدیلات فاکتور',
            'مبلغ نهایی فاکتور (تومان)',
            'وضعیت پرداخت',
            'روش پرداخت',
            'تاریخ دریافت دستگاه',
            'تاریخ تحویل دستگاه',
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

        $status = data_get($snapshot, 'source.status', '-');
        $statusLabel = match ($status) {
            'received'          => 'دریافت‌شده',
            'diagnosing'        => 'در حال تشخیص',
            'waiting_for_parts' => 'منتظر قطعات',
            'in_progress'       => 'در حال انجام',
            'completed'         => 'تکمیل‌شده',
            'delivered'         => 'تحویل‌شده',
            'canceled'          => 'لغو‌شده',
            default             => $status,
        };

        $paymentMethod   = data_get($snapshot, 'paid_invoice.payment_method');
        $estimatedPrice  = data_get($snapshot, 'source.estimated_price');
        $finalPrice      = data_get($snapshot, 'source.final_price', $record->total_amount);
        $invoiceFinal    = data_get($snapshot, 'paid_invoice.final_amount',
                             data_get($snapshot, 'paid_invoice.total_amount', $record->total_amount));

        return [
            $record->id,
            $record->source_id,
            data_get($snapshot, 'source.device_type', '-'),
            data_get($snapshot, 'source.device_serial', '-'),
            $record->customer_name ?? '-',
            $record->customer_id ?? '-',
            $this->serviceTypesText($snapshot),
            data_get($snapshot, 'source.customer_problem_description', '-'),
            data_get($snapshot, 'source.diagnosis_description', '-'),
            $this->serviceItemsText($snapshot),
            $statusLabel,
            $this->amount($estimatedPrice),
            $this->amount($finalPrice),
            $record->invoice_number ?? '-',
            $this->orderItemsText($snapshot),
            $this->adjustmentsText($snapshot),
            $this->amount($invoiceFinal),
            $this->paymentStatusLabel($record->payment_status),
            $this->paymentMethodLabel($paymentMethod),
            $this->jalali(data_get($snapshot, 'source.received_at')),
            $this->jalali(data_get($snapshot, 'source.delivered_at')),
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
            'B' => 12,
            'C' => 14,
            'D' => 16,
            'E' => 16,
            'F' => 10,
            'G' => 24,
            'H' => 28,
            'I' => 28,
            'J' => 30,
            'K' => 14,
            'L' => 14,
            'M' => 16,
            'N' => 16,
            'O' => 32,
            'P' => 22,
            'Q' => 16,
            'R' => 14,
            'S' => 14,
            'T' => 16,
            'U' => 16,
            'V' => 22,
            'W' => 16,
            'X' => 16,
            'Y' => 20,
        ];
    }

    public function styles(Worksheet $sheet): array
    {
        return [];
    }

    public function title(): string
    {
        return '🔧 سرویس‌های بایگانی‌شده';
    }

    public function registerEvents(): array
    {
        return [
            // ستون‌های چندخطی: G,H,I,J (نوع سرویس/ایراد/تشخیص/قطعات) و O,P (اقلام و تعدیلات فاکتور)
            AfterSheet::class => $this->luxuryAfterSheetEvent('Y', ['G', 'H', 'I', 'J', 'O', 'P']),
        ];
    }
}