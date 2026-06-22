<?php

namespace App\Services;

use App\Models\DailyItemStat;
use App\Models\MonthlySale;
use Illuminate\Database\Eloquent\Collection;

interface StatsServiceInterface
{
    /**
     */
    public function getDailyItemStats(int $shopId, string $statDate): Collection;

    /**
     */
    public function getMonthlySales(int $shopId, int $year, int $month): Collection;
}
