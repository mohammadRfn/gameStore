<?php

namespace App\Services;

use App\Models\Customer;
use App\Models\DailyItemStat;
use App\Models\Invoice;
use App\Models\Item;
use App\Models\MonthlySale;
use App\Models\OrderItem;
use App\Models\ServiceJob;
use App\Models\ServiceJobItem;
use App\Models\StockMovement;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class StatsService
{
    public function dashboard(string $from, string $to, bool $paidOnly = true): array
    {
        $fromAt = Carbon::parse($from)->startOfDay();
        $toAt   = Carbon::parse($to)->endOfDay();
        $days   = max(1, $fromAt->diffInDays($toAt) + 1);

        $prevTo   = $fromAt->copy()->subDay()->endOfDay();
        $prevFrom = $prevTo->copy()->subDays($days - 1)->startOfDay();

        $products = $this->productRows($fromAt, $toAt, $paidOnly);
        $services = $this->serviceRows($fromAt, $toAt, $paidOnly);
        $invoices = $this->invoiceRows($fromAt, $toAt);
        $daily    = $this->dailySeries($fromAt, $toAt, $paidOnly);
        $stock    = $this->stockSnapshot($products->pluck('item_id')->filter()->all());

        $nowKpi  = $this->kpiFromRows($products, $services, $invoices, $this->newCustomersCount($fromAt, $toAt));
        $prevKpi = $this->kpiFromRows(
            $this->productRows($prevFrom, $prevTo, $paidOnly),
            $this->serviceRows($prevFrom, $prevTo, $paidOnly),
            $this->invoiceRows($prevFrom, $prevTo),
            $this->newCustomersCount($prevFrom, $prevTo),
        );

        return [
            'from'       => $fromAt->toDateString(),
            'to'         => $toAt->toDateString(),
            'paidOnly'   => $paidOnly,
            'kpi'        => $nowKpi,
            'compare'    => $this->compareKpi($nowKpi, $prevKpi),
            'daily'      => $daily,
            'products'   => $products->values(),
            'services'   => $services->values(),
            'invoices'   => $invoices->take(20)->values(),
            'payments'   => $this->paymentMix($fromAt, $toAt),
            'aging'      => $this->agingBuckets(),
            'stock'      => $stock,
            'funnel'     => $this->serviceFunnel(),
            'heatmap'    => $this->weekdayHeatmap($fromAt, $toAt, $paidOnly),
            'rankings'   => $this->leaderboards($products, $services),
        ];
    }

    public function getDailyItemStats(string $statDate): EloquentCollection
    {
        $this->populateDailyItemStats($statDate);

        return DailyItemStat::where('stat_date', $statDate)
            ->orderByDesc('revenue')
            ->get();
    }

    public function getMonthlySales(int $year, int $month): EloquentCollection
    {
        $this->rebuildMonthlySale($year, $month);

        return MonthlySale::where('year', $year)
            ->where('month', $month)
            ->get();
    }

    public function populateDailyItemStats(string $statDate): void
    {
        $day = Carbon::parse($statDate);
        $rows = $this->productRows($day->copy()->startOfDay(), $day->copy()->endOfDay(), false);

        DailyItemStat::where('stat_date', $statDate)->delete();

        foreach ($rows as $row) {
            DailyItemStat::create([
                'stat_date'      => $statDate,
                'item_id'        => $row['item_id'],
                'product_name'   => $row['name'],
                'sold_quantity'  => $row['qty'],
                'revenue'        => $row['revenue'],
                'total_cost'     => $row['cogs'],
                'profit'         => $row['profit'],
            ]);
        }
    }

    public function rebuildMonthlySale(int $year, int $month): MonthlySale
    {
        $from = Carbon::create($year, $month, 1)->startOfDay();
        $to   = $from->copy()->endOfMonth();
        $dash = $this->dashboard($from->toDateString(), $to->toDateString(), false);
        $kpi  = $dash['kpi'];

        return MonthlySale::updateOrCreate(
            ['year' => $year, 'month' => $month],
            [
                'total_invoices'     => $kpi['invoice_count'],
                'confirmed_invoices' => $kpi['paid_count'],
                'total_revenue'      => $kpi['gross'],
                'products_revenue'   => $kpi['product_revenue'],
                'services_revenue'   => $kpi['service_revenue'],
                'total_cost'         => $kpi['product_cogs'] + $kpi['service_parts'],
                'profit'             => $kpi['net_profit'],
                'unique_customers'   => $kpi['unique_customers'],
                'new_customers'      => $kpi['new_customers'],
            ]
        );
    }

    /* -----------------------------------------------------------------
     |  Products — سود = (قیمت فروش قفل‌شده روی فاکتور − قیمت خرید آیتم) × تعداد
     |  وصل به items / order_items / invoices / stock_movements
     * ----------------------------------------------------------------- */
    public function productRows(Carbon $from, Carbon $to, bool $paidOnly): Collection
    {
        $query = OrderItem::query()
            ->from('order_items')
            ->leftJoin('items', 'items.id', '=', 'order_items.item_id')
            ->leftJoin('categories', 'categories.id', '=', 'order_items.category_id')
            ->join('invoices', 'invoices.id', '=', 'order_items.invoice_id')
            ->whereNull('order_items.deleted_at')
            ->whereNull('invoices.deleted_at');

        if ($paidOnly) {
            $query->where('invoices.payment_status', Invoice::PAYMENT_PAID)
                ->whereBetween('invoices.paid_at', [$from, $to]);
        } else {
            $query->where(function ($q) use ($from, $to) {
                $q->where(function ($paid) use ($from, $to) {
                    $paid->where('invoices.payment_status', Invoice::PAYMENT_PAID)
                        ->whereBetween('invoices.paid_at', [$from, $to]);
                })
                    ->orWhere(function ($unpaid) use ($from, $to) {
                        $unpaid->where('invoices.payment_status', '!=', Invoice::PAYMENT_PAID)
                            ->whereBetween('invoices.created_at', [$from, $to]);
                    });
            });
        }

        $this->applyInvoiceFilters($query, $paidOnly, 'invoices');

        if (Schema::hasColumn('order_items', 'is_returned')) {
            $query->where(function ($q) {
                $q->where('order_items.is_returned', false)
                    ->orWhereNull('order_items.is_returned');
            });
        }

        $rows = $query
            ->groupBy('order_items.item_id', 'order_items.product_name', 'categories.name')
            ->selectRaw('
            order_items.item_id as item_id,
            order_items.product_name as name,
            categories.name as category,
           SUM(order_items.quantity) as qty,
            SUM(order_items.total_price) as revenue,
            SUM(order_items.quantity * COALESCE(NULLIF(order_items.cost_price, 0), items.purchase_price, 0)) as cogs,
            AVG(order_items.price) as avg_sell,
            AVG(COALESCE(NULLIF(order_items.cost_price, 0), items.purchase_price, 0)) as avg_buy
        ')
            ->get();

        $stock = $this->stockSnapshot($rows->pluck('item_id')->filter()->all());

        return $rows->map(function ($row) use ($stock) {
            $revenue = (float) $row->revenue;
            $cogs    = (float) $row->cogs;
            $profit  = $revenue - $cogs;

            return [
                'item_id'  => $row->item_id,
                'name'     => $row->name ?: 'بدون نام',
                'category' => $row->category ?: 'بدون دسته',
                'qty'      => (int) $row->qty,
                'avg_buy'  => round((float) $row->avg_buy, 0),
                'avg_sell' => round((float) $row->avg_sell, 0),
                'revenue'  => round($revenue, 0),
                'cogs'     => round($cogs, 0),
                'profit'   => round($profit, 0),
                'margin'   => $revenue > 0 ? round($profit / $revenue * 100, 1) : 0,
                'stock'    => $stock[$row->item_id] ?? null,
            ];
        })->sortByDesc('profit')->values();
    }

    /* -----------------------------------------------------------------
     |  Services — درآمد final_price، قطعه از service_job_items × purchase_price
     * ----------------------------------------------------------------- */
    public function serviceRows(Carbon $from, Carbon $to, bool $paidOnly): Collection
    {
        $jobs = ServiceJob::query()
            ->with(['serviceType', 'items.item'])
            ->whereNotIn('status', [ServiceJob::STATUS_CANCELED])
            ->where(function ($q) use ($from, $to, $paidOnly) {
                $q->whereHas('invoice', function ($invoice) use ($from, $to, $paidOnly) {
                    if ($paidOnly) {
                        $invoice->where('payment_status', Invoice::PAYMENT_PAID)
                            ->whereBetween('paid_at', [$from, $to]);
                    } else {
                        $invoice->where(function ($q2) use ($from, $to) {
                            $q2->where(function ($paid) use ($from, $to) {
                                $paid->where('payment_status', Invoice::PAYMENT_PAID)
                                    ->whereBetween('paid_at', [$from, $to]);
                            })
                                ->orWhere(function ($unpaid) use ($from, $to) {
                                    $unpaid->where('payment_status', '!=', Invoice::PAYMENT_PAID)
                                        ->whereBetween('created_at', [$from, $to]);
                                });
                        });
                    }
                    $this->applyInvoiceFilters($invoice, $paidOnly);
                })
                    ->orWhere(function ($open) use ($from, $to) {
                        $open->whereNull('invoice_id')
                            ->where(function ($dates) use ($from, $to) {
                                $dates->whereBetween('completed_at', [$from, $to])
                                    ->orWhereBetween('delivered_at', [$from, $to])
                                    ->orWhereBetween('created_at', [$from, $to]);
                            });
                    });
            })
            ->get();

        return $jobs->groupBy(fn($job) => $job->service_type_id ?: 0)->map(function ($group) {
            $partsCost = 0.0;
            $partsSale = 0.0;
            foreach ($group as $job) {
                foreach ($job->items as $line) {
                    $partsSale += (float) $line->total_price;
                    $buy = (float) ($line->item?->purchase_price ?? 0);
                    $partsCost += $buy * (int) $line->quantity;
                }
            }

            $revenue = (float) $group->sum('final_price');
            $wait    = $group->whereNotIn('status', [
                ServiceJob::STATUS_COMPLETED,
                ServiceJob::STATUS_DELIVERED,
            ])->count();

            return [
                'service_type_id' => $group->first()->service_type_id,
                'name'            => $group->first()->serviceType?->name ?: 'بدون نوع',
                'jobs'            => $group->count(),
                'avg'             => $group->count() ? round($revenue / $group->count(), 0) : 0,
                'revenue'         => round($revenue, 0),
                'parts_cost'      => round($partsCost, 0),
                'parts_sale'      => round($partsSale, 0),
                'net'             => round($revenue - $partsCost, 0),
                'open'            => $wait,
            ];
        })->sortByDesc('revenue')->values();
    }
    public function invoiceRows(Carbon $from, Carbon $to): Collection
    {
        $amount = $this->invoiceAmountSql('invoices');

        return Invoice::query()
            ->with(['customer:id,name', 'orderItems', 'serviceJobs'])
            ->where(function ($q) use ($from, $to) {
                $q->where(function ($paid) use ($from, $to) {
                    $paid->where('payment_status', Invoice::PAYMENT_PAID)
                        ->whereBetween('paid_at', [$from, $to]);
                })
                    ->orWhere(function ($unpaid) use ($from, $to) {
                        $unpaid->where('payment_status', '!=', Invoice::PAYMENT_PAID)
                            ->whereBetween('created_at', [$from, $to]);
                    });
            })
            ->latest('id')
            ->get()
            ->map(function (Invoice $invoice) {
                $items = (float) $invoice->orderItems
                    ->when(Schema::hasColumn('order_items', 'is_returned'), fn($c) => $c->where('is_returned', false))
                    ->sum('total_price');
                $svc = (float) $invoice->serviceJobs->sum('final_price');
                $adj = 0.0;
                if ($invoice->relationLoaded('adjustments') || method_exists($invoice, 'adjustments')) {
                    $invoice->loadMissing('adjustments');
                    foreach ($invoice->adjustments as $adjustment) {
                        $base = $items + $svc;
                        $value = method_exists($adjustment, 'resolveAmount')
                            ? (float) $adjustment->resolveAmount($base)
                            : (float) ($adjustment->amount ?? 0);
                        $adj += ($adjustment->direction ?? 'increase') === 'increase' ? $value : -$value;
                    }
                }

                return [
                    'id'             => $invoice->id,
                    'number'         => $invoice->invoice_number,
                    'customer'       => $invoice->customer?->name ?? '—',
                    'items'          => round($items, 0),
                    'services'       => round($svc, 0),
                    'adjustment'     => round($adj, 0),
                    'total'          => round((float) ($invoice->final_amount ?? $invoice->total_amount ?? ($items + $svc + $adj)), 0),
                    'status'         => $invoice->payment_status,
                    'method'         => $invoice->payment_method,
                    'is_returned'    => (bool) $invoice->is_returned,
                    'created_at'     => optional($invoice->created_at)->toDateString(),
                ];
            });
    }

    public function dailySeries(Carbon $from, Carbon $to, bool $paidOnly): array
    {
        $cursor = $from->copy()->startOfDay();
        $end    = $to->copy()->startOfDay();
        $series = [];

        $itemQuery = OrderItem::query()
            ->join('invoices', 'invoices.id', '=', 'order_items.invoice_id')
            ->leftJoin('items', 'items.id', '=', 'order_items.item_id')
            ->whereNull('order_items.deleted_at')
            ->whereNull('invoices.deleted_at');

        if ($paidOnly) {
            $itemQuery->where('invoices.payment_status', Invoice::PAYMENT_PAID)
                ->whereBetween('invoices.paid_at', [$from, $to]);
        } else {
            $itemQuery->where(function ($q) use ($from, $to) {
                $q->where(function ($paid) use ($from, $to) {
                    $paid->where('invoices.payment_status', Invoice::PAYMENT_PAID)
                        ->whereBetween('invoices.paid_at', [$from, $to]);
                })
                    ->orWhere(function ($unpaid) use ($from, $to) {
                        $unpaid->where('invoices.payment_status', '!=', Invoice::PAYMENT_PAID)
                            ->whereBetween('invoices.created_at', [$from, $to]);
                    });
            });
        }

        $this->applyInvoiceFilters($itemQuery, $paidOnly, 'invoices');

        if (Schema::hasColumn('order_items', 'is_returned')) {
            $itemQuery->where(function ($q) {
                $q->where('order_items.is_returned', false)->orWhereNull('order_items.is_returned');
            });
        }

        $dateColumn = $paidOnly
            ? 'invoices.paid_at'
            : 'COALESCE(CASE WHEN invoices.payment_status = "' . Invoice::PAYMENT_PAID . '" THEN invoices.paid_at ELSE invoices.created_at END, invoices.created_at)';

        $itemByDay = $itemQuery
            ->selectRaw("DATE({$dateColumn}) as d, SUM(order_items.total_price) as revenue, SUM(order_items.quantity * COALESCE(NULLIF(order_items.cost_price, 0), items.purchase_price, 0)) as cogs")
            ->groupBy('d')
            ->get()
            ->keyBy('d');

        $svcQuery = ServiceJob::query()
            ->join('invoices', 'invoices.id', '=', 'service_jobs.invoice_id')
            ->whereNull('service_jobs.deleted_at')
            ->whereNull('invoices.deleted_at')
            ->whereNotIn('service_jobs.status', [ServiceJob::STATUS_CANCELED]);

        if ($paidOnly) {
            $svcQuery->where('invoices.payment_status', Invoice::PAYMENT_PAID)
                ->whereBetween('invoices.paid_at', [$from, $to]);
        } else {
            $svcQuery->where(function ($q) use ($from, $to) {
                $q->where(function ($paid) use ($from, $to) {
                    $paid->where('invoices.payment_status', Invoice::PAYMENT_PAID)
                        ->whereBetween('invoices.paid_at', [$from, $to]);
                })
                    ->orWhere(function ($unpaid) use ($from, $to) {
                        $unpaid->where('invoices.payment_status', '!=', Invoice::PAYMENT_PAID)
                            ->whereBetween('invoices.created_at', [$from, $to]);
                    });
            });
        }

        $this->applyInvoiceFilters($svcQuery, $paidOnly, 'invoices');

        $svcByDay = $svcQuery
            ->selectRaw("DATE({$dateColumn}) as d, SUM(service_jobs.final_price) as revenue")
            ->groupBy('d')
            ->get()
            ->keyBy('d');

        while ($cursor->lte($end)) {
            $key = $cursor->toDateString();
            $rev = (float) ($itemByDay[$key]->revenue ?? 0);
            $cogs = (float) ($itemByDay[$key]->cogs ?? 0);
            $svc = (float) ($svcByDay[$key]->revenue ?? 0);
            $series[] = [
                'date'     => $key,
                'label'    => $cursor->format('m/d'),
                'products' => round($rev, 0),
                'cogs'     => round($cogs, 0),
                'profit'   => round($rev - $cogs, 0),
                'services'        => round($svc, 0),
                'service_parts'   => round($parts, 0),
                'service_profit'  => round($svc - $parts, 0),
                'total'    => round($rev + $svc, 0),
            ];
            $cursor->addDay();
        }

        return $series;
    }

    public function paymentMix(Carbon $from, Carbon $to): array
    {
        $amount = $this->invoiceAmountSql();

        return Invoice::query()
            ->where(function ($q) use ($from, $to) {
                $q->where(function ($paid) use ($from, $to) {
                    $paid->where('payment_status', Invoice::PAYMENT_PAID)
                        ->whereBetween('paid_at', [$from, $to]);
                })
                    ->orWhere(function ($unpaid) use ($from, $to) {
                        $unpaid->where('payment_status', '!=', Invoice::PAYMENT_PAID)
                            ->whereBetween('created_at', [$from, $to]);
                    });
            })
            ->where(function ($q) {
                $q->where('is_returned', false)->orWhereNull('is_returned');
            })
            ->selectRaw("COALESCE(payment_method, payment_status, 'unknown') as method, COUNT(*) as cnt, SUM({$amount}) as total")
            ->groupBy('method')
            ->get()
            ->map(fn($row) => [
                'method' => $row->method,
                'count'  => (int) $row->cnt,
                'total'  => round((float) $row->total, 0),
            ])
            ->all();
    }

    public function agingBuckets(): array
    {
        $today = now()->startOfDay();

        $buckets = [
            'week'  => ['label' => '۰ تا ۷ روز',   'count' => 0, 'amount' => 0.0],
            'month' => ['label' => '۸ تا ۳۰ روز',  'count' => 0, 'amount' => 0.0],
            'older' => ['label' => 'بیش از ۳۰ روز', 'count' => 0, 'amount' => 0.0],
        ];

        $amountSql = $this->invoiceAmountSql();

        Invoice::query()
            ->where('is_returned', false)
            ->where('payment_status', '!=', Invoice::PAYMENT_PAID)
            ->selectRaw("id, invoice_number, created_at, {$amountSql} as amount")
            ->chunkById(500, function ($invoices) use (&$buckets, $today) {
                foreach ($invoices as $invoice) {
                    $days   = $invoice->created_at->startOfDay()->diffInDays($today);
                    $amount = (float) $invoice->amount;

                    $key = $days <= 7 ? 'week' : ($days <= 30 ? 'month' : 'older');

                    $buckets[$key]['count']++;
                    $buckets[$key]['amount'] += $amount;
                }
            });

        return array_values($buckets);
    }
    public function monthlyBreakdown(Carbon $from, Carbon $to): array
    {
        $amountSql = $this->invoiceAmountSql();

        return Invoice::query()
            ->where('is_returned', false)
            ->where(function ($q) use ($from, $to) {
                $q->where(function ($paid) use ($from, $to) {
                    $paid->where('payment_status', Invoice::PAYMENT_PAID)
                        ->whereBetween('paid_at', [$from, $to]);
                })
                    ->orWhere(function ($unpaid) use ($from, $to) {
                        $unpaid->where('payment_status', '!=', Invoice::PAYMENT_PAID)
                            ->whereBetween('created_at', [$from, $to]);
                    });
            })
            ->selectRaw("COALESCE(paid_at, created_at) as ref_date, payment_status, {$amountSql} as amount")
            ->get()
            ->groupBy(fn($invoice) => Carbon::parse($invoice->ref_date)->format('Y-m'))
            ->map(fn($group, $ym) => [
                'period'    => $ym,
                'count'     => $group->count(),
                'billed'    => (float) $group->sum('amount'),
                'collected' => (float) $group
                    ->where('payment_status', Invoice::PAYMENT_PAID)
                    ->sum('amount'),
            ])
            ->values()
            ->all();
    }

    public function stockSnapshot(array $itemIds): array
    {
        if ($itemIds === []) {
            return [];
        }

        $inTypes  = [StockMovement::TYPE_IN, StockMovement::TYPE_ADJUST_IN];
        $outTypes = [StockMovement::TYPE_OUT, StockMovement::TYPE_ADJUST_OUT];

        return StockMovement::query()
            ->whereIn('item_id', $itemIds)
            ->selectRaw('item_id, SUM(CASE
                WHEN movement_type IN (?, ?) THEN quantity
                WHEN movement_type IN (?, ?) THEN -quantity
                ELSE 0 END) as on_hand', array_merge($inTypes, $outTypes))
            ->groupBy('item_id')
            ->pluck('on_hand', 'item_id')
            ->map(fn($v) => (int) $v)
            ->all();
    }

    public function serviceFunnel(): array
    {
        $counts = ServiceJob::query()
            ->selectRaw('status, COUNT(*) as cnt')
            ->groupBy('status')
            ->pluck('cnt', 'status');

        return [
            ['status' => 'received', 'label' => 'پذیرش', 'count' => (int) ($counts[ServiceJob::STATUS_RECEIVED] ?? 0)],
            ['status' => 'diagnosing', 'label' => 'عیب‌یابی', 'count' => (int) ($counts[ServiceJob::STATUS_DIAGNOSING] ?? 0)],
            ['status' => 'waiting_for_parts', 'label' => 'منتظر قطعه', 'count' => (int) ($counts[ServiceJob::STATUS_WAITING_FOR_PARTS] ?? 0)],
            ['status' => 'in_progress', 'label' => 'در حال کار', 'count' => (int) ($counts[ServiceJob::STATUS_IN_PROGRESS] ?? 0)],
            ['status' => 'completed', 'label' => 'تمام‌شده', 'count' => (int) ($counts[ServiceJob::STATUS_COMPLETED] ?? 0)],
            ['status' => 'delivered', 'label' => 'تحویل', 'count' => (int) ($counts[ServiceJob::STATUS_DELIVERED] ?? 0)],
        ];
    }

    public function weekdayHeatmap(Carbon $from, Carbon $to, bool $paidOnly = false): array
    {
        // ترتیب فارسی: شنبه (index 0) تا جمعه (index 6)
        $labels = ['شنبه', 'یکشنبه', 'دوشنبه', 'سه‌شنبه', 'چهارشنبه', 'پنجشنبه', 'جمعه'];

        $days = [];
        foreach ($labels as $i => $label) {
            $days[$i] = ['label' => $label, 'count' => 0, 'amount' => 0.0];
        }

        /* فروش کالا در بازه */
        $orders = OrderItem::query()
            ->where('is_returned', false)
            ->whereBetween('order_items.created_at', [$from, $to])
            ->whereHas('invoice', function ($q) use ($paidOnly) {
                $q->where('is_returned', false)
                    ->when($paidOnly, fn($q) => $q->where('payment_status', Invoice::PAYMENT_PAID));
            })
            ->select(
                'order_items.created_at as order_at',
                'order_items.total_price as price_total'
            )
            ->get();

        /* درآمد سرویس در بازه */
        $services = ServiceJob::query()
            ->whereNotIn('status', [ServiceJob::STATUS_CANCELED])
            ->whereBetween('completed_at', [$from, $to])
            ->where(function ($q) use ($paidOnly) {
                $q->whereNull('invoice_id')
                    ->orWhereHas('invoice', function ($invoice) use ($paidOnly) {
                        $this->applyInvoiceFilters($invoice, $paidOnly);
                    });
            })
            ->get(['completed_at', 'final_price']);

        foreach ($orders as $order) {
            // Carbon: dayOfWeek => 0=یکشنبه ... 6=شنبه
            // تبدیل به ایندکس فارسی: شنبه=0 → (dayOfWeek + 1) % 7
            $idx = ($order->order_at->dayOfWeek + 1) % 7;

            $days[$idx]['count']++;
            $days[$idx]['amount'] += (float) $order->price_total;
        }

        foreach ($services as $job) {
            $idx = ($job->completed_at->dayOfWeek + 1) % 7;

            $days[$idx]['count']++;
            $days[$idx]['amount'] += (float) $job->final_price;
        }

        return array_values($days);
    }

    /* ----------------------------------------------------------------- */

    private function applyInvoiceFilters($query, bool $paidOnly, string $table = ''): void
    {
        $col = $table ? $table . '.' : '';

        $query->where(function ($q) use ($col) {
            $q->where($col . 'is_returned', false)->orWhereNull($col . 'is_returned');
        });

        if ($paidOnly) {
            $query->where($col . 'payment_status', Invoice::PAYMENT_PAID);
        } else {
            $query->where($col . 'payment_status', '!=', Invoice::PAYMENT_RETURNED);
        }
    }

    private function invoiceAmountSql(string $table = 'invoices'): string
    {
        if (Schema::hasColumn('invoices', 'final_amount')) {
            return "COALESCE({$table}.final_amount, {$table}.total_amount, 0)";
        }

        return "COALESCE({$table}.total_amount, 0)";
    }

    private function newCustomersCount(Carbon $from, Carbon $to): int
    {
        return Customer::query()->whereBetween('created_at', [$from, $to])->count();
    }

    private function kpiFromRows(Collection $products, Collection $services, Collection $invoices, int $newCustomers = 0): array
    {
        $productRevenue = (float) $products->sum('revenue');
        $productCogs    = (float) $products->sum('cogs');
        $productProfit  = (float) $products->sum('profit');
        $serviceRevenue = (float) $services->sum('revenue');
        $serviceParts   = (float) $services->sum('parts_cost');
        $serviceNet     = (float) $services->sum('net');

        $active = $invoices->where('is_returned', false);
        $paid   = $active->where('status', Invoice::PAYMENT_PAID);
        $unpaid = $active->where('status', Invoice::PAYMENT_UNPAID);
        $returned = $invoices->where('is_returned', true)
            ->merge($invoices->where('status', Invoice::PAYMENT_RETURNED));

        $billed    = (float) $active->sum('total');
        $collected = (float) $paid->sum('total');
        $outstanding = (float) $unpaid->sum('total');

        return [
            'product_revenue'  => round($productRevenue, 0),
            'product_cogs'     => round($productCogs, 0),
            'product_profit'   => round($productProfit, 0),
            'product_margin'   => $productRevenue > 0 ? round($productProfit / $productRevenue * 100, 1) : 0,
            'service_revenue'  => round($serviceRevenue, 0),
            'service_parts'    => round($serviceParts, 0),
            'service_net'      => round($serviceNet, 0),
            'service_jobs'     => (int) $services->sum('jobs'),
            'gross'            => round($productRevenue + $serviceRevenue, 0),
            'net_profit'       => round($productProfit + $serviceNet, 0),
            'billed'           => round($billed, 0),
            'collected'        => round($collected, 0),
            'outstanding'      => round($outstanding, 0),
            'returned'         => round((float) $returned->sum('total'), 0),
            'invoice_count'    => $invoices->count(),
            'paid_count'       => $paid->count(),
            'unique_customers' => $invoices->pluck('customer')->filter()->unique()->count(),
            'new_customers'    => $newCustomers,
            'avg_ticket'       => $invoices->count() ? round($billed / max($active->count(), 1), 0) : 0,
        ];
    }

    private function compareKpi(array $now, array $prev): array
    {
        $keys = ['gross', 'product_profit', 'service_revenue', 'collected', 'outstanding', 'net_profit'];
        $out = [];
        foreach ($keys as $key) {
            $a = (float) ($now[$key] ?? 0);
            $b = (float) ($prev[$key] ?? 0);
            $out[$key] = $b == 0.0 ? ($a > 0 ? 100 : 0) : round(($a - $b) / abs($b) * 100, 1);
        }

        return $out;
    }
    private function leaderboards(Collection $products, Collection $services): array
    {
        $productRows = $products->values();
        $serviceRows = $services->values();
        $categoryRows = $this->categoryRows($productRows);

        return [
            'products' => [
                'best_selling' => $this->topRows($productRows, 'qty'),
                'top_revenue'  => $this->topRows($productRows, 'revenue'),
                'top_profit'   => $this->topRows($productRows, 'profit'),
                'highest_price' => $this->topRows($productRows, 'avg_sell'),
                'best_margin'  => $this->topRows($productRows, 'margin'),
                'low_stock'    => $this->lowStockWinners($productRows),
                'slow_moving'  => $this->slowMovingProducts($productRows),
            ],

            'services' => [
                'best_selling' => $this->topRows($serviceRows, 'jobs'),
                'top_revenue'  => $this->topRows($serviceRows, 'revenue'),
                'top_profit'   => $this->topRows($serviceRows, 'net'),
                'highest_price' => $this->topRows($serviceRows, 'avg'),
                'open_services' => $this->topRows($serviceRows, 'open'),
            ],

            'categories' => [
                'best_selling' => $this->topRows($categoryRows, 'qty'),
                'top_revenue'  => $this->topRows($categoryRows, 'revenue'),
                'top_profit'   => $this->topRows($categoryRows, 'profit'),
                'best_margin'  => $this->topRows($categoryRows, 'margin'),
            ],
        ];
    }

    private function categoryRows(Collection $products): Collection
    {
        $totalRevenue = (float) $products->sum('revenue');

        return $products
            ->groupBy(fn($row) => $row['category'] ?? 'بدون دسته')
            ->map(function ($group, $category) use ($totalRevenue) {
                $qty = (int) $group->sum('qty');
                $revenue = (float) $group->sum('revenue');
                $cogs = (float) $group->sum('cogs');
                $profit = $revenue - $cogs;

                return [
                    'name'     => $category,
                    'category' => $category,
                    'count'    => $group->count(),
                    'qty'      => $qty,
                    'revenue'  => round($revenue, 0),
                    'cogs'     => round($cogs, 0),
                    'profit'   => round($profit, 0),
                    'margin'   => $revenue > 0 ? round($profit / $revenue * 100, 1) : 0,
                    'share'    => $totalRevenue > 0 ? round($revenue / $totalRevenue * 100) : 0,
                ];
            })
            ->values();
    }

    private function topRows(Collection $rows, string $metric, int $limit = 20): array
    {
        return $rows
            ->sortByDesc(fn($row) => (float) ($row[$metric] ?? 0))
            ->take($limit)
            ->values()
            ->all();
    }

    private function lowStockWinners(Collection $products, int $limit = 20): array
    {
        return $products
            ->filter(fn($row) => array_key_exists('stock', $row) && $row['stock'] !== null)
            ->sortBy(function ($row) {
                return [
                    (int) $row['stock'],
                    -1 * (int) ($row['qty'] ?? 0),
                ];
            })
            ->take($limit)
            ->values()
            ->all();
    }

    private function slowMovingProducts(Collection $products, int $limit = 20): array
    {
        return $products
            ->filter(function ($row) {
                $stock = $row['stock'] ?? null;
                $qty = (int) ($row['qty'] ?? 0);

                return $stock !== null && $stock > 0 && $qty <= 1;
            })
            ->sortByDesc(fn($row) => (int) ($row['stock'] ?? 0))
            ->take($limit)
            ->values()
            ->all();
    }
}
