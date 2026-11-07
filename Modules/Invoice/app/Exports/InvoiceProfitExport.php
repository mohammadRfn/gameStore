<?php

namespace Modules\Invoice\Exports;

use Illuminate\Support\Carbon;
use Maatwebsite\Excel\Concerns\Export;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;
use Modules\Invoice\Exports\Sheets\InvoiceProfitRowsSheet;
use Modules\Invoice\Exports\Sheets\InvoiceProfitSummarySheet;

/**
 * خروجی سود و زیان فاکتورها: شیت اول خلاصه، شیت دوم ریز تک‌تک فاکتورها.
 * $report همان خروجی InvoiceProfitReportService::build() است.
 */
class InvoiceProfitExport implements Export, WithMultipleSheets
{
    public function __construct(
        protected array $report,
        protected Carbon $from,
        protected Carbon $to
    ) {
    }

    public function sheets(): array
    {
        return [
            new InvoiceProfitSummarySheet($this->report['summary'], $this->from, $this->to),
            new InvoiceProfitRowsSheet($this->report['rows']->all()),
        ];
    }
}
