<?php

namespace Modules\Stats\Services;

use Illuminate\Database\Eloquent\Collection;
use Modules\Stats\Models\MonthlySale;

class MonthlySaleService
{
    public function getMonthlySales(int $year, int $month): Collection
    {
        return MonthlySale::where('year', $year)
            ->where('month', $month)
            ->get();
    }

    public function createOrUpdateMonthlySales(int $year, int $month, array $data): MonthlySale
    {
        return MonthlySale::updateOrCreate(
            [
                'year'  => $year,
                'month' => $month,
            ],
            $data
        );
    }
}
