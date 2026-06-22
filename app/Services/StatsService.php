<?php

namespace App\Services;

use App\Models\DailyItemStat;
use App\Models\MonthlySale;
use App\Models\OrderItem;
use Illuminate\Database\Eloquent\Collection;

class StatsService
{
    public function getDailyItemStats(string $statDate): Collection
    {
        $this->populateDailyItemStats($statDate);

        return DailyItemStat::where('stat_date', $statDate)
            ->orderByDesc('revenue')
            ->get();
    }

    private function populateDailyItemStats(string $statDate): void
    {
        DailyItemStat::where('stat_date', $statDate)->delete();

        OrderItem::whereHas('invoice', function ($q) use ($statDate) {
            $q->whereDate('created_at', $statDate);
        })
            ->select('product_name')
            ->groupBy('product_name')
            ->chunk(100, function ($items) use ($statDate) {
                foreach ($items as $item) {
                    $totals = OrderItem::where('product_name', $item->product_name)
                        ->whereHas('invoice', fn($q) => $q->whereDate('created_at', $statDate))
                        ->selectRaw('SUM(quantity) as sold_quantity, SUM(total_price) as revenue, MAX(item_id) as item_id')
                        ->first();

                    DailyItemStat::updateOrCreate(
                        [
                            'stat_date' => $statDate,
                            'item_id'   => $totals->item_id,
                        ],
                        [
                            'product_name' => $item->product_name,
                            'sold_quantity' => $totals->sold_quantity,
                            'revenue'       => $totals->revenue,
                        ]
                    );
                }
            });
    }

    public function getMonthlySales(int $year, int $month): Collection
    {
        return MonthlySale::where('year', $year)
            ->where('month', $month)
            ->get();
    }
}
