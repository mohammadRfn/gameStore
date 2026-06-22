<?php

namespace App\Services;

use App\Models\MonthlySale;
use Illuminate\Database\Eloquent\Collection;

interface MonthlySaleServiceInterface
{
    /**
     */
    public function getMonthlySales(int $shopId, int $year, int $month): Collection;

    /**
     */
    public function createOrUpdateMonthlySales(int $shopId, int $year, int $month, array $data): MonthlySale;
}
