<?php

namespace Modules\Invoice\Services;

use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Modules\Archive\Models\ArchivedRecord;
use Modules\Invoice\Models\Invoice;
use Modules\Service\Models\ServiceJob;

/**
 * گزارش سود و زیان فاکتورها در یک بازهٔ زمانی.
 *
 * قواعد (هم‌راستا با StatsService تا اعداد دو بخش با هم نخوانند):
 *  - اقلام مرجوع‌شده (order_items.is_returned) نه درآمد حساب می‌شوند نه هزینه.
 *  - فاکتور مرجوع‌شدهٔ کامل: در همهٔ ستون‌های اثر مالی صفر است (فقط در لیست می‌آید).
 *  - تعدیل‌های counts_as_revenue = true به درآمد اضافه/کم می‌شوند؛
 *    تعدیل‌های غیردرآمدی (ارسال، مالیات، کارمزد…) در سود لحاظ نمی‌شوند.
 *  - بهای تمام‌شدهٔ هر قلم: cost_price ثبت‌شده روی خود قلم، و اگر خالی/صفر بود قیمت خرید فعلی کالا.
 *  - درآمد خدمات = final_price کار سرویس؛ هزینه = قطعات مصرفی آن سرویس.
 *  - تاریخ فاکتور: پرداخت‌شده → paid_at ، بقیه → created_at.
 */
class InvoiceProfitReportService
{
    public const BUCKET_REALIZED = 'realized'; // پرداخت‌شده
    public const BUCKET_PENDING  = 'pending';  // پرداخت‌نشده (سود احتمالی)
    public const BUCKET_RETURNED = 'returned'; // مرجوع‌شدهٔ کامل (بدون اثر)

    private const DATE_SQL = "CASE WHEN invoices.payment_status = 'paid' THEN COALESCE(invoices.paid_at, invoices.created_at) ELSE invoices.created_at END";

    public function build(Carbon $from, Carbon $to): array
    {
        $invoices = $this->queryInvoices($from, $to)->get();
        $jobsByInvoice = $this->loadServiceJobs($invoices->pluck('id')->all());

        $rows = $invoices
            ->map(fn (Invoice $invoice) => $this->buildRow($invoice, $jobsByInvoice->get($invoice->id, collect())))
            ->values();

        return [
            'rows'    => $rows,
            'summary' => $this->summarize($rows),
        ];
    }

    /* ------------------------------------------------------------------ */
    /* کوئری‌ها                                                           */
    /* ------------------------------------------------------------------ */

    private function queryInvoices(Carbon $from, Carbon $to)
    {
        $query = Invoice::withTrashed()
            ->with([
                'customer:id,name',
                'orderItems.item' => fn ($q) => $q->withTrashed(),
                'adjustments',
            ])
            ->whereRaw(self::DATE_SQL . ' BETWEEN ? AND ?', [$from->toDateTimeString(), $to->toDateTimeString()]);

        $this->activeOrArchived($query, 'invoices', ArchivedRecord::TYPE_INVOICE);

        return $query->orderByRaw(self::DATE_SQL)->orderBy('invoices.id');
    }

    private function loadServiceJobs(array $invoiceIds): Collection
    {
        if (empty($invoiceIds)) {
            return collect();
        }

        $query = ServiceJob::withTrashed()
            ->with(['items.item' => fn ($q) => $q->withTrashed()])
            ->whereIn('invoice_id', $invoiceIds)
            ->whereNotIn('status', [ServiceJob::STATUS_CANCELED]);

        $this->activeOrArchived($query, 'service_jobs', ArchivedRecord::TYPE_SERVICE_JOB);

        return $query->get()->groupBy('invoice_id');
    }

    /**
     * رکوردهای حذف‌نشده + رکوردهایی که با «انتقال به بایگانی» soft-delete شده‌اند
     * (مثل StatsService، تا بایگانی‌شده‌ها از گزارش سود جا نیفتند).
     */
    private function activeOrArchived($query, string $table, string $sourceType)
    {
        return $query->where(function ($q) use ($table, $sourceType) {
            $q->whereNull("{$table}.deleted_at")
                ->orWhereExists(function ($sub) use ($table, $sourceType) {
                    $sub->selectRaw('1')
                        ->from('archived_records')
                        ->whereColumn('archived_records.source_id', "{$table}.id")
                        ->where('archived_records.source_type', $sourceType)
                        ->where('archived_records.archive_status', ArchivedRecord::STATUS_TRANSFERRED);
                });
        });
    }

    /* ------------------------------------------------------------------ */
    /* محاسبهٔ یک فاکتور                                                  */
    /* ------------------------------------------------------------------ */

