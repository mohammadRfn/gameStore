<?php

namespace App\Services;

use App\Models\MonthlySale;
use Illuminate\Database\Eloquent\Collection;

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
