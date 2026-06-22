<?php

namespace App\Http\Controllers;

use App\Services\StatsService;
use Illuminate\Http\Request;
use Inertia\Inertia;

class StatsController extends Controller
{
    public function __construct(
        protected StatsService $statsService
    ) {}

    public function dailyStats(Request $request)
    {
        $statDate = $request->input('date', now()->format('Y-m-d'));
        $stats    = $this->statsService->getDailyItemStats($statDate);

        return Inertia::render('Stats/Daily', [
            'stats'    => $stats,
            'statDate' => $statDate,
        ]);
    }

    public function monthlyStats(Request $request)
    {
        $year  = (int) $request->input('year',  now()->year);
        $month = (int) $request->input('month', now()->month);
        $sales = $this->statsService->getMonthlySales($year, $month);

        return Inertia::render('Stats/Monthly', [
            'sales' => $sales,
            'year'  => $year,
            'month' => $month,
        ]);
    }
}