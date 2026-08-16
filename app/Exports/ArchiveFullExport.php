<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\WithMultipleSheets;

/**
 * یک فایل اکسل با سه شیت جداگانه (فاکتورها / درخواست‌ها / سرویس‌ها)
 * برای وقتی که کاربر بخواهد یک‌جا کل بایگانی را خروجی بگیرد.
 */
class ArchiveFullExport implements WithMultipleSheets
{
    public function __construct(
        protected array $filters = [],
        protected ?int $actorId = null
    ) {
    }

    public function sheets(): array
    {
        return [
            new ArchivedInvoicesExport($this->filters, $this->actorId),
            new ArchivedRequestsExport($this->filters, $this->actorId),
            new ArchivedServiceJobsExport($this->filters, $this->actorId),
        ];
    }
}