    private function buildRow(Invoice $invoice, Collection $jobs): array
    {
        $isReturnedInvoice = (bool) $invoice->is_returned
            || $invoice->payment_status === Invoice::PAYMENT_RETURNED;

        /* ---- کالاها ---- */
        $itemsRevenue = 0.0;
        $itemsCost    = 0.0;
        $returnedLines = 0;
        $returnedValue = 0.0;

        foreach ($invoice->orderItems as $line) {
            $lineTotal = (float) $line->total_price;

            if ($line->is_returned) {
                $returnedLines++;
                $returnedValue += $lineTotal;
                continue;
            }

            $itemsRevenue += $lineTotal;
            $itemsCost    += $this->unitCost($line->cost_price, $line->item?->purchase_price) * (int) $line->quantity;
        }

        /* ---- خدمات ---- */
        $servicesRevenue = 0.0;
        $servicesCost    = 0.0;

        foreach ($jobs as $job) {
            $servicesRevenue += (float) $job->final_price;

            foreach ($job->items as $part) {
                $servicesCost += $this->unitCost($part->cost_price, $part->item?->purchase_price) * (int) $part->quantity;
            }
        }

        /* ---- تعدیلات ---- */
        $base         = $itemsRevenue + $servicesRevenue;
        $revenueAdj   = 0.0;
        $nonRevenueAdj = 0.0;

        foreach ($invoice->adjustments as $adjustment) {
            $amount = (float) $adjustment->resolveAmount($base);
            $signed = $adjustment->direction === 'increase' ? $amount : -$amount;

            if ($adjustment->counts_as_revenue) {
                $revenueAdj += $signed;
            } else {
                $nonRevenueAdj += $signed;
            }
        }

        $revenue = $itemsRevenue + $servicesRevenue + $revenueAdj;
        $cost    = $itemsCost + $servicesCost;

        /* ---- وضعیت و بخش‌بندی ---- */
        if ($isReturnedInvoice) {
            $bucket = self::BUCKET_RETURNED;
        } elseif ($invoice->payment_status === Invoice::PAYMENT_PAID) {
            $bucket = self::BUCKET_REALIZED;
        } else {
            $bucket = self::BUCKET_PENDING;
        }

        $note = [];
        $voided = 0.0;

        if ($bucket === self::BUCKET_RETURNED) {
            $voided = $revenue;
            $note[] = 'فاکتور مرجوع شده؛ بدون اثر در درآمد و سود';

            $itemsRevenue = $itemsCost = $servicesRevenue = $servicesCost = 0.0;
            $revenueAdj = $nonRevenueAdj = $revenue = $cost = 0.0;
        } else {
            if ($returnedLines > 0) {
                $note[] = $returnedLines . ' قلم مرجوع‌شده از محاسبه حذف شد';
            }
            if (abs($nonRevenueAdj) > 0.0001) {
                $note[] = 'تعدیل غیردرآمدی (نادیده): ' . number_format($nonRevenueAdj, 0);
            }
            if ($bucket === self::BUCKET_PENDING) {
                $note[] = 'هنوز پرداخت نشده؛ سود احتمالی';
            }
        }

        $profit = $revenue - $cost;

        $dateSource = $invoice->payment_status === Invoice::PAYMENT_PAID
            ? ($invoice->paid_at ?? $invoice->created_at)
            : $invoice->created_at;

        return [
            'id'                    => $invoice->id,
            'number'                => $invoice->invoice_number,
            'date'                  => $dateSource,
            'customer'              => $invoice->customer?->name ?? '—',
            'status'                => $invoice->payment_status,
            'method'                => $invoice->payment_method,
            'bucket'                => $bucket,
            'items_revenue'         => round($itemsRevenue, 0),
            'items_cost'            => round($itemsCost, 0),
            'services_revenue'      => round($servicesRevenue, 0),
            'services_cost'         => round($servicesCost, 0),
            'revenue_adjustments'   => round($revenueAdj, 0),
            'non_revenue_adjustments' => round($nonRevenueAdj, 0),
            'revenue'               => round($revenue, 0),
            'cost'                  => round($cost, 0),
            'profit'                => round($profit, 0),
            'margin'                => $revenue > 0 ? round($profit / $revenue * 100, 1) : null,
            'returned_lines'        => $returnedLines,
            'returned_value'        => round($returnedValue, 0),
            'voided'                => round($voided, 0),
            'note'                  => implode(' | ', $note),
        ];
    }

    /** cost_price ثبت‌شده روی قلم؛ اگر خالی یا صفر بود، قیمت خرید فعلی کالا. */
    private function unitCost($snapshot, $fallback): float
    {
        $cost = (float) $snapshot;

        return $cost > 0 ? $cost : (float) ($fallback ?? 0);
    }

    /* ------------------------------------------------------------------ */
    /* جمع‌بندی                                                           */
    /* ------------------------------------------------------------------ */

    private function summarize(Collection $rows): array
    {
        $realized = $rows->where('bucket', self::BUCKET_REALIZED);
        $pending  = $rows->where('bucket', self::BUCKET_PENDING);
        $returned = $rows->where('bucket', self::BUCKET_RETURNED);

        return [
            'invoice_count' => $rows->count(),
            'realized'      => $this->totals($realized),
            'pending'       => $this->totals($pending),
            'all_active'    => $this->totals($realized->concat($pending)),
            'returned'      => [
                'invoices_count' => $returned->count(),
                'invoices_value' => (float) $returned->sum('voided'),
                'lines_count'    => (int) $rows->where('bucket', '!=', self::BUCKET_RETURNED)->sum('returned_lines'),
                'lines_value'    => (float) $rows->where('bucket', '!=', self::BUCKET_RETURNED)->sum('returned_value'),
            ],
        ];
    }

    private function totals(Collection $rows): array
    {
        $revenue = (float) $rows->sum('revenue');
        $profit  = (float) $rows->sum('profit');

        return [
            'count'                   => $rows->count(),
            'items_revenue'           => (float) $rows->sum('items_revenue'),
            'items_cost'              => (float) $rows->sum('items_cost'),
            'items_profit'            => (float) $rows->sum('items_revenue') - (float) $rows->sum('items_cost'),
            'services_revenue'        => (float) $rows->sum('services_revenue'),
            'services_cost'           => (float) $rows->sum('services_cost'),
            'services_profit'         => (float) $rows->sum('services_revenue') - (float) $rows->sum('services_cost'),
            'revenue_adjustments'     => (float) $rows->sum('revenue_adjustments'),
            'non_revenue_adjustments' => (float) $rows->sum('non_revenue_adjustments'),
            'revenue'                 => $revenue,
            'cost'                    => (float) $rows->sum('cost'),
            'profit'                  => $profit,
            'margin'                  => $revenue > 0 ? round($profit / $revenue * 100, 1) : null,
        ];
    }
}
