<?php

namespace App\Jobs;

use App\Models\Invoice;
use App\Models\MonthlySale;
use App\Models\ServiceJob;
use DateTimeImmutable;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class UpdateMonthlySalesJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        protected int $shopId,
        protected int $year,
        protected int $month
    ) {}

    public function handle(): void
    {
        $start = new DateTimeImmutable(sprintf('%04d-%02d-01', $this->year, $this->month));
        $end   = $start->modify('last day of this month')->setTime(23, 59, 59);

        $invoiceQuery = Invoice::where('shop_id', $this->shopId)
            ->whereBetween('created_at', [$start, $end]);

        $totalInvoices     = (clone $invoiceQuery)->count();
        $confirmedInvoices = (clone $invoiceQuery)
            ->where('is_confirmed', Invoice::STATUS_CONFIRMED)
            ->count();

        $totalRevenue = (clone $invoiceQuery)
            ->where('is_confirmed', Invoice::STATUS_CONFIRMED)
            ->sum('total_amount');

        $servicesRevenue = ServiceJob::where('shop_id', $this->shopId)
            ->whereNotNull('invoice_id')
            ->whereHas('invoice', function (Builder $q) use ($start, $end) {
                $q->whereBetween('created_at', [$start, $end])
                  ->where('is_confirmed', Invoice::STATUS_CONFIRMED);
            })
            ->sum('final_price');

        $productsRevenue = max(0, $totalRevenue - $servicesRevenue);

        $uniqueCustomers = (clone $invoiceQuery)
            ->whereNotNull('customer_id')
            ->distinct('customer_id')
            ->count('customer_id');

        $newCustomers = Invoice::where('shop_id', $this->shopId)
            ->whereNotNull('customer_id')
            ->where('is_confirmed', Invoice::STATUS_CONFIRMED)
            ->whereBetween('created_at', [$start, $end])
            ->whereDoesntExist(function (Builder $q) use ($start) {
                $q->selectRaw('1')
                  ->from('invoices as i2')
                  ->whereColumn('i2.customer_id', 'invoices.customer_id')
                  ->where('i2.shop_id', $this->shopId)
                  ->where('i2.is_confirmed', Invoice::STATUS_CONFIRMED)
                  ->where('i2.created_at', '<', $start);
            })
            ->count();

        $totalCost = 0.0; 
        $profit    = $totalRevenue - $totalCost;

        MonthlySale::updateOrCreate(
            [
                'shop_id' => $this->shopId,
                'year'    => $this->year,
                'month'   => $this->month,
            ],
            [
                'total_invoices'     => $totalInvoices,
                'confirmed_invoices' => $confirmedInvoices,
                'total_revenue'      => $totalRevenue,
                'products_revenue'   => $productsRevenue,
                'services_revenue'   => $servicesRevenue,
                'total_cost'         => $totalCost,
                'profit'             => $profit,
                'unique_customers'   => $uniqueCustomers,
                'new_customers'      => $newCustomers,
            ]
        );
    }
}
