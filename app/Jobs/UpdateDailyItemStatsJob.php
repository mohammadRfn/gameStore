<?php

namespace App\Jobs;

use App\Models\DailyItemStat;
use App\Models\Invoice;
use App\Models\Item;
use App\Models\OrderItem;
use App\Models\StockMovement;
use DateTimeImmutable;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class UpdateDailyItemStatsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        protected int $shopId,
        protected string $date // 'Y-m-d'
    ) {}

    public function handle(): void
    {
        $statDate = new DateTimeImmutable($this->date);
        $startOfDay = $statDate->setTime(0, 0, 0);
        $endOfDay = $statDate->setTime(23, 59, 59);
        $startOfDayFormatted = $startOfDay->format('Y-m-d H:i:s');
        $endOfDayFormatted = $endOfDay->format('Y-m-d H:i:s');


        $orderItems = OrderItem::whereHas('invoice', function ($q) use ($startOfDayFormatted, $endOfDayFormatted) {
            $q->where('shop_id', $this->shopId)
                ->where('is_confirmed', Invoice::STATUS_CONFIRMED)
                ->whereBetween('created_at', [$startOfDayFormatted, $endOfDayFormatted]);
        })
            ->with('invoice')
            ->get()
            ->groupBy('item_id');

        $items = Item::where('shop_id', $this->shopId)->get();

        foreach ($items as $item) {
            $openingStock = $this->calculateStockUntil($item->id, $startOfDay);
            $closingStock = $this->calculateStockUntil($item->id, $endOfDay);

            $dayMovements = StockMovement::where('shop_id', $this->shopId)
                ->where('item_id', $item->id)
                ->whereBetween('created_at', [$startOfDayFormatted, $endOfDayFormatted])
                ->get();

            $soldQty = $dayMovements->whereIn('movement_type', [StockMovement::TYPE_OUT])->sum('quantity');
            $purchasedQty = $dayMovements->where('movement_type', StockMovement::TYPE_IN)->sum('quantity');
            $adjustInQty = $dayMovements->where('movement_type', StockMovement::TYPE_ADJUST_IN)->sum('quantity');
            $adjustOutQty = $dayMovements->where('movement_type', StockMovement::TYPE_ADJUST_OUT)->sum('quantity');

            $revenue = 0;
            $orderItemsForThisItem = $orderItems->get($item->id, collect());
            foreach ($orderItemsForThisItem as $orderItem) {
                $revenue += $orderItem->quantity * $orderItem->unit_price;
            }

            $totalCost = 0;
            $profit = $revenue - $totalCost;

            DailyItemStat::updateOrCreate(
                [
                    'shop_id' => $this->shopId,
                    'item_id' => $item->id,
                    'stat_date' => $statDate->format('Y-m-d'),
                ],
                [
                    'opening_stock' => $openingStock,
                    'closing_stock' => $closingStock,
                    'sold_quantity' => $soldQty,
                    'purchased_quantity' => $purchasedQty,
                    'adjusted_in_quantity' => $adjustInQty,
                    'adjusted_out_quantity' => $adjustOutQty,
                    'revenue' => $revenue,
                    'total_cost' => $totalCost,
                    'profit' => $profit,
                ]
            );
        }
    }


    protected function calculateStockUntil(int $itemId, DateTimeImmutable $until): int
    {
        $in = StockMovement::where('shop_id', $this->shopId)
            ->where('item_id', $itemId)
            ->where('created_at', '<=', $until)
            ->whereIn('movement_type', [
                StockMovement::TYPE_IN,
                StockMovement::TYPE_ADJUST_IN,
            ])
            ->sum('quantity');

        $out = StockMovement::where('shop_id', $this->shopId)
            ->where('item_id', $itemId)
            ->where('created_at', '<=', $until)
            ->whereIn('movement_type', [
                StockMovement::TYPE_OUT,
                StockMovement::TYPE_ADJUST_OUT,
            ])
            ->sum('quantity');

        return $in - $out;
    }
}
