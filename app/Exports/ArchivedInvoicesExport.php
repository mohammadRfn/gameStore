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

class ArchivedInvoicesExport implements
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

    /**
     * عرض ثابت و معقول برای هر ستون؛ قبلاً ShouldAutoSize باعث می‌شد
     * ستون‌های چندخطی (اقلام سفارش/تعدیلات) بر اساس طولانی‌ترین خط
     * عریض شوند (۷۰+ کاراکتر) و کل شیت غیرقابل‌استفاده به‌نظر برسد.
     */
    public function columnWidths(): array
    {
        return [
            'A' => 6,
            'B' => 18,
            'C' => 18,
            'D' => 10,
            'E' => 34,
            'F' => 10,
            'G' => 16,
            'H' => 26,
            'I' => 16,
            'J' => 16,
            'K' => 16,
            'L' => 16,
            'M' => 16,
            'N' => 24,
            'O' => 16,
            'P' => 16,
            'Q' => 14,
            'R' => 22,
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
            // ستون‌های چندخطی: E (اقلام سفارش) و H (تعدیلات) — برای محاسبه‌ی ارتفاع درست هر ردیف
            AfterSheet::class => $this->luxuryAfterSheetEvent('R', ['E', 'H']),
        ];
    }
}